import { expect, test } from '@playwright/test';

import { loginAsOwner } from './support/auth';

test('More menu shows org/location/role and links to switch location, profile, help, release notes, log out', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.goto('/mobile');

    if (page.url().includes('/mobile/location')) {
        await page.getByRole('button', { name: /Main/i }).first().click();
    }

    await expect(page).toHaveURL(/\/mobile$/);

    await page.getByRole('link', { name: 'More' }).click();
    await expect(page).toHaveURL(/\/mobile\/more$/);

    await expect(
        page.getByRole('heading', { name: 'More' }),
    ).toBeVisible();
    const main = page.getByRole('main');
    await expect(main.getByText('E2E Test Kitchen')).toBeVisible();
    await expect(main.getByText('Main Kitchen')).toBeVisible();
    await expect(main.getByText('owner')).toBeVisible();

    // Exactly the five items from decision #39.A, nothing more.
    await expect(page.getByRole('link', { name: 'Switch location' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Profile / account' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Help' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Release notes' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Log out' })).toBeVisible();

    // Switch location round-trips back to More, not wherever the user was before.
    await page.getByRole('link', { name: 'Switch location' }).click();
    await expect(page).toHaveURL(/\/mobile\/location/);
    await page
        .getByRole('main')
        .getByRole('button', { name: /Main/i })
        .first()
        .click();
    await expect(page).toHaveURL(/\/mobile\/more$/);

    // Help opens the existing desktop user guide.
    await page.getByRole('link', { name: 'Help' }).click();
    await expect(page).toHaveURL(/\/user-guide/);

    await page.goBack();
    await expect(page).toHaveURL(/\/mobile\/more$/);

    // Release notes opens the existing desktop page.
    await page.getByRole('link', { name: 'Release notes' }).click();
    await expect(page).toHaveURL(/\/release-notes/);

    await page.goBack();
    await expect(page).toHaveURL(/\/mobile\/more$/);

    // Profile/account opens the existing desktop settings page.
    await page.getByRole('link', { name: 'Profile / account' }).click();
    await expect(page).toHaveURL(/\/settings\/profile/);

    await page.goBack();
    await expect(page).toHaveURL(/\/mobile\/more$/);

    // Log out ends the session the same way desktop logout does: the
    // existing post-logout landing page, no mobile-specific "logged out"
    // screen.
    await page.getByRole('button', { name: 'Log out' }).click();
    await expect(page).toHaveURL(/\/$/);
});
