<?php

use App\Actions\Ai\ExecuteAiRun;
use App\Actions\Ai\QueueAiMessage;
use App\Enums\AiMessageRole;
use App\Enums\AiProvider;
use App\Enums\AiRunStatus;
use App\Enums\OrganizationRole;
use App\Jobs\ProcessAiRun;
use App\Models\AiConversation;
use App\Models\AiProviderConnection;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Ai\Providers\AiProviderAdapter;
use App\Support\Ai\Providers\AiProviderTurn;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->provider = new class implements AiProviderAdapter
    {
        public int $turns = 0;

        public function account(User $user): array
        {
            return [];
        }

        public function runDeviceCodeLogin(User $user, callable $onStarted): bool
        {
            return false;
        }

        public function logout(User $user): void {}

        public function rateLimits(User $user): array
        {
            return [];
        }

        public function converse(User $user, AiConversation $conversation, string $mcpExecutionIdentity, string $input): AiProviderTurn
        {
            $this->turns++;

            return new AiProviderTurn('turn_'.$this->turns, 'The current stock is available.', 'gpt-5', 8, 9);
        }
    };

    app()->instance(AiProviderAdapter::class, $this->provider);
});

function aiConversationOwner(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);
    OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => OrganizationRole::Owner]);
    AiProviderConnection::factory()->create(['user_id' => $user->id]);

    return [$organization, $user];
}

test('message submission durably persists only IDs in the queued job and exposes queued state', function () {
    [$organization, $user] = aiConversationOwner();
    Queue::fake();

    $conversation = $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
        ->postJson(route('ai.conversations.store'))->assertCreated()->json('conversation');

    $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
        ->postJson(route('ai.conversations.messages.store', $conversation['id']), ['content' => 'What is on hand?'])
        ->assertAccepted()->assertJsonPath('run.status', AiRunStatus::Queued->value);

    Queue::assertPushed(ProcessAiRun::class, fn (ProcessAiRun $job): bool => $job->runId > 0 && ! str_contains(serialize($job), 'access_token'));

    expect(AiConversation::query()->findOrFail($conversation['id'])->messages()->first()->role)->toBe(AiMessageRole::User);
});

test('a queued run rechecks access before provider execution', function () {
    [$organization, $user] = aiConversationOwner();
    $conversation = AiConversation::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $run = app(QueueAiMessage::class)->handle($organization, $user, $conversation, 'Check stock.')['run'];
    $organization->update(['active' => false]);

    app(ExecuteAiRun::class)->handle($run->id);

    expect($this->provider->turns)->toBe(0)->and($run->refresh()->status)->toBe(AiRunStatus::Failed)->and($run->error_code)->toBe('access_revoked');
});

test('replaying a completed run does not duplicate the assistant message', function () {
    [$organization, $user] = aiConversationOwner();
    $conversation = AiConversation::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $run = app(QueueAiMessage::class)->handle($organization, $user, $conversation, 'Check stock.')['run'];

    app(ExecuteAiRun::class)->handle($run->id);
    app(ExecuteAiRun::class)->handle($run->id);

    expect($this->provider->turns)->toBe(1)
        ->and($conversation->messages()->where('role', AiMessageRole::Assistant->value)->count())->toBe(1)
        ->and($run->refresh()->status)->toBe(AiRunStatus::Succeeded);
});

test('another organization member cannot submit to or poll a private conversation', function () {
    [$organization, $user] = aiConversationOwner();
    $otherUser = User::factory()->create(['email_verified_at' => now()]);
    OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $otherUser->id, 'role' => OrganizationRole::Owner]);
    $conversation = AiConversation::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $run = $conversation->runs()->create(['user_id' => $user->id, 'provider' => AiProvider::OpenAi, 'status' => AiRunStatus::Queued]);

    $this->actingAs($otherUser)->withSession(['active_organization_id' => $organization->id])
        ->postJson(route('ai.conversations.messages.store', $conversation), ['content' => 'Cross-user attempt.'])->assertNotFound();
    $this->actingAs($otherUser)->withSession(['active_organization_id' => $organization->id])
        ->getJson(route('ai.runs.show', $run))->assertNotFound();
});

test('message validation and polling expose stable browser-safe lifecycle state', function () {
    [$organization, $user] = aiConversationOwner();
    $conversation = AiConversation::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $run = $conversation->runs()->create(['user_id' => $user->id, 'provider' => AiProvider::OpenAi, 'status' => AiRunStatus::Failed, 'error_code' => 'provider_rate_limited', 'finished_at' => now()]);

    $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
        ->postJson(route('ai.conversations.messages.store', $conversation), ['content' => str_repeat('a', 12001)])->assertInvalid(['content']);
    $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
        ->getJson(route('ai.runs.show', $run))->assertSuccessful()->assertJsonPath('run.status', 'failed')->assertJsonPath('run.error_code', 'provider_rate_limited');
});
