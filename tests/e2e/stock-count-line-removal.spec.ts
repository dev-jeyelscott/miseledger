import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * Guards against accidental data loss when removing a populated stock-count
 * line: a populated line must prompt for confirmation before it disappears,
 * while an untouched extra line is removed immediately.
 */
test('removing a populated stock-count line requires confirmation, an untouched line is removed immediately', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/stock-counts/create');

    await page.selectOption('#stock-count-line-0-item', { index: 1 });
    await page.fill('#stock-count-line-0-quantity', '5');

    // Untouched extra line: removed immediately, no dialog.
    await page.getByRole('button', { name: 'Add item' }).click();
    await page
        .getByRole('button', { name: 'Remove line 2', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: /^Remove .+\?$/ }),
    ).not.toBeVisible();
    await expect(page.locator('#stock-count-line-0-quantity')).toHaveValue(
        '5',
    );
    await expect(
        page.getByRole('button', { name: /^Remove line \d+$/ }),
    ).toHaveCount(1);

    // Populated line: dialog opens, "Keep line" preserves the data.
    await page.getByRole('button', { name: 'Add item' }).click();
    await page
        .getByRole('button', { name: 'Remove line 1', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: /^Remove .+\?$/ }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Keep line', exact: true }).click();
    await expect(
        page.getByRole('heading', { name: /^Remove .+\?$/ }),
    ).not.toBeVisible();
    await expect(page.locator('#stock-count-line-0-quantity')).toHaveValue(
        '5',
    );

    // Populated line: confirming removal discards it.
    await page
        .getByRole('button', { name: 'Remove line 1', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Remove line', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: /^Remove .+\?$/ }),
    ).not.toBeVisible();
    await expect(page.locator('#stock-count-line-0-quantity')).toHaveValue(
        '0',
    );
});
