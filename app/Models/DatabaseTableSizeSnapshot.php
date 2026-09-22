<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One daily, per-table PostgreSQL storage-metadata snapshot (schema/table
 * identifier and catalog-reported byte sizes only, never row contents), used
 * to measure real table growth over time. A row is only ever replaced by a
 * same-day recapture for the same table (idempotent capture) or pruned by
 * bounded retention; normal business workflows never write here.
 *
 * @property int $id
 * @property Carbon $captured_on
 * @property string $schema_name
 * @property string $table_name
 * @property int $total_bytes
 * @property int $table_bytes
 * @property int $index_bytes
 * @property Carbon|null $created_at
 */
#[Fillable([
    'captured_on',
    'schema_name',
    'table_name',
    'total_bytes',
    'table_bytes',
    'index_bytes',
])]
class DatabaseTableSizeSnapshot extends Model
{
    public const UPDATED_AT = null;

    /**
     * Cast stored attributes to structured values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_on' => 'date',
            'total_bytes' => 'integer',
            'table_bytes' => 'integer',
            'index_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
