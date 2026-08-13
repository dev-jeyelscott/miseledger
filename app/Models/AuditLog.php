<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $actor_id
 * @property string $action
 * @property string $entity_type
 * @property int $entity_id
 * @property array<string, mixed>|null $before_data
 * @property array<string, mixed>|null $after_data
 * @property string|null $correlation_id
 * @property Carbon|null $created_at
 */
#[Fillable([
    'organization_id',
    'actor_id',
    'action',
    'entity_type',
    'entity_id',
    'before_data',
    'after_data',
    'correlation_id',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the organization owning this audit entry.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the actor when the user still exists.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Cast audit snapshots to structured values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
