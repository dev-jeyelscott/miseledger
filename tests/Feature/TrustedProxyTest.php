<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Verify that reverse-proxy headers preserve the external HTTPS scheme and
 * original client address seen by Laravel behind Coolify.
 */
test('trusted proxy headers resolve the original HTTPS request and client IP', function (): void {
    Route::get('/_testing/proxy-context', function (Request $request): array {
        return [
            'secure' => $request->secure(),
            'ip' => $request->ip(),
        ];
    });

    $this
        ->withServerVariables([
            'REMOTE_ADDR' => '172.18.0.10',
        ])
        ->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.10',
        ])
        ->get('/_testing/proxy-context')
        ->assertOk()
        ->assertJson([
            'secure' => true,
            'ip' => '203.0.113.10',
        ]);
});
