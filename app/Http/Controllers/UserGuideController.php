<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class UserGuideController extends Controller
{
    /** @var list<string> */
    private const array MODULES = [
        'getting-started',
        'dashboard',
        'ai-assistant',
        'inventory',
        'stock-counts',
        'waste',
        'stock-transfers',
        'purchasing',
        'recipes',
        'reports',
        'organization',
        'billing',
        'settings',
    ];

    public function index(): Response
    {
        return Inertia::render('user-guide/index');
    }

    public function show(string $module): Response
    {
        abort_unless(in_array($module, self::MODULES, true), 404);

        return Inertia::render('user-guide/show', [
            'module' => $module,
        ]);
    }
}
