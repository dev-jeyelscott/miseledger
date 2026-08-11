<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateOrganization
{
    /**
     * Create the organization and its owner membership atomically.
     */
    public function handle(User $user, string $name): Organization
    {
        return DB::transaction(function () use ($user, $name): Organization {
            $organization = Organization::query()->create([
                'name' => $name,
                'slug' => $this->makeSlug($name),
                'timezone' => 'Asia/Manila',
                'currency' => 'PHP',
                'active' => true,
            ]);

            $organization->memberships()->create([
                'user_id' => $user->getKey(),
                'role' => OrganizationRole::Owner,
            ]);

            return $organization;
        });
    }

    /**
     * Generate a stable-length globally unique organization slug.
     */
    private function makeSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'organization';
        }

        return Str::limit($base, 125, '')
            .'-'
            .Str::lower((string) Str::ulid());
    }
}
