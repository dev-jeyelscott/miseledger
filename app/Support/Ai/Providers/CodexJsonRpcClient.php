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
        $timeout = (int) config('ai.codex.timeout_seconds');
        $input = new InputStream;
        $process = $this->spawnProcess($user, $input, $configOverrides);

        try {
            $process->start();
            $this->sendRequest($input, $method, $params);

            $buffer = '';
            $response = $this->readMessageMatching(
                $process,
                $buffer,
                static fn (array $message): bool => ($message['id'] ?? null) === 2,
                $timeout,
            );

            if (isset($response['error'])) {
                throw new AiProviderException($this->errorCode((array) $response['error']));
            }

            return is_array($response['result'] ?? null) ? $response['result'] : [];
        } catch (AiProviderException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new AiProviderException(AiProviderErrorCode::Unavailable);
        } finally {
            $input->close();

            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }

    /**
     * Starts a ChatGPT device-code login and keeps the Codex process running
     * until it reports the login completed, failed, or the given window
     * elapsed. Unlike {@see call()}, the process is intentionally kept alive
     * between the initial response and the later notification, since Codex
     * only exchanges and persists credentials while that process keeps
     * polling in the background.
     *
     * @param  callable(array{login_id: string, verification_url: string, user_code: string}): void  $onStarted
     */
    public function runDeviceCodeLogin(User $user, callable $onStarted, int $awaitTimeoutSeconds): bool
    {
        $input = new InputStream;
        $process = $this->spawnProcess($user, $input);

        try {
            $process->start();
            $this->sendRequest($input, 'account/login/start', ['type' => 'chatgptDeviceCode']);

            $buffer = '';
            $response = $this->readMessageMatching(
                $process,
                $buffer,
                static fn (array $message): bool => ($message['id'] ?? null) === 2,
                (int) config('ai.codex.timeout_seconds'),
            );

            if (isset($response['error'])) {
                throw new AiProviderException($this->errorCode((array) $response['error']));
            }

            $result = is_array($response['result'] ?? null) ? $response['result'] : [];

            if (($result['type'] ?? null) !== 'chatgptDeviceCode'
                || ! is_string($result['loginId'] ?? null)
                || ! is_string($result['verificationUrl'] ?? null)
                || ! is_string($result['userCode'] ?? null)) {
                throw new AiProviderException(AiProviderErrorCode::Protocol);
            }

            $onStarted([
                'login_id' => $result['loginId'],
                'verification_url' => $result['verificationUrl'],
                'user_code' => $result['userCode'],
            ]);

            $notification = $this->readMessageMatching(
                $process,
                $buffer,
                static fn (array $message): bool => ($message['method'] ?? null) === 'account/login/completed',
                $awaitTimeoutSeconds,
            );

            $notificationParams = is_array($notification['params'] ?? null) ? $notification['params'] : [];

            return $notificationParams['success'] ?? false;
        } catch (AiProviderException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new AiProviderException(AiProviderErrorCode::Unavailable);
        } finally {
            $input->close();

            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }

    /** @param array<string, list<string>|string> $configOverrides */
    private function spawnProcess(User $user, InputStream $input, array $configOverrides = []): Process
    {
        $environment = ['CODEX_HOME' => $this->profiles->path($user)];
        $workspace = (string) config('ai.codex.workspace_path');
        $this->files->ensureDirectoryExists($workspace, 0700, true);

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

        return $process;
    }

    /** @param array<string, mixed> $params */
    private function sendRequest(InputStream $input, string $method, array $params): void
    {
        foreach ([
            ['id' => 1, 'method' => 'initialize', 'params' => [
                'clientInfo' => ['name' => 'miseledger', 'version' => '1.0'],
            ]],
            ['method' => 'initialized', 'params' => []],
            ['id' => 2, 'method' => $method, 'params' => $params],
        ] as $message) {
            $input->write(json_encode($message, JSON_THROW_ON_ERROR)."\n");
        }
    }

    /**
     * Reads decoded JSONL messages from the process until one satisfies
     * $predicate, appending to the shared $buffer so callers can issue
     * multiple successive waits (e.g. an initial response, then a later
     * notification) against the same stdout stream.
     *
     * @param  callable(array<string, mixed>): bool  $predicate
     * @return array<string, mixed>
     */
    private function readMessageMatching(Process $process, string &$buffer, callable $predicate, int $timeoutSeconds): array
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while ($process->isRunning() && microtime(true) < $deadline) {
            $buffer .= $process->getIncrementalOutput();

            while (($lineEnd = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $lineEnd);
                $buffer = substr($buffer, $lineEnd + 1);
                $message = json_decode($line, true);

                if (is_array($message) && $predicate($message)) {
                    return $message;
                }
            }

            usleep(10_000);
        }

        $buffer .= $process->getIncrementalOutput();

        foreach (preg_split('/\R/', $buffer) ?: [] as $line) {
            $message = json_decode($line, true);

            if (is_array($message) && $predicate($message)) {
                return $message;
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
