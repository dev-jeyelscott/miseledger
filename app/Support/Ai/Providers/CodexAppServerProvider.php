<?php

namespace App\Support\Ai\Providers;

use App\Enums\AiProviderErrorCode;
use App\Exceptions\AiProviderException;
use App\Models\AiConversation;
use App\Models\User;

final class CodexAppServerProvider implements AiProviderAdapter
{
    /**
     * Codex does not let a local stdio MCP server inherit this process's
     * environment; only variables explicitly listed here are forwarded.
     * Without them, the artisan-booted miseledger MCP server has no
     * APP_KEY/DB/Redis configuration and crashes before the handshake
     * completes. Mirrors the framework env compose.yaml injects into the
     * ai-worker container (which ships without a bind-mounted .env).
     */
    private const array FORWARDED_ENV_VARS = [
        'APP_KEY',
        'APP_ENV',
        'APP_NAME',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'REDIS_HOST',
        'REDIS_PORT',
        'REDIS_PASSWORD',
        'CACHE_STORE',
    ];

    public function __construct(private readonly CodexJsonRpcClient $client) {}

    public function account(User $user): array
    {
        return $this->client->call($user, 'account/read', ['refreshToken' => true]);
    }

    public function runDeviceCodeLogin(User $user, callable $onStarted): bool
    {
        return $this->client->runDeviceCodeLogin(
            $user,
            $onStarted,
            (int) config('ai.codex.device_login_timeout_seconds'),
        );
    }

    public function logout(User $user): void
    {
        $this->client->call($user, 'account/logout');
    }

    public function rateLimits(User $user): array
    {
        return $this->client->call($user, 'account/rateLimits/read');
    }

    public function converse(User $user, AiConversation $conversation, string $mcpExecutionIdentity, string $input): AiProviderTurn
    {
        $this->assertConversationOwner($user, $conversation);

        $existingThreadId = $conversation->provider_thread_id;

        $results = $this->client->callSession($user, [
            static fn (): array => is_string($existingThreadId)
                ? ['thread/resume', ['threadId' => $existingThreadId]]
                : ['thread/start', ['cwd' => (string) config('ai.codex.workspace_path')]],
            // turn/start only acknowledges that the turn was accepted
            // (status "inProgress", no output yet); the actual result
            // arrives later as a `turn/completed` notification.
            fn (array $priorResults): array => ['turn/start', [
                'threadId' => $this->threadId($priorResults[0]),
                'input' => [['type' => 'text', 'text' => $input]],
                'sandboxPolicy' => [
                    'type' => 'readOnly',
                    'networkAccess' => true,
                ],
            ], 'turn/completed'],
        ], $this->miseLedgerMcpConfig($mcpExecutionIdentity));

        $threadId = $this->threadId($results[0]);

        if ($threadId !== $existingThreadId) {
            $conversation->forceFill(['provider_thread_id' => $threadId])->save();
        }

        $turn = $results[1]['turn'] ?? [];
        $turnId = $turn['id'] ?? null;

        if (! is_string($turnId)) {
            throw new AiProviderException(AiProviderErrorCode::Protocol);
        }

        if (($turn['status'] ?? null) === 'failed') {
            throw new AiProviderException($this->client->errorCodeForMessage((string) ($turn['error']['message'] ?? '')));
        }

        // A completed turn's own `items` stay lazily unloaded ("notLoaded"),
        // so the agent's reply is read from the `item/completed` events
        // streamed alongside the terminal notification instead.
        $output = $this->output($turn)
            ?? $this->outputFromItems($results[1]['streamedItems'] ?? null);

        if ($output === null) {
            throw new AiProviderException(AiProviderErrorCode::Protocol);
        }

        $usage = $turn['usage'] ?? $results[1]['usage'] ?? [];

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

    /** @return array<string, list<string>|string|int> */
    private function miseLedgerMcpConfig(string $mcpExecutionIdentity): array
    {
        // The miseledger MCP server is a full artisan bootstrap (autoload,
        // service providers, DB connection), not a prewarmed process, so it
        // can take longer to answer Codex's initial handshake than Codex's
        // own short built-in MCP startup/tool-call timeouts allow on a cold
        // worker. Codex silently drops the server's tools when that timeout
        // is hit, which surfaces to the user as "the tool isn't available"
        // even though the request/queue-level timeout (ai.codex.timeout_seconds)
        // never fires. Align both to the same budget we already wait on.
        $timeoutSeconds = (int) config('ai.codex.timeout_seconds');

        $config = [
            'mcp_servers.miseledger.command' => PHP_BINARY,
            'mcp_servers.miseledger.args' => [base_path('artisan'), 'ai:mcp:start', '--execution-identity='.$mcpExecutionIdentity],
            'mcp_servers.miseledger.startup_timeout_sec' => $timeoutSeconds,
            'mcp_servers.miseledger.tool_timeout_sec' => $timeoutSeconds,
        ];

        foreach (self::FORWARDED_ENV_VARS as $name) {
            $value = getenv($name);

            if ($value !== false) {
                $config["mcp_servers.miseledger.env.{$name}"] = $value;
            }
        }

        return $config;
    }

    /** @param array<string, mixed> $turn */
    private function output(array $turn): ?string
    {
        foreach (['output', 'outputText', 'output_text'] as $field) {
            if (is_string($turn[$field] ?? null) && trim($turn[$field]) !== '') {
                return $turn[$field];
            }
        }

        return $this->outputFromItems($turn['items'] ?? null);
    }

    private function outputFromItems(mixed $items): ?string
    {
        if (! is_array($items)) {
            return null;
        }

        $output = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! in_array($item['type'] ?? null, ['agentMessage', 'agent_message'], true)) {
                continue;
            }

            $text = $this->itemText($item);

            if (is_string($text) && trim($text) !== '') {
                $output[] = $text;
            }
        }

        return $output === [] ? null : implode("\n\n", $output);
    }

    /**
     * An item's text is either a flat `text` field, or nested one level
     * under `content` as one or more `{type: "text", text: "..."}` parts.
     *
     * @param  array<string, mixed>  $item
     */
    private function itemText(array $item): ?string
    {
        if (is_string($item['text'] ?? null)) {
            return $item['text'];
        }

        $content = $item['content'] ?? null;

        if (! is_array($content)) {
            return null;
        }

        $parts = [];

        foreach ($content as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $parts[] = $part['text'];
            }
        }

        return $parts === [] ? null : implode('', $parts);
    }
}
