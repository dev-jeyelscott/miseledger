<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

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

test('browser billing mutations retain CSRF protection while Stripe webhooks are the sole exclusion', function () {
    $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

    expect($bootstrap)
        ->toContain("->validateCsrfTokens(except: [\n            'stripe/*',\n        ])")
        ->and(file_get_contents(base_path('routes/web.php')))
        ->toContain("Route::middleware(['auth', 'verified'])->group(function (): void {")
        ->toContain("Route::post(\n        'organizations/{organization}/billing/checkout',")
        ->toContain("Route::post(\n        'organizations/{organization}/billing/portal',");
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
