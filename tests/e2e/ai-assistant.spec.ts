import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

test('AI Assistant drawer remains usable on mobile and in dark mode without provider traffic', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsOwner(page);

    await page.getByRole('button', { name: 'Open AI Assistant' }).click();

    await expect(page.getByRole('dialog')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'AI Assistant' })).toBeVisible();
    await expect(page.locator('html')).toHaveClass(/dark/);
});
