import { expect, test } from '@playwright/test';

import { loginAsLimitedUser } from './support/auth';

test('scan → qty → reason → save → back in scanner', async ({ page }) => {
    await loginAsLimitedUser(page);

    await page.goto('/mobile');

    if (page.url().includes('/mobile/location')) {
        await page.getByRole('button', { name: /Main/i }).first().click();
    }

    await page.goto('/mobile/scan');

    // No camera hardware in the test environment: manual search renders
    // inline instead of a dead viewfinder (decision #27, Spec 2).
    await page.getByLabel('Search by name or SKU').fill('E2E-0001');
    await page.getByRole('button', { name: /E2E Test Ingredient/i }).click();

    await expect(
        page.getByRole('heading', { name: 'E2E Test Ingredient' }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Waste' }).click();
    await expect(page).toHaveURL(/\/mobile\/waste\?/);

    await expect(
        page.getByRole('heading', { name: 'E2E Test Ingredient' }),
    ).toBeVisible();

    for (const digit of '2'.split('')) {
        await page.getByRole('button', { name: digit, exact: true }).click();
    }

    await page.getByRole('button', { name: 'Next' }).click();

    await page.getByRole('radio', { name: /Spoilage/i }).click();

    await page.getByRole('button', { name: 'Save waste' }).click();

    await expect(page).toHaveURL(/\/mobile\/scan$/);
});
