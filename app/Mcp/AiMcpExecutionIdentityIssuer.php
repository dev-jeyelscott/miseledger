<?php

namespace App\Mcp;

use App\Models\AiRun;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Issues a short-lived, HMAC-authenticated identity for one worker-launched
 * local MCP process. This token is intentionally not an MCP tool argument.
 */
final class AiMcpExecutionIdentityIssuer
{
    public const int MAX_LIFETIME_SECONDS = 300;

    public function issue(AiRun $run, ?DateTimeInterface $expiresAt = null): string
    {
        $expiresAt = Carbon::instance($expiresAt ?? now()->addSeconds(self::MAX_LIFETIME_SECONDS));

        if ($expiresAt->lessThanOrEqualTo(now()) || $expiresAt->greaterThan(now()->addSeconds(self::MAX_LIFETIME_SECONDS))) {
            throw new \InvalidArgumentException('The AI execution identity expiry is invalid.');
        }

        $conversation = $run->conversation;

        $payload = json_encode([
            'version' => 1,
            'ai_run_id' => $run->id,
            'user_id' => $run->user_id,
            'organization_id' => $conversation->organization_id,
            'expires_at' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        $encodedPayload = Str::toBase64($payload);
        $signature = hash_hmac('sha256', $encodedPayload, (string) config('app.key'));

        return $encodedPayload.'.'.$signature;
    }
}
