<?php

namespace App\Actions\Ai;

use App\Enums\AiProvider;
use App\Enums\AiProviderErrorCode;
use App\Exceptions\AiProviderException;
use App\Models\AiProviderConnection;
use App\Models\User;
use App\Support\Ai\Providers\AiProviderAdapter;
use App\Support\Ai\Providers\CodexProfileLocator;

final class CompleteCodexDeviceCodeLogin
{
    public function __construct(
        private readonly ActivateAiProviderConnection $activateConnection,
        private readonly AiProviderAdapter $provider,
        private readonly CodexProfileLocator $profiles,
    ) {}

    public function handle(User $user): AiProviderConnection
    {
        $account = $this->provider->account($user);
        $details = $account['account'] ?? null;

        if (! is_array($details) || ($details['type'] ?? null) !== 'chatgpt') {
            throw new AiProviderException(AiProviderErrorCode::LoginRequired);
        }

        $planType = $details['planType'] ?? null;
        $email = $details['email'] ?? null;

        return $this->activateConnection->handle(
            user: $user,
            provider: AiProvider::OpenAi,
            externalAccountId: null,
            accountLabel: is_string($email) ? $email : null,
            metadata: [
                'account_type' => is_string($planType) ? $planType : 'unknown',
                'runtime_profile_ref' => $this->profiles->reference($user),
            ],
        );
    }
}
