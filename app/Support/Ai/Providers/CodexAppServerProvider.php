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

    public function startThread(User $user, AiConversation $conversation, string $mcpExecutionIdentity): string
    {
        $this->assertConversationOwner($user, $conversation);

        $result = $this->client->call($user, 'thread/start', [
            'cwd' => (string) config('ai.codex.workspace_path'),
        ], $this->miseLedgerMcpConfig($mcpExecutionIdentity));
        $threadId = $this->threadId($result);

        $conversation->forceFill(['provider_thread_id' => $threadId])->save();

        return $threadId;
    }

    public function resumeThread(User $user, AiConversation $conversation, string $mcpExecutionIdentity): string
    {
        $this->assertConversationOwner($user, $conversation);

        if (! is_string($conversation->provider_thread_id)) {
            return $this->startThread($user, $conversation, $mcpExecutionIdentity);
        }

        $result = $this->client->call($user, 'thread/resume', [
            'threadId' => $conversation->provider_thread_id,
        ], $this->miseLedgerMcpConfig($mcpExecutionIdentity));

        $threadId = $this->threadId($result);

        if ($threadId !== $conversation->provider_thread_id) {
            $conversation->forceFill(['provider_thread_id' => $threadId])->save();
        }

        return $threadId;
    }

    public function startTurn(User $user, string $threadId, string $input): AiProviderTurn
    {
        $result = $this->client->call($user, 'turn/start', [
            'threadId' => $threadId,
            'input' => [['type' => 'text', 'text' => $input]],
        ]);

        $turn = $result['turn'] ?? [];
        $turnId = $turn['id'] ?? null;

        if (! is_string($turnId)) {
            throw new AiProviderException(AiProviderErrorCode::Protocol);
        }

        $output = $this->output($turn);

        if ($output === null) {
            throw new AiProviderException(AiProviderErrorCode::Protocol);
        }

        $usage = $turn['usage'] ?? $result['usage'] ?? [];

        return new AiProviderTurn(
            id: $turnId,
            output: $output,
            model: is_string($turn['model'] ?? null) ? $turn['model'] : null,
            inputTokens: is_int($usage['inputTokens'] ?? null) ? $usage['inputTokens'] : null,
            outputTokens: is_int($usage['outputTokens'] ?? null) ? $usage['outputTokens'] : null,
        );
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

    private function assertConversationOwner(User $user, AiConversation $conversation): void
    {
        if ($conversation->user_id !== $user->getKey()) {
            throw new AiProviderException(AiProviderErrorCode::InvalidRequest);
        }
    }

    /** @return array<string, list<string>|string> */
    private function miseLedgerMcpConfig(string $mcpExecutionIdentity): array
    {
        return [
            'mcp_servers.miseledger.command' => PHP_BINARY,
            'mcp_servers.miseledger.args' => [base_path('artisan'), 'ai:mcp:start', '--execution-identity='.$mcpExecutionIdentity],
        ];
    }

    /** @param array<string, mixed> $turn */
    private function output(array $turn): ?string
    {
        foreach (['output', 'outputText', 'output_text'] as $field) {
            if (is_string($turn[$field] ?? null) && trim($turn[$field]) !== '') {
                return $turn[$field];
            }
        }

        $items = $turn['items'] ?? null;

        if (! is_array($items)) {
            return null;
        }

        $output = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! in_array($item['type'] ?? null, ['agentMessage', 'agent_message'], true)) {
                continue;
            }

            $text = $item['text'] ?? null;

            if (is_string($text) && trim($text) !== '') {
                $output[] = $text;
            }
        }

        return $output === [] ? null : implode("\n\n", $output);
    }
}
