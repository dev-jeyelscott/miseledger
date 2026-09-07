<?php

declare(strict_types=1);

if (array_slice($argv, 1, 4) !== ['--sandbox', 'read-only', '--ask-for-approval', 'never']
    || ($argv[5] ?? null) !== '--cd'
    || ! is_string($argv[6] ?? null)
    || array_slice($argv, 7) !== ['app-server', '--stdio']) {
    fwrite(STDERR, 'Unexpected Codex command.');
    exit(1);
}

$messages = [];

while (count($messages) < 3 && ($line = fgets(STDIN)) !== false) {
    $message = json_decode($line, true);

    if (! is_array($message)) {
        fwrite(STDERR, 'Invalid JSONL input.');
        exit(1);
    }

    $messages[] = $message;
}

if (count($messages) !== 3
    || ($messages[0]['method'] ?? null) !== 'initialize'
    || array_key_exists('jsonrpc', $messages[0])
    || ($messages[0]['id'] ?? null) !== 1
    || ($messages[0]['params']['clientInfo']['name'] ?? null) !== 'miseledger'
    || ($messages[1] ?? null) !== ['method' => 'initialized', 'params' => []]
    || ($messages[2]['id'] ?? null) !== 2) {
    fwrite(STDERR, 'Unexpected app-server protocol sequence.');
    exit(1);
}

$profilePath = getenv('CODEX_HOME');

if (! is_string($profilePath) || $profilePath === '') {
    fwrite(STDERR, 'Missing CODEX_HOME.');
    exit(1);
}

file_put_contents($profilePath.'/protocol.jsonl', json_encode($messages, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
file_put_contents($profilePath.'/workspace', getcwd()."\n", LOCK_EX);

$method = $messages[2]['method'] ?? null;
$result = match ($method) {
    'account/read' => ['account' => ['type' => 'chatgpt', 'email' => 'user@example.test', 'planType' => 'plus']],
    'account/login/start' => ['type' => 'chatgptDeviceCode', 'loginId' => 'login_123', 'verificationUrl' => 'https://auth.openai.example/device', 'userCode' => 'ABCD-1234'],
    'account/logout' => [],
    'account/rateLimits/read' => ['rateLimits' => ['primary' => ['usedPercent' => 20]]],
    'thread/start', 'thread/resume' => ['thread' => ['id' => 'thr_123']],
    'turn/start' => ['turn' => ['id' => 'turn_123']],
    default => null,
};

if ($method === 'test/rate-limit') {
    echo json_encode(['id' => 2, 'error' => ['code' => 429, 'message' => 'Rate limit reached']], JSON_THROW_ON_ERROR)."\n";

    exit(0);
}

if (! is_array($result)) {
    fwrite(STDERR, 'Unexpected method.');
    exit(1);
}

echo json_encode(['id' => 1, 'result' => ['userAgent' => 'fake']], JSON_THROW_ON_ERROR)."\n";
echo json_encode(['method' => 'initialized', 'params' => []], JSON_THROW_ON_ERROR)."\n";
echo json_encode(['id' => 2, 'result' => $result], JSON_THROW_ON_ERROR)."\n";
