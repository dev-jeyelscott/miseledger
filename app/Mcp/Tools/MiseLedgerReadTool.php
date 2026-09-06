<?php

namespace App\Mcp\Tools;

use App\Actions\Ai\RecordAiToolCall;
use App\Enums\OrganizationPermission;
use App\Mcp\AiMcpExecutionIdentity;
use App\Mcp\AiMcpExecutionIdentityResolver;
use App\Support\Billing\OrganizationFeatureEntitlement;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Server\Tool;
use Throwable;

abstract class MiseLedgerReadTool extends Tool
{
    protected const int DEFAULT_LIMIT = 1000;

    protected const int MAX_LIMIT = 1000;

    protected const int MAX_DATE_RANGE_DAYS = 90;

    abstract protected function requiredPermission(): OrganizationPermission;

    protected function requiredFeature(): ?string
    {
        return null;
    }

    public function shouldRegister(AiMcpExecutionIdentityResolver $identityResolver): bool
    {
        try {
            $this->authorize($identityResolver);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function authorize(AiMcpExecutionIdentityResolver $identityResolver): AiMcpExecutionIdentity
    {
        $startedAt = hrtime(true);

        try {
            $identity = $identityResolver->resolve();

            if (! Gate::forUser($identity->user)->allows($this->requiredPermission()->value, $identity->organization)) {
                throw new AuthorizationException('You are not authorized to use this AI tool.');
            }

            $feature = $this->requiredFeature();

            if ($feature !== null && ! OrganizationFeatureEntitlement::isGranted($identity->organization, $feature)) {
                throw new AuthorizationException('This tool is not included in the organization\'s current plan.');
            }

            return $identity;
        } catch (Throwable $throwable) {
            if (isset($identity)) {
                $this->recordAudit($identity, 'denied', $startedAt, ['error_code' => 'authorization_denied']);
            }

            throw $throwable;
        }
    }

    /**
     * @param  array<string, int|string>  $metadata
     */
    protected function recordAudit(AiMcpExecutionIdentity $identity, string $outcome, int $startedAt, array $metadata = []): void
    {
        app(RecordAiToolCall::class)->handle(
            run: $identity->run,
            toolName: $this->name(),
            status: $outcome,
            metadata: [
                ...$metadata,
                'outcome' => $outcome,
                'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            ],
        );
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array{rows: array<int, mixed>, metadata: array{returned_row_count: int, limit: int, truncated: bool}}
     */
    protected function boundedRows(array $rows, int $limit): array
    {
        $truncated = count($rows) > $limit;

        return [
            'rows' => array_slice($rows, 0, $limit),
            'metadata' => [
                'returned_row_count' => min(count($rows), $limit),
                'limit' => $limit,
                'truncated' => $truncated,
            ],
        ];
    }
}
