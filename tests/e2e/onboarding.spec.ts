import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';

const testEnv = {
    ...process.env,
    APP_ENV: 'testing',
    DB_DATABASE: 'miseledger_test',
};

/** Email links cannot be followed in E2E, so verify the new account directly. */
function markEmailVerified(email: string): void {
    execFileSync(
        'php',
        [
            '-r',
            [
                'require "vendor/autoload.php";',
                '$app = require "bootstrap/app.php";',
                '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();',
                'App\\Models\\User::query()->where("email", $argv[1])->update(["email_verified_at" => now()]);',
            ].join(' '),
            email,
        ],
        { env: testEnv, stdio: 'pipe' },
    );
}

async function xsrfToken(page: Page): Promise<string> {
    const cookie = (await page.context().cookies()).find(
        (candidate) => candidate.name === 'XSRF-TOKEN',
    );

    return decodeURIComponent(cookie?.value ?? '');
}

function setupSteps(page: Page) {
    return page.getByRole('navigation', { name: 'Setup steps' });
}

async function uploadCsv(page: Page, name: string, contents: string) {
    await page.getByLabel('CSV file').setInputFiles({
        name,
        mimeType: 'text/csv',
        buffer: Buffer.from(contents),
    });
    await page.getByRole('button', { name: 'Check file' }).click();
}

test('a new owner completes first-time setup and reaches the dashboard', async ({
    page,
}) => {
    test.setTimeout(120_000);

    const email = `e2e-onboarding-${Date.now()}@miseledger.test`;

    await page.goto('/register');
    await page.fill('#name', 'Onboarding Owner');
    await page.fill('#email', email);
    await page.fill('#password', 'password');
    await page.fill('#password_confirmation', 'password');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/email\/verify/);

    markEmailVerified(email);

    // Organization: business name only.
    await page.goto('/onboarding');
    await expect(
        page.getByRole('heading', { name: 'Set up your organization' }),
    ).toBeVisible();
    await page.getByLabel('Business name').fill('Sinta Test Kitchen');
    await page
        .getByRole('button', { name: 'Create organization and continue' })
        .click();
    await expect(
        setupSteps(page).getByRole('link', { name: /Organization\s*Complete/ }),
    ).toBeVisible();

    // Location: name only, safe across refresh and history navigation.
    await page.getByLabel('Location name').fill('Main Kitchen');
    await page.getByRole('button', { name: 'Save location' }).click();
    await expect(page).toHaveURL(/step=units/);

    await page.goBack();
    await page.goForward();
    await page.reload();
    await page.goto('/onboarding?step=location');
    await expect(page.getByText('Main Kitchen', { exact: true })).toHaveCount(
        1,
    );
    await expect(
        setupSteps(page).getByRole('link', { name: /Location\s*Complete/ }),
    ).toBeVisible();

    // Units: explicit selection from the catalog.
    await page.goto('/onboarding?step=units');
    await page.getByLabel(/Kilogram/).check();
    await page.getByLabel(/Piece/).check();
    await page.getByRole('button', { name: 'Add selected units' }).click();
    await expect(page).toHaveURL(/step=inventory/);

    // Operational workflows stay navigable but blocked server-side.
    await page.goto('/stock-counts');
    await expect(
        page.getByText('Finish setup to start recording stock'),
    ).toBeVisible();
    const blocked = await page.request.post('/waste', {
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': await xsrfToken(page),
        },
        data: {},
    });
    expect(blocked.status()).toBe(409);
    await page.getByRole('link', { name: 'Continue setup' }).click();
    await expect(page).toHaveURL(/\/onboarding/);

    // Suppliers are optional.
    await page.goto('/onboarding?step=suppliers');
    await page.getByRole('button', { name: 'Skip suppliers for now' }).click();
    await expect(
        setupSteps(page).getByRole('link', { name: /Suppliers\s*Skipped/ }),
    ).toBeVisible();

    // Inventory: manual entry.
    await page.goto('/onboarding?step=inventory');
    await page.getByLabel('Item name').fill('Jasmine Rice');
    await page.getByLabel('SKU').fill('RICE');
    await page.getByLabel('Base unit').selectOption({ label: 'Kilogram (kg)' });
    await page.getByRole('button', { name: 'Add item' }).click();
    await expect(page.getByText(/1\s+active item so far/)).toBeVisible();

    // Inventory: an invalid CSV is rejected before anything is saved.
    await uploadCsv(
        page,
        'invalid.csv',
        'sku,name,base_unit_symbol\nBAD,Bad Item,gallon\n',
    );
    await expect(page.getByText(/1 row needs fixing/)).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Import items' }),
    ).toBeDisabled();

    // Inventory: CSV with opening quantities.
    await uploadCsv(
        page,
        'inventory.csv',
        'sku,name,base_unit_symbol,opening_quantity,opening_unit_cost\nSUGAR,White Sugar,kg,10,55\nEGG,Eggs,piece,,\n',
    );
    await expect(page.getByText('File is ready to import')).toBeVisible();
    await page.getByRole('button', { name: 'Import items' }).click();
    await expect(page).toHaveURL(/step=opening_stock/);

    // Opening stock: a quantity for one item, an explicit "none" for another.
    await expect(page.getByText('2 items still need')).toBeVisible();
    await page.getByLabel('Quantity (kg)').first().fill('12.5');
    await page.getByLabel('Cost per kg').first().fill('48');
    await page
        .getByRole('button', { name: 'Save opening stock for Jasmine Rice' })
        .click();
    await expect(page.getByText('1 item still needs')).toBeVisible();

    await page
        .getByRole('button', {
            name: 'Mark Eggs as having no opening stock',
        })
        .click();

    // Minimum setup complete: straight to the dashboard.
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(
        page.getByText('Finish setup to start recording stock'),
    ).toHaveCount(0);

    // Team invitations open after minimum setup and can be skipped.
    await page.getByRole('link', { name: 'Setup', exact: true }).click();
    await expect(page).toHaveURL(/\/onboarding/);
    await page.goto('/onboarding?step=team');
    await page.getByRole('button', { name: 'Skip for now' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(
        page.getByRole('link', { name: 'Setup', exact: true }),
    ).toHaveCount(0);
});
