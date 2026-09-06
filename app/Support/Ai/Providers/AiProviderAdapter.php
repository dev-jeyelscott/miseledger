<?php

namespace App\Support\Ai\Providers;

use App\Models\AiConversation;
use App\Models\User;

/**
 * Deliberately limited to MiseLedger's subscription-managed connection and
 * thread lifecycle. Provider credentials are always provider-owned runtime
 * state and are not accepted by this boundary.
 */
interface AiProviderAdapter
{
    /** @return array<string, mixed> */
    public function account(User $user): array;

    /** @return array{login_id: string, verification_url: string, user_code: string} */
    public function startDeviceCodeLogin(User $user): array;

    public function logout(User $user): void;

    /** @return array<string, mixed> */
    public function rateLimits(User $user): array;

    public function startThread(User $user, AiConversation $conversation): string;

    public function resumeThread(User $user, AiConversation $conversation): string;

    public function startTurn(User $user, string $threadId, string $input): string;
}
