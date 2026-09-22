<?php

use App\Enums\PlatformAuditAction;
use App\Models\BillingCustomer;
use App\Models\BillingPlanVersion;
use App\Models\BillingPlanVersionPrice;
use App\Models\BillingSubscription;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function productCatalogFixturePlans(): array
{
    return [
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'manual_amounts' => ['monthly' => 49_900, 'yearly' => null],
            'features' => [],
            'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
        ],
        'growth' => [
            'name' => 'Growth',
            'tier' => 2,
            'manual_amounts' => ['monthly' => 99_900, 'yearly' => null],
            'features' => ['purchasing'],
            'limits' => ['seats' => 10, 'locations' => 5, 'inventory_items' => 5000],
        ],
    ];
}

function actingPlatformAdmin(): User
{
    $platformUser = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    return $platformUser;
}

beforeEach(function (): void {
    Config::set('billing.plans', productCatalogFixturePlans());
});

test('product catalog routes preserve the platform administrator boundary', function () {
    $normalUser = User::factory()->create();
    $platformUser = actingPlatformAdmin();

    $draft = BillingPlanVersion::factory()->create([
        'plan_code' => 'starter',
        'version' => 1,
        'feature_codes' => [],
        'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
    ]);

    $this->get(route('admin.product-catalog.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($normalUser)
        ->get(route('admin.product-catalog.index'))
        ->assertForbidden();

    $this->actingAs($normalUser)
        ->get(route('admin.product-catalog.show', 'starter'))
        ->assertForbidden();

    $this->actingAs($normalUser)
        ->get(route('admin.product-catalog.versions.edit', $draft))
        ->assertForbidden();

    $this->actingAs($platformUser)
        ->get(route('admin.product-catalog.index'))
        ->assertOk();

    $this->actingAs($platformUser)
        ->get(route('admin.product-catalog.show', 'starter'))
        ->assertOk();

    $route = Route::getRoutes()->getByName('admin.product-catalog.index');

    expect($route?->gatherMiddleware())
        ->toContain('auth')
        ->toContain('verified')
        ->toContain('platform.admin');
});

test('index summarizes recognized plan codes without exposing unrecognized codes', function () {
    $platformUser = actingPlatformAdmin();

    $current = BillingPlanVersion::factory()->create([
        'plan_code' => 'starter',
        'version' => 1,
        'feature_codes' => [],
        'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
        'published_at' => now()->subDay(),
        'published_by_user_id' => $platformUser->id,
    ]);

    $customer = BillingCustomer::factory()->create();

    BillingSubscription::factory()->create([
        'billing_customer_id' => $customer->id,
        'organization_id' => $customer->organization_id,
        'provider' => $customer->provider,
        'type' => config('billing.subscription_type'),
        'plan_code' => 'starter',
        'plan_version_id' => $current->id,
    ]);

    $this->actingAs($platformUser)
        ->get(route('admin.product-catalog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/product-catalog/index')
            ->has('plans', 2)
            ->where('plans.0.planCode', 'starter')
            ->where('plans.0.hasCurrentVersion', true)
            ->where('plans.0.currentVersionNumber', 1)
            ->where('plans.0.subscriberCount', 1)
            ->where('plans.1.planCode', 'growth')
            ->where('plans.1.hasCurrentVersion', false)
            ->where('plans.1.draftCount', 0));
});

test('an unrecognized plan code returns 404 for show and store', function () {
    $platformUser = actingPlatformAdmin();

    $this->actingAs($platformUser)
        ->get(route('admin.product-catalog.show', 'not_a_real_plan'))
        ->assertNotFound();

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.product-catalog.versions.store', 'not_a_real_plan'), [
            'name' => 'Rogue',
            'tier' => 1,
            'feature_codes' => [],
            'limits' => ['seats' => 1, 'locations' => 1, 'inventory_items' => 1],
        ])
        ->assertNotFound();
});

test('creating a draft version rejects arbitrary feature codes and incomplete limits', function () {
    $platformUser = actingPlatformAdmin();

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.product-catalog.versions.store', 'starter'), [
            'name' => 'Starter',
            'tier' => 1,
            'feature_codes' => ['not_a_real_feature'],
            'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
        ])
        ->assertSessionHasErrors(['feature_codes.0']);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.product-catalog.versions.store', 'starter'), [
            'name' => 'Starter',
            'tier' => 1,
            'feature_codes' => [],
            'limits' => ['seats' => 3],
        ])
        ->assertSessionHasErrors(['limits']);

    expect(BillingPlanVersion::query()->where('plan_code', 'starter')->count())->toBe(0);
});

