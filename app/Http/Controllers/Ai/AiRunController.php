<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiRun;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AiRunController extends Controller
{
    public function show(Request $request, AiRun $run): JsonResponse
    {
        $organization = $request->attributes->get('activeOrganization');
        $user = $request->user();
        abort_unless($organization instanceof Organization && $user instanceof User, 404);

        $conversation = $run->conversation()->ownedBy($organization, $user)->firstOrFail();
        $run = AiRun::query()
            ->forConversation($conversation)
            ->whereKey($run->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return response()->json(['run' => [
            'id' => $run->id,
            'status' => $run->status->value,
            'error_code' => $run->error_code,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
        ]]);
    }
}
