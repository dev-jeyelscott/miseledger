import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * Guards against accidental data loss when removing a populated goods
 * receipt line: a populated line must prompt for confirmation before it
 * disappears, while an untouched extra line is removed immediately.
 */
test('removing a populated goods receipt line requires confirmation, an untouched line is removed immediately', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/purchase-orders/1/edit');

    if (page.url().includes('/login')) {
        test.skip(
            true,
            'No approved purchase order is seeded for this organization.',
        );
    }

    await page.getByRole('link', { name: 'Receive stock' }).click();
    await expect(page).toHaveURL(/\/purchase-orders\/1\/receipts\/create/);

    await page.selectOption('#line-0-po-line', { index: 1 });
    await page.fill('#line-0-received-qty', '5');

    // Untouched extra line: removed immediately, no dialog.
    await page.getByRole('button', { name: 'Add line' }).click();
    await page.getByRole('button', { name: 'Remove' }).nth(1).click();
    await expect(
        page.getByRole('heading', { name: /^Remove .+\?$/ }),
    ).not.toBeVisible();
    await expect(page.locator('#line-0-received-qty')).toHaveValue('5');
    await expect(page.getByRole('button', { name: 'Remove' })).toHaveCount(1);

    // Populated line: dialog opens, "Keep line" preserves the data.
    await page.getByRole('button', { name: 'Add line' }).click();
    await page.getByRole('button', { name: 'Remove' }).nth(0).click();
    await expect(
        page.getByRole('heading', { name: /^Remove .+\?$/ }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Keep line' }).click();
    await expect(
        page.getByRole('heading', { name: /^Remove .+\?$/ }),
    ).not.toBeVisible();
    await expect(page.locator('#line-0-received-qty')).toHaveValue('5');

    // Populated line: confirming removal discards it.
    await page.getByRole('button', { name: 'Remove' }).nth(0).click();
    await page.getByRole('button', { name: 'Remove line' }).click();
    await expect(
        page.getByRole('heading', { name: /^Remove .+\?$/ }),
    ).not.toBeVisible();
    await expect(page.locator('#line-0-received-qty')).toHaveValue('');
});
