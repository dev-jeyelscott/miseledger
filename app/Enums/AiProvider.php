<?php

namespace App\Enums;

/**
 * Provider identities supported by durable AI records. Provider credentials
 * remain outside the application database.
 */
enum AiProvider: string
{
    case OpenAi = 'openai';
}
