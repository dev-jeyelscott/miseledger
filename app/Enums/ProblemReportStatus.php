<?php

namespace App\Enums;

enum ProblemReportStatus: string
{
    case Submitted = 'submitted';
    case InReview = 'in-review';
    case InProgress = 'in-progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
