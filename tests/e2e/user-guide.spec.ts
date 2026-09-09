import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/** Verify search replaces discovery, Clear restores it, and User Guide deep links remain keyboard-operable. */
test('User Guide search and discovery remain keyboard operable', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/user-guide');

    const searchInput = page.getByLabel('Search the User Guide');
    const popularTasksHeading = page.getByRole('heading', {
        name: 'Popular Tasks',
    });
    const browseModulesHeading = page.getByRole('heading', {
        name: 'Browse by Module',
    });
    const popularTasksRegion = page.getByRole('region', {
        name: 'Popular Tasks',
    });
    const browseModulesRegion = page.getByRole('region', {
        name: 'Browse by Module',
    });

    await expect(searchInput).toBeVisible();
    await expect(popularTasksHeading).toBeVisible();
    await expect(browseModulesHeading).toBeVisible();
    await expect(popularTasksRegion.getByRole('link')).toHaveCount(4);
    await expect(browseModulesRegion.getByRole('link')).toHaveCount(13);

    await searchInput.fill('Receive a purchase order');

    await expect(popularTasksHeading).toBeHidden();
    await expect(browseModulesHeading).toBeHidden();
    await expect(
        page.getByRole('heading', { name: 'Search results' }),
    ).toBeVisible();
    await expect(page.getByText('1 result found', { exact: true })).toBeVisible();

    const tutorialResult = page.getByRole('link', {
        name: /Receive a purchase order/i,
    });
    const clearButton = page.getByRole('button', { name: 'Clear' });

    await expect(tutorialResult).toHaveAttribute(
        'href',
        /\/user-guide\/purchasing#receive-a-purchase-order$/,
    );

    await clearButton.focus();
    await expect(clearButton).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(tutorialResult).toBeFocused();

    await page.keyboard.press('Shift+Tab');
    await expect(clearButton).toBeFocused();

    await page.keyboard.press('Enter');

    await expect(searchInput).toHaveValue('');
    await expect(
        page.getByRole('heading', { name: 'Search results' }),
    ).toBeHidden();
    await expect(popularTasksHeading).toBeVisible();
    await expect(browseModulesHeading).toBeVisible();

    const receiveStockLink = page.getByRole('link', {
        name: 'Receive Stock',
    });

    await receiveStockLink.focus();
    await expect(receiveStockLink).toBeFocused();

    await page.keyboard.press('Enter');

    await expect(page).toHaveURL(
        /\/user-guide\/purchasing#receive-a-purchase-order$/,
    );
    await expect(
        page.getByRole('heading', { name: 'Purchasing' }),
    ).toBeVisible();
});
