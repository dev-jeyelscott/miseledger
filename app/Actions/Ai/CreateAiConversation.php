<?php

namespace App\Actions\Ai;

use App\Models\AiConversation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Billing\MemberAIAccessResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class CreateAiConversation
{
    public function handle(Organization $organization, User $user): AiConversation
    {
        return DB::transaction(function () use ($organization, $user): AiConversation {
            $membership = OrganizationMembership::query()
                ->whereBelongsTo($organization)
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();

            if (! MemberAIAccessResolver::resolve($organization, $membership)->canUse()) {
                throw new AuthorizationException('AI access is not currently permitted.');
            }

            return AiConversation::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ]);
        });
    }
}
