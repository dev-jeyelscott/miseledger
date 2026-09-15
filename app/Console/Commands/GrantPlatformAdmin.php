<?php

namespace App\Console\Commands;

use App\Enums\PlatformAdminAuditAction;
use App\Models\PlatformAdmin;
use App\Models\PlatformAdminAuditEvent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GrantPlatformAdmin extends Command
{
    protected $signature = 'platform-admin:grant {email : Existing MiseLedger user email address} {--reason= : Reason for granting platform console access}';

    protected $description = 'Grant platform console access to an existing MiseLedger user.';

    /**
     * Grant the identity-level platform administrator capability idempotently.
     */
    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $reason = trim((string) $this->option('reason'));

        if ($reason === '') {
            $this->components->error(
                'A non-blank --reason is required to grant platform administrator access.',
            );

            return self::FAILURE;
        }

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user instanceof User) {
            $this->components->error(
                "No existing MiseLedger user was found for email [{$email}].",
            );

            return self::FAILURE;
        }

        $wasGranted = DB::transaction(function () use ($user, $reason): bool {
            $now = now();

            $inserted = PlatformAdmin::query()->insertOrIgnore([
                'user_id' => $user->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === 0) {
                return false;
            }

            PlatformAdminAuditEvent::query()->create([
                'user_id' => $user->getKey(),
                'target_email' => $user->email,
                'action' => PlatformAdminAuditAction::Grant,
                'actor_id' => null,
                'source' => $this->getName(),
                'reason' => $reason,
            ]);

            return true;
        });

        if (! $wasGranted) {
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
