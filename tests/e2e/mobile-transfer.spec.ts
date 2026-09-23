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

test('create flow through review renders the direction header and reaches a submittable draft', async ({
    page,
}) => {
    await loginAsOwner(page);
    await selectMobileLocation(page);

    await page.goto('/mobile/transfers');
    await expect(
        page.getByRole('heading', {
            name: 'Transfer from which storage location?',
        }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Main Storage' }).click();

    await expect(
        page.getByRole('heading', { name: 'Transfer to which location?' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Secondary Kitchen' }).click();

    await expect(
        page.getByRole('heading', { name: 'Which storage location?' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Secondary Storage' }).click();

    await expect(page).toHaveURL(/\/mobile\/transfers\/scan/);

    // No camera hardware in the test environment: manual search renders
    // inline instead of a dead viewfinder (decision #27, Spec 2).
    await page.getByLabel('Search by name or SKU').fill('E2E-0001');
    await page.getByRole('button', { name: /E2E Test Ingredient/i }).click();

    await expect(
        page.getByRole('heading', { name: 'E2E Test Ingredient' }),
    ).toBeVisible();

    for (const digit of '5'.split('')) {
        await page.getByRole('button', { name: digit, exact: true }).click();
    }

    await page.getByRole('button', { name: 'Next' }).click();

    await expect(page.getByText(/1 item added/i)).toBeVisible();

    await page.getByRole('button', { name: 'Review' }).click();
    await expect(page).toHaveURL(/\/mobile\/transfers\/\d+\/review/);

    await expect(
        page.getByText(/Moving from.*Main Storage.*to.*Secondary Kitchen/s),
    ).toBeVisible();
    await expect(page.getByText('E2E Test Ingredient')).toBeVisible();

    await page.getByRole('button', { name: /^Submit transfer/ }).click();

    await expect(page).toHaveURL(/\/mobile\/transfers\/\d+$/);
    await expect(page.getByText(/Draft — not yet shipped/i)).toBeVisible();
});
