<?php

namespace App\Actions\Ai;

use App\Models\AiProviderConnection;
use App\Models\User;
use App\Support\Ai\Providers\AiProviderAdapter;
use Illuminate\Support\Facades\DB;

final class LogoutCodexConnection
{
    public function __construct(private readonly AiProviderAdapter $provider) {}

    public function handle(User $user): void
    {
        $this->provider->logout($user);

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
