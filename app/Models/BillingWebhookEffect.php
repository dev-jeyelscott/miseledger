<?php

namespace App\Models;

use App\Enums\BillingLifecycleEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $stripe_event_id
 * @property BillingLifecycleEvent $lifecycle_event
 * @property Carbon|null $notification_dispatched_at
 */
#[Fillable([
    'organization_id',
    'stripe_event_id',
    'lifecycle_event',
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
            'lifecycle_event' => BillingLifecycleEvent::class,
            'notification_dispatched_at' => 'datetime',
        ];
    }
}
