<?php

namespace App\Http\Middleware;

use App\Support\Ai\AiFeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAiFeatureEnabled
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        AiFeatureGate::ensureEnabled();

        return $next($request);
    }
}
