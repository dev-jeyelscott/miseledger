import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/** Verify wide User Guide side navigation remains pinned while long module content scrolls. */
test('wide User Guide navigation remains sticky while scrolling', async ({
    page,
}) => {
    await page.setViewportSize({
        width: 1728,
        height: 1000,
    });

    await loginAsOwner(page);
    await page.goto('/user-guide/stock-counts');

    const guideNavigation = page.getByRole('navigation', {
        name: 'Guide navigation',
    });
    const pageNavigation = page.getByRole('navigation', {
        name: 'On this page',
    });

    await expect(guideNavigation).toBeVisible();
    await expect(pageNavigation).toBeVisible();

    await page.evaluate(() => window.scrollTo(0, 900));

    const firstGuideBox = await guideNavigation.boundingBox();
    const firstPageBox = await pageNavigation.boundingBox();

    expect(firstGuideBox).not.toBeNull();
    expect(firstPageBox).not.toBeNull();

    expect(firstGuideBox!.y).toBeGreaterThanOrEqual(90);
    expect(firstGuideBox!.y).toBeLessThanOrEqual(102);
    expect(firstPageBox!.y).toBeGreaterThanOrEqual(90);
    expect(firstPageBox!.y).toBeLessThanOrEqual(102);

    await page.evaluate(() => window.scrollBy(0, 600));

    const secondGuideBox = await guideNavigation.boundingBox();
    const secondPageBox = await pageNavigation.boundingBox();

    expect(secondGuideBox).not.toBeNull();
    expect(secondPageBox).not.toBeNull();

    expect(Math.abs(secondGuideBox!.y - firstGuideBox!.y)).toBeLessThanOrEqual(
        2,
    );
    expect(Math.abs(secondPageBox!.y - firstPageBox!.y)).toBeLessThanOrEqual(
        2,
    );
});
