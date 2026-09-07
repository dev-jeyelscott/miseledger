<?php

namespace App\Http\Controllers\Ai;

use App\Actions\Ai\CreateAiConversation;
use App\Actions\Ai\DeleteAiConversation;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AiConversationController extends Controller
{
    public function store(Request $request, CreateAiConversation $create): JsonResponse
    {
        $conversation = $create->handle($this->organization($request), $this->user($request));

        return response()->json(['conversation' => $this->conversation($conversation)], 201);
    }

    public function destroy(Request $request, AiConversation $conversation, DeleteAiConversation $delete): JsonResponse
    {
        $delete->handle($this->organization($request), $this->user($request), $conversation);

        return response()->json([], 204);
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('activeOrganization');
        abort_unless($organization instanceof Organization, 404);

        return $organization;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return array{id: int, title: string|null, created_at: string|null, updated_at: string|null} */
    private function conversation(AiConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'created_at' => $conversation->created_at?->toIso8601String(),
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }
}
