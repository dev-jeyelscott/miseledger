<?php

use App\Models\BillingPlanVersion;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

/**
 * POC-V10.6: the cross-cutting bypass scenarios not already exercised by
 * PlatformAdminBoundaryTest (grant/factor/revocation state matrix) or
 * PlatformProductCatalogTest (recent-auth, rate limit, audit evidence).
 */
test('browser platform mutations reject requests without a valid CSRF token', function () {
    $csrf = new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    };

    $session = $this->app['session.store'];
    $session->start();

    $storeRequest = Request::create('/admin/product-catalog/plans/starter/versions', 'POST');
    $storeRequest->setLaravelSession($session);

    expect(fn () => $csrf->handle($storeRequest, fn () => response('')))
        ->toThrow(TokenMismatchException::class);

    $publishRequest = Request::create('/admin/product-catalog/versions/1/publish', 'POST');
    $publishRequest->setLaravelSession($session);

    expect(fn () => $csrf->handle($publishRequest, fn () => response('')))
        ->toThrow(TokenMismatchException::class);
});

test('a direct API-style request to a platform mutation route without any gate state is redirected, never processed', function () {
    $platformUser = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create(['user_id' => $platformUser->getKey()]);

    $draft = BillingPlanVersion::factory()->create([
        'plan_code' => 'starter',
        'version' => 1,
        'feature_codes' => [],
        'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
    ]);

    $response = $this->actingAs($platformUser)
        ->post(route('admin.product-catalog.versions.publish', $draft), [
            'confirm' => '1',
        ]);

    $response->assertRedirect(route('password.confirm'));

    expect($draft->fresh()->isDraft())->toBeTrue();
});

test('an authenticated non-platform user cannot bypass the platform boundary via a direct mutation request', function () {
    $user = User::factory()->withTwoFactor()->create();

    $draft = BillingPlanVersion::factory()->create([
        'plan_code' => 'starter',
        'version' => 1,
        'feature_codes' => [],
        'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.product-catalog.versions.publish', $draft), [
            'confirm' => '1',
        ])
        ->assertForbidden();

    expect($draft->fresh()->isDraft())->toBeTrue();
});
