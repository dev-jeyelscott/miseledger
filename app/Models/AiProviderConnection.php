<?php

namespace App\Models;

use App\Enums\AiProvider;
use Database\Factories\AiProviderConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property AiProvider $provider
 * @property string|null $external_account_id
 * @property string|null $account_label
 * @property array<string, mixed>|null $metadata
 * @property bool $is_active
 * @property Carbon|null $activated_at
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'provider',
    'external_account_id',
    'account_label',
    'metadata',
    'is_active',
    'activated_at',
    'deactivated_at',
])]
class AiProviderConnection extends Model
{
    /** @use HasFactory<AiProviderConnectionFactory> */
    use HasFactory;

    /**
     * Get the user who owns this provider connection.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get durable runs that retain this connection as their origin.
     *
     * @return HasMany<AiRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AiRun::class);
    }

    /**
     * Limit a query to one user's provider connections.
     *
     * @param  Builder<AiProviderConnection>  $query
     * @return Builder<AiProviderConnection>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereBelongsTo($user);
    }

    /**
     * Limit a query to the active provider connection.
     *
     * @param  Builder<AiProviderConnection>  $query
     * @return Builder<AiProviderConnection>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Cast provider and safe connection metadata to application types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'metadata' => 'array',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }
}
