<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateOrganization
{
    public function __construct(
        private readonly EnsureDefaultWasteReasons $ensureDefaultWasteReasons,
    ) {}

    /**
     * Create the organization, owner membership, and waste reasons atomically.
     *
     * Units of measure are not seeded: the owner explicitly selects them from
     * the standard catalog during first-time setup.
     *
     * When an operation ID is supplied, a repeated submission of the same
     * creation request returns the organization it already created instead
     * of creating a duplicate tenant.
     */
    public function handle(
        User $user,
        string $name,
        ?string $operationId = null,
    ): Organization {
        if ($operationId !== null) {
            $existing = $this->organizationForOperation($user, $operationId);

            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use ($user, $name, $operationId): Organization {
                $organization = new Organization([
                    'name' => $name,
                    'slug' => $this->makeSlug($name),
                    'timezone' => 'Asia/Manila',
                    'currency' => 'PHP',
                    'active' => true,
                    'trial_ends_at' => $this->genericTrialEndsAt(),
                ]);

                $organization->creation_operation_id = $operationId;
                $organization->save();

                $organization->memberships()->create([
                    'user_id' => $user->getKey(),
                    'role' => OrganizationRole::Owner,
                ]);

                $this->ensureDefaultWasteReasons->handle($organization);

                return $organization;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($operationId === null) {
                throw $exception;
            }

            return $this->organizationForOperation($user, $operationId)
                ?? throw $exception;
        }
    }

    /**
     * Resolve an organization previously created by this user for the same
     * operation, rejecting operation IDs that belong to anyone else.
     */
    private function organizationForOperation(
        User $user,
        string $operationId,
    ): ?Organization {
        $organization = Organization::query()
            ->where('creation_operation_id', $operationId)
            ->first();

        if ($organization === null) {
            return null;
        }

        $isOwner = $organization->memberships()
            ->where('user_id', $user->getKey())
            ->where('role', OrganizationRole::Owner)
            ->exists();

        if (! $isOwner) {
            throw ValidationException::withMessages([
                'name' => __('This organization request could not be processed. Reload the page and try again.'),
            ]);
        }

        return $organization;
    }

    /**
     * Resolve the Cashier generic trial end timestamp from the configured
     * billing trial duration, without contacting Stripe.
     */
    private function genericTrialEndsAt(): ?Carbon
    {
        $trialDays = Config::get('billing.trial_days');

        if ($trialDays === null) {
            return null;
        }

        return Carbon::now()->addDays((int) $trialDays);
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
