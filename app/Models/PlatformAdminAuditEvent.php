<?php

namespace App\Models;

use App\Enums\PlatformAdminAuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only history of platform administrator grant and revoke transitions.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $target_email
 * @property PlatformAdminAuditAction $action
 * @property int|null $actor_id
 * @property string $source
 * @property string $reason
 * @property Carbon|null $created_at
 */
#[Fillable([
    'user_id',
    'target_email',
    'action',
    'actor_id',
    'source',
    'reason',
])]
class PlatformAdminAuditEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the MiseLedger user targeted by this platform admin transition.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the actor who performed this platform admin transition, when known.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Cast stored attributes to structured values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => PlatformAdminAuditAction::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * Prevent mutation or deletion of committed platform admin audit history.
     */
    protected static function booted(): void
    {
        static::updating(static function (PlatformAdminAuditEvent $event): never {
            throw new LogicException(
                'Platform admin audit events are immutable.',
            );
        });

        static::deleting(static function (PlatformAdminAuditEvent $event): never {
            throw new LogicException(
                'Platform admin audit events cannot be deleted.',
            );
        });
    }
}
