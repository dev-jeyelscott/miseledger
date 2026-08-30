import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * The seeded organization is fixed to Asia/Manila. Every scenario below runs
 * the browser in an unrelated timezone to prove the confirmation summaries
 * echo the exact wall-clock value the user typed, never a browser-local
 * reinterpretation of it.
 */
test.use({ timezoneId: 'America/Los_Angeles' });

test('manual adjustment confirmation echoes the entered organization-local time', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/inventory/adjustments/create');

    await page.selectOption('#location_id', { index: 1 });
    await page.selectOption('#storage_location_id', { index: 1 });
    await page.selectOption('#inventory_item_id', { index: 1 });
    await page.fill('#quantity', '1');
    await page.fill('#occurred_at', '2026-03-15T09:30');
    await page.fill('#reason', 'E2E timezone regression check');

    await page.getByRole('button', { name: /review adjustment/i }).click();

    await expect(page.getByText('Mar 15, 2026, 9:30 AM')).toBeVisible();
});

test('opening balance confirmation echoes the entered organization-local time', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/inventory/opening-balances/create');

    await page.selectOption('#location_id', { index: 1 });
    await page.selectOption('#storage_location_id', { index: 1 });
    await page.selectOption('#inventory_item_id', { index: 1 });
    await page.fill('#quantity', '10');
    await page.selectOption('#unit_id', { index: 1 });
    await page.fill('#base_unit_cost', '2.50');
    await page.fill('#occurred_at', '2026-03-15T09:30');

    await page.getByRole('button', { name: /review opening balance/i }).click();

    await expect(page.getByText('Mar 15, 2026, 9:30 AM')).toBeVisible();
});

test('opening balance confirmation never shows a client-computed estimated total', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/inventory/opening-balances/create');

    await page.selectOption('#location_id', { index: 1 });
    await page.selectOption('#storage_location_id', { index: 1 });
    await page.selectOption('#inventory_item_id', { index: 1 });
    await page.fill('#quantity', '10');
    await page.selectOption('#unit_id', { index: 1 });
    await page.fill('#base_unit_cost', '2.50');
    await page.fill('#occurred_at', '2026-03-15T09:30');

    await page.getByRole('button', { name: /review opening balance/i }).click();

    await expect(page.getByText(/estimated total/i)).toHaveCount(0);
});

test('new purchase order default order date reflects the organization timezone, not the browser', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/purchase-orders/create');

    const orderDate = page.locator('#order_date');
    const orgLocalDate = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
    }).format(new Date());

    await expect(orderDate).toHaveValue(orgLocalDate);
});
