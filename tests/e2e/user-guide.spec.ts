import { expect, test } from '@playwright/test';
import {
    loginAsLimitedUser,
    loginAsOwner,
    loginWithoutOrganization,
} from './support/auth';

test('the guide supports search, keyboard navigation, and direct tutorial anchors across desktop and mobile', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsOwner(page);

    await page.goto('/user-guide');
    await expect(page.locator('html')).toHaveClass(/dark/);
    await expect(
        page.getByRole('heading', { level: 1, name: 'User Guide' }),
    ).toBeVisible();

    const search = page.getByRole('textbox', { name: 'Search the guide' });
    await search.fill('receive a purchase');
    await expect(page.getByText('1 topic found')).toBeVisible();

    await page.getByRole('button', { name: 'Clear' }).click();
    await expect(page.getByRole('button', { name: 'Clear' })).toHaveCount(0);

    await search.fill('not-a-guide-topic');
    await expect(
        page.getByText('No guide topics match your search.'),
    ).toBeVisible();

    await search.fill('receive a purchase');

    const result = page.getByRole('link', { name: /Receive a purchase order/ });
    await result.focus();
    await expect(result).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(
        /\/user-guide\/purchasing#receive-a-purchase-order$/,
    );

    const tutorial = page.locator('#receive-a-purchase-order');
    await expect(tutorial).toBeVisible();
    await expect(tutorial).toHaveAttribute('aria-expanded', 'true');
    await expect(
        page.getByText(
            'Finalize the receipt after validating quantities and costs.',
        ),
    ).toBeVisible();

    await page.goto('/user-guide/purchasing#receive-a-purchase-order');
    await expect(tutorial).toHaveAttribute('aria-expanded', 'true');

    await page.setViewportSize({ width: 375, height: 812 });
    await expect(
        page.getByRole('heading', { level: 1, name: 'Purchasing' }),
    ).toBeVisible();
    await expect(tutorial).toBeVisible();
    expect(
        await page.locator('body').evaluate((element) => element.scrollWidth),
    ).toBeLessThanOrEqual(375);

    // A 640px viewport exercises the usable-width equivalent of 200% zoom
    // from the standard 1280px desktop layout.
    await page.setViewportSize({ width: 640, height: 900 });
    await expect(tutorial).toBeVisible();
    expect(
        await page.locator('body').evaluate((element) => element.scrollWidth),
    ).toBeLessThanOrEqual(640);

    await page.emulateMedia({ colorScheme: 'light' });
    await page.reload();
    await expect(page.locator('html')).not.toHaveClass(/dark/);
});

test('guide Open links follow broad access', async ({ page }) => {
    await loginAsOwner(page);
    await page.goto('/user-guide/organization');
    await expect(
        page.getByRole('link', { name: 'Open locations' }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Open members' }),
    ).toBeVisible();

    await page.goto('/user-guide/billing');
    await expect(
        page.getByRole('link', { name: 'Open billing' }),
    ).toBeVisible();
});

test('guide search reaches page-level topics through the local navigation', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/user-guide');

    await page
        .getByRole('textbox', { name: 'Search the guide' })
        .fill('switch organization');
    const result = page.getByRole('link', {
        name: /Choose the right organization/,
    });
    await result.focus();
    await page.keyboard.press('Enter');

    await expect(page).toHaveURL(
        /\/user-guide\/getting-started#organization-selection$/,
    );
    await expect(
        page.getByRole('navigation', { name: 'Getting started topics' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', {
            level: 2,
            name: 'Choose the right organization',
        }),
    ).toBeVisible();

    await page.goto('/user-guide/dashboard');
    await expect(
        page.getByRole('navigation', { name: 'Dashboard topics' }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Recent inventory activity' }),
    ).toBeVisible();
});

test('stock workflow tutorial anchors open their collapsible instructions', async ({
    page,
}) => {
    await loginAsOwner(page);

    const targets = [
        {
            url: '/user-guide/stock-counts#create-a-stock-count',
            text: 'Select Create stock count from the list.',
        },
        {
            url: '/user-guide/stock-counts#finalize-a-count',
            text: 'Select Finalize count and confirm the finalization dialog.',
        },
        {
            url: '/user-guide/waste#record-waste',
            text: 'Select Review and record waste, verify the confirmation details, then confirm the record.',
        },
        {
            url: '/user-guide/stock-transfers#ship-a-transfer',
            text: 'Select the shipment action and confirm shipment.',
        },
        {
            url: '/user-guide/stock-transfers#receive-a-transfer',
            text: 'Select Review receipt, verify the destination and quantities, then confirm receipt.',
        },
    ];

    for (const target of targets) {
        await page.goto(target.url);

        const tutorial = page.locator(new URL(target.url, 'http://test').hash);
        await expect(tutorial).toBeVisible();
        await expect(tutorial).toHaveAttribute('aria-expanded', 'true');
        await expect(page.getByText(target.text)).toBeVisible();
    }
});

test('guide hides unavailable Open links for limited access', async ({
    page,
}) => {
    await loginAsLimitedUser(page);
    await page.goto('/user-guide/purchasing');
    await expect(
        page.getByRole('link', { name: 'Open purchase orders' }),
    ).toHaveCount(0);
    await expect(
        page.getByText(
            'You may not see this feature if it is not included in your plan or your access level.',
        ),
    ).toBeVisible();
});

test('guide remains readable without an active organization', async ({
    page,
}) => {
    await loginWithoutOrganization(page);
    await page.goto('/user-guide/organization');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Organization' }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: /^Open / })).toHaveCount(0);
    await expect(
        page.getByText(
            'You may not see this feature if it is not included in your plan or your access level.',
        ),
    ).toBeVisible();
});
