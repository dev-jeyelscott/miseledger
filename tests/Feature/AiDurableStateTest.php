<?php

use App\Actions\Ai\ActivateAiProviderConnection;
use App\Actions\Ai\DeleteAiConversation;
use App\Actions\Ai\RecordAiToolCall;
use App\Enums\AiMessageRole;
use App\Enums\AiProvider;
use App\Enums\AiRunStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiProviderConnection;
use App\Models\AiRun;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('a provider connection keeps only safe metadata and database enforces one active connection per user', function () {
    $user = User::factory()->create();

    $connection = app(ActivateAiProviderConnection::class)->handle(
        user: $user,
        provider: AiProvider::OpenAi,
        externalAccountId: 'acct_123',
        accountLabel: 'Operations workspace',
        metadata: [
            'account_type' => 'organization',
            'workspace_name' => 'MiseLedger',
            'scopes' => ['responses.write'],
            'access_token' => 'must-not-persist',
            'refresh_token' => 'must-not-persist',
        ],
    );

    expect($connection->metadata)->toBe([
        'account_type' => 'organization',
        'workspace_name' => 'MiseLedger',
        'scopes' => ['responses.write'],
    ]);

    DB::beginTransaction();

    try {
        AiProviderConnection::query()->create([
            'user_id' => $user->id,
            'provider' => AiProvider::OpenAi,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $this->fail('Expected the one-active-connection index to reject a duplicate.');
    } catch (QueryException) {
        DB::rollBack();

        expect(true)->toBeTrue();
    }

    expect(AiProviderConnection::query()->forUser($user)->active()->count())
        ->toBeOne();

    $columns = Schema::getColumnListing('ai_provider_connections');

    expect($columns)
        ->not->toContain('access_token')
        ->not->toContain('refresh_token')
        ->not->toContain('oauth_token')
        ->not->toContain('api_key');
});

test('conversations are private to a tenant member and deletion removes all user-visible content', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);
    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $otherUser->id,
    ]);

    $conversation = AiConversation::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
        'title' => 'Private purchasing question',
    ]);
    $message = AiMessage::factory()->for($conversation, 'conversation')->create([
        'role' => AiMessageRole::User,
        'content' => 'What was my last supplier price?',
    ]);
    $run = AiRun::factory()->for($conversation, 'conversation')->create([
        'status' => AiRunStatus::Succeeded,
    ]);
    $toolCall = app(RecordAiToolCall::class)->handle(
        run: $run,
        toolName: 'supplier_price_lookup',
        status: 'succeeded',
        providerToolCallId: 'call_123',
        metadata: [
            'resource_type' => 'supplier_item',
            'resource_id' => '42',
            'access_token' => 'must-not-persist',
        ],
    );

    expect(AiConversation::query()->ownedBy($organization, $owner)->sole()->id)
        ->toBe($conversation->id);
    expect(AiConversation::query()->ownedBy($organization, $otherUser)->exists())
        ->toBeFalse();
    expect($toolCall->metadata)->toBe([
        'resource_type' => 'supplier_item',
        'resource_id' => '42',
    ]);

    app(DeleteAiConversation::class)->handle(
        organization: $organization,
        user: $owner,
        conversation: $conversation,
    );

    $this->assertModelMissing($conversation);
    $this->assertModelMissing($message);
    $this->assertModelMissing($run);
    $this->assertModelMissing($toolCall);

    $auditLog = AuditLog::query()
        ->where('action', 'ai_conversation.deleted')
        ->sole();

    expect($auditLog->after_data)->toBe([
        'conversation_id' => $conversation->id,
    ]);
});

test('a conversation cannot be created for a user outside its organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    expect(fn () => AiConversation::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'title' => 'Cross-tenant attempt',
    ]))->toThrow(QueryException::class);
});

test('a run cannot use another users provider connection', function () {
    $organization = Organization::factory()->create();
    $conversationOwner = User::factory()->create();
    $connectionOwner = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $conversationOwner->id,
    ]);

    $conversation = AiConversation::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $conversationOwner->id,
    ]);
    $connection = AiProviderConnection::factory()->create([
        'user_id' => $connectionOwner->id,
    ]);

    DB::beginTransaction();

    try {
        AiRun::query()->create([
            'ai_conversation_id' => $conversation->id,
            'user_id' => $conversationOwner->id,
            'ai_provider_connection_id' => $connection->id,
            'ai_provider_connection_user_id' => $connectionOwner->id,
            'provider' => AiProvider::OpenAi,
            'status' => AiRunStatus::Queued,
        ]);

        $this->fail('Expected the provider-connection owner constraint to reject this run.');
    } catch (QueryException) {
        DB::rollBack();

        expect(true)->toBeTrue();
    }
});
