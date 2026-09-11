<?php

namespace App\Policies;

use App\Models\ProblemReport;
use App\Models\User;

class ProblemReportPolicy
{
    public function view(User $user, ProblemReport $report): bool
    {
        return $user->id === $report->user_id;
    }

    public function viewAttachment(User $user, ProblemReport $report): bool
    {
        return $user->id === $report->user_id;
    }

    public function viewAsOperator(User $user, ProblemReport $report): bool
    {
        return true;
    }
}
