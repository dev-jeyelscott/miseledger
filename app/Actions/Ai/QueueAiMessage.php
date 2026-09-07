<?php

namespace App\Actions\Ai;

use App\Enums\AiMessageRole;
use App\Enums\AiRunStatus;
use App\Jobs\ProcessAiRun;
use App\Models\AiConversation;
use App\Models\AiProviderConnection;
use App\Models\AiRun;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Billing\MemberAIAccessResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class QueueAiMessage
{
    /** @return array{message_id: int, run: AiRun} */
    public function handle(
        Organization $organization,
        User $user,
        AiConversation $conversation,
        string $content,
    ): array {
        $result = DB::transaction(function () use ($organization, $user, $conversation, $content): array {
            $lockedConversation = AiConversation::query()
                ->ownedBy($organization, $user)
                ->whereKey($conversation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $membership = OrganizationMembership::query()
                ->whereBelongsTo($organization)
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();

            if (! MemberAIAccessResolver::resolve($organization, $membership)->canUse()) {
                throw new AuthorizationException('AI access is not currently permitted.');
            }

            if ($lockedConversation->runs()
                ->whereIn('status', [AiRunStatus::Queued->value, AiRunStatus::Running->value])
                ->exists()) {
                throw ValidationException::withMessages([
                    'content' => __('Wait for the current AI response before sending another message.'),
                ]);
            }

            $connection = AiProviderConnection::query()
                ->forUser($user)
                ->active()
                ->lockForUpdate()
                ->firstOrFail();

            $nextSequence = (int) $lockedConversation->messages()
                ->max('sequence') + 1;

            $message = $lockedConversation->messages()->create([
                'role' => AiMessageRole::User,
                'sequence' => $nextSequence,
                'content' => $content,
            ]);

            if ($lockedConversation->title === null) {
                $lockedConversation->update(['title' => Str::limit($content, 160, '')]);
            }

            $run = $lockedConversation->runs()->create([
                'user_id' => $user->id,
                'ai_provider_connection_id' => $connection->id,
                'ai_provider_connection_user_id' => $user->id,
                'provider' => $connection->provider,
                'status' => AiRunStatus::Queued,
            ]);

            DB::afterCommit(fn (): mixed => ProcessAiRun::dispatch($run->id)->onConnection('ai'));

            return ['message_id' => $message->id, 'run' => $run];
        });

        return $result;
    }
}
