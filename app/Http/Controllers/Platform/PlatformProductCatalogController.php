<?php

namespace App\Http\Controllers\Platform;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use App\Enums\PlanCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PublishPlatformPlanVersionRequest;
use App\Http\Requests\Platform\SavePlatformPlanVersionRequest;
use App\Models\BillingPlanVersion;
use App\Models\BillingPlanVersionPrice;
use App\Models\BillingSubscription;
use App\Models\User;
use App\Support\Billing\FeatureCode;
use App\Support\Billing\PlanCatalog;
use App\Support\Billing\PlanDefinition;
use App\Support\Billing\UsageLimitKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * The first intentionally mutable commercial-configuration surface in the
 * platform console. Recognized plan codes are sourced exclusively from
 * `PlanCatalog::all()`; feature codes and limit keys are validated against
 * their application-owned registries. Published versions are immutable
 * historical commercial state and are never edited through this surface.
 */
final class PlatformProductCatalogController extends Controller
{
    /** Render every recognized plan code with its current lifecycle summary. */
    public function index(PlanCatalog $catalog): Response
    {
        $subscriptionType = (string) config('billing.subscription_type');

        $plans = array_map(
            fn (PlanDefinition $definition): array => $this->planSummary(
                $definition,
                $subscriptionType,
            ),
            $catalog->all(),
        );

        return Inertia::render('admin/product-catalog/index', [
            'plans' => $plans,
        ]);
    }

    /** Render the full version history for one recognized plan code. */
    public function show(string $planCode, PlanCatalog $catalog): Response
    {
        $definition = $this->resolveDefinition($planCode, $catalog);
        $subscriptionType = (string) config('billing.subscription_type');

        $versions = BillingPlanVersion::query()
            ->where('plan_code', $planCode)
            ->with(['prices', 'createdBy:id,name,email', 'publishedBy:id,name,email'])
            ->orderByDesc('version')
            ->get();

        return Inertia::render('admin/product-catalog/show', [
            'plan' => [
                'planCode' => $planCode,
                'name' => $definition->name,
                'tier' => $definition->tier,
            ],
            'versions' => $versions
                ->map(fn (BillingPlanVersion $version): array => $this->versionPayload(
                    $version,
                    $subscriptionType,
                ))
                ->all(),
            'hasDraft' => $versions->contains(
                fn (BillingPlanVersion $version): bool => $version->isDraft(),
            ),
            'featureOptions' => $this->featureOptions(),
            'limitOptions' => $this->limitOptions(),
        ]);
    }

