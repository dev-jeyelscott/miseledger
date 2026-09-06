<?php

namespace App\Mcp;

use App\Models\AiRun;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

final readonly class AiMcpExecutionIdentity
{
    public function __construct(
        public AiRun $run,
        public User $user,
        public Organization $organization,
        public OrganizationMembership $membership,
    ) {}
}
