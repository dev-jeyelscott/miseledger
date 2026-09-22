<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $platform_alert_id
 * @property int $recipient_user_id
 * @property string $channel
 * @property string $event_kind
 * @property string $dedupe_key
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 */
#[Fillable([
    'platform_alert_id',
    'recipient_user_id',
    'channel',
    'event_kind',
    'dedupe_key',
    'sent_at',
    'failed_at',
])]
class PlatformAlertDelivery extends Model
{
    /** @return BelongsTo<PlatformAlert, $this> */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(PlatformAlert::class, 'platform_alert_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
