<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformAdmin
{
    /**
     * Allow only explicitly granted MiseLedger platform administrators who have
     * enrolled an approved strong authentication factor (confirmed TOTP or a
     * registered passkey). Factor enrollment alone never grants platform authority.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isPlatformAdmin()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (! $user->hasApprovedStrongFactor()) {
            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
