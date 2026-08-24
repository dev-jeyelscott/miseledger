<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

test('a member without billing access is denied every organization billing action', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'role' => OrganizationRole::Manager,
    ]);

    $this->actingAs($user)->get(route('organizations.billing.show', $organization))
        ->assertForbidden();
    $this->actingAs($user)->post(route('organizations.billing.checkout', $organization), [
        'plan' => 'starter',
        'interval' => 'monthly',
    ])->assertForbidden();
    $this->actingAs($user)->post(route('organizations.billing.portal', $organization))
        ->assertForbidden();
    $this->actingAs($user)->get(route('organizations.billing.checkout.success', $organization))
        ->assertForbidden();
    $this->actingAs($user)->get(route('organizations.billing.checkout.cancel', $organization))
        ->assertForbidden();
});

test('an owner cannot access billing actions for another organization', function () {
    $user = User::factory()->create();
    $ownedOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()->for($ownedOrganization)->for($user)->create([
        'role' => OrganizationRole::Owner,
    ]);

    $this->actingAs($user)->get(route('organizations.billing.show', $otherOrganization))
        ->assertForbidden();
    $this->actingAs($user)->post(route('organizations.billing.checkout', $otherOrganization), [
        'plan' => 'starter',
        'interval' => 'monthly',
    ])->assertForbidden();
    $this->actingAs($user)->post(route('organizations.billing.portal', $otherOrganization))
        ->assertForbidden();
    $this->actingAs($user)->get(route('organizations.billing.checkout.success', $otherOrganization))
        ->assertForbidden();
    $this->actingAs($user)->get(route('organizations.billing.checkout.cancel', $otherOrganization))
        ->assertForbidden();
});

test('browser billing mutations reject requests without a CSRF token', function () {
    $csrf = new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    };

    $session = $this->app['session.store'];
    $session->start();

    $billingRequest = Request::create('/organizations/1/billing/checkout', 'POST');
    $billingRequest->setLaravelSession($session);

    expect(fn () => $csrf->handle($billingRequest, fn () => response('')))
        ->toThrow(TokenMismatchException::class);

    $portalRequest = Request::create('/organizations/1/billing/portal', 'POST');
    $portalRequest->setLaravelSession($session);

    expect(fn () => $csrf->handle($portalRequest, fn () => response('')))
        ->toThrow(TokenMismatchException::class);

    $webhookRequest = Request::create('/stripe/webhook', 'POST');
    $webhookRequest->setLaravelSession($session);

    expect($csrf->handle($webhookRequest, fn () => response('')))
        ->toBeInstanceOf(Response::class);
});

test('Stripe webhooks are the sole billing CSRF exclusion', function () {
    expect(file_get_contents(base_path('bootstrap/app.php')))
        ->toContain("->validateCsrfTokens(except: [\n            'stripe/*',\n        ])");
});

test('billing code remains isolated from stock balance projections and stock movement recording', function () {
    $billingFiles = glob(app_path('Actions/Billing/*.php'));
    $billingFiles[] = app_path('Http/Controllers/Billing/OrganizationBillingController.php');
    $billingFiles[] = app_path('Http/Controllers/Billing/OrganizationBillingPortalController.php');
    $billingFiles[] = app_path('Http/Controllers/Billing/OrganizationCheckoutController.php');
    $billingFiles[] = app_path('Http/Controllers/Billing/OrganizationCheckoutStatusController.php');
    $billingFiles[] = app_path('Http/Controllers/Billing/StripeWebhookController.php');

    $billingSource = implode("\n", array_map(file_get_contents(...), $billingFiles));
    $recordStockMovementSource = file_get_contents(
        app_path('Actions/Inventory/RecordStockMovement.php'),
    );

    expect($billingSource)
        ->not->toContain('StockBalance')
        ->not->toContain('RecordStockMovement')
        ->and($recordStockMovementSource)
        ->not->toContain('subscription')
        ->not->toContain('billing');
});
