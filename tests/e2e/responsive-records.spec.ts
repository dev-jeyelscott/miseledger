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

test('suppliers mobile record shows the primary contact email and phone alongside the contact name at 375px', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/suppliers');

    const supplierArticle = page
        .getByTestId('mobile-suppliers')
        .locator('article')
        .filter({ hasText: 'E2E Test Supplier' });

    await expect(supplierArticle).toContainText('E2E Supplier Contact');
    await expect(supplierArticle).toContainText(
        'e2e-supplier-contact@example.com',
    );
    await expect(supplierArticle).toContainText('+1 555-010-0100');
});

test('organization members mobile record gives identity its own full-width row above role and AI metadata at 320px', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.setViewportSize({ width: 320, height: 812 });
    await page.goto('/organizations/1/members');

    const firstMember = page
        .getByTestId('mobile-organization-members')
        .locator('article')
        .first();
    const identityRow = firstMember.locator('p.truncate.font-medium').first();

    await expect(identityRow).toBeVisible();

    const identityBox = await identityRow.boundingBox();
    const badgeBox = await firstMember
        .locator('[class*="justify-between"]')
        .boundingBox();

    expect(identityBox).not.toBeNull();
    expect(badgeBox).not.toBeNull();

    if (identityBox && badgeBox) {
        expect(badgeBox.y).toBeGreaterThan(identityBox.y);
        expect(identityBox.width).toBeGreaterThan(150);
    }
});