test('only one draft version may exist per plan at a time', function () {
    $platformUser = actingPlatformAdmin();

    $validPayload = [
        'name' => 'Starter',
        'tier' => 1,
        'feature_codes' => [],
        'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
    ];

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.product-catalog.versions.store', 'starter'), $validPayload)
        ->assertRedirect();

    expect(BillingPlanVersion::query()->where('plan_code', 'starter')->count())->toBe(1);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.product-catalog.versions.store', 'starter'), $validPayload)
        ->assertSessionHasErrors(['name']);

    expect(BillingPlanVersion::query()->where('plan_code', 'starter')->count())->toBe(1);
});

test('published versions cannot be edited or updated through the UI', function () {
    $platformUser = actingPlatformAdmin();

    $published = BillingPlanVersion::factory()->create([
        'plan_code' => 'starter',
        'version' => 1,
        'feature_codes' => [],
        'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
        'published_at' => now()->subDay(),
        'published_by_user_id' => $platformUser->id,
    ]);

    $this->actingAs($platformUser)
        ->get(route('admin.product-catalog.versions.edit', $published))
        ->assertForbidden();

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('admin.product-catalog.versions.update', $published), [
            'name' => 'Renamed',
            'tier' => 1,
            'feature_codes' => [],
            'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
        ])
        ->assertForbidden();

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.product-catalog.versions.publish', $published), [
            'confirm' => '1',
        ])
        ->assertForbidden();

    expect($published->fresh()->name)->toBe('Starter Plan');
});

test('updating a draft replaces its prices and validated entitlements', function () {
    $platformUser = actingPlatformAdmin();

    $draft = BillingPlanVersion::factory()->create([
        'plan_code' => 'starter',
        'version' => 1,
        'feature_codes' => [],
        'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
    ]);

    BillingPlanVersionPrice::factory()->create([
        'billing_plan_version_id' => $draft->id,
    ]);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('admin.product-catalog.versions.update', $draft), [
            'name' => 'Starter Updated',
            'tier' => 1,
            'feature_codes' => ['purchasing'],
            'limits' => ['seats' => 5, 'locations' => 2, 'inventory_items' => 1000],
            'prices' => [
                [
                    'provider' => 'paymongo',
                    'collection_method' => 'manual',
                    'interval' => 'monthly',
                    'currency' => 'PHP',
                    'amount_minor' => 59900,
                ],
            ],
        ])
        ->assertRedirect();

    $draft->refresh();

    expect($draft->name)->toBe('Starter Updated')
        ->and($draft->feature_codes)->toBe(['purchasing'])
        ->and($draft->limits)->toBe([
            'seats' => 5,
            'locations' => 2,
            'inventory_items' => 1000,
        ])
        ->and($draft->prices()->count())->toBe(1)
        ->and($draft->prices()->first()->amount_minor)->toBe(59900);
});

test('publishing requires explicit confirmation and at least one recorded price', function () {
    $platformUser = actingPlatformAdmin();

    $draft = BillingPlanVersion::factory()->create([
        'plan_code' => 'starter',
        'version' => 1,
        'feature_codes' => [],
        'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
    ]);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.product-catalog.versions.publish', $draft))
        ->assertSessionHasErrors(['confirm']);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.product-catalog.versions.publish', $draft), [
            'confirm' => '1',
        ])
        ->assertSessionHasErrors(['confirm']);

    expect($draft->fresh()->isDraft())->toBeTrue();
});

