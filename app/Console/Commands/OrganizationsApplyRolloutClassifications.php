<?php

namespace App\Console\Commands;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrganizationRolloutClassification;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies the operator-approved `config('organization_rollout.classifications')`
 * map (see docs/existing-organization-rollout-plan.md) to every pre-existing
 * organization. Refuses to run while any organization is missing an approved
 * entry, so the rollout can never silently leave a tenant unclassified. Only
 * ever writes `rollout_classification`; never touches `Organization.active`
 * or any subscription/trial field, so it never mutates commercial access.
 */
final class OrganizationsApplyRolloutClassifications extends Command
{
    public function __construct(
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {
        parent::__construct();
    }

    protected $signature = 'organizations:apply-rollout-classifications
        {--dry-run : Report the planned changes without writing them}';

    protected $description = 'Apply the approved existing-organization rollout classification map without changing commercial access.';

    public function handle(): int
    {
        $approved = config('organization_rollout.classifications', []);
        $organizations = Organization::query()->orderBy('id')->get();

        $missing = $organizations
            ->reject(fn (Organization $organization): bool => array_key_exists($organization->slug, $approved))
            ->pluck('slug');

        if ($missing->isNotEmpty()) {
            $this->error(
                'Refusing to apply rollout classifications: no approved classification exists for organization slug(s): '
                .$missing->implode(', '),
            );

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $applied = 0;
        $unchanged = 0;

        foreach ($organizations as $organization) {
            $entry = $approved[$organization->slug];
            $classification = $entry['classification'] ?? null;

            if (! $classification instanceof OrganizationRolloutClassification) {
                throw new RuntimeException("Invalid approved rollout classification configured for organization slug [{$organization->slug}].");
            }

            if ($organization->rollout_classification === $classification) {
                $unchanged++;

                continue;
            }

            if ($dryRun) {
                $this->line("Would classify [{$organization->slug}] as {$classification->value}.");
                $applied++;

                continue;
            }

            $this->applyClassification($organization, $classification, $entry['rationale'] ?? null);
            $applied++;
        }

        $this->line(sprintf(
            '%s%d organization%s classified, %d unchanged.',
            $dryRun ? '[dry-run] ' : '',
            $applied,
            $applied === 1 ? '' : 's',
            $unchanged,
        ));

        return self::SUCCESS;
    }

    private function applyClassification(
        Organization $organization,
        OrganizationRolloutClassification $classification,
        mixed $rationale,
    ): void {
        DB::transaction(function () use ($organization, $classification, $rationale): void {
            $before = ['rollout_classification' => $organization->rollout_classification?->value];

            $organization->update(['rollout_classification' => $classification]);

            $this->recordAuditEntry->handle(
                $organization,
                null,
                'organization.rollout_classification.applied',
                Organization::class,
                $organization->id,
                $before,
                [
                    'rollout_classification' => $classification->value,
                    'rationale' => is_string($rationale) ? $rationale : null,
                ],
            );
        });
    }
}
