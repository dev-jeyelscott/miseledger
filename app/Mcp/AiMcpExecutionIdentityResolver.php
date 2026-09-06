<?php

namespace App\Mcp;

use App\Enums\AiRunStatus;
use App\Models\AiRun;
use App\Models\OrganizationMembership;
use App\Support\Billing\MemberAIAccessResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use JsonException;

/**
 * Re-resolves the signed execution identity on every tool discovery and call.
 * Durable records remain authoritative so a revoked membership or entitlement
 * takes effect without waiting for the signed credential to expire.
 */
final class AiMcpExecutionIdentityResolver
{
    public function __construct(
        private readonly AiMcpExecutionContext $context,
    ) {}

    public function resolve(): AiMcpExecutionIdentity
    {
        $claims = $this->claims($this->context->signedIdentity());

        $run = AiRun::query()
            ->with(['conversation.organization', 'user'])
            ->whereKey($claims['ai_run_id'])
            ->where('user_id', $claims['user_id'])
            ->whereIn('status', [AiRunStatus::Queued, AiRunStatus::Running])
            ->first();

        if ($run === null || $run->conversation->organization_id !== $claims['organization_id']) {
            throw new AuthorizationException('The AI execution identity is no longer valid.');
        }

        $membership = OrganizationMembership::query()
            ->where('organization_id', $run->conversation->organization_id)
            ->where('user_id', $run->user_id)
            ->first();

        $organization = $run->conversation->organization;

        if (! MemberAIAccessResolver::resolve($organization, $membership)->canUse()) {
            throw new AuthorizationException('AI access is not currently permitted.');
        }

        if ($membership === null) {
            throw new AuthorizationException('The AI execution identity is no longer a member of this organization.');
        }

        return new AiMcpExecutionIdentity(
            run: $run,
            user: $run->user,
            organization: $organization,
            membership: $membership,
        );
    }

    /**
     * @return array{ai_run_id: int, user_id: int, organization_id: int, expires_at: int}
     */
    private function claims(string $signedIdentity): array
    {
        [$encodedPayload, $signature] = array_pad(explode('.', $signedIdentity, 2), 2, null);

        if (! is_string($encodedPayload) || ! is_string($signature) || ! hash_equals(hash_hmac('sha256', $encodedPayload, (string) config('app.key')), $signature)) {
            throw new AuthorizationException('The AI execution identity signature is invalid.');
        }

        try {
            $payload = json_decode(base64_decode($encodedPayload, true) ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AuthorizationException('The AI execution identity payload is invalid.');
        }

        if (! is_array($payload)
            || ($payload['version'] ?? null) !== 1
            || ! is_int($payload['ai_run_id'] ?? null)
            || ! is_int($payload['user_id'] ?? null)
            || ! is_int($payload['organization_id'] ?? null)
            || ! is_int($payload['expires_at'] ?? null)
            || Carbon::createFromTimestamp($payload['expires_at'])->lessThanOrEqualTo(now())) {
            throw new AuthorizationException('The AI execution identity is invalid or expired.');
        }

        return $payload;
    }
}
