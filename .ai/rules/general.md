---
paths:
  - compose.yaml
---

# General

## Codex sandbox requires a writable /tmp
The dedicated ai-worker remains read-only, but its whole /tmp must be a tmpfs. Codex creates Bubblewrap mount targets directly below /tmp before it can launch the local MCP server; mounting only /tmp/codex-workspace makes tool calls fail.
