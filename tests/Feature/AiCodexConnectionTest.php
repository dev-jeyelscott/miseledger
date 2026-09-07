<?php

use App\Actions\Ai\ActivateAiProviderConnection;
use App\Enums\AiProviderErrorCode;
use App\Exceptions\AiProviderException;
use App\Jobs\AwaitCodexDeviceCodeLogin;
use App\Models\AiConversation;
use App\Models\AiProviderConnection;
use App\Models\User;
use App\Support\Ai\Providers\AiProviderAdapter;
use App\Support\Ai\Providers\AiProviderTurn;
use App\Support\Ai\Providers\CodexProfileLocator;
use Illuminate\Support\Facades\Queue;

/**
 * The login job runs on the isolated `ai` queue connection (a dedicated
 * sandboxed worker), not the connection Testbench's sync override targets,
 * so tests fake the dispatch and invoke the job body directly to simulate
 * that worker picking it up.
 */
function runQueuedCodexLogin(User $user): void
{
    Queue::assertPushed(AwaitCodexDeviceCodeLogin::class, function (AwaitCodexDeviceCodeLogin $job) use ($user): bool {
        expect($job->userId)->toBe($user->id);

        $job->handle(
            app(AiProviderAdapter::class),
            app(ActivateAiProviderConnection::class),
            app(CodexProfileLocator::class),
        );

        return true;
    });
}

beforeEach(function (): void {
    $this->fakeCodex = new class implements AiProviderAdapter
    {
        public bool $connected = false;

        public function account(User $user): array
        {
            return $this->connected
                ? ['account' => ['type' => 'chatgpt', 'email' => 'user@example.test', 'planType' => 'plus']]
                : ['account' => null, 'requiresOpenaiAuth' => true];
        }

        public function runDeviceCodeLogin(User $user, callable $onStarted): bool
        {
            $onStarted([
                'login_id' => 'login_123',
                'verification_url' => 'https://auth.openai.example/device',
                'user_code' => 'ABCD-1234',
            ]);

            $this->connected = true;

            return true;
        }

        public function logout(User $user): void
        {
            $this->connected = false;
        }

        public function rateLimits(User $user): array
        {
            return ['rateLimits' => ['primary' => ['usedPercent' => 20]], 'accessToken' => 'never-expose'];
        }

        public function startThread(User $user, AiConversation $conversation, string $mcpExecutionIdentity): string
        {
            return 'thread_123';
        }

        public function resumeThread(User $user, AiConversation $conversation, string $mcpExecutionIdentity): string
        {
            return 'thread_123';
        }

        public function startTurn(User $user, string $threadId, string $input): AiProviderTurn
        {
            return new AiProviderTurn('turn_123', 'Inventory is stable.');
        }
    };

    app()->instance(AiProviderAdapter::class, $this->fakeCodex);
});

test('a verified user can complete managed device login without persisting credentials', function () {
    Queue::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->postJson(route('ai.codex.login.start'))
        ->assertSuccessful()
        ->assertExactJson(['status' => 'starting']);

    runQueuedCodexLogin($user);

    $this->actingAs($user)
        ->getJson(route('ai.codex.login.status'))
        ->assertSuccessful()
        ->assertJsonPath('status', 'connected')
        ->assertJsonMissing(['access_token', 'refresh_token', 'oauth_token', 'api_key']);

    $connection = AiProviderConnection::query()->forUser($user)->active()->sole();

    expect($connection->metadata)
        ->toBe(['account_type' => 'plus', 'runtime_profile_ref' => app(CodexProfileLocator::class)->reference($user)])
        ->and(json_encode($connection->getAttributes(), JSON_THROW_ON_ERROR))
        ->not->toContain('token');
});

test('a Codex runtime profile reference is unique and non-reversible between users', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $profiles = app(CodexProfileLocator::class);

    expect($profiles->reference($first))
        ->not->toBe($profiles->reference($second))
        ->toHaveLength(64);
});

test('rate limits expose only safe provider state and logout deactivates the users connection', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->fakeCodex->connected = true;

    $this->actingAs($user)->postJson(route('ai.codex.login.start'))->assertSuccessful();

    $this->actingAs($user)
        ->getJson(route('ai.codex.rate-limits.show'))
        ->assertSuccessful()
        ->assertJsonPath('rate_limits.rateLimits.primary.usedPercent', 20)
        ->assertJsonMissing(['accessToken']);

    $this->actingAs($user)
        ->deleteJson(route('ai.codex.connection.destroy'))
        ->assertSuccessful()
        ->assertJsonPath('connected', false);

    expect(AiProviderConnection::query()->forUser($user)->active()->exists())->toBeFalse();
});

test('provider failures are stable browser-safe codes', function () {
    Queue::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);
    app()->instance(AiProviderAdapter::class, new class implements AiProviderAdapter
    {
        public function account(User $user): array
        {
            throw new AiProviderException(AiProviderErrorCode::Unavailable);
        }

        public function runDeviceCodeLogin(User $user, callable $onStarted): bool
        {
            throw new AiProviderException(AiProviderErrorCode::Unavailable);
        }

        public function logout(User $user): void
        {
            throw new AiProviderException(AiProviderErrorCode::Unavailable);
        }

        public function rateLimits(User $user): array
        {
            throw new AiProviderException(AiProviderErrorCode::Unavailable);
        }

        public function startThread(User $user, AiConversation $conversation, string $mcpExecutionIdentity): string
        {
            throw new AiProviderException(AiProviderErrorCode::Unavailable);
        }

        public function resumeThread(User $user, AiConversation $conversation, string $mcpExecutionIdentity): string
        {
            throw new AiProviderException(AiProviderErrorCode::Unavailable);
        }

        public function startTurn(User $user, string $threadId, string $input): AiProviderTurn
        {
            throw new AiProviderException(AiProviderErrorCode::Unavailable);
        }
    });

    $this->actingAs($user)
        ->postJson(route('ai.codex.login.start'))
        ->assertSuccessful()
        ->assertExactJson(['status' => 'starting']);

    // The login job absorbs provider failures into the polled status rather
    // than failing the dispatching request, since it keeps running long
    // after the request that started it returns.
    runQueuedCodexLogin($user);

    $this->actingAs($user)
        ->getJson(route('ai.codex.login.status'))
        ->assertSuccessful()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('error_code', 'provider_unavailable');
});
