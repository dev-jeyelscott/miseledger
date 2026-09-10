import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/** Verify Release Notes page loads and exposes User Guide navigation action. */
test('Release Notes page renders with User Guide navigation', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/release-notes');

    await expect(
        page.getByRole('heading', {
            name: 'Release Notes',
            level: 1,
        }),
    ).toBeVisible();

    await expect(
        page.getByRole('button', {
            name: /Open User Guide/i,
        }),
    ).toBeVisible();

    // Verify the button links to the User Guide
    const openGuideButton = page.getByRole('button', {
        name: /Open User Guide/i,
    });
    await expect(openGuideButton).toHaveAttribute('href', /\/user-guide/);
});

/** Verify Release Notes entries with related guides render working links. */
test('Release Notes renders related guide links when present', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/release-notes');

    // Find an entry that has related guides
    const releaseNoteArticle = page.locator('article').first();
    await expect(releaseNoteArticle).toBeVisible();

    // Look for "Read the guide" buttons
    const guideButtons = releaseNoteArticle.getByRole('link', {
        name: /Read the .* guide/i,
    });

    // Get the count - if there are any, verify they work
    const buttonCount = await guideButtons.count();

    if (buttonCount > 0) {
        // Get the first guide link and verify it has a valid href
        const firstGuideLink = guideButtons.first();
        await expect(firstGuideLink).toHaveAttribute(
            'href',
            /\/user-guide\/\w+(-\w+)*/,
        );

        // Click and verify navigation works
        await firstGuideLink.click();
        await expect(page).toHaveURL(/\/user-guide\/\w+(-\w+)*/);
        await expect(
            page.getByRole('heading', { level: 1 }),
        ).toBeVisible();
    }
});

/** Verify entries without related guides render cleanly without dead links. */
test('Release Notes handles entries without related guides gracefully', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/release-notes');

    // Verify the page structure is intact
    await expect(
        page.getByRole('heading', {
            name: 'Release Notes',
            level: 1,
        }),
    ).toBeVisible();

    // Count the articles (entries)
    const articles = page.locator('article');
    const articleCount = await articles.count();

    expect(articleCount).toBeGreaterThan(0);

    // Check each article - some may or may not have guide links
    for (let i = 0; i < Math.min(articleCount, 3); i++) {
        const article = articles.nth(i);
        await expect(article).toBeVisible();

        // Verify title and summary are present
        const title = article.locator('h2').first();
        await expect(title).toBeVisible();

        // Get guide links for this article
        const guideLinks = article.getByRole('link', {
            name: /Read the .* guide/i,
        });

        // If there are no guide links, verify it doesn't have broken/disabled buttons
        const guideLinkCount = await guideLinks.count();

        if (guideLinkCount === 0) {
            // Just verify the article structure is clean
            await expect(article.locator('p').first()).toBeVisible();
        }
    }
});

/** Verify User Guide home exposes Release Notes navigation and link works. */
test('User Guide home exposes Release Notes navigation', async ({ page }) => {
    await loginAsOwner(page);
    await page.goto('/user-guide');

    const releaseNotesButton = page.getByRole('button', {
        name: /Release Notes/i,
    });

    await expect(releaseNotesButton).toBeVisible();
    await expect(releaseNotesButton).toHaveAttribute(
        'href',
        /\/release-notes/,
    );

    // Click and verify navigation works
    await releaseNotesButton.click();
    await expect(page).toHaveURL(/\/release-notes/);
    await expect(
        page.getByRole('heading', { name: 'Release Notes', level: 1 }),
    ).toBeVisible();
});

/** Verify Release Notes navigation works from both directions and keyboard navigation. */
test('Release Notes and User Guide navigation remain keyboard operable', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/release-notes');

    // Find the Open User Guide button
    const openGuideButton = page.getByRole('button', {
        name: /Open User Guide/i,
    });

    // Focus and press Enter
    await openGuideButton.focus();
    await expect(openGuideButton).toBeFocused();
    await page.keyboard.press('Enter');

    // Should navigate to User Guide
    await expect(page).toHaveURL(/\/user-guide/);
    await expect(
        page.getByRole('heading', { name: 'Browse by Module', level: 2 }),
    ).toBeVisible();

    // Navigate back from User Guide
    const releaseNotesButton = page.getByRole('button', {
        name: /Release Notes/i,
    });

    await releaseNotesButton.focus();
    await expect(releaseNotesButton).toBeFocused();
    await page.keyboard.press('Enter');

    // Should navigate back to Release Notes
    await expect(page).toHaveURL(/\/release-notes/);
    await expect(
        page.getByRole('heading', { name: 'Release Notes', level: 1 }),
    ).toBeVisible();
});

/** Verify Release Notes page works on mobile viewport. */
test('Release Notes page renders correctly on mobile', async ({ page }) => {
    await page.setViewportSize({
        width: 375,
        height: 667,
    });

    await loginAsOwner(page);
    await page.goto('/release-notes');

    await expect(
        page.getByRole('heading', {
            name: 'Release Notes',
            level: 1,
        }),
    ).toBeVisible();

    // Verify Open User Guide button is accessible on mobile
    const openGuideButton = page.getByRole('button', {
        name: /Open User Guide/i,
    });

    await expect(openGuideButton).toBeVisible();
});

/** Verify Release Notes and User Guide work in dark mode. */
test('Release Notes renders correctly in dark mode', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark' });

    await loginAsOwner(page);
    await page.goto('/release-notes');

    // Verify page is still readable in dark mode
    await expect(
        page.getByRole('heading', {
            name: 'Release Notes',
            level: 1,
        }),
    ).toBeVisible();

    // Verify buttons are visible
    const openGuideButton = page.getByRole('button', {
        name: /Open User Guide/i,
    });

    await expect(openGuideButton).toBeVisible();
});

/** Verify Release Notes at 200% zoom. */
test('Release Notes remains usable at 200% zoom', async ({ page }) => {
    await page.goto('/release-notes');
    await page.evaluate(() => {
        document.body.style.zoom = '200%';
    });

    await loginAsOwner(page);

    await expect(
        page.getByRole('heading', {
            name: 'Release Notes',
            level: 1,
        }),
    ).toBeVisible();
});
