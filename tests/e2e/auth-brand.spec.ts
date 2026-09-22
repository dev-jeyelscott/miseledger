import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { E2E_OWNER_EMAIL, E2E_OWNER_PASSWORD } from './support/auth';

const DESKTOP = { width: 1440, height: 900 };
const MOBILE = { width: 390, height: 844 };

async function hasHorizontalOverflow(page: Page): Promise<boolean> {
    return page.evaluate(
        () => document.documentElement.scrollWidth > window.innerWidth,
    );
}

test.describe('Login (desktop)', () => {
    test('renders the branded split shell with the full form', async ({
        page,
    }) => {
        await page.setViewportSize(DESKTOP);
        await page.goto('/login');

        await expect(
            page.getByText('Clear inventory. Connected purchasing.', {
                exact: false,
            }),
        ).toBeVisible();
        await expect(page.getByText('Know what is on hand')).toBeVisible();

        await expect(
            page.getByRole('heading', { name: 'Welcome back' }),
        ).toBeVisible();
        await expect(page.getByLabel('Email address')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Sign in with a passkey' }),
        ).toBeVisible();
        await expect(
            page.getByLabel('Password', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Forgot your password?' }),
        ).toBeVisible();
        await expect(page.getByLabel('Remember me')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Log in' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Start free' }),
        ).toBeVisible();
    });
});

test.describe('Login (mobile)', () => {
    test('hides the desktop brand panel and keeps every control reachable without overflow', async ({
        page,
    }) => {
        await page.setViewportSize(MOBILE);
        await page.goto('/login');

        await expect(
            page.getByText('Clear inventory. Connected purchasing.', {
                exact: false,
            }),
        ).toBeHidden();

        await expect(page.getByLabel('Email address')).toBeVisible();
        await expect(
            page.getByLabel('Password', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Forgot your password?' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Log in' }),
        ).toBeVisible();

        expect(await hasHorizontalOverflow(page)).toBe(false);
    });

    test('authenticates through the redesigned form', async ({ page }) => {
        await page.setViewportSize(MOBILE);
        await page.goto('/login');

        await page.getByLabel('Email address').fill(E2E_OWNER_EMAIL);
        await page
            .getByLabel('Password', { exact: true })
            .fill(E2E_OWNER_PASSWORD);
        await page.getByRole('button', { name: 'Log in' }).click();

        await expect(page).toHaveURL(/\/dashboard/);
    });
});

test.describe('Register (desktop)', () => {
    test('renders the branded split shell with all four fields', async ({
        page,
    }) => {
        await page.setViewportSize(DESKTOP);
        await page.goto('/register');

        await expect(
            page.getByRole('heading', { name: 'Create your account' }),
        ).toBeVisible();
        await expect(page.getByLabel('Name')).toBeVisible();
        await expect(page.getByLabel('Email address')).toBeVisible();
        await expect(
            page.getByLabel('Password', { exact: true }),
        ).toBeVisible();
        await expect(page.getByLabel('Confirm password')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Create account' }),
        ).toBeVisible();
        await expect(page.getByRole('link', { name: 'Log in' })).toBeVisible();
    });
});

test.describe('Register (mobile)', () => {
    test('collapses to single-column with no clipping and usable password controls', async ({
        page,
    }) => {
        await page.setViewportSize(MOBILE);
        await page.goto('/register');

        await expect(page.getByLabel('Name')).toBeVisible();
        await expect(page.getByLabel('Email address')).toBeVisible();

        const password = page.getByLabel('Password', { exact: true });
        await password.fill('correct horse battery staple');
        await expect(password).toHaveValue('correct horse battery staple');

        await expect(
            page.getByRole('button', { name: 'Create account' }),
        ).toBeVisible();

        expect(await hasHorizontalOverflow(page)).toBe(false);
    });
});

test.describe('Shared auth shell (security pages)', () => {
    test('forgot password renders inside the branded shell without acquisition copy', async ({
        page,
    }) => {
        await page.setViewportSize(DESKTOP);
        await page.goto('/forgot-password');

        await expect(
            page.getByRole('heading', { name: 'Forgot password' }),
        ).toBeVisible();
        await expect(page.getByLabel('Email address')).toBeVisible();
        await expect(
            page.getByRole('button', {
                name: 'Email password reset link',
            }),
        ).toBeVisible();
        await expect(page.getByText('Start free')).toBeHidden();
    });

    test('reset password renders inside the branded shell without acquisition copy', async ({
        page,
    }) => {
        await page.setViewportSize(DESKTOP);
        await page.goto(
            '/reset-password/e2e-smoke-token?email=e2e-smoke%40miseledger.test',
        );

        await expect(
            page.getByRole('heading', { name: 'Reset password' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Reset password' }),
        ).toBeVisible();
        await expect(page.getByText('Start free')).toBeHidden();
    });

    test('confirm password renders inside the branded shell for an authenticated user', async ({
        page,
    }) => {
        await page.setViewportSize(DESKTOP);
        await page.goto('/login');
        await page.getByLabel('Email address').fill(E2E_OWNER_EMAIL);
        await page
            .getByLabel('Password', { exact: true })
            .fill(E2E_OWNER_PASSWORD);
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(page).toHaveURL(/\/dashboard/);

        await page.goto('/user/confirm-password');

        await expect(
            page.getByRole('heading', { name: 'Confirm password' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Confirm password' }),
        ).toBeVisible();
        await expect(page.getByText('Start free')).toBeHidden();
    });

    test('verify email renders inside the branded shell without acquisition copy', async ({
        page,
    }) => {
        await page.setViewportSize(DESKTOP);

        // Self-register a throwaway account through the real registration
        // form: a freshly registered user is unverified, which is the only
        // guest-reachable way to land on Verify Email without seeding
        // backend fixtures outside this redesign's scope.
        await page.goto('/register');
        const uniqueEmail = `e2e-verify-${Date.now()}@miseledger.test`;
        await page.getByLabel('Name').fill('E2E Verify Smoke');
        await page.getByLabel('Email address').fill(uniqueEmail);
        await page
            .getByLabel('Password', { exact: true })
            .fill('correct horse battery staple');
        await page
            .getByLabel('Confirm password')
            .fill('correct horse battery staple');
        await page.getByRole('button', { name: 'Create account' }).click();

        // Fortify redirects a freshly registered, unverified user straight
        // to Verify Email; wait for that redirect instead of racing it with
        // an immediate goto.
        await page.waitForURL(/\/email\/verify/);

        await expect(
            page.getByRole('heading', { name: 'Email verification' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', {
                name: 'Resend verification email',
            }),
        ).toBeVisible();
        await expect(page.getByText('Start free')).toBeHidden();
    });

    // Two-Factor Challenge is intentionally not covered here: reaching it
    // requires a user with a confirmed TOTP secret, which this redesign's
    // scope does not extend to seeding (no Fortify/backend changes, no new
    // seed fixtures). Its rendering under the shared shell, OTP input, and
    // recovery-code toggle are covered by tests/Feature/Auth/
    // TwoFactorChallengeTest.php, which still passes unchanged.
});
