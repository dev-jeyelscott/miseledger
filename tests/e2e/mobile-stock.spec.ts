import { expect, test } from '@playwright/test';

import { loginAsLimitedUser } from './support/auth';

test('search by SKU surfaces the item and the detail screen shows quantity, storage breakdown, and status, with actions behind the hub', async ({
    page,
}) => {
    await loginAsLimitedUser(page);

    await page.goto('/mobile');
    await expect(page).toHaveURL(/\/mobile\/location/);
    await page.getByRole('button', { name: /Main/i }).first().click();
    await expect(page).toHaveURL(/\/mobile$/);

    await page.getByRole('link', { name: 'Stock' }).click();
    await expect(page).toHaveURL(/\/mobile\/stock$/);

    await page
        .getByLabel('Search by name, SKU, or barcode')
        .fill('E2E-0001');

    const resultButton = page.getByRole('button', {
        name: /E2E Test Ingredient/i,
    });
    await expect(resultButton).toBeVisible();
    await resultButton.click();

    await expect(page).toHaveURL(/\/mobile\/stock\/items\/\d+$/);
    await expect(
        page.getByRole('heading', { name: 'E2E Test Ingredient' }),
    ).toBeVisible();

    // Matches the desktop Stock on Hand figure for this seeded item/location.
    await expect(page.getByText(/^25(\.0+)?$/)).toBeVisible();
    await expect(page.getByText('kg on hand')).toBeVisible();
    await expect(page.getByText('Main Storage')).toBeVisible();
    await expect(page.getByText('Active')).toBeVisible();
    await expect(page.getByText('Low stock')).toHaveCount(0);

    // "View actions" opens the same permission-filtered hub Scan would
    // (Seeded Kitchen Staff only has WasteRecord + InventoryView).
    await page.getByRole('button', { name: 'View actions' }).click();
    await expect(page.getByRole('link', { name: 'Waste' })).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Stock Details' }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Receive' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Count' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Transfer' })).toHaveCount(0);
});

test('a zero-balance item shows the low-stock badge', async ({ page }) => {
    await loginAsLimitedUser(page);

    await page.goto('/mobile');
    await expect(page).toHaveURL(/\/mobile\/location/);
    await page.getByRole('button', { name: /Main/i }).first().click();
    await expect(page).toHaveURL(/\/mobile$/);

    await page.goto('/mobile/stock');
    await page
        .getByLabel('Search by name, SKU, or barcode')
        .fill('E2E-0002');

    const resultButton = page.getByRole('button', {
        name: /E2E Low Stock Ingredient/i,
    });
    await expect(resultButton).toBeVisible();
    await resultButton.click();

    await expect(page).toHaveURL(/\/mobile\/stock\/items\/\d+$/);
    await expect(page.getByText('Low stock')).toBeVisible();
});
