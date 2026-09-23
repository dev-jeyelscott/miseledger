import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

test('a fresh mobile session lands on the location picker before Home, then remembers the pick', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.goto('/mobile');
    await expect(page).toHaveURL(/\/mobile\/location/);
    await expect(page.getByRole('heading', { name: 'Select a location' })).toBeVisible();

    await page.getByRole('button', { name: /Main/i }).first().click();
    await expect(page).toHaveURL(/\/mobile$/);

    await expect(page.getByRole('link', { name: 'Home' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Scan' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Tasks' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Stock' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'More' })).toBeVisible();

    await page.reload();
    await expect(page).toHaveURL(/\/mobile$/);
});
