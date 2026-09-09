import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

export const E2E_OWNER_EMAIL = 'e2e-owner@miseledger.test';
export const E2E_OWNER_PASSWORD = 'password';
export const E2E_LIMITED_EMAIL = 'e2e-kitchen-staff@miseledger.test';
export const E2E_NO_ORGANIZATION_EMAIL = 'e2e-no-organization@miseledger.test';

/** Log in as the seeded organization owner via the real login form. */
export async function loginAsOwner(page: Page): Promise<void> {
    await login(page, E2E_OWNER_EMAIL);
}

export async function loginAsLimitedUser(page: Page): Promise<void> {
    await login(page, E2E_LIMITED_EMAIL);
}

export async function loginWithoutOrganization(page: Page): Promise<void> {
    await login(page, E2E_NO_ORGANIZATION_EMAIL);
}

async function login(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.fill('#email', email);
    await page.fill('#password', E2E_OWNER_PASSWORD);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/dashboard/);
}
