<?php

namespace Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A plain top-level job class, not a Pest test-closure: a closure declared
 * inline inside a Pest test file is bound to that test run's dynamically
 * generated class, which does not exist in an independently booted worker
 * process, so it can never be unserialized there. This job exercises real
 * cross-process queue consumption the same way WritesCodexProfileMarkerForTest
 * does for the ai queue.
 */
final class WritesFileMarkerForTest implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $path, public readonly string $marker) {}

    public function handle(): void
    {
        file_put_contents($this->path, $this->marker);
    }
}
