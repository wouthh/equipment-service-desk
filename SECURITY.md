# Security policy

This is original synthetic reference software for a local demonstration, not a production service or security certification.

Do not use production credentials or data. Do not submit tokens, database URLs, sensitive logs, or exploit data in public issues. No dedicated reporting mailbox is configured; do not reuse a profile contact address as project configuration. Establish an appropriate private reporting route before publication if needed.

If a local token is exposed, revoke its ID using the console command, remove that local token file, and review the access boundary. Repository cleanup does not revoke tokens or establish external-service security.

See [the threat model](docs/security.md), [operations](docs/operations.md), and [dependency inventory](docs/dependencies.md). No provider testing, credential validation against external systems, or deployment belongs to the default checks.
