<?php

declare(strict_types=1);

if (! in_array('--sandbox', $argv, true)
    || ! in_array('read-only', $argv, true)
    || ! in_array('--ask-for-approval', $argv, true)
    || ! in_array('never', $argv, true)
    || ($cdIndex = array_search('--cd', $argv, true)) === false
    || ! is_string($argv[$cdIndex + 1] ?? null)
    || array_slice($argv, $cdIndex + 2) !== ['app-server', '--stdio']) {
    fwrite(STDERR, 'Unexpected Codex command.');
    exit(1);
}

$profilePath = getenv('CODEX_HOME');

if (! is_string($profilePath) || $profilePath === '') {
    fwrite(STDERR, 'Missing CODEX_HOME.');
    exit(1);
}

$pid = getmypid();

// Logged eagerly, per message, rather than buffered for one write at the end
// of the process: the real client closes stdin and kills this process (via
// SIGTERM, uncaught by default) as soon as it has what it needs, so nothing
// deferred past the last message we read is guaranteed to ever run.
$logMessage = function (array $message) use ($profilePath, $pid, &$seq): void {
    file_put_contents(
        $profilePath.'/protocol.jsonl',
        json_encode(['pid' => $pid, 'seq' => $seq++, 'message' => $message], JSON_THROW_ON_ERROR)."\n",
        FILE_APPEND | LOCK_EX,
    );
};
$seq = 0;

file_put_contents($profilePath.'/command.jsonl', json_encode(['pid' => $pid, 'argv' => $argv], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
file_put_contents($profilePath.'/workspace', getcwd()."\n", LOCK_EX);

$handshake = [];

while (count($handshake) < 2 && ($line = fgets(STDIN)) !== false) {
    $message = json_decode($line, true);

    if (! is_array($message)) {
        fwrite(STDERR, 'Invalid JSONL input.');
        exit(1);
    }

    $handshake[] = $message;
    $logMessage($message);
}

if (count($handshake) !== 2
    || ($handshake[0]['method'] ?? null) !== 'initialize'
    || array_key_exists('jsonrpc', $handshake[0])
    || ($handshake[0]['id'] ?? null) !== 1
    || ($handshake[0]['params']['clientInfo']['name'] ?? null) !== 'miseledger'
    || ($handshake[1] ?? null) !== ['method' => 'initialized', 'params' => []]) {
    fwrite(STDERR, 'Unexpected app-server protocol sequence.');
    exit(1);
}

echo json_encode(['id' => 1, 'result' => ['userAgent' => 'fake']], JSON_THROW_ON_ERROR)."\n";
echo json_encode(['method' => 'initialized', 'params' => []], JSON_THROW_ON_ERROR)."\n";

while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);

    if ($line === '') {
        continue;
    }

    $message = json_decode($line, true);

    if (! is_array($message)) {
        fwrite(STDERR, 'Invalid JSONL input.');
        exit(1);
    }

    $logMessage($message);
    $id = $message['id'] ?? null;
    $method = $message['method'] ?? null;

    if ($method === 'test/rate-limit') {
        echo json_encode(['id' => $id, 'error' => ['code' => 429, 'message' => 'Rate limit reached']], JSON_THROW_ON_ERROR)."\n";

        continue;
    }

    $result = match ($method) {
        'account/read' => ['account' => ['type' => 'chatgpt', 'email' => 'user@example.test', 'planType' => 'plus']],
        'account/login/start' => ['type' => 'chatgptDeviceCode', 'loginId' => 'login_123', 'verificationUrl' => 'https://auth.openai.example/device', 'userCode' => 'ABCD-1234'],
        'account/logout' => [],
        'account/rateLimits/read' => ['rateLimits' => ['primary' => ['usedPercent' => 20]]],
        'thread/start' => ['thread' => ['id' => 'thr_123']],
        'thread/resume' => ['thread' => ['id' => $message['params']['threadId'] ?? 'thr_123']],
        // Mirrors the real app-server: turn/start only acknowledges the turn
        // was accepted; items stay lazily unloaded even once it completes.
        'turn/start' => ['turn' => ['id' => 'turn_123', 'items' => [], 'itemsView' => 'notLoaded', 'status' => 'inProgress']],
        default => null,
    };

    if (! is_array($result)) {
        fwrite(STDERR, 'Unexpected method.');
        exit(1);
    }

    echo json_encode(['id' => $id, 'result' => $result], JSON_THROW_ON_ERROR)."\n";

    if ($method === 'account/login/start') {
        echo json_encode([
            'method' => 'account/login/completed',
            'params' => ['success' => true, 'loginId' => $result['loginId']],
        ], JSON_THROW_ON_ERROR)."\n";
    }

    if ($method === 'turn/start') {
        $threadId = $message['params']['threadId'] ?? null;

        echo json_encode([
            'method' => 'item/completed',
            'params' => [
                'threadId' => $threadId,
                'turnId' => 'turn_123',
                'item' => ['type' => 'agentMessage', 'content' => [['type' => 'text', 'text' => 'Inventory is stable.']]],
            ],
        ], JSON_THROW_ON_ERROR)."\n";

        echo json_encode([
            'method' => 'turn/completed',
            'params' => [
                'threadId' => $threadId,
                'turn' => ['id' => 'turn_123', 'items' => [], 'itemsView' => 'notLoaded', 'status' => 'completed', 'model' => 'gpt-5', 'usage' => ['inputTokens' => 3, 'outputTokens' => 4]],
            ],
        ], JSON_THROW_ON_ERROR)."\n";
    }
}
