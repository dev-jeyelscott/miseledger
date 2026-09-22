<?php

namespace App\Actions\Platform;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Bounded Redis availability probe for the Platform Health page (POC-V8.7).
 * A single PING against the same "default" connection Horizon uses; no
 * payload contents, keys, or credentials are read or exposed.
 */
final class CheckRedisHealth
{
    /**
     * @return array{
     *     status: string,
     *     source: string,
     *     checkedAt: string,
     *     latencyMs: int|null,
     * }
     */
    public function handle(): array
    {
        $checkedAt = Carbon::now()->toIso8601String();

        try {
            $startedAt = microtime(true);
            Redis::connection('default')->ping();
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            return [
                'status' => 'healthy',
                'source' => 'Redis PING (default connection)',
                'checkedAt' => $checkedAt,
                'latencyMs' => $latencyMs,
            ];
        } catch (Throwable) {
            return [
                'status' => 'down',
                'source' => 'Redis PING (default connection)',
                'checkedAt' => $checkedAt,
                'latencyMs' => null,
            ];
        }
    }
}
