<?php

use App\Actions\Ai\ExecuteAiRun;
use App\Actions\Ai\QueueAiMessage;
use App\Enums\AiProviderErrorCode;
use App\Enums\AiRunStatus;
use App\Enums\OrganizationRole;
use App\Exceptions\AiProviderException;
use App\Models\AiConversation;
use App\Models\AiProviderConnection;
use App\Models\AiRun;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Ai\Providers\CodexAppServerProvider;
use App\Support\Ai\Providers\CodexJsonRpcClient;
use App\Support\Ai\Providers\CodexProfileLocator;

beforeEach(function (): void {
    config()->set('ai.codex.command', [PHP_BINARY, base_path('tests/Fixtures/Ai/fake-codex-app-server.php')]);
    config()->set('ai.codex.profile_root', sys_get_temp_dir().'/miseledger-codex-app-server-tests-'.bin2hex(random_bytes(8)));
    config()->set('ai.codex.workspace_path', sys_get_temp_dir().'/miseledger-codex-workspace-tests-'.bin2hex(random_bytes(8)));
});

/**
 * The fixture logs one line per message, tagged by process id, as it
 * receives each one (rather than one line per process at the end) so a
 * killed process can't lose the tail of its own transcript. This groups
 * those lines back into one message list per process, in spawn order.
 *
 * @return list<list<array<string, mixed>>>
 */
function readCodexProtocolTranscripts(string $profilePath): array
{
    $lines = array_map(
        static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
        array_filter(file($profilePath.'/protocol.jsonl', FILE_IGNORE_NEW_LINES) ?: []),
    );

    $byPid = [];

    foreach ($lines as $line) {
        $byPid[$line['pid']][$line['seq']] = $line['message'];
    }

    return array_values(array_map(static function (array $bySeq): array {
        ksort($bySeq);

        return array_values($bySeq);
    }, $byPid));
}

/** @return list<list<string>> */
function readCodexCommands(string $profilePath): array
{
    return array_map(
        static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR)['argv'],
        array_filter(file($profilePath.'/command.jsonl', FILE_IGNORE_NEW_LINES) ?: []),
    );
}

test('the Codex provider uses documented direct stdio JSON-RPC for connection and conversation operations', function () {
    $conversation = AiConversation::factory()->create();
    $user = $conversation->user;
    $provider = app(CodexAppServerProvider::class);

    $startedDetails = null;

    expect($provider->account($user)['account']['type'])->toBe('chatgpt')
        ->and($provider->runDeviceCodeLogin($user, function (array $details) use (&$startedDetails): void {
            $startedDetails = $details;
        }))->toBeTrue();

    expect($startedDetails)->toBe([
        'login_id' => 'login_123',
        'verification_url' => 'https://auth.openai.example/device',
        'user_code' => 'ABCD-1234',
    ]);

    $provider->logout($user);

    expect($provider->rateLimits($user)['rateLimits']['primary']['usedPercent'])->toBe(20);

    $firstRun = AiRun::factory()->for($conversation, 'conversation')->create();
    $firstTurn = $provider->converse($user, $conversation, $firstRun, 'signed-identity', 'Summarize today.');

    expect($conversation->refresh()->provider_thread_id)->toBe('thr_123')
        ->and($firstTurn->id)->toBe('turn_123')
        ->and($firstRun->refresh()->accepted_provider_turn_id)->toBe('turn_123');

    $secondRun = AiRun::factory()->for($conversation, 'conversation')->create();
    $secondTurn = $provider->converse($user, $conversation, $secondRun, 'signed-identity', 'And tomorrow?');

    expect($conversation->refresh()->provider_thread_id)->toBe('thr_123')
        ->and($secondTurn->id)->toBe('turn_123');

    $profilePath = app(CodexProfileLocator::class)->path($user);
    $transcripts = readCodexProtocolTranscripts($profilePath);

    // account/read, account/login/start, account/logout, account/rateLimits/read
    // each open their own process; the two converse() calls each run their
    // thread setup and turn/start together in one shared process/session,
    // since Codex only keeps thread and turn state within one process.
    expect($transcripts)->toHaveCount(6);

    foreach ($transcripts as $transcript) {
        expect($transcript[0])
            ->toMatchArray(['id' => 1, 'method' => 'initialize'])
            ->not->toHaveKey('jsonrpc')
            ->and($transcript[1])->toBe(['method' => 'initialized', 'params' => []]);
    }

    expect(array_map(static fn (array $transcript): array => array_column(array_slice($transcript, 2), 'method'), $transcripts))->toBe([
        ['account/read'],
        ['account/login/start'],
        ['account/logout'],
        ['account/rateLimits/read'],
        ['thread/start', 'turn/start'],
        ['thread/resume', 'turn/start'],
    ]);

    expect($transcripts[4][2]['params']['cwd'])->toBe(config('ai.codex.workspace_path'))
        ->and($transcripts[4][3]['params']['threadId'])->toBe('thr_123')
        ->and($transcripts[4][3]['params']['sandboxPolicy'])->toBe([
            'type' => 'readOnly',
            'networkAccess' => true,
        ])
        ->and($transcripts[5][2]['params']['threadId'])->toBe('thr_123');

    $commands = readCodexCommands($profilePath);

    expect($commands[4])->toContain('--config')
        ->and(implode(' ', $commands[4]))->toContain('mcp_servers.miseledger.command')
        ->and(implode(' ', $commands[4]))->toContain('--execution-identity=signed-identity');
    expect(trim((string) file_get_contents($profilePath.'/workspace')))->toBe(config('ai.codex.workspace_path'));
});

