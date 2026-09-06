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
        return $this->respond(fn (): array => $login->handle($this->user($request)));
    }

    public function complete(Request $request, CompleteCodexDeviceCodeLogin $login): JsonResponse
    {
        return $this->respond(function () use ($request, $login): array {
            $connection = $login->handle($this->user($request));

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
     * @return array<string, mixed>
     */
    private function safeRateLimits(array $rateLimits): array
    {
        return array_intersect_key($rateLimits, array_flip(['rateLimits', 'credits']));
    }
}
