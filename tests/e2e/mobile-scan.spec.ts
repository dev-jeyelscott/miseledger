import { expect, test } from '@playwright/test';

import { loginAsLimitedUser } from './support/auth';

test('camera denial falls back to manual search, and a matched item opens a permission-filtered action hub', async ({
    page,
}) => {
    await loginAsLimitedUser(page);

    await page.goto('/mobile');
    await expect(page).toHaveURL(/\/mobile\/location/);
    await page.getByRole('button', { name: /Main/i }).first().click();
    await expect(page).toHaveURL(/\/mobile$/);

    await page.getByRole('link', { name: 'Scan' }).click();
    await expect(page).toHaveURL(/\/mobile\/scan$/);

    // No camera hardware in the test environment: getUserMedia fails and
    // the manual-entry fallback renders inline instead of a dead
    // viewfinder (decision #27).
    await expect(page.getByLabel('Search by name or SKU')).toBeVisible();

    await page.getByLabel('Search by name or SKU').fill('E2E-0001');

    const resultButton = page.getByRole('button', {
        name: /E2E Test Ingredient/i,
    });
    await expect(resultButton).toBeVisible();

    await resultButton.click();

    await expect(
        page.getByRole('heading', { name: 'E2E Test Ingredient' }),
    ).toBeVisible();

    // Seeded Kitchen Staff only has WasteRecord + InventoryView, so only
    // Waste and Stock Details may appear (acceptance criterion).
    await expect(page.getByRole('link', { name: 'Waste' })).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Stock Details' }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Receive' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Count' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Transfer' })).toHaveCount(0);
});