    /** Create a new draft version for a recognized plan code. */
    public function store(
        string $planCode,
        SavePlatformPlanVersionRequest $request,
        PlanCatalog $catalog,
    ): RedirectResponse {
        $this->resolveDefinition($planCode, $catalog);

        if (
            BillingPlanVersion::query()
                ->where('plan_code', $planCode)
                ->whereNull('published_at')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'name' => 'A draft version already exists for this plan. Edit the existing draft instead of creating another.',
            ]);
        }

        $nextVersion = (int) (
            BillingPlanVersion::query()
                ->where('plan_code', $planCode)
                ->max('version') ?? 0
        ) + 1;

        $user = $request->user();

        $version = BillingPlanVersion::query()->create([
            'plan_code' => $planCode,
            'version' => $nextVersion,
            'name' => (string) $request->validated('name'),
            'tier' => (int) $request->validated('tier'),
            'feature_codes' => array_values(
                (array) $request->validated('feature_codes', []),
            ),
            'limits' => (array) $request->validated('limits'),
            'created_by_user_id' => $user instanceof User ? $user->id : null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Draft version {$nextVersion} created.",
        ]);

        return to_route('admin.product-catalog.versions.edit', $version);
    }

    /** Render the edit page for one draft commercial version. */
    public function edit(
        BillingPlanVersion $billingPlanVersion,
        PlanCatalog $catalog,
    ): Response {
        abort_unless($billingPlanVersion->isDraft(), 403);

        $definition = $catalog->legacyGet($billingPlanVersion->planCode());

        return Inertia::render('admin/product-catalog/versions/edit', [
            'plan' => [
                'planCode' => $billingPlanVersion->plan_code,
                'name' => $definition->name ?? $billingPlanVersion->plan_code,
            ],
            'version' => $this->versionPayload(
                $billingPlanVersion,
                (string) config('billing.subscription_type'),
            ),
            'featureOptions' => $this->featureOptions(),
            'limitOptions' => $this->limitOptions(),
            'providerOptions' => $this->providerOptions(),
            'collectionMethodOptions' => $this->collectionMethodOptions(),
            'intervalOptions' => $this->intervalOptions(),
        ]);
    }

    /** Update a draft commercial version's declared entitlements and prices. */
    public function update(
        BillingPlanVersion $billingPlanVersion,
        SavePlatformPlanVersionRequest $request,
    ): RedirectResponse {
        abort_unless($billingPlanVersion->isDraft(), 403);

        DB::transaction(function () use ($billingPlanVersion, $request): void {
            $billingPlanVersion->update([
                'name' => (string) $request->validated('name'),
                'tier' => (int) $request->validated('tier'),
                'feature_codes' => array_values(
                    (array) $request->validated('feature_codes', []),
                ),
                'limits' => (array) $request->validated('limits'),
            ]);

            $billingPlanVersion->prices()->delete();

            /** @var list<array<string, mixed>> $prices */
            $prices = (array) $request->validated('prices', []);

            foreach ($prices as $price) {
                $billingPlanVersion->prices()->create([
                    'provider' => $price['provider'],
                    'collection_method' => $price['collection_method'],
                    'interval' => $price['interval'],
                    'currency' => $price['currency'],
                    'amount_minor' => $price['amount_minor'],
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Draft version updated.',
        ]);

        return back();
    }

    /**
     * Publish a draft version, superseding the prior current version.
     * Existing subscribers stay pinned to their already-resolved plan
     * version and are never repriced by this action.
     */
    public function publish(
        BillingPlanVersion $billingPlanVersion,
        PublishPlatformPlanVersionRequest $request,
        PlanCatalog $catalog,
    ): RedirectResponse {
        abort_unless($billingPlanVersion->isDraft(), 403);

        $planCode = $billingPlanVersion->planCode();
        $requirements = $catalog->priceRequirements($planCode);

        if ($requirements === []) {
            throw ValidationException::withMessages([
                'confirm' => 'This plan has no configured provider or manual pricing paths to publish.',
            ]);
        }

        $missing = $catalog->missingPriceRequirements(
            $planCode,
            $billingPlanVersion->prices,
        );

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'confirm' => 'Add authoritative prices for every configured provider, collection method, interval, and currency before publishing.',
            ]);
        }

        $user = $request->user();

        DB::transaction(function () use ($billingPlanVersion, $user): void {
            $current = BillingPlanVersion::query()
                ->where('plan_code', $billingPlanVersion->plan_code)
                ->whereNotNull('published_at')
                ->whereNull('superseded_at')
                ->lockForUpdate()
                ->first();

            $now = now();

            if ($current !== null) {
                $current->update(['superseded_at' => $now]);
            }

            $billingPlanVersion->update([
                'published_at' => $now,
                'published_by_user_id' => $user instanceof User ? $user->id : null,
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Version published. Existing subscribers remain pinned to their prior plan version and are not repriced.',
        ]);

        return to_route('admin.product-catalog.show', $billingPlanVersion->plan_code);
    }

    /** Resolve a route plan-code segment to its recognized catalog definition or 404. */
    private function resolveDefinition(
        string $planCode,
        PlanCatalog $catalog,
    ): PlanDefinition {
        try {
            $code = PlanCode::from($planCode);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $definition = $catalog->legacyGet($code);

        if ($definition === null) {
            abort(404);
        }

        return $definition;
    }

    /**
     * Summarize one recognized plan code's current/draft/superseded state.
     *
     * @return array<string, mixed>
     */
    private function planSummary(
        PlanDefinition $definition,
        string $subscriptionType,
    ): array {
        $planCode = $definition->code->value;

        $current = BillingPlanVersion::query()
            ->where('plan_code', $planCode)
            ->whereNotNull('published_at')
            ->whereNull('superseded_at')
            ->first();

        $draftCount = BillingPlanVersion::query()
            ->where('plan_code', $planCode)
            ->whereNull('published_at')
            ->count();

        $supersededCount = BillingPlanVersion::query()
            ->where('plan_code', $planCode)
            ->whereNotNull('superseded_at')
            ->count();

        $subscriberCount = $current !== null
            ? BillingSubscription::query()
                ->where('type', $subscriptionType)
                ->where('plan_version_id', $current->id)
                ->count()
            : 0;

        return [
            'planCode' => $planCode,
            'name' => $current->name ?? $definition->name,
            'tier' => $current->tier ?? $definition->tier,
            'hasCurrentVersion' => $current !== null,
            'currentVersionNumber' => $current?->version,
            'draftCount' => $draftCount,
            'supersededCount' => $supersededCount,
            'subscriberCount' => $subscriberCount,
        ];
    }

    /**
     * Render one commercial version without exposing any provider secret
     * or external identifier.
     *
     * @return array<string, mixed>
     */
    private function versionPayload(
        BillingPlanVersion $version,
        string $subscriptionType,
    ): array {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'name' => $version->name,
            'tier' => $version->tier,
            'status' => $version->status(),
            'featureCodes' => $version->feature_codes,
            'limits' => $version->limits,
            'prices' => $version->prices
                ->map(fn (BillingPlanVersionPrice $price): array => [
                    'id' => $price->id,
                    'provider' => $price->provider->value,
                    'collectionMethod' => $price->collection_method->value,
                    'interval' => $price->interval,
                    'currency' => $price->currency,
                    'amountMinor' => (string) $price->amount_minor,
                ])
                ->all(),
            'subscriberCount' => BillingSubscription::query()
                ->where('type', $subscriptionType)
                ->where('plan_version_id', $version->id)
                ->count(),
            'createdBy' => $version->createdBy instanceof User
                ? [
                    'name' => $version->createdBy->name,
                    'email' => $version->createdBy->email,
                ]
                : null,
            'createdAt' => $version->created_at?->toIso8601String(),
            'publishedBy' => $version->publishedBy instanceof User
                ? [
                    'name' => $version->publishedBy->name,
                    'email' => $version->publishedBy->email,
                ]
                : null,
            'publishedAt' => $version->published_at?->toIso8601String(),
            'supersededAt' => $version->superseded_at?->toIso8601String(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function featureOptions(): array
    {
        return array_map(
            static fn (string $code): array => [
                'value' => $code,
                'label' => Str::headline(str_replace('.', ' ', $code)),
            ],
            FeatureCode::all(),
        );
    }

    /** @return list<array{value: string, label: string}> */
    private function limitOptions(): array
    {
        return array_map(
            static fn (string $key): array => [
                'value' => $key,
                'label' => Str::headline($key),
            ],
            UsageLimitKey::all(),
        );
    }

    /** @return list<array{value: string, label: string}> */
    private function providerOptions(): array
    {
        return array_map(
            static fn (BillingProvider $provider): array => [
                'value' => $provider->value,
                'label' => match ($provider) {
                    BillingProvider::Stripe => 'Stripe',
                    BillingProvider::PayMongo => 'PayMongo',
                },
            ],
            BillingProvider::cases(),
        );
    }

    /** @return list<array{value: string, label: string}> */
    private function collectionMethodOptions(): array
    {
        return array_map(
            static fn (BillingCollectionMethod $method): array => [
                'value' => $method->value,
                'label' => match ($method) {
                    BillingCollectionMethod::Automatic => 'Automatic',
                    BillingCollectionMethod::Manual => 'Manual',
                },
            ],
            BillingCollectionMethod::cases(),
        );
    }

    /** @return list<array{value: string, label: string}> */
    private function intervalOptions(): array
    {
        return [
            ['value' => 'monthly', 'label' => 'Monthly'],
            ['value' => 'yearly', 'label' => 'Yearly'],
        ];
    }
}
