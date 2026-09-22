<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deployment/CI gate for POC-V10.4 and the POC-V10.7 production activation
 * checklist. Fails closed (non-zero exit) when session/cookie/debug/URL
 * configuration would weaken the privileged platform boundary. Reports only
 * setting names, never the actual configured secret/value.
 */
class VerifyPlatformSecurityConfiguration extends Command
{
    protected $signature = 'platform:verify-security-config';

    protected $description = 'Fail closed if production session/cookie/debug/URL configuration is unsafe for the platform console.';

    public function handle(): int
    {
        $problems = $this->collectProblems();

        if ($problems !== []) {
            $this->components->error(
                'Platform security configuration is unsafe for production:',
            );

            foreach ($problems as $problem) {
                $this->components->bulletList([$problem]);
            }

            return self::FAILURE;
        }

        $this->components->info(
            'Platform security configuration passed all production checks.',
        );

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function collectProblems(): array
    {
        $problems = [];

        if ((bool) config('app.debug')) {
            $problems[] = 'APP_DEBUG must be false in production.';
        }

        if (! (bool) config('session.secure')) {
            $problems[] = 'SESSION_SECURE_COOKIE must be true in production.';
        }

        if (! (bool) config('session.http_only')) {
            $problems[] = 'SESSION_HTTP_ONLY must be true in production.';
        }

        if (! in_array(config('session.same_site'), ['lax', 'strict'], true)) {
            $problems[] = "SESSION_SAME_SITE must be 'lax' or 'strict' in production.";
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $problems[] = 'APP_URL must use https in production.';
        }

        return $problems;
    }
}
