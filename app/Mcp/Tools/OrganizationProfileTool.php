<?php

namespace App\Mcp\Tools;

use App\Enums\OrganizationPermission;
use App\Mcp\AiMcpExecutionIdentityResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('organization_profile')]
#[Description('Read the worker-established organization\'s own name, slug, timezone, and currency. Use this for any question about "my organization" or "this organization".')]
#[IsReadOnly]
final class OrganizationProfileTool extends MiseLedgerReadTool
{
    protected function requiredPermission(): OrganizationPermission
    {
        return OrganizationPermission::ReportsView;
    }

    public function handle(Request $request, AiMcpExecutionIdentityResolver $identityResolver): ResponseFactory
    {
        $startedAt = hrtime(true);
        $identity = $this->authorize($identityResolver);

        $organization = $identity->organization;

        $this->recordAudit($identity, 'succeeded', $startedAt, ['resource_type' => 'organization_profile']);

        return Response::structured([
            'name' => $organization->name,
            'slug' => $organization->slug,
            'timezone' => $organization->timezone,
            'currency' => $organization->currency,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
