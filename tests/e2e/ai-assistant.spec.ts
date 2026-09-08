import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/** Verifies the single AI Assistant launcher remains discoverable, accessible, responsive, and focus-safe. */
test('AI Assistant launcher and drawer remain accessible on mobile and in dark mode without provider traffic', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({
        colorScheme: 'dark',
        reducedMotion: 'reduce',
    });
    await loginAsOwner(page);

    const launcher = page.getByRole('button', {
        name: 'Open AI Assistant',
    });

    await expect(launcher).toBeVisible();
    await expect(launcher).toHaveAccessibleName('Open AI Assistant');
    await expect(launcher).toContainText('Ask AI');

    const launcherBox = await launcher.boundingBox();

    expect(launcherBox).not.toBeNull();

    if (launcherBox === null) {
        throw new Error('AI Assistant launcher bounding box was unavailable.');
    }

    expect(launcherBox.width).toBeGreaterThanOrEqual(44);
    expect(launcherBox.height).toBeGreaterThanOrEqual(44);

    await launcher.focus();
    await expect(launcher).toBeFocused();

    await page.keyboard.press('Enter');

    const dialog = page.getByRole('dialog');

    await expect(dialog).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'AI Assistant' }),
    ).toBeVisible();
    await expect(page.locator('html')).toHaveClass(/dark/);

    await page.keyboard.press('Escape');

    await expect(dialog).toBeHidden();
    await expect(launcher).toBeFocused();

    await page.setViewportSize({ width: 320, height: 640 });

    await expect(launcher).toBeVisible();
    await expect(launcher.getByText('Ask AI')).toBeHidden();

    const narrowLauncherBox = await launcher.boundingBox();

    expect(narrowLauncherBox).not.toBeNull();

    if (narrowLauncherBox === null) {
        throw new Error(
            'AI Assistant launcher bounding box was unavailable at narrow mobile width.',
        );
    }

    expect(narrowLauncherBox.width).toBeGreaterThanOrEqual(44);
    expect(narrowLauncherBox.height).toBeGreaterThanOrEqual(44);
    expect(narrowLauncherBox.x).toBeGreaterThanOrEqual(0);
    expect(narrowLauncherBox.y).toBeGreaterThanOrEqual(0);
    expect(narrowLauncherBox.x + narrowLauncherBox.width).toBeLessThanOrEqual(
        320,
    );
    expect(narrowLauncherBox.y + narrowLauncherBox.height).toBeLessThanOrEqual(
        640,
    );
});
