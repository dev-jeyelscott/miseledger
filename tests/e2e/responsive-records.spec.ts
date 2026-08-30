import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * Every server-authoritative list surface must expose a usable mobile record
 * view below the md breakpoint and its full table at desktop width, using
 * the same server-rendered data in both compositions.
 */
const pages: Array<{ url: string; mobileTestId: string }> = [
    { url: '/inventory/stock-on-hand', mobileTestId: 'mobile-stock-on-hand' },
    { url: '/inventory/low-stock', mobileTestId: 'mobile-low-stock' },
    { url: '/inventory/valuation', mobileTestId: 'mobile-valuation' },
    {
        url: '/inventory/purchasing-history',
        mobileTestId: 'mobile-purchasing-history',
    },
    { url: '/purchase-orders', mobileTestId: 'mobile-purchase-orders' },
    { url: '/suppliers', mobileTestId: 'mobile-suppliers' },
    {
        url: '/organizations/1/members',
        mobileTestId: 'mobile-organization-members',
    },
    {
        url: '/organizations/1/locations/1/storage-locations',
        mobileTestId: 'mobile-storage-locations',
    },
];

for (const { url, mobileTestId } of pages) {
    test(`${url} composes a mobile record view below md and a table at desktop width`, async ({
        page,
    }) => {
        await loginAsOwner(page);

        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(url);

        const mobileRegion = page.getByTestId(mobileTestId);
        const table = page.locator('table').first();

        await expect(mobileRegion).toBeVisible();
        await expect(table).toBeHidden();

        await page.setViewportSize({ width: 1280, height: 900 });
        await expect(table).toBeVisible();
        await expect(mobileRegion).toBeHidden();
    });
}
