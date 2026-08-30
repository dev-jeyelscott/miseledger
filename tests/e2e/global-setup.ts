import { execSync } from 'node:child_process';

/**
 * Reset the isolated E2E test database and seed a single deterministic
 * organization owner. Runs `php artisan` against APP_ENV=testing so it
 * never touches development or production data.
 */
export default function globalSetup(): void {
    const env = { ...process.env, APP_ENV: 'testing' };

    execSync('php artisan migrate:fresh --force', {
        stdio: 'inherit',
        env,
    });

    execSync(
        'php artisan db:seed --class="Database\\\\Seeders\\\\E2ETestSeeder" --force',
        { stdio: 'inherit', env },
    );
}
