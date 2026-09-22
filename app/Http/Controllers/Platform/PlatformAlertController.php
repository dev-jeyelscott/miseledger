<?php

namespace App\Http\Controllers\Platform;

use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertType;
use App\Http\Controllers\Controller;
use App\Models\PlatformAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bounded, read-only Platform Alerts console (POC-V9.4). This page never
 * exposes a resolve, acknowledge, retry, or any other mutation control;
 * alert lifecycle is exclusively evaluator-owned (POC-V9.1, POC-V9.6).
 */
final class PlatformAlertController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'state' => ['nullable', Rule::in(['open', 'resolved'])],
            'severity' => ['nullable', Rule::in(array_column(PlatformAlertSeverity::cases(), 'value'))],
            'type' => ['nullable', Rule::in(array_column(PlatformAlertType::cases(), 'value'))],
            'period' => ['nullable', Rule::in(['24h', '7d', '30d'])],
            'per_page' => ['nullable', 'integer', Rule::in(config('platform_alerts.per_page_options'))],
        ]);

        $state = isset($validated['state']) ? (string) $validated['state'] : null;
        $severity = isset($validated['severity']) ? (string) $validated['severity'] : null;
        $type = isset($validated['type']) ? (string) $validated['type'] : null;
        $period = isset($validated['period']) ? (string) $validated['period'] : null;
        $perPage = (int) ($validated['per_page'] ?? config('platform_alerts.default_per_page'));

        $alertsQuery = PlatformAlert::query()
            ->select([
                'id', 'fingerprint', 'type', 'source', 'severity', 'state',
                'title', 'first_seen_at', 'last_seen_at', 'resolved_at', 'occurrence_count',
            ]);

        if ($state !== null) {
            $alertsQuery->where('state', $state);
        }

        if ($severity !== null) {
            $alertsQuery->where('severity', $severity);
        }

        if ($type !== null) {
            $alertsQuery->where('type', $type);
        }

        if ($period !== null) {
            $since = match ($period) {
                '24h' => Carbon::now()->subDay(),
                '7d' => Carbon::now()->subDays(7),
                '30d' => Carbon::now()->subDays(30),
                default => null,
            };

            if ($since !== null) {
                $alertsQuery->where('last_seen_at', '>=', $since);
            }
        }

        $alerts = $alertsQuery
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PlatformAlert $alert): array => $this->summaryData($alert));

        return Inertia::render('admin/alerts/index', [
            'alerts' => $alerts->items(),
            'pagination' => [
                'current_page' => $alerts->currentPage(),
                'from' => $alerts->firstItem(),
                'last_page' => $alerts->lastPage(),
                'next_page_url' => $alerts->nextPageUrl(),
                'per_page' => $alerts->perPage(),
                'prev_page_url' => $alerts->previousPageUrl(),
                'to' => $alerts->lastItem(),
                'total' => $alerts->total(),
            ],
            'filters' => [
                'state' => $state,
                'severity' => $severity,
                'type' => $type,
                'period' => $period,
                'perPage' => $perPage,
            ],
            'typeOptions' => array_map(
                fn (PlatformAlertType $type): array => ['value' => $type->value, 'label' => $type->label()],
                PlatformAlertType::cases(),
            ),
        ]);
    }

    public function show(PlatformAlert $platformAlert): Response
    {
        $history = PlatformAlert::query()
            ->select(['id', 'state', 'severity', 'first_seen_at', 'last_seen_at', 'resolved_at', 'occurrence_count'])
            ->where('fingerprint', $platformAlert->fingerprint)
            ->where('id', '!=', $platformAlert->getKey())
            ->orderByDesc('first_seen_at')
            ->limit(20)
            ->get()
            ->map(fn (PlatformAlert $alert): array => [
                'id' => $alert->id,
                'state' => $alert->state->value,
                'severity' => $alert->severity->value,
                'firstSeenAt' => $alert->first_seen_at->toIso8601String(),
                'lastSeenAt' => $alert->last_seen_at->toIso8601String(),
                'resolvedAt' => $alert->resolved_at?->toIso8601String(),
                'occurrenceCount' => $alert->occurrence_count,
            ])
            ->all();

        return Inertia::render('admin/alerts/show', [
            'alert' => [
                ...$this->summaryData($platformAlert),
                'summary' => $platformAlert->summary,
                'context' => $platformAlert->context,
                'targetUrl' => $platformAlert->targetUrl(),
            ],
            'history' => $history,
        ]);
    }

    /**
     * Minimized, list-safe alert representation shared by index and show.
     *
     * @return array{
     *     id: int,
     *     fingerprint: string,
     *     type: string,
     *     typeLabel: string,
     *     source: string,
     *     severity: string,
     *     state: string,
     *     title: string,
     *     firstSeenAt: string,
     *     lastSeenAt: string,
     *     resolvedAt: string|null,
     *     occurrenceCount: int
     * }
     */
    private function summaryData(PlatformAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'fingerprint' => $alert->fingerprint,
            'type' => $alert->type->value,
            'typeLabel' => $alert->type->label(),
            'source' => $alert->source,
            'severity' => $alert->severity->value,
            'state' => $alert->state->value,
            'title' => $alert->title,
            'firstSeenAt' => $alert->first_seen_at->toIso8601String(),
            'lastSeenAt' => $alert->last_seen_at->toIso8601String(),
            'resolvedAt' => $alert->resolved_at?->toIso8601String(),
            'occurrenceCount' => $alert->occurrence_count,
        ];
    }
}
