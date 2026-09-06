<?php

return [
    'codex' => [
        // This binary is provisioned by the deployment image. Keeping the
        // version here makes an incompatible runtime fail closed at startup.
        'command' => env('AI_CODEX_COMMAND', 'codex'),
        'version' => '0.153.4',
        'profile_root' => storage_path('app/private/codex'),
        'timeout_seconds' => 20,
    ],
];