test('publishing a version supersedes the prior current version and pins existing subscribers', function () {
    $platformUser = actingPlatformAdmin();

    $current = BillingPlanVersion::factory()->create([
        'plan_code' => 'starter',
        'version' => 1,
        'feature_codes' => [],
        'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
        'published_at' => now()->subWeek(),
        'published_by_user_id' => $platformUser->id,
    ]);

    $customer = BillingCustomer::factory()->create();

    $subscription = BillingSubscription::factory()->create([
        'billing_customer_id' => $customer->id,
        'organization_id' => $customer->organization_id,
        'provider' => $customer->provider,
        'type' => config('billing.subscription_type'),
        'plan_code' => 'starter',
        'plan_version_id' => $current->id,
    ]);

    $draft = BillingPlanVersion::factory()->create([
        'plan_code' => 'starter',
        'version' => 2,
        'feature_codes' => [],
        'limits' => ['seats' => 5, 'locations' => 2, 'inventory_items' => 1000],
    ]);

    BillingPlanVersionPrice::factory()->create([
        'billing_plan_version_id' => $draft->id,
    ]);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.product-catalog.versions.publish', $draft), [
            'confirm' => '1',
        ])
        ->assertRedirect(route('admin.product-catalog.show', 'starter'));

    $current->refresh();
    $draft->refresh();
    $subscription->refresh();

    expect($current->isSuperseded())->toBeTrue()
        ->and($draft->isCurrent())->toBeTrue()
        ->and($draft->published_by_user_id)->toBe($platformUser->id)
        ->and($subscription->plan_version_id)->toBe($current->id);
});

test('version payloads never expose provider secrets or external identifiers', function () {
    $platformUser = actingPlatformAdmin();

    $draft = BillingPlanVersion::factory()->create([
        'plan_code' => 'starter',
        'version' => 1,
        'feature_codes' => [],
        'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
    ]);

    BillingPlanVersionPrice::factory()->create([
        'billing_plan_version_id' => $draft->id,
    ]);

    $response = $this->actingAs($platformUser)
        ->get(route('admin.product-catalog.show', 'starter'))
        ->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page->component('admin/product-catalog/show');

        $json = json_encode($page->toArray());

        expect($json)
            ->not->toContain('external_plan_id')
            ->not->toContain('externalPlanId')
            ->not->toContain('secret')
            ->not->toContain('price_')
            ->not->toContain('webhook');
    });
});

test('draft creation without recent password confirmation is redirected to confirm password', function () {
    $platformUser = actingPlatformAdmin();

    $this->actingAs($platformUser)
        ->post(route('admin.product-catalog.versions.store', 'starter'), [
            'name' => 'Starter',
            'tier' => 1,
            'feature_codes' => [],
            'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
        ])
        ->assertRedirect(route('password.confirm'));

    expect(BillingPlanVersion::query()->where('plan_code', 'starter')->count())->toBe(0);
});

test('a stale password confirmation blocks a sensitive mutation', function () {
    $platformUser = actingPlatformAdmin();

    $this->actingAs($platformUser)
        ->withSession([
            'auth.password_confirmed_at' => time() - config('auth.password_timeout') - 1,
        ])
        ->post(route('admin.product-catalog.versions.store', 'starter'), [
            'name' => 'Starter',
            'tier' => 1,
            'feature_codes' => [],
            'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
        ])
        ->assertRedirect(route('password.confirm'));

    expect(BillingPlanVersion::query()->where('plan_code', 'starter')->count())->toBe(0);
});

test('publishing a version writes dedicated platform audit evidence', function () {
    $platformUser = actingPlatformAdmin();

    $draft = BillingPlanVersion::factory()->create([
        'plan_code' => 'starter',
        'version' => 1,
        'feature_codes' => [],
        'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
    ]);

    BillingPlanVersionPrice::factory()->create([
        'billing_plan_version_id' => $draft->id,
    ]);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.product-catalog.versions.publish', $draft), [
            'confirm' => '1',
        ])
        ->assertRedirect();

    $event = PlatformAuditEvent::query()->latest('id')->first();

    expect($event)->not->toBeNull()
        ->and($event->action)->toBe(PlatformAuditAction::CatalogVersionPublished)
        ->and($event->subject_type)->toBe('billing_plan_version')
        ->and($event->subject_id)->toBe((string) $draft->id)
        ->and($event->actor_user_id)->toBe($platformUser->id)
        ->and($event->occurred_at)->not->toBeNull();

    expect(fn () => $event->update(['action' => PlatformAuditAction::CatalogVersionDrafted]))
        ->toThrow(LogicException::class);

    expect(fn () => $event->delete())
        ->toThrow(LogicException::class);
});

test('sensitive platform mutations are rate limited independently per admin', function () {
    $platformUser = actingPlatformAdmin();

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()]);

    for ($i = 0; $i < 20; $i++) {
        $this->post(route('admin.product-catalog.versions.store', 'not_a_real_plan'), []);
    }

    $this->post(route('admin.product-catalog.versions.store', 'not_a_real_plan'), [])
        ->assertStatus(429);
});
