<?php

namespace App\Actions\Ai;

use App\Models\User;
use App\Support\Ai\Providers\AiProviderAdapter;

final class StartCodexDeviceCodeLogin
{
    public function __construct(private readonly AiProviderAdapter $provider) {}

    /** @return array{login_id: string, verification_url: string, user_code: string} */
    public function handle(User $user): array
    {
        return $this->provider->startDeviceCodeLogin($user);
    }
}
