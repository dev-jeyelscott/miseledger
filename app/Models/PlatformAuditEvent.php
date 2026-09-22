<?php

namespace App\Models;

use App\Enums\PlatformAuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only evidence trail for consequential, non-organization-scoped
 * platform-owner actions (catalog/CMS/security transitions). Separate from
 * the tenant-scoped `AuditLog` and from `PlatformAdminAuditEvent`
 * (grant/revoke history).
 *
 * @property int $id
 * @property int|null $actor_user_id
 * @property PlatformAuditAction $action
 * @property string $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $correlation_key
 * @property string $source
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'actor_user_id',
    'action',
    'subject_type',
    'subject_id',
    'before',
    'after',
    'correlation_key',
    'source',
    'occurred_at',
])]
class PlatformAuditEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the platform actor who performed this transition, when known.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Cast stored attributes to structured values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => PlatformAuditAction::class,
            'before' => 'array',
            'after' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Prevent mutation or deletion of committed platform audit evidence.
     */
    protected static function booted(): void
    {
        static::updating(static function (PlatformAuditEvent $event): never {
            throw new LogicException(
                'Platform audit events are immutable.',
            );
        });

        static::deleting(static function (PlatformAuditEvent $event): never {
            throw new LogicException(
                'Platform audit events cannot be deleted.',
            );
        });
    }
}
