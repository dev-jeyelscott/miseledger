<?php

use App\Enums\AiProviderErrorCode;
use App\Exceptions\AiProviderException;
use App\Models\AiConversation;
use App\Models\User;
use App\Support\Ai\Providers\CodexAppServerProvider;
use App\Support\Ai\Providers\CodexJsonRpcClient;
use App\Support\Ai\Providers\CodexProfileLocator;

beforeEach(function (): void {
    config()->set('ai.codex.command', [PHP_BINARY, base_path('tests/Fixtures/Ai/fake-codex-app-server.php')]);
    config()->set('ai.codex.profile_root', sys_get_temp_dir().'/miseledger-codex-app-server-tests-'.bin2hex(random_bytes(8)));
});

test('the Codex provider uses documented direct stdio JSON-RPC for connection and conversation operations', function () {
    $conversation = AiConversation::factory()->create();
    $user = $conversation->user;
    $provider = app(CodexAppServerProvider::class);

    expect($provider->account($user)['account']['type'])->toBe('chatgpt')
        ->and($provider->startDeviceCodeLogin($user))->toBe([
            'login_id' => 'login_123',
            'verification_url' => 'https://auth.openai.example/device',
            'user_code' => 'ABCD-1234',
        ]);

    $provider->logout($user);

    expect($provider->rateLimits($user)['rateLimits']['primary']['usedPercent'])->toBe(20)
        ->and($provider->startThread($user, $conversation))->toBe('thr_123');

    $conversation->forceFill(['provider_thread_id' => 'thr_123']);

    expect($provider->resumeThread($user, $conversation))->toBe('thr_123')
        ->and($provider->startTurn($user, 'thr_123', 'Summarize today.'))->toBe('turn_123');

    $profilePath = app(CodexProfileLocator::class)->path($user);
    $transcripts = array_map(
        static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
        array_filter(file($profilePath.'/protocol.jsonl', FILE_IGNORE_NEW_LINES) ?: []),
    );

    expect($transcripts)->toHaveCount(7);

    foreach ($transcripts as $transcript) {
        expect($transcript[0])
            ->toMatchArray(['id' => 1, 'method' => 'initialize'])
            ->not->toHaveKey('jsonrpc')
            ->and($transcript[1])->toBe(['method' => 'initialized', 'params' => []]);
    }

    expect(array_column(array_column($transcripts, 2), 'method'))->toBe([
        'account/read',
        'account/login/start',
        'account/logout',
        'account/rateLimits/read',
        'thread/start',
        'thread/resume',
        'turn/start',
    ]);
});

test('the Codex JSON-RPC client maps provider errors without exposing provider details', function () {
    $user = User::factory()->create();

    expect(fn (): array => app(CodexJsonRpcClient::class)->call($user, 'test/rate-limit'))
        ->toThrow(AiProviderException::class, 'The AI provider could not complete the request.');

    try {
        app(CodexJsonRpcClient::class)->call($user, 'test/rate-limit');
    } catch (AiProviderException $exception) {
        expect($exception->errorCode)->toBe(AiProviderErrorCode::RateLimited);
    }
});
