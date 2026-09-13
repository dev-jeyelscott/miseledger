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
 * Stand-in for AwaitCodexDeviceCodeLogin that only exercises the shared
 * Codex profile filesystem, since a real device-code exchange cannot be
 * faked across an independently booted worker process.
 */
final class WritesCodexProfileMarkerForTest implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $userId, public readonly string $marker) {}

    public function handle(CodexProfileLocator $profiles): void
    {
        $user = (new User)->forceFill(['id' => $this->userId]);

        file_put_contents($profiles->path($user).'/marker', $this->marker);
    }
}
