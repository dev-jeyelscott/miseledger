<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiProviderConnection;
use App\Models\AiRun;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AiAssistantController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $data = $this->data($this->organization($request), $this->user($request), $request);

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return Inertia::render('ai/index', $data);
    }

    /** @return array{conversations: array<int, array{id: int, title: string|null, updated_at: string|null}>, conversation: array{id: int, title: string|null, messages: array<int, array{id: int, role: string, content: string, created_at: string|null}>, runs: array<int, array{id: int, status: string, error_code: string|null}>}|null, codex: array{connected: bool, accountLabel: string|null}} */
    private function data(Organization $organization, User $user, Request $request): array
    {
        $conversations = AiConversation::query()
            ->ownedBy($organization, $user)
            ->latest('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'updated_at']);

        $selectedId = $request->integer('conversation') ?: $conversations->first()?->id;
        $conversation = $selectedId === null ? null : AiConversation::query()
            ->ownedBy($organization, $user)
            ->with([
                'messages' => fn ($query) => $query->orderBy('sequence')->limit(100),
                'runs' => fn ($query) => $query->latest()->limit(25),
            ])
            ->find($selectedId);

        $connection = AiProviderConnection::query()
            ->forUser($user)
            ->active()
            ->first(['id', 'account_label']);

        return [
            'conversations' => $conversations->map(fn (AiConversation $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'updated_at' => $item->updated_at?->toIso8601String(),
            ])->values()->all(),
            'conversation' => $conversation === null ? null : [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'messages' => $conversation->messages->map(fn (AiMessage $message): array => [
                    'id' => $message->id,
                    'role' => $message->role->value,
                    'content' => $message->content,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])->values()->all(),
                'runs' => $conversation->runs->map(fn (AiRun $run): array => [
                    'id' => $run->id,
                    'status' => $run->status->value,
                    'error_code' => $run->error_code,
                ])->values()->all(),
            ],
            'codex' => [
                'connected' => $connection !== null,
                'accountLabel' => $connection?->account_label,
            ],
        ];
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
}
