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
     * @param  array<string, int|list<string>|string>  $configOverrides
     * @return array<string, mixed>
     */
    public function call(User $user, string $method, array $params = [], array $configOverrides = []): array
    {
        return $this->callSession($user, [
            static fn (): array => [$method, $params],
        ], $configOverrides)[0];
    }

    /**
     * Runs a sequence of requests against a single Codex app-server process.
     * Codex only keeps thread and turn state for the lifetime of the process
     * that created them, so a thread started by one process is invisible to
     * another — steps that depend on each other's state (starting a thread,
     * then a turn on it) must therefore share one process instead of each
     * opening its own.
     *
     * A step's request/response pair is a synchronous acknowledgement only:
     * for a request like `turn/start`, that ack just confirms the turn was
     * accepted (`status: "inProgress"`, no output yet) — the actual result
     * arrives later as a notification (e.g. `turn/completed`). A step may
     * name such a notification method; when it does, that notification's
     * `params` become the step's result instead of the ack's `result`.
     *
     * @param  list<callable(list<array<string, mixed>> $priorResults): array{0: string, 1: array<string, mixed>, 2?: string}>  $steps
     * @param  array<string, int|list<string>|string>  $configOverrides
     * @return list<array<string, mixed>>
     */
    public function callSession(User $user, array $steps, array $configOverrides = []): array
    {
        $timeout = (int) config('ai.codex.timeout_seconds');
        $input = new InputStream;
        $process = $this->spawnProcess($user, $input, $configOverrides);
        $results = [];

        try {
            $process->start();
            $this->sendHandshake($input);

            $buffer = '';

            foreach ($steps as $index => $step) {
                $step = $step($results);
                [$method, $params] = $step;
                $completionNotification = $step[2] ?? null;
                $id = $index + 2;

                $this->sendCall($input, $id, $method, $params);

                $response = $this->readMessageMatching(
                    $process,
                    $buffer,
                    static fn (array $message): bool => ($message['id'] ?? null) === $id,
                    $timeout,
                );

                if (isset($response['error'])) {
                    throw new AiProviderException($this->errorCode((array) $response['error']));
                }

                if ($completionNotification === null) {
                    $results[] = is_array($response['result'] ?? null) ? $response['result'] : [];

                    continue;
                }

                // Codex only loads a turn's items lazily (the terminal
                // notification's own `turn.items` stays empty), so the
                // agent's reply must be gathered from the `item/completed`
                // events streamed while waiting for the terminal one.
                $events = $this->collectMessagesUntil(
                    $process,
                    $buffer,
                    static fn (array $message): bool => ($message['method'] ?? null) === $completionNotification,
                    $timeout,
                );
                $notification = end($events);

                $notificationParams = is_array($notification['params'] ?? null) ? $notification['params'] : [];
                $notificationParams['streamedItems'] = array_values(array_filter(array_map(
                    static fn (array $message): ?array => ($message['method'] ?? null) === 'item/completed'
                        ? ($message['params']['item'] ?? null)
                        : null,
                    $events,
                )));

                $results[] = $notificationParams;
            }

            return $results;
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
            $this->sendHandshake($input);
            $this->sendCall($input, 2, 'account/login/start', ['type' => 'chatgptDeviceCode']);

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

    /** @param array<string, int|list<string>|string> $configOverrides */
    private function spawnProcess(User $user, InputStream $input, array $configOverrides = []): Process
    {
        $environment = ['CODEX_HOME' => $this->profiles->path($user)];
        $workspace = (string) config('ai.codex.workspace_path');
        $this->files->ensureDirectoryExists($workspace, 0700, true);

        $process = new Process([
            ...$this->command(),
            ...$this->configOverrides($configOverrides),
            '--config',
            'features.use_legacy_landlock=true',
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

    private function sendHandshake(InputStream $input): void
    {
        $input->write(json_encode([
            'id' => 1,
            'method' => 'initialize',
            'params' => ['clientInfo' => ['name' => 'miseledger', 'version' => '1.0']],
        ], JSON_THROW_ON_ERROR)."\n");
        $input->write(json_encode(['method' => 'initialized', 'params' => []], JSON_THROW_ON_ERROR)."\n");
    }

    /** @param array<string, mixed> $params */
    private function sendCall(InputStream $input, int $id, string $method, array $params): void
    {
        $input->write(json_encode(['id' => $id, 'method' => $method, 'params' => $params], JSON_THROW_ON_ERROR)."\n");
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
        $messages = $this->collectMessagesUntil($process, $buffer, $predicate, $timeoutSeconds);

        return $messages[array_key_last($messages)];
    }

    /**
     * Like {@see readMessageMatching()}, but returns every message seen
     * along the way (including the matching one), so a caller can react to
     * intermediate notifications too.
     *
     * @param  callable(array<string, mixed>): bool  $predicate
     * @return list<array<string, mixed>>
     */
    private function collectMessagesUntil(Process $process, string &$buffer, callable $predicate, int $timeoutSeconds): array
    {
        $messages = [];
        $deadline = microtime(true) + $timeoutSeconds;

        while ($process->isRunning() && microtime(true) < $deadline) {
            $buffer .= $process->getIncrementalOutput();

            while (($lineEnd = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $lineEnd);
                $buffer = substr($buffer, $lineEnd + 1);
                $message = json_decode($line, true);

                if (! is_array($message)) {
                    continue;
                }

                $messages[] = $message;

                if ($predicate($message)) {
                    return $messages;
                }
            }

            usleep(10_000);
        }

        $buffer .= $process->getIncrementalOutput();

        foreach (preg_split('/\R/', $buffer) ?: [] as $line) {
            $message = json_decode($line, true);

            if (! is_array($message)) {
                continue;
            }

            $messages[] = $message;

            if ($predicate($message)) {
                return $messages;
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
     * @param  array<string, int|list<string>|string>  $configOverrides
     * @return list<string>
     */
    private function configOverrides(array $configOverrides): array
    {
        $arguments = [];

        foreach ($configOverrides as $key => $value) {
            $arguments[] = '--config';
            // Codex parses --config values as TOML, not JSON: TOML's string
            // grammar has no `\/` escape, so an unescaped-slash JSON encoding
            // is required for a value like a filesystem path to parse as the
            // intended array/string rather than falling back to a raw string.
            $arguments[] = $key.'='.json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        return $arguments;
    }

    /** @param array<string, mixed> $error */
    private function errorCode(array $error): AiProviderErrorCode
    {
        return $this->errorCodeForMessage((string) ($error['message'] ?? ''));
    }

    /**
     * Classifies a provider-reported failure message, whether it arrived as
     * a JSON-RPC error or as a terminal `status: "failed"` on an otherwise
     * successful turn (e.g. an expired provider token surfaces only in the
     * turn's own `error.message`, not as a JSON-RPC error).
     */
    public function errorCodeForMessage(string $message): AiProviderErrorCode
    {
        $message = strtolower($message);

        return match (true) {
            str_contains($message, 'unauthor') => AiProviderErrorCode::Unauthorized,
            str_contains($message, 'rate limit'), str_contains($message, 'usage limit') => AiProviderErrorCode::RateLimited,
            str_contains($message, 'timeout') => AiProviderErrorCode::Timeout,
            str_contains($message, 'tool') => AiProviderErrorCode::ToolFailed,
            str_contains($message, 'invalid') => AiProviderErrorCode::InvalidRequest,
            default => AiProviderErrorCode::Failed,
        };
    }
}
