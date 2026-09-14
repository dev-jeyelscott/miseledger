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

test('cancelling a dirty create supplier form prompts for confirmation', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/suppliers/create');

    await page.fill('#name', 'Metro Food Supply');

    let nativeConfirmSeen = false;
    page.once('dialog', (dialog) => {
        nativeConfirmSeen = true;
        void dialog.dismiss();
    });

    await page.getByRole('button', { name: /^cancel$/i }).click();

    expect(nativeConfirmSeen).toBe(true);
    await expect(page.locator('#name')).toHaveValue('Metro Food Supply');
});

test('cancelling a dirty manual inventory adjustment form prompts for confirmation', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/inventory/adjustments/create');

    if (page.url().includes('/login')) {
        test.skip(
            true,
            'No adjustable inventory item is seeded for this organization.',
        );
    }

    await page.fill('#reason', 'E2E dirty navigation check');

    let nativeConfirmSeen = false;
    page.once('dialog', (dialog) => {
        nativeConfirmSeen = true;
        void dialog.dismiss();
    });

    await page.getByRole('button', { name: /^cancel$/i }).click();

    expect(nativeConfirmSeen).toBe(true);
    await expect(page.locator('#reason')).toHaveValue(
        'E2E dirty navigation check',
    );
});

test('navigating away from a dirty profile form prompts for confirmation', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/settings/profile');

    await page.fill('#name', 'Renamed via E2E');

    let nativeConfirmSeen = false;
    page.once('dialog', (dialog) => {
        nativeConfirmSeen = true;
        void dialog.dismiss();
    });

    await page.getByRole('link', { name: 'Security' }).click();

    expect(nativeConfirmSeen).toBe(true);
    await expect(page.locator('#name')).toHaveValue('Renamed via E2E');
});

test('saving the profile form clears the dirty navigation guard', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/settings/profile');

    const currentName = await page.locator('#name').inputValue();

    await page.fill('#name', 'Renamed via E2E');
    await page.locator('[data-test="update-profile-button"]').click();

    await expect(page.getByText('Saved.')).toBeVisible();

    let nativeConfirmSeen = false;
    page.once('dialog', (dialog) => {
        nativeConfirmSeen = true;
        void dialog.dismiss();
    });

    await page.getByRole('link', { name: 'Security' }).click();
    await expect(page).toHaveURL(/\/settings\/security/);

    expect(nativeConfirmSeen).toBe(false);

    await page.goto('/settings/profile');
    await page.fill('#name', currentName);
    await page.locator('[data-test="update-profile-button"]').click();
    await expect(page.getByText('Saved.')).toBeVisible();
});
