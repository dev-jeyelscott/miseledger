<?php

namespace App\Actions\Ai;

use App\Actions\Audit\RecordAuditEntry;
use App\Models\AiConversation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteAiConversation
{
    public function __construct(
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {}

    /**
     * Delete a conversation owned by the specified user and organization.
     * Database cascades permanently remove its messages, runs, and tool-call
     * metadata. The shared audit log retains no conversation content.
     */
    public function handle(
        Organization $organization,
        User $user,
        AiConversation $conversation,
    ): void {
        DB::transaction(function () use (
            $organization,
            $user,
            $conversation,
        ): void {
            $lockedConversation = AiConversation::query()
                ->ownedBy($organization, $user)
                ->whereKey($conversation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $conversationId = $lockedConversation->getKey();

            $lockedConversation->delete();

            $this->recordAuditEntry->handle(
                organization: $organization,
                actor: $user,
                action: 'ai_conversation.deleted',
                entityType: 'ai_conversation',
                entityId: $conversationId,
                beforeData: null,
                afterData: ['conversation_id' => $conversationId],
            );
        });
    }
}
