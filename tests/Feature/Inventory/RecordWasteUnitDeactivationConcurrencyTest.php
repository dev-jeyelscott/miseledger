<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Inventory\RecordWaste;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WasteReason;
use App\Models\WasteRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->storage = new StorageLocation;
    $this->storage->organization_id = $this->organization->id;
    $this->storage->location_id = $this->location->id;
    $this->storage->name = 'Waste Storage';
    $this->storage->code = 'WASTE';
    $this->storage->active = true;
    $this->storage->save();

    $this->gram = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $this->kilogram = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->gram->id,
        'name' => 'Chicken',
        'sku' => 'CHICKEN-CONCURRENCY',
        'active' => true,
    ]);

    $this->reason = WasteReason::query()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Spoilage',
        'active' => true,
    ]);

    $this->actor = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->actor->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storage,
        inventoryItem: $this->item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '1000',
        baseUnitOfMeasure: $this->gram,
        referenceType: 'opening_balance',
        referenceId: $this->item->id,
        occurredAt: now()->subHour(),
        idempotencyKey: "waste-concurrency-test:opening:{$this->item->id}",
        inboundUnitCost: '0.25',
    );
});

test(
    'waste recording is serialized against a concurrent selected-unit deactivation',
    function () {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL concurrency coverage runs in CI.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required for concurrency coverage.');
        }

        DB::commit();
        DB::disconnect();

        $resultPath = tempnam(sys_get_temp_dir(), 'miseledger-unit-deactivation-');

        if ($resultPath === false) {
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());

            throw new RuntimeException('Unable to create the concurrency result file.');
        }

        $childPid = pcntl_fork();

        if ($childPid === -1) {
            unlink($resultPath);
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());

            throw new RuntimeException('Unable to fork the concurrency test process.');
        }

        if ($childPid === 0) {
            try {
                DB::transaction(function (): void {
                    $lockedUnit = UnitOfMeasure::query()
                        ->whereKey($this->kilogram->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    // Hold the row lock briefly to widen the race window so
                    // the parent process's waste recording must wait on it.
                    usleep(500000);

                    $lockedUnit->update(['active' => false]);
                });

                file_put_contents($resultPath, 'success');
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($resultPath, get_class($exception));
                exit(1);
            }
        }

        // Give the child a head start so it acquires the unit row lock
        // before this process attempts to record waste using it.
        usleep(100000);

        $thrown = null;

        try {
            app(RecordWaste::class)->handle(
                $this->organization,
                $this->actor,
                [
                    'operation_id' => (string) Str::uuid(),
                    'location_id' => $this->location->id,
                    'storage_location_id' => $this->storage->id,
                    'inventory_item_id' => $this->item->id,
                    'waste_reason_id' => $this->reason->id,
                    'quantity' => '0.5',
                    'unit_id' => $this->kilogram->id,
                    'occurred_at' => now()
                        ->setTimezone($this->organization->timezone)
                        ->format('Y-m-d\TH:i'),
                    'notes' => null,
                ],
            );
        } catch (ValidationException $exception) {
            $thrown = $exception;
        }

        $childReaped = false;

        try {
            pcntl_waitpid($childPid, $status);
            $childReaped = true;

            expect(pcntl_wifexited($status))->toBeTrue()
                ->and(pcntl_wexitstatus($status))->toBe(0)
                ->and(file_get_contents($resultPath))->toBe('success')
                ->and($thrown)->not->toBeNull()
                ->and($thrown->errors())->toHaveKey('unit_id')
                ->and($this->kilogram->refresh()->active)->toBeFalse()
                ->and(
                    WasteRecord::query()
                        ->where('organization_id', $this->organization->id)
                        ->where('unit_id', $this->kilogram->id)
                        ->exists(),
                )->toBeFalse();
        } finally {
            if (! $childReaped) {
                pcntl_waitpid($childPid, $status);
            }

            if (file_exists($resultPath)) {
                unlink($resultPath);
            }

            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
        }
    },
);
