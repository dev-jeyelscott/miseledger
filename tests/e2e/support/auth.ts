import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

export const E2E_OWNER_EMAIL = 'e2e-owner@miseledger.test';
export const E2E_OWNER_PASSWORD = 'password';

/** Log in as the seeded organization owner via the real login form. */
export async function loginAsOwner(page: Page): Promise<void> {
    await page.goto('/login');
    await page.fill('#email', E2E_OWNER_EMAIL);
    await page.fill('#password', E2E_OWNER_PASSWORD);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/dashboard/);
}
