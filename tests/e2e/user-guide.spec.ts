import { expect, test } from '@playwright/test';
import {
    loginAsLimitedUser,
    loginAsOwner,
} from './support/auth';

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

    await searchInput.fill('Record a complete receipt');

    await expect(popularTasksHeading).toBeHidden();
    await expect(browseModulesHeading).toBeHidden();
    await expect(
        page.getByRole('heading', { name: 'Search results' }),
    ).toBeVisible();
    await expect(
        page.getByText('1 result found', { exact: true }),
    ).toBeVisible();

    const tutorialResult = page.getByRole('link', {
        name: /Record a complete receipt/i,
    });
    const clearButton = page.getByRole('button', { name: 'Clear' });

    await expect(tutorialResult).toHaveAttribute(
        'href',
        /\/user-guide\/purchasing#record-a-complete-receipt$/,
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
        /\/user-guide\/purchasing#record-a-complete-receipt$/,
    );
    await expect(
        page.getByRole('heading', { name: 'Purchasing' }),
    ).toBeVisible();

    const receiptTutorial = page.getByRole('button', {
        name: 'Record a complete receipt',
    });

    await expect(receiptTutorial).toBeVisible();
    await expect(receiptTutorial).toHaveAttribute(
        'aria-expanded',
        'true',
    );
});

/** Verify wide User Guide pages expose canonical module navigation and stable section anchors. */
test('User Guide module reader exposes stable wide-screen navigation', async ({
    page,
}) => {
    await page.setViewportSize({
        width: 1728,
        height: 1000,
    });
    await loginAsOwner(page);
    await page.goto('/user-guide/getting-started');

    const guideNavigation = page.getByRole('navigation', {
        name: 'Guide navigation',
    });

    await expect(guideNavigation).toBeVisible();
    await expect(guideNavigation.getByRole('link')).toHaveCount(13);

    const currentModuleLink = guideNavigation.getByRole('link', {
        name: 'Getting started',
        exact: true,
    });

    await expect(currentModuleLink).toHaveAttribute(
        'aria-current',
        'page',
    );

    const pageNavigation = page.getByRole('navigation', {
        name: 'On this page',
    });

    await expect(pageNavigation).toBeVisible();

    const welcomeLink = pageNavigation.getByRole('link', {
        name: 'Welcome to MiseLedger',
        exact: true,
    });

    await expect(welcomeLink).toHaveAttribute(
        'href',
        '#welcome-to-miseledger',
    );

    await welcomeLink.click();

    await expect(page).toHaveURL(
        /\/user-guide\/getting-started#welcome-to-miseledger$/,
    );
    await expect(
        page.locator('#welcome-to-miseledger'),
    ).toBeVisible();

    await expect(
        page.getByRole('link', { name: 'Open dashboard' }),
    ).toBeVisible();
});

/** Verify compact navigation remains keyboard-operable and unavailable actions stay permission-aware. */
test('User Guide compact navigation preserves access-aware actions', async ({
    page,
}) => {
    await page.setViewportSize({
        width: 1024,
        height: 900,
    });
    await loginAsLimitedUser(page);
    await page.goto('/user-guide/stock-counts');

    const guideNavigation = page.getByRole('navigation', {
        name: 'Guide navigation',
    });
    const guideNavigationTrigger = guideNavigation.getByRole(
        'button',
        {
            name: /Guide Nav/i,
        },
    );

    await expect(guideNavigationTrigger).toBeVisible();
    await expect(guideNavigationTrigger).toHaveAttribute(
        'aria-expanded',
        'false',
    );

    await guideNavigationTrigger.focus();
    await expect(guideNavigationTrigger).toBeFocused();

    await page.keyboard.press('Enter');

    await expect(guideNavigationTrigger).toHaveAttribute(
        'aria-expanded',
        'true',
    );

    const currentModuleLink = guideNavigation.getByRole('link', {
        name: 'Stock counts',
        exact: true,
    });

    await expect(currentModuleLink).toBeVisible();
    await expect(currentModuleLink).toHaveAttribute(
        'aria-current',
        'page',
    );

    const pageNavigation = page.getByRole('navigation', {
        name: 'On this page',
    });
    const pageNavigationTrigger = pageNavigation.getByRole(
        'button',
        {
            name: 'On this page',
            exact: true,
        },
    );

    await pageNavigationTrigger.focus();
    await expect(pageNavigationTrigger).toBeFocused();

    await page.keyboard.press('Enter');

    await expect(pageNavigation.getByRole('link').first()).toBeVisible();

    await expect(
        page.getByText(
            'You may not see this feature if it is not included in your plan or your access level.',
            { exact: true },
        ),
    ).toBeVisible();

    await expect(
        page.getByRole('link', {
            name: 'Open stock counts',
            exact: true,
        }),
    ).toHaveCount(0);
});

/** Verify modules without page data continue to render the canonical legacy guide content. */
test('User Guide legacy module content remains available', async ({
    page,
}) => {
    await loginAsOwner(page);
    await page.goto('/user-guide/ai-assistant');

    await expect(
        page.getByRole('heading', {
            name: 'AI Assistant',
            level: 1,
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Tutorials' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Key fields' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Troubleshooting' }),
    ).toBeVisible();

    const legacyTutorial = page.getByRole('button', {
        name: 'Ask a useful question',
    });

    await expect(legacyTutorial).toBeVisible();
    await expect(legacyTutorial).toHaveAttribute(
        'id',
        'ask-a-question',
    );
});
