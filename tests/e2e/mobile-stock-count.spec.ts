import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';

import { loginAsOwner } from './support/auth';

/** Select the seeded Main Kitchen mobile location once, if the picker is shown. */
async function selectMobileLocation(page: Page) {
    await page.goto('/mobile');

    if (page.url().includes('/mobile/location')) {
        await page.getByRole('button', { name: /Main/i }).first().click();
    }
}

test('a stock count from location through review, including a re-scan correction, submits cleanly', async ({
    page,
}) => {
    await loginAsOwner(page);
    await selectMobileLocation(page);

    await page.goto('/mobile/stock-counts');
    await expect(
        page.getByRole('heading', { name: 'Stock counts' }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Start a stock count' }).click();
    await expect(page).toHaveURL(/\/mobile\/stock-counts\/create/);

    await page.getByRole('button', { name: /Main Storage/i }).click();
    await expect(page).toHaveURL(/\/mobile\/stock-counts\/scan/);

    // No camera hardware in the test environment: manual search renders
    // inline instead of a dead viewfinder (decision #27, Spec 2).
    await page.getByLabel('Search by name or SKU').fill('E2E-0001');
    await page.getByRole('button', { name: /E2E Test Ingredient/i }).click();

    await expect(
        page.getByRole('heading', { name: 'E2E Test Ingredient' }),
    ).toBeVisible();

    for (const digit of '20'.split('')) {
        await page.getByRole('button', { name: digit, exact: true }).click();
    }

    await page.getByRole('button', { name: 'Next' }).click();

    await expect(page.getByText(/1 item counted/i)).toBeVisible();

    // Re-scan the same item with a corrected quantity (decision #35.A):
    // this must update the existing line, not add a second one.
    await page.getByLabel('Search by name or SKU').fill('E2E-0001');
    await page.getByRole('button', { name: /E2E Test Ingredient/i }).click();

    for (const digit of '18'.split('')) {
        await page.getByRole('button', { name: digit, exact: true }).click();
    }

    await page.getByRole('button', { name: 'Next' }).click();

    await expect(page.getByText(/1 item counted/i)).toBeVisible();
    await expect(page.getByText(/updated to 18/i)).toBeVisible();

    await page.getByRole('button', { name: 'Review' }).click();
    await expect(page).toHaveURL(/\/mobile\/stock-counts\/\d+\/review/);

    await expect(page.getByText('E2E Test Ingredient')).toBeVisible();
    await expect(page.getByText(/18\.000000 kg counted/i)).toBeVisible();

    await page.getByRole('button', { name: /^Submit count/ }).click();

    await expect(page).toHaveURL(/\/mobile\/scan$/);
});
