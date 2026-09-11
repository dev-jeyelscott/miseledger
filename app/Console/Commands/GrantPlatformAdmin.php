<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Console\Command;

class GrantPlatformAdmin extends Command
{
    protected $signature = 'platform-admin:grant {email : Existing MiseLedger user email address}';

    protected $description = 'Grant platform console access to an existing MiseLedger user.';

    /**
     * Grant the identity-level platform administrator capability idempotently.
     */
    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user instanceof User) {
            $this->components->error(
                "No existing MiseLedger user was found for email [{$email}].",
            );

            return self::FAILURE;
        }

        $now = now();

        $inserted = PlatformAdmin::query()->insertOrIgnore([
            'user_id' => $user->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            $this->components->info(
                "Platform administrator access is already granted to [{$email}].",
            );

            return self::SUCCESS;
        }

        $this->components->info(
            "Platform administrator access granted to [{$email}].",
        );

        return self::SUCCESS;
    }
}
