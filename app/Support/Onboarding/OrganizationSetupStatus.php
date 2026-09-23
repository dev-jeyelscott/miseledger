<?php

namespace App\Support\Onboarding;

use App\Enums\OnboardingStep;
use App\Enums\OnboardingStepStatus;

/**
 * Server-derived first-time setup state for one organization. Every value is
 * computed from durable organization records; the browser never decides it.
 */
final readonly class OrganizationSetupStatus
{
    public function __construct(
        public int $activeLocationCount,
        public int $activeUnitCount,
        public int $activeItemCount,
        public int $resolvedOpeningStockCount,
        public int $unresolvedOpeningStockCount,
        public int $supplierCount,
        public int $supplierItemCount,
        public int $memberCount,
        public bool $suppliersSkipped,
        public bool $teamSkipped,
        public bool $ready,
        public bool $justCompleted,
    ) {}

    /**
     * Resolve the display status of one checklist step.
     */
    public function statusFor(OnboardingStep $step): OnboardingStepStatus
    {
        return match ($step) {
            OnboardingStep::Organization => OnboardingStepStatus::Complete,
            OnboardingStep::Location => $this->activeLocationCount > 0
                ? OnboardingStepStatus::Complete
                : OnboardingStepStatus::NotStarted,
            OnboardingStep::Units => $this->activeUnitCount > 0
                ? OnboardingStepStatus::Complete
                : OnboardingStepStatus::NotStarted,
            OnboardingStep::Inventory => $this->activeItemCount > 0
                ? OnboardingStepStatus::Complete
                : OnboardingStepStatus::NotStarted,
            OnboardingStep::OpeningStock => $this->openingStockStatus(),
            OnboardingStep::Suppliers => $this->suppliersStatus(),
            OnboardingStep::Team => $this->teamStatus(),
        };
    }

    /**
     * Whether an optional step still needs an explicit action or skip.
     */
    public function hasPendingOptionalSteps(): bool
    {
        foreach ([OnboardingStep::Suppliers, OnboardingStep::Team] as $step) {
            $status = $this->statusFor($step);

            if (
                $status !== OnboardingStepStatus::Complete
                && $status !== OnboardingStepStatus::Skipped
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Opening stock is complete only when every counted item is resolved.
     */
    private function openingStockStatus(): OnboardingStepStatus
    {
        if ($this->activeItemCount === 0) {
            return OnboardingStepStatus::Locked;
        }

        if ($this->unresolvedOpeningStockCount === 0) {
            return OnboardingStepStatus::Complete;
        }

        return $this->resolvedOpeningStockCount > 0
            ? OnboardingStepStatus::InProgress
            : OnboardingStepStatus::NotStarted;
    }

    /**
     * Suppliers are optional; a supplier with linked items counts as complete.
     */
    private function suppliersStatus(): OnboardingStepStatus
    {
        if ($this->supplierItemCount > 0) {
            return OnboardingStepStatus::Complete;
        }

        if ($this->supplierCount > 0) {
            return OnboardingStepStatus::InProgress;
        }

        return $this->suppliersSkipped
            ? OnboardingStepStatus::Skipped
            : OnboardingStepStatus::NotStarted;
    }

    /**
     * Team invitations open only after minimum operational setup.
     */
    private function teamStatus(): OnboardingStepStatus
    {
        if ($this->memberCount > 1) {
            return OnboardingStepStatus::Complete;
        }

        if ($this->teamSkipped) {
            return OnboardingStepStatus::Skipped;
        }

        return $this->ready
            ? OnboardingStepStatus::NotStarted
            : OnboardingStepStatus::Locked;
    }
}
