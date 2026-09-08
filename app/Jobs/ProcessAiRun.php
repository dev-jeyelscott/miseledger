<?php

namespace App\Jobs;

use App\Actions\Ai\ExecuteAiRun;
use App\Exceptions\AiProviderException;
use App\Models\AiRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessAiRun implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 300];

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $runId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        $run = AiRun::query()->find($this->runId);

        if ($run === null) {
            return [];
        }

        return [
            new RateLimited('ai-provider-openai'),
            (new WithoutOverlapping('ai:conversation:'.$run->ai_conversation_id))
                ->releaseAfter(5)
                ->expireAfter(115),
            (new WithoutOverlapping('ai:user-provider:'.$run->user_id.':'.$run->provider->value))
                ->releaseAfter(5)
                ->expireAfter(115),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(ExecuteAiRun $execute): void
    {
        $execute->handle($this->runId);
    }

    public function failed(Throwable $exception): void
    {
        app(ExecuteAiRun::class)->fail(
            $this->runId,
            $exception instanceof AiProviderException
                ? $exception->errorCode->value
                : 'provider_unavailable',
        );
    }
}
