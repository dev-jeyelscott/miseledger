import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * An active organization must never be deactivated without an explicit,
 * accessible confirmation naming the consequence.
 */
test('deactivating an active organization requires explicit confirmation', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/organizations/1/settings');

    await page.getByRole('radio', { name: /^inactive$/i }).check();
    await page.getByRole('button', { name: /save changes/i }).click();

    const dialog = page.getByRole('dialog', {
        name: /deactivate this organization\?/i,
    });
    await expect(dialog).toBeVisible();

    await page.getByRole('button', { name: /keep editing/i }).click();
    await expect(dialog).toBeHidden();

    // Dismissing the dialog only closes it: no update request was submitted,
    // so the unsaved Inactive selection remains on-screen and the page never
    // navigated away from the settings form.
    await expect(
        page.getByRole('radio', { name: /^inactive$/i }),
    ).toBeChecked();
    await expect(page).toHaveURL(/\/organizations\/1\/settings$/);

    // Reloading re-reads the persisted server state, proving the organization
    // was never actually deactivated by the dismissed confirmation.
    await page.reload();
    await expect(page.getByRole('radio', { name: /^active$/i })).toBeChecked();
});
