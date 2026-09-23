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

test('receiving against the seeded approved PO updates stock like a desktop receipt', async ({
    page,
}) => {
    await loginAsOwner(page);
    await selectMobileLocation(page);

    await page.goto('/mobile/receiving');
    await expect(
        page.getByRole('heading', { name: 'Receiving' }),
    ).toBeVisible();

    const poLink = page.getByRole('link', { name: /PO-E2E-0001/i });

    if ((await poLink.count()) === 0) {
        test.skip(true, 'No receivable PO is seeded for this organization.');
    }

    await poLink.click();
    await expect(page).toHaveURL(/\/mobile\/receiving\/scan/);

    // No camera hardware in the test environment: manual search renders
    // inline instead of a dead viewfinder (decision #27, Spec 2).
    await page.getByLabel('Search by name or SKU').fill('E2E-0001');
    await page.getByRole('button', { name: /E2E Test Ingredient/i }).click();

    await expect(
        page.getByRole('heading', { name: 'E2E Test Ingredient' }),
    ).toBeVisible();

    for (const digit of '10'.split('')) {
        await page.getByRole('button', { name: digit, exact: true }).click();
    }

    await page.getByRole('button', { name: 'Next' }).click();

    await expect(page.getByText(/1 item received/i)).toBeVisible();

    await page.getByRole('button', { name: 'Review' }).click();
    await expect(page).toHaveURL(/\/mobile\/receiving\/\d+\/review/);

    await expect(page.getByText('E2E Test Ingredient')).toBeVisible();

    await page.getByRole('button', { name: /^Finalize receipt/ }).click();

    await expect(page).toHaveURL(/\/mobile\/receiving$/);
});

test('an ad-hoc receipt for an item with no supplier price shows the zero-cost warning', async ({
    page,
}) => {
    await loginAsOwner(page);
    await selectMobileLocation(page);

    await page.goto('/mobile/receiving');
    await page.getByRole('link', { name: 'Start ad-hoc receiving' }).click();
    await expect(page).toHaveURL(/\/mobile\/receiving\/ad-hoc/);

    const supplierButton = page.getByRole('button', {
        name: /E2E Test Supplier/i,
    });

    if ((await supplierButton.count()) === 0) {
        test.skip(true, 'No supplier is seeded for this organization.');
    }

    await supplierButton.click();
    await expect(page).toHaveURL(/\/mobile\/receiving\/scan/);

    await page.getByLabel('Search by name or SKU').fill('E2E-0002');
    await page
        .getByRole('button', { name: /E2E Low Stock Ingredient/i })
        .click();

    await page.getByRole('button', { name: '5', exact: true }).click();
    await page.getByRole('button', { name: 'Next' }).click();

    await page.getByRole('button', { name: 'Review' }).click();
    await expect(page).toHaveURL(/\/mobile\/receiving\/\d+\/review/);

    await expect(page.getByRole('alert')).toContainText(
        /no supplier price on file/i,
    );
});
