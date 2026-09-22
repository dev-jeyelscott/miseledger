<?php

namespace App\Models;

use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertState;
use App\Enums\PlatformAlertType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $fingerprint
 * @property PlatformAlertType $type
 * @property string $source
 * @property PlatformAlertSeverity $severity
 * @property PlatformAlertState $state
 * @property string $title
 * @property string $summary
 * @property array<string, mixed> $context
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $resolved_at
 * @property int $occurrence_count
 */
#[Fillable([
    'fingerprint',
    'type',
    'source',
    'severity',
    'state',
    'title',
    'summary',
    'context',
    'first_seen_at',
    'last_seen_at',
    'resolved_at',
    'occurrence_count',
])]
class PlatformAlert extends Model
{
    /** @return HasMany<PlatformAlertDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(PlatformAlertDelivery::class);
    }

    /**
     * Resolve the exact read-only Platform Console page this alert's
     * condition is about (POC-V9.2 / POC-V9.4). Every case links to a page
     * that already exists; no new route is invented for this feature.
     */
    public function targetUrl(): string
    {
        return match ($this->type) {
            PlatformAlertType::BillingFailedPayments => isset($this->context['organizationId'])
                ? route('admin.organizations.show', $this->context['organizationId'])
                : route('admin.billing.payments.index'),
            PlatformAlertType::ProblemReportAttention => isset($this->context['reference'])
                ? route('problem-reports.show-operator', $this->context['reference'])
                : route('admin.dashboard'),
            PlatformAlertType::QueueBacklog,
            PlatformAlertType::SlowQueries,
            PlatformAlertType::DatabaseHealth,
            PlatformAlertType::MigrationBacklog,
            PlatformAlertType::TableGrowthAnomaly,
            PlatformAlertType::BackupFailure => route('admin.health.index'),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PlatformAlertType::class,
            'severity' => PlatformAlertSeverity::class,
            'state' => PlatformAlertState::class,
            'context' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
