<?php

return [
    'email' => [
        'enabled' => env('PROBLEM_REPORT_EMAIL_ENABLED', false),
        'to' => env('PROBLEM_REPORT_EMAIL_TO'),
    ],
];
