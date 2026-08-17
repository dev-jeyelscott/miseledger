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
                $errors[] = new ImportRowError($row['number'], $rowErrors);

                continue;
            }

            $location = Location::query()
                ->where('organization_id', $organization->getKey())
                ->where('code', $locationCode)
                ->where('active', true)
                ->first();

            if ($location === null) {
                $errors[] = new ImportRowError($row['number'], [
                    __(
                        'No active location with code ":code" exists for this organization.',
                        ['code' => $locationCode],
                    ),
                ]);

                continue;
            }

            $storageLocation = StorageLocation::query()
                ->where('organization_id', $organization->getKey())
                ->where('location_id', $location->getKey())
                ->where('code', $storageLocationCode)
                ->where('active', true)
                ->first();

            if ($storageLocation === null) {
                $errors[] = new ImportRowError($row['number'], [
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

            $item = InventoryItem::query()
                ->with('baseUnitOfMeasure')
                ->where('organization_id', $organization->getKey())
                ->where('sku', $itemSku)
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
                ->first();

            if ($item === null) {
                $errors[] = new ImportRowError($row['number'], [
                    __(
                        'No active inventory item with sku ":sku" exists for this organization.',
                        ['sku' => $itemSku],
                    ),
                ]);

                continue;
            }

            $unit = UnitOfMeasure::query()
                ->where('organization_id', $organization->getKey())
                ->where('symbol', $unitSymbol)
                ->where('active', true)
                ->first();

            if ($unit === null) {
                $errors[] = new ImportRowError($row['number'], [
                    __(
                        'No active unit of measure with symbol ":symbol" exists for this organization.',
                        ['symbol' => $unitSymbol],
                    ),
                ]);

                continue;
            }

            try {
                $occurredAt = $occurredAtRaw === ''
                    ? CarbonImmutable::now($organization->timezone)->utc()
                    : CarbonImmutable::parse(
                        $occurredAtRaw,
                        $organization->timezone,
                    )->utc();
            } catch (Throwable) {
                $errors[] = new ImportRowError($row['number'], [
                    __(
                        'The occurred_at column must be a valid date and time when present.',
                    ),
                ]);

                continue;
            }

            $idempotencyKey = "opening_balance:import:{$batchId}:{$row['number']}";

            $alreadyImported = StockMovement::query()
                ->where('organization_id', $organization->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->exists();

            try {
                $this->recordOpeningBalance->handle(
                    organization: $organization,
                    location: $location,
                    storageLocation: $storageLocation,
                    inventoryItem: $item,
                    quantity: $quantity,
                    unit: $unit,
                    baseUnitCost: $unitCost,
                    referenceType: 'csv_opening_balance_import',
                    referenceId: $item->id,
                    occurredAt: $occurredAt,
                    idempotencyKey: $idempotencyKey,
                    actor: $actor,
                    notes: $notes === '' ? null : $notes,
                );
            } catch (ValidationException $exception) {
                $errors[] = new ImportRowError(
                    $row['number'],
                    array_values($exception->validator->errors()->all()),
                );

                continue;
            }

            $alreadyImported ? $skipped++ : $created++;
        }

        return new OpeningBalanceImportResult($created, $skipped, $errors);
    }
}
