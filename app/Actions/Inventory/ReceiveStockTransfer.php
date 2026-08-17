<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrganizationPermission;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReceiveStockTransfer
{
    private const MAX_QUANTITY = '999999999.999999';

    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {}

    /**
     * Atomically receive actual stock and retain shipment variance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Organization $organization,
        User $actor,
        StockTransfer $stockTransfer,
        array $attributes,
    ): StockTransfer {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $stockTransfer,
            $attributes,
        ): StockTransfer {
            $this->authorize(
                $organization,
                $actor,
            );

            $transfer = StockTransfer::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->whereKey($stockTransfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $transfer->status
                === StockTransferStatus::Received
            ) {
                $lines = $this->lockedTransferLines(
                    $transfer,
                );

                $receivedByLine =
                    $this->receivedLineSet(
                        $attributes,
                    );

                $this->assertCompleteReceivedLineSet(
                    $lines,
                    $receivedByLine,
                );

                $this->assertReceiptReplayMatches(
                    $lines,
                    $receivedByLine,
                );

                return $transfer->refresh();
            }

            if (
                $transfer->status
                !== StockTransferStatus::Shipped
            ) {
                throw ValidationException::withMessages([
                    'stock_transfer' => __(
                        'Only shipped stock transfers can be received.',
                    ),
                ]);
            }

            $location = Location::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->whereKey($transfer->to_location_id)
                ->where('active', true)
                ->lockForUpdate()
                ->first();

            if ($location === null) {
                throw ValidationException::withMessages([
                    'to_location_id' => __(
                        'The transfer destination location is no longer active.',
                    ),
                ]);
            }

            $storageLocation =
                StorageLocation::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->where(
                        'location_id',
                        $location->id,
                    )
                    ->whereKey(
                        $transfer
                            ->to_storage_location_id,
                    )
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

            if ($storageLocation === null) {
                throw ValidationException::withMessages([
                    'to_storage_location_id' => __(
                        'The transfer destination storage location is no longer active.',
                    ),
                ]);
            }

            $lines = $this->lockedTransferLines(
                $transfer,
            );

            $receivedByLine =
                $this->receivedLineSet(
                    $attributes,
                );

            $this->assertCompleteReceivedLineSet(
                $lines,
                $receivedByLine,
            );

            $receivedAt = now();
            $movementCount = 0;
            $varianceLineCount = 0;

            foreach ($lines as $line) {
                if (
                    $line->shipped_base_quantity === null
                    || $line->unit_cost === null
                ) {
                    throw ValidationException::withMessages([
                        'stock_transfer' => __(
                            'The transfer shipment snapshot is incomplete.',
                        ),
                    ]);
                }

                $receivedBaseQuantity =
                    $this->nonNegativeQuantity(
                        $receivedByLine[$line->id],
                        'received_base_quantity',
                    );

                $shippedBaseQuantity =
                    BigDecimal::of(
                        $line->shipped_base_quantity,
                    )->toScale(
                        6,
                        RoundingMode::HalfUp,
                    );

                $inventoryItem = InventoryItem::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->whereKey(
                        $line->inventory_item_id,
                    )
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

                if ($inventoryItem === null) {
                    throw ValidationException::withMessages([
                        'stock_transfer' => __(
                            'One or more transferred inventory items are no longer active.',
                        ),
                    ]);
                }

                $baseUnit = UnitOfMeasure::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->whereKey(
                        $inventoryItem
                            ->base_unit_of_measure_id,
                    )
                    ->where('active', true)
                    ->first();

                if ($baseUnit === null) {
                    throw ValidationException::withMessages([
                        'stock_transfer' => __(
                            'One or more transferred inventory items do not have an active base unit.',
                        ),
                    ]);
                }

                if (
                    $receivedBaseQuantity->compareTo(
                        BigDecimal::zero(),
                    ) > 0
                ) {
                    $this->recordStockMovement->handle(
                        organization: $organization,
                        location: $location,
                        storageLocation: $storageLocation,
                        inventoryItem: $inventoryItem,
                        type: StockMovementType::TransferIn,
                        baseQuantity: (string) $receivedBaseQuantity,
                        baseUnitOfMeasure: $baseUnit,
                        referenceType: 'stock_transfer_line',
                        referenceId: $line->id,
                        occurredAt: $receivedAt,
                        actor: $actor,
                        idempotencyKey: "stock_transfer:{$transfer->id}:line:{$line->id}:in",
                        notes: __(
                            'Stock transfer :number receipt',
                            [
                                'number' => $transfer->number,
                            ],
                        ),
                        inboundUnitCost: $line->unit_cost,
                    );

                    $movementCount++;
                }

                $varianceBaseQuantity =
                    $receivedBaseQuantity
                        ->minus($shippedBaseQuantity)
                        ->toScale(
                            6,
                            RoundingMode::HalfUp,
                        );

                if (
                    $varianceBaseQuantity->compareTo(
                        BigDecimal::zero(),
                    ) !== 0
                ) {
                    $varianceLineCount++;
                }

                $line->forceFill([
                    'received_base_quantity' => (string) $receivedBaseQuantity,
                    'variance_base_quantity' => (string) $varianceBaseQuantity,
                ])->save();
            }

            $transfer->forceFill([
                'status' => StockTransferStatus::Received,
                'received_at' => $receivedAt,
                'received_by' => $actor->id,
            ])->save();

            $this->recordAuditEntry->handle(
                organization: $organization,
                actor: $actor,
                action: 'stock_transfer.received',
                entityType: 'stock_transfer',
                entityId: $transfer->id,
                beforeData: [
                    'status' => StockTransferStatus::Shipped->value,
                ],
                afterData: [
                    'status' => StockTransferStatus::Received->value,
                    'received_at' => $receivedAt->toIso8601String(),
                    'line_count' => $lines->count(),
                    'movement_count' => $movementCount,
                    'variance_line_count' => $varianceLineCount,
                ],
                correlationId: "stock-transfer:{$transfer->id}:receive",
            );

            return $transfer->refresh();
        }, 3);
    }

    /**
     * Require explicit transfer receipt permission.
     */
    private function authorize(
        Organization $organization,
        User $actor,
    ): void {
        if (
            ! $actor->hasOrganizationPermission(
                $organization,
                OrganizationPermission::TransfersReceive,
            )
        ) {
            abort(403);
        }
    }

    /**
     * Lock the immutable transfer lines before receiving or comparing a replay.
     *
     * @return EloquentCollection<int, StockTransferLine>
     */
    private function lockedTransferLines(
        StockTransfer $transfer,
    ): EloquentCollection {
        $lines = StockTransferLine::query()
            ->where(
                'stock_transfer_id',
                $transfer->id,
            )
            ->orderBy('inventory_item_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => __(
                    'At least one stock-transfer line is required.',
                ),
            ]);
        }

        return $lines;
    }

    /**
     * Key the submitted receipt values by unique transfer line id.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<int, mixed>
     */
    private function receivedLineSet(
        array $attributes,
    ): array {
        $rawLines = $attributes['lines'] ?? [];

        if (! is_array($rawLines)) {
            throw ValidationException::withMessages([
                'lines' => __(
                    'Actual received quantities are required.',
                ),
            ]);
        }

        $receivedByLine = [];

        foreach (
            array_values($rawLines) as $index => $rawLine
        ) {
            if (! is_array($rawLine)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => __(
                        'Invalid receipt line.',
                    ),
                ]);
            }

            $lineId = (int) (
                $rawLine['id'] ?? 0
            );

            if (
                $lineId <= 0
                || array_key_exists(
                    $lineId,
                    $receivedByLine,
                )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.id" => __(
                        'Each transfer line must be received exactly once.',
                    ),
                ]);
            }

            $receivedByLine[$lineId] =
                $rawLine['received_base_quantity']
                ?? null;
        }

        return $receivedByLine;
    }

    /**
     * Require every persisted transfer line exactly once and reject extra ids.
     *
     * @param  EloquentCollection<int, StockTransferLine>  $lines
     * @param  array<int, mixed>  $receivedByLine
     */
    private function assertCompleteReceivedLineSet(
        EloquentCollection $lines,
        array $receivedByLine,
    ): void {
        if (
            count($receivedByLine)
            !== $lines->count()
        ) {
            throw ValidationException::withMessages([
                'lines' => __(
                    'A received quantity is required for every transfer line.',
                ),
            ]);
        }

        foreach ($lines as $line) {
            if (
                ! array_key_exists(
                    $line->id,
                    $receivedByLine,
                )
            ) {
                throw ValidationException::withMessages([
                    'lines' => __(
                        'A received quantity is required for every transfer line.',
                    ),
                ]);
            }
        }
    }

    /**
     * Accept a received-transfer retry only when it exactly replays the stored receipt snapshot.
     *
     * @param  EloquentCollection<int, StockTransferLine>  $lines
     * @param  array<int, mixed>  $receivedByLine
     */
    private function assertReceiptReplayMatches(
        EloquentCollection $lines,
        array $receivedByLine,
    ): void {
        foreach ($lines as $line) {
            if ($line->received_base_quantity === null) {
                throw ValidationException::withMessages([
                    'stock_transfer' => __(
                        'The recorded stock-transfer receipt snapshot is incomplete.',
                    ),
                ]);
            }

            $receivedBaseQuantity =
                $this->nonNegativeQuantity(
                    $receivedByLine[$line->id],
                    'received_base_quantity',
                );

            $persistedQuantity = BigDecimal::of(
                $line->received_base_quantity,
            )->toScale(
                6,
                RoundingMode::HalfUp,
            );

            if (
                (string) $receivedBaseQuantity
                !== (string) $persistedQuantity
            ) {
                throw ValidationException::withMessages([
                    'lines' => __(
                        'This stock transfer was already received with different quantities.',
                    ),
                ]);
            }
        }
    }

    /**
     * Parse a non-negative fixed-precision receipt quantity.
     */
    private function nonNegativeQuantity(
        mixed $value,
        string $field,
    ): BigDecimal {
        try {
            $quantity = BigDecimal::of(
                trim((string) $value),
            );
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                $field => __('A valid quantity is required.'),
            ]);
        }

        if (
            $quantity->compareTo(BigDecimal::zero()) < 0
            || $quantity->getScale() > 6
            || $quantity->compareTo(
                BigDecimal::of(self::MAX_QUANTITY),
            ) > 0
        ) {
            throw ValidationException::withMessages([
                $field => __(
                    'Received quantity must be non-negative with at most six decimal places.',
                ),
            ]);
        }

        return $quantity->toScale(
            6,
            RoundingMode::HalfUp,
        );
    }
}
