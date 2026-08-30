import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * Checkout-success card submission must disable the submit control while a
 * request is pending, block duplicate submissions, surface a visible
 * recoverable error on provider failure, and clear stale errors on retry.
 * Every PayMongo network call is intercepted so this scenario never
 * contacts a live payment provider; the session-scoped payment payload is
 * seeded deterministically via a testing-only fixture route instead of an
 * actual Checkout session.
 */
test.beforeEach(async ({ page }) => {
    await loginAsOwner(page);
    await page.goto('/organizations/1/billing/checkout/e2e-payment-fixture');
});

test('a failed payment attempt surfaces a visible recoverable error and re-enables the submit control', async ({
    page,
}) => {
    let paymentMethodCalls = 0;

    await page.route('**/payment_methods', async (route) => {
        paymentMethodCalls += 1;

        await route.fulfill({
            status: 422,
            contentType: 'application/json',
            body: JSON.stringify({ errors: [{ detail: 'Card declined' }] }),
        });
    });

    const cardNumberField = page.locator('input[name="cardNumber"]');
    await expect(cardNumberField).toBeVisible();

    await cardNumberField.fill('4111111111111111');
    await page.fill('input[name="expiryMonth"]', '12');
    await page.fill('input[name="expiryYear"]', '2030');
    await page.fill('input[name="cvc"]', '123');

    const submitButton = page.getByRole('button', {
        name: /continue to payment/i,
    });

    await submitButton.click();

    await expect(page.getByRole('alert')).toBeVisible();
    await expect(submitButton).toBeEnabled();
    expect(paymentMethodCalls).toBe(1);
});

test('the submit control is disabled while a payment request is pending and blocks a duplicate submission', async ({
    page,
}) => {
    let paymentMethodCalls = 0;

    await page.route('**/payment_methods', async (route) => {
        paymentMethodCalls += 1;

        // Hold the response open long enough to observe the disabled state
        // and attempt a duplicate click before it resolves.
        await new Promise((resolve) => setTimeout(resolve, 500));

        await route.fulfill({
            status: 422,
            contentType: 'application/json',
            body: JSON.stringify({ errors: [{ detail: 'Card declined' }] }),
        });
    });

    await page.fill('input[name="cardNumber"]', '4111111111111111');
    await page.fill('input[name="expiryMonth"]', '12');
    await page.fill('input[name="expiryYear"]', '2030');
    await page.fill('input[name="cvc"]', '123');

    const submitButton = page.getByRole('button', {
        name: /continue to payment/i,
    });

    await submitButton.click();
    await expect(submitButton).toBeDisabled();

    // A duplicate click while disabled must never fire a second request.
    await submitButton.click({ force: true });

    await expect(submitButton).toBeEnabled();
    expect(paymentMethodCalls).toBe(1);
});

test('retrying after a failed payment clears the stale error instead of stacking a second one', async ({
    page,
}) => {
    await page.route('**/payment_methods', async (route) => {
        await route.fulfill({
            status: 422,
            contentType: 'application/json',
            body: JSON.stringify({ errors: [{ detail: 'Card declined' }] }),
        });
    });

    await page.fill('input[name="cardNumber"]', '4111111111111111');
    await page.fill('input[name="expiryMonth"]', '12');
    await page.fill('input[name="expiryYear"]', '2030');
    await page.fill('input[name="cvc"]', '123');

    const submitButton = page.getByRole('button', {
        name: /continue to payment/i,
    });

    await submitButton.click();
    await expect(page.getByRole('alert')).toHaveCount(1);

    await submitButton.click();
    await expect(page.getByRole('alert')).toHaveCount(1);
});
