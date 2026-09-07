<?php

namespace App\Jobs;

use App\Actions\Ai\ActivateAiProviderConnection;
use App\Enums\AiProvider;
use App\Enums\AiProviderErrorCode;
use App\Exceptions\AiProviderException;
use App\Models\User;
use App\Support\Ai\Providers\AiProviderAdapter;
use App\Support\Ai\Providers\CodexProfileLocator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Codex only exchanges and persists device-code login credentials while its
 * CLI process keeps polling in the background, so this job keeps that
 * process alive for the whole login window instead of the request/response
 * cycle that dispatched it. Progress is reported through the cache so the
 * browser can poll {@see \App\Http\Controllers\Ai\CodexConnectionController::loginStatus()}.
 */
final class AwaitCodexDeviceCodeLogin implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 930;

    public function __construct(public readonly int $userId) {}

    public function handle(
        AiProviderAdapter $provider,
        ActivateAiProviderConnection $activateConnection,
        CodexProfileLocator $profiles,
    ): void {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $cacheKey = self::cacheKey($user);

        try {
            $success = $provider->runDeviceCodeLogin($user, function (array $details) use ($cacheKey): void {
                Cache::put($cacheKey, [
                    'status' => 'pending',
                    'verification_url' => $details['verification_url'],
                    'user_code' => $details['user_code'],
                ], now()->addMinutes(20));
            });
        } catch (AiProviderException $exception) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'error_code' => $exception->errorCode->value,
            ], now()->addMinutes(5));

            return;
        }

        if (! $success) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'error_code' => AiProviderErrorCode::LoginRequired->value,
            ], now()->addMinutes(5));

            return;
        }

        $account = $provider->account($user);
        $details = $account['account'] ?? null;
        $email = is_array($details) ? ($details['email'] ?? null) : null;
        $planType = is_array($details) ? ($details['planType'] ?? null) : null;

        $activateConnection->handle(
            user: $user,
            provider: AiProvider::OpenAi,
            externalAccountId: null,
            accountLabel: is_string($email) ? $email : null,
            metadata: [
                'account_type' => is_string($planType) ? $planType : 'unknown',
                'runtime_profile_ref' => $profiles->reference($user),
            ],
        );

        Cache::put($cacheKey, ['status' => 'connected'], now()->addMinutes(5));
    }

    public function failed(Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        Cache::put(self::cacheKey($user), [
            'status' => 'failed',
            'error_code' => $exception instanceof AiProviderException
                ? $exception->errorCode->value
                : AiProviderErrorCode::Unavailable->value,
        ], now()->addMinutes(5));
    }

    public static function cacheKey(User $user): string
    {
        return 'ai.codex.device-login.'.$user->getKey();
    }
}
