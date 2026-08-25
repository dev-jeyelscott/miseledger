<?php

namespace App\Models;

use App\Enums\BillingLifecycleEvent;
use App\Enums\BillingProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property BillingProvider $provider
 * @property string $external_event_id
 * @property string|null $stripe_event_id Populated only for provider=stripe; kept for backward compatibility.
 * @property BillingLifecycleEvent $lifecycle_event
 * @property Carbon|null $notification_claimed_at
 * @property Carbon|null $notification_dispatched_at
 */
#[Fillable([
    'organization_id',
    'provider',
    'external_event_id',
    'stripe_event_id',
    'lifecycle_event',
    'notification_claimed_at',
    'notification_dispatched_at',
])]
class BillingWebhookEffect extends Model
{
    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => BillingProvider::class,
            'lifecycle_event' => BillingLifecycleEvent::class,
            'notification_claimed_at' => 'datetime',
            'notification_dispatched_at' => 'datetime',
        ];
    }
}
