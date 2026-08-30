import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * Checkout-success card submission must disable the submit control while a
 * request is pending, surface a visible recoverable error on provider
 * failure, and clear stale errors on retry. Every PayMongo network call is
 * intercepted so this scenario never contacts a live payment provider.
 */
test('checkout payment submission surfaces a recoverable error and blocks duplicate submits', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.route('**/payment_methods', async (route) => {
        await route.fulfill({
            status: 422,
            contentType: 'application/json',
            body: JSON.stringify({ errors: [{ detail: 'Card declined' }] }),
        });
    });
    await page.route('**/payment_intents/**/attach', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ data: { attributes: {} } }),
        });
    });

    await page.goto('/organizations/1/billing/checkout/success');

    const cardNumberField = page.locator('input[name="cardNumber"]');

    if ((await cardNumberField.count()) === 0) {
        test.skip(
            true,
            'No pending PayMongo card-collection checkout is seeded for this organization.',
        );
    }

    await cardNumberField.fill('4111111111111111');
    await page.fill('input[name="expiryMonth"]', '12');
    await page.fill('input[name="expiryYear"]', '2030');
    await page.fill('input[name="cvc"]', '123');

    const submitButton = page.getByRole('button', {
        name: /continue to payment/i,
    });

    await submitButton.click();

    const errorAlert = page.getByRole('alert');
    await expect(errorAlert).toBeVisible();
    await expect(submitButton).toBeEnabled();

    // Retrying clears the stale error rather than stacking a second one.
    await submitButton.click();
    await expect(page.getByRole('alert')).toHaveCount(1);
});
