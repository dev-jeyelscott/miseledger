<?php

namespace App\Support\Ai\Providers;

use App\Models\AiConversation;
use App\Models\AiRun;
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

    /**
     * Starts a ChatGPT device-code login and blocks until Codex reports the
     * login completed, failed, or the login window elapsed. Codex only
     * persists exchanged credentials while its own process keeps polling in
     * the background, so this call keeps that process running for the whole
     * window instead of one-shotting a single request/response.
     *
     * $onStarted is invoked once, as soon as the verification URL and user
     * code are known, so a caller can surface them before login completes.
     *
     * @param  callable(array{login_id: string, verification_url: string, user_code: string}): void  $onStarted
     */
    public function runDeviceCodeLogin(User $user, callable $onStarted): bool;

    public function logout(User $user): void;

    /** @return array<string, mixed> */
    public function rateLimits(User $user): array;

    /**
     * Starts or resumes the conversation's thread and runs one turn on it,
     * in a single provider session. Codex only keeps thread and turn state
     * for the lifetime of the process that created them, so starting (or
     * resuming) the thread and starting the turn must not be split across
     * independent provider calls.
     *
     * $run is the durable attempt this turn belongs to. Implementations
     * must persist the provider thread id and an accepted turn id onto
     * $run as soon as the provider acknowledges them (before waiting on
     * the turn's completion), so a retry after a completion-wait timeout
     * can detect that the turn was already accepted instead of blindly
     * resubmitting it.
     */
    public function converse(User $user, AiConversation $conversation, AiRun $run, string $mcpExecutionIdentity, string $input): AiProviderTurn;
}
