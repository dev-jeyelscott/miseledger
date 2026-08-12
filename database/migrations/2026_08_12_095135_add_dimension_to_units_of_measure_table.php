<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standard units needed to safely align existing tenant-scoped UOMs
     * with the locked MVP specification.
     *
     * @var array<string, array{name: string, dimension: string}>
     */
    private const STANDARD_UNITS = [
        'mg' => ['name' => 'Milligram', 'dimension' => 'weight'],
        'g' => ['name' => 'Gram', 'dimension' => 'weight'],
        'kg' => ['name' => 'Kilogram', 'dimension' => 'weight'],
        'oz' => ['name' => 'Ounce', 'dimension' => 'weight'],
        'lb' => ['name' => 'Pound', 'dimension' => 'weight'],

        'ml' => ['name' => 'Milliliter', 'dimension' => 'volume'],
        'l' => ['name' => 'Liter', 'dimension' => 'volume'],
        'tsp' => ['name' => 'Teaspoon', 'dimension' => 'volume'],
        'tbsp' => ['name' => 'Tablespoon', 'dimension' => 'volume'],
        'cup' => ['name' => 'Cup', 'dimension' => 'volume'],
        'floz' => ['name' => 'Fluid Ounce', 'dimension' => 'volume'],

        'piece' => ['name' => 'Piece', 'dimension' => 'count'],
        'bottle' => ['name' => 'Bottle', 'dimension' => 'count'],
        'can' => ['name' => 'Can', 'dimension' => 'count'],
        'pack' => ['name' => 'Pack', 'dimension' => 'count'],
        'tray' => ['name' => 'Tray', 'dimension' => 'count'],
        'box' => ['name' => 'Box', 'dimension' => 'count'],
        'case' => ['name' => 'Case', 'dimension' => 'count'],
        'bag' => ['name' => 'Bag', 'dimension' => 'count'],
        'sack' => ['name' => 'Sack', 'dimension' => 'count'],
    ];

    /**
     * Add dimension and safely align existing organizations.
     */
    public function up(): void
    {
        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table
                ->enum('dimension', ['weight', 'volume', 'count'])
                ->default('count');
        });

        $this->backfillKnownDimensions();
        $this->seedMissingStandardUnits();
    }

    /**
     * Remove only schema introduced by this migration.
     *
     * Seeded master-data rows are intentionally retained during rollback
     * because deleting a unit that may have become referenced would be
     * destructive.
     */
    public function down(): void
    {
        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table->dropColumn('dimension');
        });
    }

    /**
     * Give recognized legacy symbols their authoritative dimension.
     *
     * Unknown existing units remain count. This conservative fallback
     * prevents an unknown unit from receiving an accidental global
     * weight or volume conversion.
     */
    private function backfillKnownDimensions(): void
    {
        DB::table('units_of_measure')
            ->select(['id', 'symbol'])
            ->orderBy('id')
            ->chunkById(200, function ($units): void {
                foreach ($units as $unit) {
                    $symbol = strtolower(trim((string) $unit->symbol));
                    $definition = self::STANDARD_UNITS[$symbol] ?? null;

                    if ($definition === null) {
                        continue;
                    }

                    DB::table('units_of_measure')
                        ->where('id', $unit->id)
                        ->update([
                            'dimension' => $definition['dimension'],
                        ]);
                }
            });
    }

    /**
     * Ensure every existing tenant receives the approved standard UOM set.
     */
    private function seedMissingStandardUnits(): void
    {
        DB::table('organizations')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($organizations): void {
                foreach ($organizations as $organization) {
                    foreach (self::STANDARD_UNITS as $symbol => $definition) {
                        $this->ensureStandardUnit(
                            (int) $organization->id,
                            $symbol,
                            $definition['name'],
                            $definition['dimension'],
                        );
                    }
                }
            });
    }

    /**
     * Insert a missing standard unit without overwriting legacy master data.
     */
    private function ensureStandardUnit(
        int $organizationId,
        string $symbol,
        string $name,
        string $dimension,
    ): void {
        $existing = DB::table('units_of_measure')
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(symbol) = ?', [strtolower($symbol)])
            ->first();

        if ($existing !== null) {
            if ($existing->dimension !== $dimension) {
                DB::table('units_of_measure')
                    ->where('id', $existing->id)
                    ->update([
                        'dimension' => $dimension,
                    ]);
            }

            return;
        }

        $timestamp = now();

        DB::table('units_of_measure')->insert([
            'organization_id' => $organizationId,
            'name' => $this->availableName(
                $organizationId,
                $name,
                $symbol,
            ),
            'symbol' => $symbol,
            'dimension' => $dimension,
            'active' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /**
     * Avoid destroying or renaming an existing custom UOM on name collision.
     */
    private function availableName(
        int $organizationId,
        string $name,
        string $symbol,
    ): string {
        if (! $this->nameExists($organizationId, $name)) {
            return $name;
        }

        $candidate = "{$name} ({$symbol})";
        $suffix = 2;

        while ($this->nameExists($organizationId, $candidate)) {
            $candidate = "{$name} ({$symbol} {$suffix})";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Determine whether an organization already uses a UOM display name.
     */
    private function nameExists(
        int $organizationId,
        string $name,
    ): bool {
        return DB::table('units_of_measure')
            ->where('organization_id', $organizationId)
            ->where('name', $name)
            ->exists();
    }
};
