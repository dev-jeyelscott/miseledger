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

test('screenshot upload surface shows a visible focus ring when the hidden file input is focused', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.goto('/problem-reports/create');

    await page.locator('#screenshots').focus();

    const uploadLabel = page.locator('label[for="screenshots"]');
    await expect(uploadLabel).not.toHaveCSS('box-shadow', 'none');
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

    const copyButton = page.getByRole('button', { name: 'Copy reference' });

    const box = await copyButton.boundingBox();
    expect(box?.width).toBeGreaterThanOrEqual(44);
    expect(box?.height).toBeGreaterThanOrEqual(44);

    await copyButton.focus();
    await expect(copyButton).toBeFocused();
    await page.keyboard.press('Enter');

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

test('screenshot preview exposes an explicit download action', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.goto('/problem-reports/create');
    await page.fill('#description', 'The oven timer resets unexpectedly.');
    await page.setInputFiles('#screenshots', {
        name: 'screenshot.png',
        mimeType: 'image/png',
        buffer: Buffer.alloc(1024 * 1024, 1),
    });
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/problem-reports\/[^/]+$/);

    const downloadLink = page.getByRole('link', {
        name: 'Download screenshot.png',
    });
    await expect(downloadLink).toBeVisible();
    await expect(downloadLink).toHaveAttribute('download', 'screenshot.png');

    const preview = page.getByRole('img', { name: 'screenshot.png' });
    await expect(preview.locator('xpath=ancestor::a')).toHaveCount(0);
});

test('page titles reflect the current problem report page', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.goto('/problem-reports');
    await expect(page).toHaveTitle(/My Reports/);

    await page.goto('/problem-reports/create');
    await expect(page).toHaveTitle(/Report a Problem/);

    await page.fill('#description', 'The oven timer resets unexpectedly.');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/problem-reports\/[^/]+$/);
    await expect(page).toHaveTitle(/Problem Report/);
});

test('page header actions wrap below the title at mobile widths and stay usable', async ({
    page,
}) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await loginAsOwner(page);

    await page.goto('/problem-reports');

    const indexPageHeader = page.locator('header[data-slot="page-header"]');
    const heading = indexPageHeader.getByRole('heading', {
        name: 'My Reports',
    });
    const submitReportLink = indexPageHeader.getByRole('link', {
        name: 'Submit Report',
    });
    await expect(heading).toBeVisible();
    await expect(submitReportLink).toBeVisible();

    const headingBox = await heading.boundingBox();
    const actionBox = await submitReportLink.boundingBox();
    expect(headingBox).not.toBeNull();
    expect(actionBox).not.toBeNull();
    expect(actionBox!.y).toBeGreaterThan(headingBox!.y + headingBox!.height);

    await submitReportLink.click();
    await expect(page).toHaveURL(/\/problem-reports\/create$/);

    const createPageHeader = page.locator('header[data-slot="page-header"]');
    const createHeading = createPageHeader.getByRole('heading', {
        name: 'Report a Problem',
    });
    const myReportsLink = createPageHeader.getByRole('link', {
        name: 'My Reports',
    });
    await expect(createHeading).toBeVisible();
    await expect(myReportsLink).toBeVisible();

    const createHeadingBox = await createHeading.boundingBox();
    const myReportsBox = await myReportsLink.boundingBox();
    expect(createHeadingBox).not.toBeNull();
    expect(myReportsBox).not.toBeNull();
    expect(myReportsBox!.y).toBeGreaterThan(
        createHeadingBox!.y + createHeadingBox!.height,
    );

    await page.fill('#description', 'The oven timer resets unexpectedly.');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/problem-reports\/[^/]+$/);

    const showPageHeader = page.locator('header[data-slot="page-header"]');
    const detailHeading = showPageHeader.getByRole('heading', {
        name: 'Problem Report',
    });
    const backLink = showPageHeader.getByRole('link', { name: 'My Reports' });
    await expect(detailHeading).toBeVisible();
    await expect(backLink).toBeVisible();

    const detailHeadingBox = await detailHeading.boundingBox();
    const backLinkBox = await backLink.boundingBox();
    expect(detailHeadingBox).not.toBeNull();
    expect(backLinkBox).not.toBeNull();
    expect(backLinkBox!.y).toBeGreaterThan(
        detailHeadingBox!.y + detailHeadingBox!.height,
    );

    await backLink.click();
    await expect(page).toHaveURL(/\/problem-reports$/);
});

test('report detail Submitted timestamp includes a time and timezone', async ({
    page,
}) => {
    await loginAsOwner(page);

    await page.goto('/problem-reports/create');
    await page.fill('#description', 'The oven timer resets unexpectedly.');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/problem-reports\/[^/]+$/);

    const submittedLabel = page.getByText('Submitted', { exact: true });
    await expect(submittedLabel).toBeVisible();

    const submittedValue = submittedLabel.locator(
        'xpath=following-sibling::p[1]',
    );
    await expect(submittedValue).toHaveText(
        /\d{1,2}:\d{2}\s?(AM|PM).*[A-Za-z]{2,5}$/,
    );
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
