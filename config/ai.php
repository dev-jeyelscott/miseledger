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
    ],
];
