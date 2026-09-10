# AI Assistant production security verification

This is the release sign-off for the embedded AI Assistant. It is an
operational verification guide, not a source of authorization. The current
application, access matrix, inventory-ledger rules, and deployment contract
remain authoritative.

## Preconditions

1. Deploy the same immutable application revision to web, normal worker,
   scheduler, `ai-worker`, and `ai-login-worker` resources.
2. Keep `AI_ENABLED=false` and `AI_OPENAI_ENABLED=false` until the checks
   below complete. These flags are separate from plan entitlement and member
   access.
3. Run the automated verification with no live Codex, OpenAI, or payment
   provider credentials. The test suite uses the in-process AI adapter fake
   and the local fake Codex app server.

## Threat-model closure matrix

| Threat / required property                           | Enforced boundary                                                                                                                                                                                                                                                                                   | Regression evidence                                                                                                                                                                                 |
| ---------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Cross-tenant or cross-member conversation read/write | The HTTP controllers scope conversations and runs to the active organization and signed-in owner. The worker-issued MCP identity is re-resolved against its durable run, membership, organization, feature access, and enabled provider for every discovery and call.                               | `tests/Feature/AiChatOrchestrationTest.php`, `tests/Feature/AiDurableStateTest.php`, `tests/Feature/Mcp/MiseLedgerMcpServerTest.php`, `tests/Feature/Mcp/OrganizationDataQueryStockBalanceTest.php` |
| Unauthorized tool access or cost visibility          | The server registers exactly `organization_data_query`; its catalog owns all resources, fields, relationships, aggregate fields, permissions, and feature entitlements. Cost and valuation fields require `costs.view`.                                                                             | `tests/Feature/Mcp/MiseLedgerMcpServerTest.php`                                                                                                                                                     |
| Prompt injection through tenant data                 | MCP instructions classify returned organization content as untrusted data. Input is a bounded, allowlisted query envelope with no caller-supplied organization ID, model class, table, SQL, relation, or column.                                                                                    | `app/Mcp/Servers/MiseLedgerMcpServer.php`, `app/Mcp/OrganizationDataQueryExecutor.php`, `tests/Feature/Mcp/MiseLedgerMcpServerTest.php`                                                             |
| Raw SQL or mutation capability                       | There is one read-only MCP tool. Application data access is compiled from the server catalog through Eloquent and a PostgreSQL read-only transaction. The `SET TRANSACTION READ ONLY` session statements are server-owned safeguards, not model-controlled SQL. No MCP tool calls business actions. | `app/Mcp/ReadOnlyOrganizationDataSession.php`, `tests/Feature/Mcp/MiseLedgerMcpServerTest.php`, `tests/Feature/AiSecurityHardeningTest.php`                                                         |
| Stock-ledger mutation                                | AI routes, actions, jobs, and MCP server do not reference `RecordStockMovement`. The regression captures `StockMovement` and `StockBalance` counts before a hostile stock-adjustment prompt and asserts they are unchanged.                                                                         | `tests/Feature/AiSecurityHardeningTest.php`                                                                                                                                                         |
| Commercial read-only and plan denial                 | `MemberAIAccessResolver` requires active organization, writable commercial access, `ai.assistant` entitlement, and owner or explicit member enablement. Both enqueue and post-dequeue execution recheck that state.                                                                                 | `tests/Feature/Billing/MemberAIAccessResolverTest.php`, `tests/Feature/AiChatOrchestrationTest.php`                                                                                                 |
| Member-control escalation                            | Only an Owner can change a non-owner's `ai_enabled` state. An Owner always retains access and cannot be disabled through that endpoint.                                                                                                                                                             | `app/Actions/Organizations/ToggleOrganizationMemberAIAccess.php`, `tests/Feature/Organizations/OrganizationMembershipTest.php`                                                                      |
| Credential exposure or profile reuse                 | Provider credentials stay in disposable, per-user HMAC-derived `0700` profiles in the private AI worker. Connection metadata and Inertia props contain only allowlisted presentation fields. Provider profile paths and credentials are not logged.                                                 | `tests/Feature/AiCodexConnectionTest.php`, `tests/Feature/AiAssistantPageTest.php`, `tests/Feature/AiSecurityHardeningTest.php`, `tests/Feature/AiWorkerRuntimeTest.php`                            |
| AI outage affecting normal work                      | The normal image excludes Codex. AI has a private read-only worker image and dedicated `ai` / `ai-login` queues; web, normal worker, and scheduler do not consume them. Job serialization and retry handling make redelivery safe.                                                                  | `tests/Feature/AiWorkerRuntimeTest.php`, `tests/Feature/AiChatOrchestrationTest.php`, `docs/deployment.md`                                                                                          |
| Abuse and provider incident blast radius             | Message throttling is keyed by organization member, connection attempts by member, and provider execution by a shared queue limiter. Global and provider flags are rechecked before provider execution.                                                                                             | `tests/Feature/AiSecurityHardeningTest.php`                                                                                                                                                         |
| Sensitive observability                              | Metric-shaped `ai.run.*` and `ai.tool.*` logs carry only lifecycle IDs, provider, status, safe error code, counters, bounded duration, and allowlisted tool metadata. They exclude prompts, responses, arguments, result bodies, profile paths, and credentials.                                    | `app/Support/Ai/AiObservability.php`, `app/Actions/Ai/RecordAiToolCall.php`, `tests/Feature/AiSecurityHardeningTest.php`                                                                            |
| Browser, mobile, and dark-mode regressions           | The drawer is exercised at a mobile viewport with dark mode and no provider network traffic.                                                                                                                                                                                                        | `tests/e2e/ai-assistant.spec.ts`                                                                                                                                                                    |

