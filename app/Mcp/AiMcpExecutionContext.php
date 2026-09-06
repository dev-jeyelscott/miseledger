<?php

namespace App\Mcp;

use LogicException;

/**
 * Process-local execution credential supplied only by the AI worker before
 * the local stdio server starts. MCP clients never provide this value.
 */
final class AiMcpExecutionContext
{
    private ?string $signedIdentity = null;

    public function set(string $signedIdentity): void
    {
        $this->signedIdentity = $signedIdentity;
    }

    public function signedIdentity(): string
    {
        if ($this->signedIdentity === null) {
            throw new LogicException('An AI execution identity is required.');
        }

        return $this->signedIdentity;
    }
}
