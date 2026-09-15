<?php

namespace App\Console\Commands;

use App\Enums\PlatformAdminAuditAction;
use App\Models\PlatformAdmin;
use App\Models\PlatformAdminAuditEvent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevokePlatformAdmin extends Command
{
    protected $signature = 'platform-admin:revoke {email : Existing MiseLedger user email address} {--reason= : Reason for revoking platform console access}';

    protected $description = 'Revoke platform console access from an existing MiseLedger user.';

    /**
     * Revoke the identity-level platform administrator capability idempotently.
     */
    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $reason = trim((string) $this->option('reason'));

        if ($reason === '') {
            $this->components->error(
                'A non-blank --reason is required to revoke platform administrator access.',
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

        $wasRevoked = DB::transaction(function () use ($user, $reason): bool {
            $deleted = PlatformAdmin::query()
                ->where('user_id', $user->getKey())
                ->delete();

            if ($deleted === 0) {
                return false;
            }

            PlatformAdminAuditEvent::query()->create([
                'user_id' => $user->getKey(),
                'target_email' => $user->email,
                'action' => PlatformAdminAuditAction::Revoke,
                'actor_id' => null,
                'source' => $this->getName(),
                'reason' => $reason,
            ]);

            return true;
        });

        if (! $wasRevoked) {
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
