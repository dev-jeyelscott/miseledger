<?php

namespace App\Enums;

/**
 * Speaker roles persisted in the user-visible conversation transcript.
 */
enum AiMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';
}
