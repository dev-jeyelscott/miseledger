<?php

namespace App\Support\Ai;

use App\Enums\AiProvider;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The operational rollout boundary for AI. Organization entitlement and
 * membership checks remain authoritative at their existing boundaries.
 */
final class AiFeatureGate
{
    public static function isEnabled(): bool
    {
        return (bool) config('ai.enabled');
    }

    public static function isProviderEnabled(AiProvider $provider): bool
    {
        return self::isEnabled()
            && (bool) config("ai.providers.{$provider->value}.enabled");
    }

    public static function ensureEnabled(): void
    {
        if (! self::isEnabled()) {
            throw new AuthorizationException('The AI Assistant is currently unavailable.');
        }
    }

    public static function ensureProviderEnabled(AiProvider $provider): void
    {
        self::ensureEnabled();

        if (! self::isProviderEnabled($provider)) {
            throw new AuthorizationException('The selected AI provider is currently unavailable.');
        }
    }
}
