<?php

return [
    'codex' => [
        // This binary is provisioned by the deployment image. Keeping the
        // version here makes an incompatible runtime fail closed at startup.
        'command' => env('AI_CODEX_COMMAND', 'codex'),
        'version' => '0.153.4',
        'profile_root' => env('AI_CODEX_PROFILE_ROOT', storage_path('app/private/codex')),
        // Deliberately outside the application's own git working tree: Codex
        // walks a turn's cwd up to the enclosing repository root and loads
        // any AGENTS.md / project-local config it finds there as thread
        // instructions. Pointing this inside the repo leaked this project's
        // own developer-facing AGENTS.md into the customer-facing assistant.
        'workspace_path' => env('AI_CODEX_WORKSPACE', sys_get_temp_dir().'/miseledger-ai-workspace'),
        // MCP startup and a tool-backed answer both happen within a single
        // app-server turn. Twenty seconds expires before the first tool can
        // be discovered on a cold worker, while this remains bounded below
        // the AI job and queue retry windows.
        'timeout_seconds' => (int) env('AI_CODEX_TIMEOUT_SECONDS', 75),
        // Device-code logins stay open in the background until the user
        // finishes signing in at the verification URL, so this window is
        // much longer than a normal RPC round trip.
        'device_login_timeout_seconds' => env('AI_CODEX_DEVICE_LOGIN_TIMEOUT', 900),
    ],
];
