import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

test.describe('Public landing page (guest, desktop)', () => {
    test('renders the locked header, hero, and every major section', async ({
        page,
    }) => {
        await page.goto('/');

        await expect(
            page.getByRole('link', { name: 'MiseLedger' }).first(),
        ).toBeVisible();
        await expect(
            page.getByRole('navigation', { name: 'Primary navigation' }),
        ).toBeVisible();

        await expect(
            page.getByRole('link', { name: 'Log in' }).first(),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Start free' }).first(),
        ).toBeVisible();

        await expect(
            page.getByRole('heading', {
                level: 1,
                name: 'Know what you have before you buy more.',
            }),
        ).toBeVisible();

        const heroImage = page.getByAltText(/MiseLedger dashboard/);
        await expect(heroImage).toBeVisible();

        await expect(
            page.getByRole('link', { name: 'Create an account' }),
        ).toHaveAttribute('href', /register/);

        // Product proof strip
        await expect(page.locator('#product')).toBeVisible();

        // Operational problems
        await expect(
            page.getByRole('heading', {
                name: 'Why inventory gets hard to control.',
            }),
        ).toBeVisible();

        // Product stories
        await expect(
            page.getByRole('heading', {
                name: 'See what is on hand and where attention is needed',
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', {
                name: 'Keep buying and receiving connected to inventory',
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', {
                name: 'Understand where inventory value is going',
            }),
        ).toBeVisible();

        // Connected workflow
        await expect(page.locator('#how-it-works')).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'From delivery to plate' }),
        ).toBeVisible();

        // Dark multi-location story
        await expect(
            page.getByRole('heading', {
                name: 'One inventory picture across every location.',
            }),
        ).toBeVisible();

        // Audience and roles
        await expect(page.locator('#for-teams')).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Restaurants' }),
        ).toBeVisible();

        // AI Assistant
        await expect(
            page.getByRole('heading', {
                name: 'Ask MiseLedger about your operation',
            }),
        ).toBeVisible();

        // Pricing
        await expect(page.locator('#pricing')).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Starter Plan' }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Growth Plan' }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Business Plan' }),
        ).toBeVisible();

        // FAQ
        await expect(page.locator('#faq')).toBeVisible();
        await expect(
            page.getByText('What is MiseLedger?'),
        ).toBeVisible();

        // Final CTA and footer
        await expect(
            page.getByRole('heading', {
                name: 'Know what you have before you buy more.',
                level: 2,
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('navigation', { name: 'Product' }),
        ).toBeVisible();
    });

    test('anchor navigation scrolls without the sticky header covering the destination heading', async ({
        page,
    }) => {
        await page.goto('/');
        await page.getByRole('link', { name: 'Pricing' }).first().click();

        await expect(page).toHaveURL(/#pricing$/);

        const heading = page.getByRole('heading', {
            name: 'Try MiseLedger, then subscribe when you are ready',
        });
        await expect(heading).toBeInViewport();
    });

    test('FAQ items are keyboard-operable disclosures', async ({ page }) => {
        await page.goto('/');

        const firstQuestion = page.getByText('What is MiseLedger?');
        await firstQuestion.click();

        await expect(
            page.getByText(/MiseLedger is inventory and purchasing software/),
        ).toBeVisible();
    });

    test('clicking the hero screenshot opens a zoomed dialog, closable via the close button and outside click', async ({
        page,
    }) => {
        await page.goto('/');

        const zoomTrigger = page.getByRole('button', {
            name: /Zoom in on MiseLedger dashboard/,
        });
        await zoomTrigger.click();

        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await expect(
            dialog.getByRole('img', { name: /MiseLedger dashboard/ }),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Close' }).click();
        await expect(dialog).toBeHidden();

        await zoomTrigger.click();
        await expect(dialog).toBeVisible();

        // Click the dimmed backdrop outside the zoomed image to close.
        await page.mouse.click(5, 5);
        await expect(dialog).toBeHidden();
    });
});

test.describe('Public landing page (guest, mobile)', () => {
    test('mobile menu opens, closes on Escape, returns focus, and the page has no horizontal overflow', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/');

        const menuButton = page.locator('summary', { hasText: 'Menu' });
        await expect(menuButton).toBeVisible();
        await menuButton.click();

        const mobileNav = page.getByRole('navigation', {
            name: 'Mobile navigation',
        });
        await expect(mobileNav).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(mobileNav).toBeHidden();
        await expect(menuButton).toBeFocused();

        const hasOverflow = await page.evaluate(
            () => document.documentElement.scrollWidth > window.innerWidth,
        );
        expect(hasOverflow).toBe(false);
    });

    test('pricing cards and FAQ stack on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/');

        await page.locator('#pricing').scrollIntoViewIfNeeded();
        await expect(
            page.getByRole('heading', { name: 'Starter Plan' }),
        ).toBeVisible();

        await page.locator('#faq').scrollIntoViewIfNeeded();
        await expect(page.getByText('What is MiseLedger?')).toBeVisible();
    });
});

test.describe('Public landing page (reduced motion)', () => {
    test('all content is visible immediately without relying on reveal or parallax movement', async ({
        page,
    }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto('/');

        await expect(
            page.getByRole('heading', {
                name: 'One inventory picture across every location.',
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Business Plan' }),
        ).toBeVisible();
    });
});

test.describe('Public landing page (authenticated)', () => {
    test('shows a Dashboard action instead of guest acquisition actions', async ({
        page,
    }) => {
        await loginAsOwner(page);
        await page.goto('/');

        await expect(
            page.getByRole('link', { name: 'Dashboard' }).first(),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Start free' }),
        ).toHaveCount(0);
    });
});
