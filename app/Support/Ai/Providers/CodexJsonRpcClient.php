<?php

namespace App\Support\Ai\Providers;

use App\Enums\AiProviderErrorCode;
use App\Exceptions\AiProviderException;
use App\Models\User;
use Symfony\Component\Process\Process;
use Throwable;

/** @internal A line-delimited JSON-RPC v2 transport to one user profile. */
final class CodexJsonRpcClient
{
    public function __construct(private readonly CodexProfileLocator $profiles) {}

    /** @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function call(User $user, string $method, array $params = []): array
    {
        $environment = ['CODEX_HOME' => $this->profiles->path($user)];
        $command = (string) config('ai.codex.command');
        $timeout = (int) config('ai.codex.timeout_seconds');

        try {
            (new Process([$command, 'app-server', 'daemon', 'start'], null, $environment))
                ->setTimeout($timeout)
                ->mustRun();

            $input = implode("\n", [
                json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
                    'clientInfo' => ['name' => 'miseledger', 'version' => '1.0'],
                ]], JSON_THROW_ON_ERROR),
                json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => $method, 'params' => $params], JSON_THROW_ON_ERROR),
            ])."\n";

            $process = new Process([$command, 'app-server', 'proxy'], null, $environment, $input);
            $process->setTimeout($timeout);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new AiProviderException(AiProviderErrorCode::Unavailable);
            }

            foreach (preg_split('/\R/', $process->getOutput()) ?: [] as $line) {
                $response = json_decode($line, true);

                if (! is_array($response) || ($response['id'] ?? null) !== 2) {
                    continue;
                }

                if (isset($response['error'])) {
                    throw new AiProviderException($this->errorCode((array) $response['error']));
                }

                return is_array($response['result'] ?? null) ? $response['result'] : [];
            }
        } catch (AiProviderException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new AiProviderException(AiProviderErrorCode::Unavailable);
        }

        throw new AiProviderException(AiProviderErrorCode::Protocol);
    }

    /** @param array<string, mixed> $error */
    private function errorCode(array $error): AiProviderErrorCode
    {
        $message = strtolower((string) ($error['message'] ?? ''));

        return match (true) {
            str_contains($message, 'unauthor') => AiProviderErrorCode::Unauthorized,
            str_contains($message, 'rate limit') => AiProviderErrorCode::RateLimited,
            str_contains($message, 'invalid') => AiProviderErrorCode::InvalidRequest,
            default => AiProviderErrorCode::Failed,
        };
    }
}
