import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

test('screenshot upload shows accessible progress feedback', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.route('**/problem-reports', async (route) => {
        if (route.request().method() !== 'POST') {
            return route.fallback();
        }

        await new Promise((resolve) => setTimeout(resolve, 500));

        return route.fulfill({
            status: 303,
            headers: { Location: '/problem-reports' },
        });
    });

    await page.goto('/problem-reports/create');

    await page.fill('#description', 'The oven timer resets unexpectedly.');
    await page.setInputFiles('#screenshots', {
        name: 'screenshot.png',
        mimeType: 'image/png',
        buffer: Buffer.alloc(1024 * 1024, 1),
    });

    await page.click('button[type="submit"]');

    const status = page.getByRole('status');
    await expect(status).toBeVisible();
    await expect(status).toHaveText(/Uploading… \d+%/);
    await expect(page.locator('progress')).toBeVisible();
});

test('indexed screenshot validation errors are shown beside the upload field', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.route('**/problem-reports', async (route) => {
        if (route.request().method() !== 'POST') {
            return route.fallback();
        }

        return route.fulfill({
            status: 422,
            contentType: 'application/json',
            body: JSON.stringify({
                message: 'The given data was invalid.',
                errors: {
                    'screenshots.0': [
                        'The screenshots.0 must be an image.',
                    ],
                },
            }),
        });
    });

    await page.goto('/problem-reports/create');

    await page.fill('#description', 'The oven timer resets unexpectedly.');
    await page.setInputFiles('#screenshots', {
        name: 'screenshot.png',
        mimeType: 'image/png',
        buffer: Buffer.alloc(1024, 1),
    });

    await page.click('button[type="submit"]');

    await expect(
        page.getByText('The screenshots.0 must be an image.'),
    ).toBeVisible();
    await expect(page.locator('#screenshots')).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    await expect(page.locator('#screenshots')).toHaveAttribute(
        'aria-describedby',
        'screenshots-error',
    );
});

test('screenshot remove button meets touch target size and supports keyboard removal on mobile', async ({
    page,
}) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await loginAsOwner(page);

    await page.goto('/problem-reports/create');

    await page.setInputFiles('#screenshots', {
        name: 'screenshot.png',
        mimeType: 'image/png',
        buffer: Buffer.alloc(1024 * 1024, 1),
    });

    const removeButton = page.getByRole('button', {
        name: 'Remove screenshot 1',
    });
    await expect(removeButton).toBeVisible();

    const box = await removeButton.boundingBox();
    expect(box?.width).toBeGreaterThanOrEqual(44);
    expect(box?.height).toBeGreaterThanOrEqual(44);

    await removeButton.focus();
    await expect(removeButton).toBeFocused();
    await page.keyboard.press('Enter');

    await expect(removeButton).not.toBeVisible();
});

test('copy reference button confirms a successful copy', async ({
    page,
    context,
}) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await loginAsOwner(page);

    await page.goto('/problem-reports/create');
    await page.fill('#description', 'The oven timer resets unexpectedly.');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/problem-reports\/[^/]+$/);

    const reference = await page
        .locator('code.font-mono')
        .first()
        .innerText();

    await page.getByRole('button', { name: 'Copy reference' }).click();

    await expect(page.getByText('Reference copied to clipboard')).toBeVisible();
    await expect(
        page.evaluate(() => navigator.clipboard.readText()),
    ).resolves.toBe(reference);
});

test('synchronization alert states verified behavior without implementation status', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.goto('/problem-reports/create');
    await page.fill('#description', 'The oven timer resets unexpectedly.');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/problem-reports\/[^/]+$/);

    await expect(
        page.getByText('Status updates may take up to one hour to appear.'),
    ).toBeVisible();
    await expect(page.getByText(/synchronization is implemented/i)).toHaveCount(
        0,
    );
});

test('navigation controls render as single links and support keyboard activation', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.goto('/problem-reports/create');

    const myReportsLink = page.getByRole('link', { name: 'My Reports' });
    await expect(myReportsLink).toBeVisible();
    await expect(myReportsLink.getByRole('button')).toHaveCount(0);

    await page.fill('#description', 'The oven timer resets unexpectedly.');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/problem-reports\/[^/]+$/);

    const headerBackLink = page.getByRole('link', { name: 'My Reports' });
    await expect(headerBackLink).toBeVisible();
    await expect(headerBackLink.getByRole('button')).toHaveCount(0);

    const footerBackLink = page.getByRole('link', {
        name: 'Back to My Reports',
    });
    await expect(footerBackLink).toBeVisible();
    await expect(footerBackLink.getByRole('button')).toHaveCount(0);

    await footerBackLink.focus();
    await expect(footerBackLink).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/problem-reports$/);

    const submitReportLink = page.getByRole('link', { name: 'Submit Report' });
    await expect(submitReportLink).toBeVisible();
    await expect(submitReportLink.getByRole('button')).toHaveCount(0);

    await submitReportLink.focus();
    await expect(submitReportLink).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/problem-reports\/create$/);
});

test('copy reference button reports a failed copy', async ({ page }) => {
    await loginAsOwner(page);

    await page.goto('/problem-reports/create');
    await page.fill('#description', 'The oven timer resets unexpectedly.');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/problem-reports\/[^/]+$/);

    await page.evaluate(() => {
        navigator.clipboard.writeText = () =>
            Promise.reject(new Error('Clipboard access denied'));
    });

    await page.getByRole('button', { name: 'Copy reference' }).click();

    await expect(
        page.getByText('Could not copy reference to clipboard'),
    ).toBeVisible();
});
