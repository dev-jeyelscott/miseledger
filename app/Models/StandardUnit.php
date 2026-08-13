<?php

namespace App\Models;

use Database\Factories\StandardUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $dimension
 * @property string|null $canonical_factor
 * @property bool $active
 */
#[Fillable([
    'code',
    'name',
    'dimension',
    'canonical_factor',
    'active',
])]
class StandardUnit extends Model
{
    /** @use HasFactory<StandardUnitFactory> */
    use HasFactory;

    /**
     * Cast persisted standard-unit state.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'canonical_factor' => 'decimal:12',
        ];
    }
}
