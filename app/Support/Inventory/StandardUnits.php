<?php

namespace App\Support\Inventory;

final class StandardUnits
{
    /**
     * Standard MVP units keyed by their stable symbol.
     *
     * Canonical factors use grams for weight and milliliters for volume.
     * Count units deliberately have no global factor.
     *
     * @var array<string, array{
     *     symbol: string,
     *     name: string,
     *     dimension: string,
     *     canonical_factor: string|null
     * }>
     */
    private const UNITS = [
        'mg' => [
            'symbol' => 'mg',
            'name' => 'Milligram',
            'dimension' => 'weight',
            'canonical_factor' => '0.001',
        ],
        'g' => [
            'symbol' => 'g',
            'name' => 'Gram',
            'dimension' => 'weight',
            'canonical_factor' => '1',
        ],
        'kg' => [
            'symbol' => 'kg',
            'name' => 'Kilogram',
            'dimension' => 'weight',
            'canonical_factor' => '1000',
        ],
        'oz' => [
            'symbol' => 'oz',
            'name' => 'Ounce',
            'dimension' => 'weight',
            'canonical_factor' => '28.349523125',
        ],
        'lb' => [
            'symbol' => 'lb',
            'name' => 'Pound',
            'dimension' => 'weight',
            'canonical_factor' => '453.59237',
        ],

        'ml' => [
            'symbol' => 'ml',
            'name' => 'Milliliter',
            'dimension' => 'volume',
            'canonical_factor' => '1',
        ],
        'l' => [
            'symbol' => 'l',
            'name' => 'Liter',
            'dimension' => 'volume',
            'canonical_factor' => '1000',
        ],
        'tsp' => [
            'symbol' => 'tsp',
            'name' => 'Teaspoon',
            'dimension' => 'volume',
            'canonical_factor' => '4.92892159375',
        ],
        'tbsp' => [
            'symbol' => 'tbsp',
            'name' => 'Tablespoon',
            'dimension' => 'volume',
            'canonical_factor' => '14.78676478125',
        ],
        'cup' => [
            'symbol' => 'cup',
            'name' => 'Cup',
            'dimension' => 'volume',
            'canonical_factor' => '236.5882365',
        ],
        'floz' => [
            'symbol' => 'floz',
            'name' => 'Fluid Ounce',
            'dimension' => 'volume',
            'canonical_factor' => '29.5735295625',
        ],

        'piece' => [
            'symbol' => 'piece',
            'name' => 'Piece',
            'dimension' => 'count',
            'canonical_factor' => null,
        ],
        'bottle' => [
            'symbol' => 'bottle',
            'name' => 'Bottle',
            'dimension' => 'count',
            'canonical_factor' => null,
        ],
        'can' => [
            'symbol' => 'can',
            'name' => 'Can',
            'dimension' => 'count',
            'canonical_factor' => null,
        ],
        'pack' => [
            'symbol' => 'pack',
            'name' => 'Pack',
            'dimension' => 'count',
            'canonical_factor' => null,
        ],
        'tray' => [
            'symbol' => 'tray',
            'name' => 'Tray',
            'dimension' => 'count',
            'canonical_factor' => null,
        ],
        'box' => [
            'symbol' => 'box',
            'name' => 'Box',
            'dimension' => 'count',
            'canonical_factor' => null,
        ],
        'case' => [
            'symbol' => 'case',
            'name' => 'Case',
            'dimension' => 'count',
            'canonical_factor' => null,
        ],
        'bag' => [
            'symbol' => 'bag',
            'name' => 'Bag',
            'dimension' => 'count',
            'canonical_factor' => null,
        ],
        'sack' => [
            'symbol' => 'sack',
            'name' => 'Sack',
            'dimension' => 'count',
            'canonical_factor' => null,
        ],
    ];

    /**
     * Return the only supported MVP measurement dimensions.
     *
     * @return list<string>
     */
    public static function dimensions(): array
    {
        return [
            'weight',
            'volume',
            'count',
        ];
    }

    /**
     * Return standard units in deterministic insertion order.
     *
     * @return list<array{
     *     symbol: string,
     *     name: string,
     *     dimension: string,
     *     canonical_factor: string|null
     * }>
     */
    public static function definitions(): array
    {
        return array_values(self::UNITS);
    }

    /**
     * Resolve the required dimension for a reserved standard symbol.
     */
    public static function dimensionFor(string $symbol): ?string
    {
        $definition = self::UNITS[self::normalizeSymbol($symbol)] ?? null;

        return $definition['dimension'] ?? null;
    }

    /**
     * Resolve a standard factor to its dimension's canonical unit.
     *
     * Count units intentionally return null because pack/count
     * relationships are item-specific by default.
     */
    public static function canonicalFactor(
        string $symbol,
        string $dimension,
    ): ?string {
        $definition = self::UNITS[self::normalizeSymbol($symbol)] ?? null;

        if (
            $definition === null
            || $definition['dimension'] !== $dimension
        ) {
            return null;
        }

        return $definition['canonical_factor'];
    }

    /**
     * Normalize symbols only for deterministic registry lookup.
     */
    private static function normalizeSymbol(string $symbol): string
    {
        return strtolower(trim($symbol));
    }
}
