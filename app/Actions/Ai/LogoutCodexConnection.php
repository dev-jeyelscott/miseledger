<?php

namespace App\Actions\Ai;

use App\Exceptions\AiProviderException;
use App\Models\AiProviderConnection;
use App\Models\User;
use App\Support\Ai\Providers\AiProviderAdapter;
use Illuminate\Support\Facades\DB;

final class LogoutCodexConnection
{
    public function __construct(private readonly AiProviderAdapter $provider) {}

    public function handle(User $user): void
    {
        // The connection must be deactivated locally even when the provider
        // session is already broken (e.g. an expired or revoked token) —
        // that's precisely the state a user is trying to disconnect from.
        try {
            $this->provider->logout($user);
        } catch (AiProviderException) {
            // ignore: local deactivation below still proceeds
        }

        DB::transaction(function () use ($user): void {
            AiProviderConnection::query()
                ->forUser($user)
                ->active()
                ->lockForUpdate()
                ->update([
                    'is_active' => false,
                    'deactivated_at' => now(),
                ]);
        });
    }
}
