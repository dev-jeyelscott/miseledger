<?php

namespace App\Actions\Platform;

use Illuminate\Support\Carbon;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

/**
 * Bounded normal-queue runtime signal for the Platform Health page
 * (POC-V8.7), read from Horizon's own supported repository contracts. The
 * hardened AI worker and AI login worker run their own dedicated queues
 * outside Horizon and are intentionally not reported here (see
 * config/horizon.php and docs/deployment.md). No purge, retry, or restart
 * action is exposed.
 */
final class CheckQueueHealth
{
    public function __construct(
        private readonly MasterSupervisorRepository $masters,
        private readonly JobRepository $jobs,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     source: string,
     *     checkedAt: string,
     *     activeMasters: int|null,
     *     pendingJobs: int|null,
     *     recentlyFailedJobs: int|null,
     * }
     */
    public function handle(): array
    {
        $checkedAt = Carbon::now()->toIso8601String();

        try {
            $activeMasters = count($this->masters->all());
            $pendingJobs = $this->jobs->countPending();
            $recentlyFailedJobs = $this->jobs->countRecentlyFailed();

            $status = 'healthy';

            if ($activeMasters === 0) {
                $status = 'warning';
            } elseif ($recentlyFailedJobs > 0) {
                $status = 'warning';
            }

            return [
                'status' => $status,
                'source' => 'Horizon supervisor and job repositories',
                'checkedAt' => $checkedAt,
                'activeMasters' => $activeMasters,
                'pendingJobs' => $pendingJobs,
                'recentlyFailedJobs' => $recentlyFailedJobs,
            ];
        } catch (Throwable) {
            return [
                'status' => 'unknown',
                'source' => 'Horizon supervisor and job repositories',
                'checkedAt' => $checkedAt,
                'activeMasters' => null,
                'pendingJobs' => null,
                'recentlyFailedJobs' => null,
            ];
        }
    }
}
