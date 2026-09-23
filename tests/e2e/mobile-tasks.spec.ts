import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';

import { loginAsLimitedUser, loginAsOwner } from './support/auth';

/** Select the seeded Main Kitchen mobile location once, if the picker is shown. */
async function selectMobileLocation(page: Page) {
    await page.goto('/mobile');

    if (page.url().includes('/mobile/location')) {
        await page.getByRole('button', { name: /Main/i }).first().click();
    }
}

test('Home shows the top tasks under "Today\'s work", ordered by urgency band', async ({
    page,
}) => {
    await loginAsOwner(page);
    await selectMobileLocation(page);

    await expect(page.getByRole('heading', { name: "Today's work" })).toBeVisible();

    const taskLinks = page.locator('main a', { hasText: /Receive|Count|Ship|Restock/ });
    await expect(taskLinks).toHaveCount(5);

    // A transfer shipped 2h ago outranks same-second-created ready work
    // (Spec 7 Behavior step 1: secondary sort is oldest first within a band).
    await expect(taskLinks.first()).toContainText('Receive transfer ST-E2E-0002');

    // Restock (band: attention) always sorts after every ready/in-progress/
    // overdue task.
    await expect(taskLinks.last()).toContainText('Restock');
    await expect(taskLinks.last()).toContainText('E2E Low Stock Ingredient');

    // The three remaining ready-band tasks are present as a set, regardless
    // of same-second tie order between them.
    await expect(taskLinks).toContainText([
        /Receive transfer ST-E2E-0002/,
        /Receive PO-E2E-0001|Receive PO PO-E2E-0001/,
        /Count SC-E2E-0001/,
        /Ship transfer ST-E2E-0001/,
        /Restock/,
    ]);

    await page.getByRole('link', { name: 'View all tasks' }).click();
    await expect(page).toHaveURL(/\/mobile\/tasks$/);
});

test('Tasks tab shows the full list grouped by type with a count badge per group', async ({
    page,
}) => {
    await loginAsOwner(page);
    await selectMobileLocation(page);

    await page.getByRole('link', { name: 'Tasks' }).click();
    await expect(page).toHaveURL(/\/mobile\/tasks$/);
    await expect(page.getByRole('heading', { name: 'Tasks', exact: true })).toBeVisible();

    for (const label of ['Receive', 'Count', 'Ship', 'Receive transfer', 'Restock']) {
        await expect(
            page.getByRole('heading', { name: label, exact: true }),
        ).toBeVisible();
    }

    await expect(page.getByText('PO-E2E-0001')).toBeVisible();
    await expect(page.getByText('SC-E2E-0001')).toBeVisible();
    await expect(page.getByText('ST-E2E-0001')).toBeVisible();
    await expect(page.getByText('ST-E2E-0002')).toBeVisible();
    await expect(page.getByText('E2E Low Stock Ingredient')).toBeVisible();
});

test('a Kitchen Staff account sees only the Restock task on Home and Tasks', async ({
    page,
}) => {
    await loginAsLimitedUser(page);
    await selectMobileLocation(page);

    const taskLinks = page.locator('main a', { hasText: /Receive|Count|Ship|Restock/ });
    await expect(taskLinks).toHaveCount(1);
    await expect(taskLinks.first()).toContainText('Restock');

    await page.getByRole('link', { name: 'Tasks' }).click();
    await expect(page).toHaveURL(/\/mobile\/tasks$/);

    await expect(page.getByRole('heading', { name: 'Restock', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Receive', exact: true })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Count', exact: true })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Ship', exact: true })).toHaveCount(0);
    await expect(
        page.getByRole('heading', { name: 'Receive transfer', exact: true }),
    ).toHaveCount(0);
});
