<?php

namespace App\Http\Controllers\Ai;

use App\Actions\Ai\CompleteCodexDeviceCodeLogin;
use App\Actions\Ai\LogoutCodexConnection;
use App\Actions\Ai\StartCodexDeviceCodeLogin;
use App\Exceptions\AiProviderException;
use App\Http\Controllers\Controller;
use App\Models\AiProviderConnection;
use App\Models\User;
use App\Support\Ai\Providers\AiProviderAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CodexConnectionController extends Controller
{
    public function show(Request $request, AiProviderAdapter $provider): JsonResponse
    {
        return $this->respond(function () use ($request, $provider): array {
            $user = $this->user($request);
            $account = $provider->account($user);

            return [
                'connected' => is_array($account['account'] ?? null)
                    && ($account['account']['type'] ?? null) === 'chatgpt',
                'connection' => AiProviderConnection::query()->forUser($user)->active()->first(['id', 'account_label', 'metadata']),
            ];
        });
    }

    public function start(Request $request, StartCodexDeviceCodeLogin $login): JsonResponse
    {
        return $this->respond(function () use ($request, $login): array {
            $details = $login->handle($this->user($request));
            $request->session()->put('ai.codex.login_id', $details['login_id']);

            return $details;
        });
    }

    public function complete(Request $request, CompleteCodexDeviceCodeLogin $login): JsonResponse
    {
        return $this->respond(function () use ($request, $login): array {
            $connection = $login->handle(
                $this->user($request),
                (string) $request->session()->get('ai.codex.login_id', ''),
            );
            $request->session()->forget('ai.codex.login_id');

            return [
                'connected' => true,
                'connection_id' => $connection->id,
                'account_label' => $connection->account_label,
            ];
        });
    }

    public function destroy(Request $request, LogoutCodexConnection $logout): JsonResponse
    {
        return $this->respond(function () use ($request, $logout): array {
            $logout->handle($this->user($request));

            return ['connected' => false];
        });
    }

    public function rateLimits(Request $request, AiProviderAdapter $provider): JsonResponse
    {
        return $this->respond(fn (): array => ['rate_limits' => $this->safeRateLimits($provider->rateLimits($this->user($request)))]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @param callable(): array<string, mixed> $callback */
    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (AiProviderException $exception) {
            return response()->json(['error_code' => $exception->errorCode->value], 422);
        }
    }

    /** @param array<string, mixed> $rateLimits
     * @return array{rateLimits: array<string, mixed>}
     */
    private function safeRateLimits(array $rateLimits): array
    {
        $snapshot = $rateLimits['rateLimits'] ?? null;

        if (! is_array($snapshot)) {
            return ['rateLimits' => []];
        }

        $safe = [];

        foreach (['primary', 'secondary'] as $window) {
            $value = $snapshot[$window] ?? null;

            if (is_array($value)) {
                $safeWindow = array_filter(
                    [
                        'usedPercent' => $value['usedPercent'] ?? null,
                        'windowDurationMins' => $value['windowDurationMins'] ?? null,
                        'resetsAt' => $value['resetsAt'] ?? null,
                    ],
                    static fn (mixed $item): bool => is_int($item) || $item === null,
                );

                $safe[$window] = $safeWindow;
            }
        }

        foreach (['planType', 'rateLimitReachedType'] as $field) {
            if (is_string($snapshot[$field] ?? null) || ($snapshot[$field] ?? null) === null) {
                $safe[$field] = $snapshot[$field] ?? null;
            }
        }

        $credits = $snapshot['credits'] ?? null;

        if (is_array($credits)) {
            $safe['credits'] = array_filter(
                [
                    'hasCredits' => $credits['hasCredits'] ?? null,
                    'unlimited' => $credits['unlimited'] ?? null,
                    'balance' => $credits['balance'] ?? null,
                ],
                static fn (mixed $item): bool => is_bool($item)
                    || is_string($item)
                    || $item === null,
            );
        }

        return ['rateLimits' => $safe];
    }
}
