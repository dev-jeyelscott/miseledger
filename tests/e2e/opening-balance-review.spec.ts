import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

test('review opening balance stays disabled until the opening date is set', async ({
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

    const reviewButton = page.getByRole('button', {
        name: /review opening balance/i,
    });

    await page.fill('#occurred_at', '');
    await expect(reviewButton).toBeDisabled();

    await page.fill('#occurred_at', '2026-03-15T09:30');
    await expect(reviewButton).toBeEnabled();
});

test('a rejected opening balance closes the confirmation dialog and surfaces the field error', async ({
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
    await expect(
        page.getByRole('heading', { name: /create this initial stock/i }),
    ).toBeVisible();

    await page.evaluate(() => {
        const input = document.querySelector<HTMLInputElement>(
            'input[name="occurred_at"]',
        );

        if (input !== null) {
            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    await page.getByRole('button', { name: /confirm and record/i }).click();

    await expect(
        page.getByRole('heading', { name: /create this initial stock/i }),
    ).toBeHidden();
    await expect(page.getByText(/opening date/i).first()).toBeVisible();
});
