<?php

namespace App\Enums;

enum PlatformAlertSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    /** Rank severities so escalation can be detected deterministically. */
    public function rank(): int
    {
        return match ($this) {
            self::Info => 0,
            self::Warning => 1,
            self::Critical => 2,
        };
    }

    public function isMoreSevereThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }
}
