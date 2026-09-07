---
paths:
  - 'app/Actions/Ai/**'
---

# Ai

## Durable AI run ownership and execution
Persist the user message and queued run transactionally before dispatching ProcessAiRun with only the durable run ID. Recheck membership, commercial access, and active provider connection after dequeue. Keep user/provider and conversation execution serialized, and persist only one final assistant message per run.
