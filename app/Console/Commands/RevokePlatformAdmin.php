<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Console\Command;

class RevokePlatformAdmin extends Command
{
    protected $signature = 'platform-admin:revoke {email : Existing MiseLedger user email address}';

    protected $description = 'Revoke platform console access from an existing MiseLedger user.';

    /**
     * Revoke the identity-level platform administrator capability idempotently.
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

        $deleted = PlatformAdmin::query()
            ->where('user_id', $user->getKey())
            ->delete();

        if ($deleted === 0) {
            $this->components->info(
                "Platform administrator access is already revoked for [{$email}].",
            );

            return self::SUCCESS;
        }

        $this->components->info(
            "Platform administrator access revoked from [{$email}].",
        );

        return self::SUCCESS;
    }
}
