<?php

namespace App\Actions\Ai;

use App\Jobs\AwaitCodexDeviceCodeLogin;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class StartCodexDeviceCodeLogin
{
    public function handle(User $user): void
    {
        Cache::put(AwaitCodexDeviceCodeLogin::cacheKey($user), ['status' => 'starting'], now()->addMinutes(20));

        // Device-code polling can wait for up to fifteen minutes. Keep it
        // off the interactive chat queue so it cannot block AI responses.
        AwaitCodexDeviceCodeLogin::dispatch($user->getKey())
            ->onConnection('ai')
            ->onQueue('ai-login');
    }
}
