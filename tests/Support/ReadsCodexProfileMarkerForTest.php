<?php

namespace Tests\Support;

use App\Models\User;
use App\Support\Ai\Providers\CodexProfileLocator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Stand-in for the AI turn worker: reads the Codex profile a separate
 * ai-login queue consumer persisted, proving the shared filesystem is
 * actually visible across independently booted worker processes.
 */
final class ReadsCodexProfileMarkerForTest implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $userId, public readonly string $resultPath) {}

    public function handle(CodexProfileLocator $profiles): void
    {
        $user = (new User)->forceFill(['id' => $this->userId]);
        $markerPath = $profiles->path($user).'/marker';

        file_put_contents(
            $this->resultPath,
            file_exists($markerPath) ? file_get_contents($markerPath) : 'MISSING',
        );
    }
}
