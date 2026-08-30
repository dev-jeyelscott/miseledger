import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * Confirms the billing workspace never relies on the native window.confirm
 * dialog and instead exposes an accessible, dismissible confirmation for
 * destructive renewal-cancellation actions. No PayMongo/Stripe network call
 * is ever made by this scenario.
 */
test('billing renewal cancellation uses an accessible dialog, not window.confirm', async ({
    page,
}) => {
    let nativeConfirmCalled = false;
    page.on('dialog', (dialog) => {
        nativeConfirmCalled = true;
        void dialog.dismiss();
    });

    await loginAsOwner(page);
    await page.goto('/organizations/1/billing');

    const cancelButton = page.getByRole('button', { name: /cancel renewal/i });

    if ((await cancelButton.count()) === 0) {
        test.skip(
            true,
            'No cancellable PayMongo subscription is seeded for this organization.',
        );
    }

    await cancelButton.click();

    await expect(
        page.getByRole('dialog', { name: /cancel renewal\?/i }),
    ).toBeVisible();
    expect(nativeConfirmCalled).toBe(false);

    await page.getByRole('button', { name: /keep renewal/i }).click();
    await expect(
        page.getByRole('dialog', { name: /cancel renewal\?/i }),
    ).toBeHidden();
});
