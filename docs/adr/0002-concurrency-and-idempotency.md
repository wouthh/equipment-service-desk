# ADR 0002: Concurrency and idempotency

Status: accepted.

An HTTP precondition alone cannot prevent two handlers observing the same version. Use a required ETag plus Doctrine's database optimistic version condition.

Reserve each principal/key uniquely. Store its fingerprint and successful response with domain effects. Concurrent identical commands wait; changed input conflicts; failed commands retain no successful replay.

Recheck authority before cached data, and recognize replay before rejecting an old ETag. Retain records for this bounded demo; expiration must not silently permit duplicate execution.

Storage grows and lock timeouts require retry. This is transaction-scoped consistency, not global exactly-once behavior.
