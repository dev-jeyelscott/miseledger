<?php

namespace App\Enums;

/**
 * Durable lifecycle states for a provider execution attempt.
 */
enum AiRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
