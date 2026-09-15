import { execSync } from 'node:child_process';

/**
 * Reset the isolated E2E test database and seed a single deterministic
 * organization owner. `APP_ENV=testing` alone does NOT redirect the
 * database — there is no .env.testing file, so Laravel falls back to the
 * plain .env and DB_DATABASE would resolve to the real dev database.
 * DB_DATABASE must be forced explicitly, matching phpunit.xml's value.
 */
export default function globalSetup(): void {
    const env = {
        ...process.env,
        APP_ENV: 'testing',
        DB_DATABASE: 'miseledger_test',
    };

    execSync('php artisan migrate:fresh --force', {
        stdio: 'inherit',
        env,
    });

    execSync(
        'php artisan db:seed --class="Database\\\\Seeders\\\\E2ETestSeeder" --force',
        { stdio: 'inherit', env },
    );
}
