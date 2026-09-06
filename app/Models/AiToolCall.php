<?php

namespace App\Models;

use Database\Factories\AiToolCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ai_run_id
 * @property string $tool_name
 * @property string|null $provider_tool_call_id
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 */
#[Fillable([
    'ai_run_id',
    'tool_name',
    'provider_tool_call_id',
    'status',
    'metadata',
])]
class AiToolCall extends Model
{
    /** @use HasFactory<AiToolCallFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<AiRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    /**
     * Limit a query to one durable execution attempt.
     *
     * @param  Builder<AiToolCall>  $query
     * @return Builder<AiToolCall>
     */
    public function scopeForRun(Builder $query, AiRun $run): Builder
    {
        return $query->whereBelongsTo($run, 'run');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
