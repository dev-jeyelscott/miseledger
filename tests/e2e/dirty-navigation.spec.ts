import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * Confirms internal Inertia navigation away from a dirty organization
 * location form is guarded, not only the browser tab-close/refresh path.
 */
test('leaving a dirty organization location edit form prompts for confirmation', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/organizations/1/locations/1/edit');

    if (page.url().includes('/login')) {
        test.skip(
            true,
            'No editable location is seeded for this organization.',
        );
    }

    await page.fill('#name', 'Renamed via E2E');

    let nativeConfirmSeen = false;
    page.once('dialog', (dialog) => {
        nativeConfirmSeen = true;
        void dialog.dismiss();
    });

    await page.getByRole('button', { name: /^cancel$/i }).click();

    expect(nativeConfirmSeen).toBe(true);
    await expect(page.locator('#name')).toHaveValue('Renamed via E2E');
});
