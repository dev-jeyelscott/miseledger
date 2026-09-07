<?php

return [
    'codex' => [
        // This binary is provisioned by the deployment image. Keeping the
        // version here makes an incompatible runtime fail closed at startup.
        'command' => env('AI_CODEX_COMMAND', 'codex'),
        'version' => '0.153.4',
        'profile_root' => env('AI_CODEX_PROFILE_ROOT', storage_path('app/private/codex')),
        'workspace_path' => env('AI_CODEX_WORKSPACE', storage_path('app/private/codex-workspace')),
        'timeout_seconds' => 20,
        // Device-code logins stay open in the background until the user
        // finishes signing in at the verification URL, so this window is
        // much longer than a normal RPC round trip.
        'device_login_timeout_seconds' => env('AI_CODEX_DEVICE_LOGIN_TIMEOUT', 900),
    ],
];
