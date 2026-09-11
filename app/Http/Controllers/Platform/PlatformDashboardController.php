<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformDashboardController extends Controller
{
    /**
     * Render the platform-level administration shell.
     */
    public function index(): Response
    {
        return Inertia::render('admin/dashboard');
    }
}
