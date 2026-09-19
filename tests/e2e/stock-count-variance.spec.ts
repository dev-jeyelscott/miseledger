import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * Confirms the variance report's loading feedback is scoped to visits that
 * actually refresh the report, not every global Inertia navigation.
 */
test('navigating away from the variance report does not trigger report-loading feedback', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/stock-counts/variance');

    await page.route(
        (url) => url.pathname === '/stock-counts',
        async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 500));

            return route.fallback();
        },
    );

    const details = page.locator(
        '[aria-labelledby="stock-count-variance-details-title"]',
    );

    await page.getByRole('link', { name: 'Stock counts' }).click();

    await expect(details).toHaveAttribute('aria-busy', 'false');
    await expect(page.getByText('Updating count variance report…')).toHaveCount(
        0,
    );

    await expect(page).toHaveURL(/\/stock-counts$/);
});

test('applying a variance filter shows report-loading feedback until it refreshes', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/stock-counts/variance');

    await page.route(
        (url) =>
            url.pathname === '/stock-counts/variance' &&
            url.searchParams.get('from') === '2020-01-01',
        async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 500));

            return route.fallback();
        },
    );

    const details = page.locator(
        '[aria-labelledby="stock-count-variance-details-title"]',
    );

    await page.fill('#stock-count-variance-from', '2020-01-01');
    await page.getByRole('button', { name: 'Apply filters' }).click();

    await expect(details).toHaveAttribute('aria-busy', 'true');
    await expect(
        page.getByText('Updating count variance report…'),
    ).toBeAttached();

    await expect(details).toHaveAttribute('aria-busy', 'false');
});
