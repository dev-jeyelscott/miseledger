import { expect, test } from '@playwright/test';
import { E2E_OWNER_EMAIL } from './support/auth';

test('password visibility toggle is reachable and operable by keyboard', async ({
    page,
}) => {
    await page.goto('/login');

    await page.fill('#email', E2E_OWNER_EMAIL);
    const password = page.locator('#password');
    await password.focus();

    const toggle = page.getByRole('button', { name: 'Show password' });

    await page.keyboard.press('Tab');
    await expect(toggle).toBeFocused();
    await expect(password).toHaveAttribute('type', 'password');

    await page.keyboard.press('Enter');
    await expect(password).toHaveAttribute('type', 'text');
    await expect(page.getByRole('button', { name: 'Hide password' })).toBeFocused();

    await page.keyboard.press('Space');
    await expect(password).toHaveAttribute('type', 'password');
    await expect(page.getByRole('button', { name: 'Show password' })).toBeFocused();
});