## Release verification steps

1. Run the focused AI, MCP, billing-access, and worker-runtime tests:

    ```bash
    docker compose exec app php artisan test --compact tests/Feature/AiSecurityHardeningTest.php tests/Feature/AiChatOrchestrationTest.php tests/Feature/AiDurableStateTest.php tests/Feature/AiWorkerRuntimeTest.php tests/Feature/Mcp tests/Feature/Billing/MemberAIAccessResolverTest.php
    ```

2. Run the browser check against the isolated E2E database. It must not be
   configured with live provider credentials:

    ```bash
    docker compose exec vite npm run test:e2e -- ai-assistant.spec.ts
    ```

3. Run the repository quality gate from the application container:

    ```bash
    docker compose exec app composer ci:check
    ```

4. Confirm the deployed services independently: web `/up`, normal worker,
   scheduler, `ai-worker`, and `ai-login-worker`. Confirm that only AI workers
   consume `ai` or `ai-login` and that their worker timeouts remain below the
   960-second AI Redis `retry_after`.

5. Review only structured `ai.run.*` signals and failed-job metadata. Never
   attach prompts, assistant responses, tool arguments/results, profile
   directories, or credentials to a ticket or incident note.

## Staged rollout and rollback

1. Deploy with both AI flags disabled.
2. Set `AI_ENABLED=true` while keeping `AI_OPENAI_ENABLED=false`, then verify
   normal application writes and all non-AI workers remain healthy.
3. Enable `AI_OPENAI_ENABLED=true` only for an internal pilot with the
   existing `ai.assistant` entitlement and Owner-managed member access.
4. Expand by small allowlisted cohorts after daily review of lifecycle logs,
   failed jobs, queue depth, Redis/PostgreSQL health, and this regression
   matrix.
5. Enable globally only after the agreed observation window has no unresolved
   tenant, ledger, credential, or provider failures.

For an incident, set `AI_OPENAI_ENABLED=false` to stop only the provider, or
set `AI_ENABLED=false` to stop all AI routes and queued provider turns. Then
restart or redeploy only the private AI workers to terminate in-flight Codex
processes and discard their disposable profiles. Do not restart web, normal
worker, or scheduler merely to contain an AI incident. Follow the detailed
disconnect, profile cleanup, stuck-run, and queue-health procedure in
[`deployment.md`](deployment.md#ai-operator-runbook).
