<?php

namespace App\Actions\MasterImport;

use App\Actions\Inventory\RecordOpeningBalance;
use App\Enums\OrganizationPermission;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Csv\CsvTable;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ImportOpeningBalances
{
    private const LOOKUP_CHUNK_SIZE = 1000;

    public function __construct(
        private readonly RecordOpeningBalance $recordOpeningBalance,
    ) {}

    /**
     * Import initial stock quantities from CSV content, recording one
     * auditable OPENING_BALANCE movement per row through the same workflow
     * used by the manual opening-balance form. No balance is ever written
     * directly; every row flows through RecordOpeningBalance so conversion
     * to the item base unit and ledger accounting stay authoritative.
     *
     * Expected columns: location_code, storage_location_code, item_sku,
     * quantity, unit_symbol, unit_cost, occurred_at (optional, defaults to
     * now), notes (optional).
     *
     * The batch identifier must stay identical across retries of the same
     * import run: each row's idempotency key is derived from the batch
     * identifier and its row number, so retrying an unchanged batch never
     * duplicates stock movements. Re-running the batch with a row's data
     * changed is rejected as a row error instead of silently overwriting
     * the original movement.
     */
    public function handle(
        Organization $organization,
        User $actor,
        string $batchId,
        string $csvContents,
    ): OpeningBalanceImportResult {
        $batchId = trim($batchId);

        if ($batchId === '') {
            throw ValidationException::withMessages([
                'batch_id' => __(
                    'A stable batch identifier is required to import opening balances.',
                ),
            ]);
        }

        if (! $actor->hasOrganizationPermission(
            $organization,
            OrganizationPermission::InventoryAdjust,
        )) {
            throw new AuthorizationException(
                'You are not authorized to import opening inventory.',
            );
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        $preparedRows = [];
        $locationCodes = [];
        $storageLocationCodes = [];
        $itemSkus = [];
        $unitSymbols = [];

        foreach (CsvTable::parse($csvContents) as $row) {
            $data = $row['data'];
            $locationCode = strtoupper(trim($data['location_code'] ?? ''));
            $storageLocationCode = strtoupper(
                trim($data['storage_location_code'] ?? ''),
            );
            $itemSku = strtoupper(trim($data['item_sku'] ?? ''));
            $quantity = trim($data['quantity'] ?? '');
            $unitSymbol = trim($data['unit_symbol'] ?? '');
            $unitCost = trim($data['unit_cost'] ?? '');
            $occurredAtRaw = trim($data['occurred_at'] ?? '');
            $notes = trim($data['notes'] ?? '');

            $rowErrors = [];

            if ($locationCode === '') {
                $rowErrors[] = __(
                    'The location_code column is required.',
                );
            }

            if ($storageLocationCode === '') {
                $rowErrors[] = __(
                    'The storage_location_code column is required.',
                );
            }

            if ($itemSku === '') {
                $rowErrors[] = __('The item_sku column is required.');
            }

            if ($quantity === '' || ! is_numeric($quantity)) {
                $rowErrors[] = __(
                    'The quantity column must be an explicit numeric value.',
                );
            }

            if ($unitSymbol === '') {
                $rowErrors[] = __(
                    'The unit_symbol column is required.',
                );
            }

            if ($unitCost === '' || ! is_numeric($unitCost)) {
                $rowErrors[] = __(
                    'The unit_cost column must be an explicit numeric value.',
                );
            }

            if ($rowErrors !== []) {
                $preparedRows[] = [
                    'number' => $row['number'],
                    'location_code' => $locationCode,
                    'storage_location_code' => $storageLocationCode,
                    'item_sku' => $itemSku,
                    'quantity' => $quantity,
                    'unit_symbol' => $unitSymbol,
                    'unit_cost' => $unitCost,
                    'occurred_at' => $occurredAtRaw,
                    'notes' => $notes,
                    'errors' => $rowErrors,
                ];

                continue;
            }

            $preparedRows[] = [
                'number' => $row['number'],
                'location_code' => $locationCode,
                'storage_location_code' => $storageLocationCode,
                'item_sku' => $itemSku,
                'quantity' => $quantity,
                'unit_symbol' => $unitSymbol,
                'unit_cost' => $unitCost,
                'occurred_at' => $occurredAtRaw,
                'notes' => $notes,
                'errors' => [],
            ];

            $locationCodes[$locationCode] = true;
            $storageLocationCodes[$storageLocationCode] = true;
            $itemSkus[$itemSku] = true;
            $unitSymbols[$unitSymbol] = true;
        }

        $locations = [];

        foreach (array_chunk(array_keys($locationCodes), self::LOOKUP_CHUNK_SIZE) as $codes) {
            foreach (Location::query()
                ->where('organization_id', $organization->getKey())
                ->whereIn('code', $codes)
                ->where('active', true)
                ->get() as $location) {
                $locations[$location->code] = $location;
            }
        }

        $storageLocations = [];
        $locationIds = array_map(
            static fn (Location $location): int => $location->getKey(),
            $locations,
        );

        if ($locationIds !== []) {
            foreach (array_chunk(array_keys($storageLocationCodes), self::LOOKUP_CHUNK_SIZE) as $codes) {
                foreach (StorageLocation::query()
                    ->where('organization_id', $organization->getKey())
                    ->whereIn('location_id', $locationIds)
                    ->whereIn('code', $codes)
                    ->where('active', true)
                    ->get() as $storageLocation) {
                    $storageLocations[
                        "{$storageLocation->location_id}:{$storageLocation->code}"
                    ] = $storageLocation;
                }
            }
        }

        $items = [];

        foreach (array_chunk(array_keys($itemSkus), self::LOOKUP_CHUNK_SIZE) as $skus) {
            foreach (InventoryItem::query()
                ->with('baseUnitOfMeasure')
                ->where('organization_id', $organization->getKey())
                ->whereIn('sku', $skus)
                ->where('active', true)
                ->whereHas(
                    'baseUnitOfMeasure',
                    fn ($query) => $query
                        ->where(
                            'organization_id',
                            $organization->getKey(),
                        )
                        ->where('active', true),
                )
                ->get() as $item) {
                $items[$item->sku] = $item;
            }
        }

        $units = [];

        foreach (array_chunk(array_keys($unitSymbols), self::LOOKUP_CHUNK_SIZE) as $symbols) {
            foreach (UnitOfMeasure::query()
                ->where('organization_id', $organization->getKey())
                ->whereIn('symbol', $symbols)
                ->where('active', true)
                ->get() as $unit) {
                $units[$unit->symbol] = $unit;
            }
        }

        $idempotencyKeys = [];
        $validRowNumbers = [];

        foreach ($preparedRows as $preparedRow) {
            if ($preparedRow['errors'] === []) {
                $validRowNumbers[] = $preparedRow['number'];
            }
        }

        foreach (array_chunk($validRowNumbers, self::LOOKUP_CHUNK_SIZE) as $rowNumbers) {
            $keys = array_map(
                static fn (int $rowNumber): string => "opening_balance:import:{$batchId}:{$rowNumber}",
                $rowNumbers,
            );

            foreach (StockMovement::query()
                ->where('organization_id', $organization->getKey())
                ->whereIn('idempotency_key', $keys)
                ->pluck('idempotency_key') as $idempotencyKey) {
                $idempotencyKeys[$idempotencyKey] = true;
            }
        }

        foreach ($preparedRows as $preparedRow) {
            $rowNumber = $preparedRow['number'];

            if ($preparedRow['errors'] !== []) {
                $errors[] = new ImportRowError($rowNumber, $preparedRow['errors']);

                continue;
            }

            $locationCode = $preparedRow['location_code'];
            $storageLocationCode = $preparedRow['storage_location_code'];
            $itemSku = $preparedRow['item_sku'];
            $unitSymbol = $preparedRow['unit_symbol'];
            $location = $locations[$locationCode] ?? null;

            if ($location === null) {
                $errors[] = new ImportRowError($rowNumber, [
                    __(
                        'No active location with code ":code" exists for this organization.',
                        ['code' => $locationCode],
                    ),
                ]);

                continue;
            }

            $storageLocation = $storageLocations[
                "{$location->getKey()}:{$storageLocationCode}"
            ] ?? null;

            if ($storageLocation === null) {
                $errors[] = new ImportRowError($rowNumber, [
                    __(
                        'No active storage location with code ":code" exists for location ":location".',
                        [
                            'code' => $storageLocationCode,
                            'location' => $locationCode,
                        ],
                    ),
                ]);

                continue;
            }

            $item = $items[$itemSku] ?? null;

            if ($item === null) {
                $errors[] = new ImportRowError($rowNumber, [
                    __(
                        'No active inventory item with sku ":sku" exists for this organization.',
                        ['sku' => $itemSku],
                    ),
                ]);

                continue;
            }

            $unit = $units[$unitSymbol] ?? null;

            if ($unit === null) {
                $errors[] = new ImportRowError($rowNumber, [
                    __(
                        'No active unit of measure with symbol ":symbol" exists for this organization.',
                        ['symbol' => $unitSymbol],
                    ),
                ]);

                continue;
            }

            try {
                $occurredAt = $preparedRow['occurred_at'] === ''
                    ? CarbonImmutable::now($organization->timezone)->utc()
                    : CarbonImmutable::parse(
                        $preparedRow['occurred_at'],
                        $organization->timezone,
                    )->utc();
            } catch (Throwable) {
                $errors[] = new ImportRowError($rowNumber, [
                    __(
                        'The occurred_at column must be a valid date and time when present.',
                    ),
                ]);

                continue;
            }

            $idempotencyKey = "opening_balance:import:{$batchId}:{$rowNumber}";
            $alreadyImported = isset($idempotencyKeys[$idempotencyKey]);

            try {
                $this->recordOpeningBalance->handle(
                    organization: $organization,
                    location: $location,
                    storageLocation: $storageLocation,
                    inventoryItem: $item,
                    quantity: $preparedRow['quantity'],
                    unit: $unit,
                    baseUnitCost: $preparedRow['unit_cost'],
                    referenceType: 'csv_opening_balance_import',
                    referenceId: $item->id,
                    occurredAt: $occurredAt,
                    idempotencyKey: $idempotencyKey,
                    actor: $actor,
                    notes: $preparedRow['notes'] === ''
                        ? null
                        : $preparedRow['notes'],
                );
            } catch (ValidationException $exception) {
                $errors[] = new ImportRowError(
                    $rowNumber,
                    array_values($exception->validator->errors()->all()),
                );

                continue;
            }

            $alreadyImported ? $skipped++ : $created++;
        }

        return new OpeningBalanceImportResult($created, $skipped, $errors);
    }
}
