<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformObservabilityController extends Controller
{
    /**
     * Render the platform Observability landing page.
     *
     * This links out to the native Pulse and Horizon dashboards rather than
     * cloning their charts/tables; both dashboards enforce the same
     * platform-admin authority independently (see config/pulse.php,
     * config/horizon.php, and HorizonServiceProvider::gate()).
     */
    public function index(): Response
    {
        return Inertia::render('admin/observability', [
            'pulse' => [
                'enabled' => (bool) config('pulse.enabled'),
                'url' => route('pulse'),
            ],
            'horizon' => [
                'url' => route('horizon.index'),
            ],
        ]);
    }
}
