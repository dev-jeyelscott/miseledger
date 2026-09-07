<?php

namespace App\Http\Controllers\Ai;

use App\Actions\Ai\QueueAiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\StoreAiMessageRequest;
use App\Models\AiConversation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class AiMessageController extends Controller
{
    public function store(StoreAiMessageRequest $request, AiConversation $conversation, QueueAiMessage $queue): JsonResponse
    {
        $result = $queue->handle($this->organization($request), $this->user($request), $conversation, $request->validated('content'));

        return response()->json([
            'message_id' => $result['message_id'],
            'run' => [
                'id' => $result['run']->id,
                'status' => $result['run']->status->value,
                'error_code' => null,
            ],
        ], 202);
    }

    private function organization(StoreAiMessageRequest $request): Organization
    {
        $organization = $request->attributes->get('activeOrganization');
        abort_unless($organization instanceof Organization, 404);

        return $organization;
    }

    private function user(StoreAiMessageRequest $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
