<?php

namespace App\Enums;

enum OnboardingStepStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Complete = 'complete';
    case Skipped = 'skipped';
    case Locked = 'locked';
}
