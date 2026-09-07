<?php

use App\Enums\OrganizationRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiProviderConnection;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

test('AI Assistant page exposes only the signed-in member conversation data and safe connection state', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $otherUser = User::factory()->create(['email_verified_at' => now()]);
    OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => OrganizationRole::Owner]);
    OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $otherUser->id, 'role' => OrganizationRole::Owner]);
    $conversation = AiConversation::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'title' => 'My inventory question']);
    AiConversation::factory()->create(['organization_id' => $organization->id, 'user_id' => $otherUser->id, 'title' => 'Private conversation']);
    AiMessage::factory()->create(['ai_conversation_id' => $conversation->id, 'content' => 'What stock is low?']);
    AiProviderConnection::factory()->create(['user_id' => $user->id, 'account_label' => 'MiseLedger user']);

    $this->actingAs($user)
        ->withSession(['active_organization_id' => $organization->id])
        ->get(route('ai.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/index')
            ->has('conversations', 1)
            ->where('conversations.0.title', 'My inventory question')
            ->where('codex.connected', true)
            ->where('codex.accountLabel', 'MiseLedger user')
            ->missing('codex.metadata'));
});

test('AI capability context provides an actionable server reason when member access is disabled', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);
    OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => OrganizationRole::InventoryStaff, 'ai_enabled' => false]);

    $this->actingAs($user)
        ->withSession(['active_organization_id' => $organization->id])
        ->get(route('ai.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('organizationContext.ai.canUse', false)
            ->where('organizationContext.ai.reason', 'member_access_disabled'));
});

test('the persistent AI drawer refreshes its own server model after mutations and while a run is active', function (): void {
    $drawer = File::get(resource_path('js/components/ai-assistant-drawer.tsx'));
    $assistant = File::get(resource_path('js/components/ai-assistant.tsx'));

    expect($drawer)
        ->toContain('const refresh = useCallback((): void =>')
        ->toContain('(conversationId: number | null): void =>')
        ->toContain('window.setInterval(refresh, 2_000)')
        ->toContain('assistantData?.organizationId === activeOrganizationId')
        ->toContain('onConversationChange={selectConversation}')
        ->toContain('onRefresh={refresh}');

    expect($assistant)
        ->toContain('onConversationChange?: (conversationId: number | null) => void;')
        ->toContain('onRefresh?: () => void;')
        ->toContain('function refreshAssistant(): void')
        ->toContain('onSuccess: () => selectConversation(null)')
        ->toContain('onSuccess: refreshAssistant');
});