test('the Codex client uses a distinct provider-owned profile for each user', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $client = app(CodexJsonRpcClient::class);
    $profiles = app(CodexProfileLocator::class);

    $client->call($first, 'account/read');
    $client->call($second, 'account/read');

    expect($profiles->path($first))->not->toBe($profiles->path($second))
        ->and(readCodexProtocolTranscripts($profiles->path($first)))->toHaveCount(1)
        ->and(readCodexProtocolTranscripts($profiles->path($second)))->toHaveCount(1);
});

test('a user cannot bind another users conversation to their Codex profile', function () {
    $conversation = AiConversation::factory()->create();
    $otherUser = User::factory()->create();
    $run = AiRun::factory()->for($conversation, 'conversation')->create();

    expect(fn () => app(CodexAppServerProvider::class)->converse($otherUser, $conversation, $run, 'signed-identity', 'hi'))
        ->toThrow(AiProviderException::class);
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

test('a timeout awaiting turn completion after Codex already accepted the turn does not resubmit the same run on retry', function () {
    config()->set('ai.codex.timeout_seconds', 1);

    $organization = Organization::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);
    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => OrganizationRole::Owner,
    ]);
    AiProviderConnection::factory()->create(['user_id' => $user->id]);
    $conversation = AiConversation::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    $run = app(QueueAiMessage::class)->handle(
        $organization,
        $user,
        $conversation,
        'SIMULATE_ACCEPTANCE_TIMEOUT',
    )['run'];

    app(ExecuteAiRun::class)->handle($run->id);

    expect($run->refresh())
        ->status->toBe(AiRunStatus::Failed)
        ->error_code->toBe('provider_turn_ambiguous')
        ->accepted_provider_turn_id->toBe('turn_123');

    // A queue retry of the same durable run must not resubmit the turn: the
    // run is already terminal, so a second handle() call (standing in for
    // the job's next attempt) must not talk to the provider again at all.
    app(ExecuteAiRun::class)->handle($run->id);

    $profilePath = app(CodexProfileLocator::class)->path($user);
    $transcripts = readCodexProtocolTranscripts($profilePath);

    expect($transcripts)->toHaveCount(1)
        ->and(array_column(array_slice($transcripts[0], 2), 'method'))->toBe(['thread/start', 'turn/start']);
});
