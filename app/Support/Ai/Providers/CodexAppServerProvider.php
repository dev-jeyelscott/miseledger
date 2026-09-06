<?php

namespace App\Support\Ai\Providers;

use App\Enums\AiProviderErrorCode;
use App\Exceptions\AiProviderException;
use App\Models\AiConversation;
use App\Models\User;

final class CodexAppServerProvider implements AiProviderAdapter
{
    public function __construct(private readonly CodexJsonRpcClient $client) {}

    public function account(User $user): array
    {
        return $this->client->call($user, 'account/read', ['refreshToken' => true]);
    }

    public function startDeviceCodeLogin(User $user): array
    {
        $result = $this->client->call($user, 'account/login/start', ['type' => 'chatgptDeviceCode']);

        if (($result['type'] ?? null) !== 'chatgptDeviceCode'
            || ! is_string($result['loginId'] ?? null)
            || ! is_string($result['verificationUrl'] ?? null)
            || ! is_string($result['userCode'] ?? null)) {
            throw new AiProviderException(AiProviderErrorCode::Protocol);
        }

        return [
            'login_id' => $result['loginId'],
            'verification_url' => $result['verificationUrl'],
            'user_code' => $result['userCode'],
        ];
    }

    public function logout(User $user): void
    {
        $this->client->call($user, 'account/logout');
    }

    public function rateLimits(User $user): array
    {
        return $this->client->call($user, 'account/rateLimits/read');
    }

    public function startThread(User $user, AiConversation $conversation): string
    {
        $result = $this->client->call($user, 'thread/start', ['cwd' => base_path()]);

        return $this->threadId($result);
    }

    public function resumeThread(User $user, AiConversation $conversation): string
    {
        if (! is_string($conversation->provider_thread_id)) {
            return $this->startThread($user, $conversation);
        }

        $result = $this->client->call($user, 'thread/resume', ['threadId' => $conversation->provider_thread_id]);

        return $this->threadId($result);
    }

    public function startTurn(User $user, string $threadId, string $input): string
    {
        $result = $this->client->call($user, 'turn/start', [
            'threadId' => $threadId,
            'input' => [['type' => 'text', 'text' => $input]],
        ]);

        $turnId = $result['turn']['id'] ?? null;

        if (! is_string($turnId)) {
            throw new AiProviderException(AiProviderErrorCode::Protocol);
        }

        return $turnId;
    }

    /** @param array<string, mixed> $result */
    private function threadId(array $result): string
    {
        $threadId = $result['thread']['id'] ?? null;

        if (! is_string($threadId)) {
            throw new AiProviderException(AiProviderErrorCode::Protocol);
        }

        return $threadId;
    }
}
