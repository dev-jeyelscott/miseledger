<?php

namespace App\Support\Ai\Providers;

use App\Enums\AiProviderErrorCode;
use App\Exceptions\AiProviderException;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Throwable;

/** @internal A line-delimited JSON-RPC v2 transport to one user profile. */
final class CodexJsonRpcClient
{
    public function __construct(
        private readonly CodexProfileLocator $profiles,
        private readonly Filesystem $files,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, list<string>|string>  $configOverrides
     * @return array<string, mixed>
     */
    public function call(User $user, string $method, array $params = [], array $configOverrides = []): array
    {
        $environment = ['CODEX_HOME' => $this->profiles->path($user)];
        $timeout = (int) config('ai.codex.timeout_seconds');
        $workspace = (string) config('ai.codex.workspace_path');
        $this->files->ensureDirectoryExists($workspace, 0700, true);

        try {
            $input = new InputStream;
            $process = new Process([
                ...$this->command(),
                ...$this->configOverrides($configOverrides),
                '--sandbox',
                'read-only',
                '--ask-for-approval',
                'never',
                '--cd',
                $workspace,
                'app-server',
                '--stdio',
            ], $workspace, $environment);
            $process->setInput($input);
            $process->setTimeout($timeout);

            $process->start();

            foreach ([
                ['id' => 1, 'method' => 'initialize', 'params' => [
                    'clientInfo' => ['name' => 'miseledger', 'version' => '1.0'],
                ]],
                ['method' => 'initialized', 'params' => []],
                ['id' => 2, 'method' => $method, 'params' => $params],
            ] as $message) {
                $input->write(json_encode($message, JSON_THROW_ON_ERROR)."\n");
            }

            $response = $this->waitForResponse($process, $timeout);
            $input->close();

            if ($process->isRunning()) {
                $process->stop();
            }

            if (isset($response['error'])) {
                throw new AiProviderException($this->errorCode((array) $response['error']));
            }

            return is_array($response['result'] ?? null) ? $response['result'] : [];
        } catch (AiProviderException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new AiProviderException(AiProviderErrorCode::Unavailable);
        }
    }

    /** @return array<string, mixed> */
    private function waitForResponse(Process $process, int $timeout): array
    {
        $deadline = microtime(true) + $timeout;
        $buffer = '';

        while ($process->isRunning() && microtime(true) < $deadline) {
            $buffer .= $process->getIncrementalOutput();

            while (($lineEnd = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $lineEnd);
                $buffer = substr($buffer, $lineEnd + 1);
                $response = json_decode($line, true);

                if (is_array($response) && ($response['id'] ?? null) === 2) {
                    return $response;
                }
            }

            usleep(10_000);
        }

        $buffer .= $process->getIncrementalOutput();

        foreach (preg_split('/\R/', $buffer) ?: [] as $line) {
            $response = json_decode($line, true);

            if (is_array($response) && ($response['id'] ?? null) === 2) {
                return $response;
            }
        }

        if ($process->isRunning()) {
            $process->stop();

            throw new AiProviderException(AiProviderErrorCode::Unavailable);
        }

        throw new AiProviderException(AiProviderErrorCode::Protocol);
    }

    /** @return list<string> */
    private function command(): array
    {
        $configuredCommand = config('ai.codex.command');

        if (is_string($configuredCommand) && $configuredCommand !== '') {
            return [$configuredCommand];
        }

        if (is_array($configuredCommand)
            && $configuredCommand !== []
            && array_is_list($configuredCommand)
            && array_all($configuredCommand, static fn (mixed $part): bool => is_string($part) && $part !== '')) {
            return $configuredCommand;
        }

        throw new AiProviderException(AiProviderErrorCode::Unavailable);
    }

    /**
     * @param  array<string, list<string>|string>  $configOverrides
     * @return list<string>
     */
    private function configOverrides(array $configOverrides): array
    {
        $arguments = [];

        foreach ($configOverrides as $key => $value) {
            $arguments[] = '--config';
            $arguments[] = $key.'='.json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $arguments;
    }

    /** @param array<string, mixed> $error */
    private function errorCode(array $error): AiProviderErrorCode
    {
        $message = strtolower((string) ($error['message'] ?? ''));

        return match (true) {
            str_contains($message, 'unauthor') => AiProviderErrorCode::Unauthorized,
            str_contains($message, 'rate limit') => AiProviderErrorCode::RateLimited,
            str_contains($message, 'timeout') => AiProviderErrorCode::Timeout,
            str_contains($message, 'tool') => AiProviderErrorCode::ToolFailed,
            str_contains($message, 'invalid') => AiProviderErrorCode::InvalidRequest,
            default => AiProviderErrorCode::Failed,
        };
    }
}
