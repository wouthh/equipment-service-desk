# Testing and validation

All data is original and synthetic. PostgreSQL tests use a dedicated ephemeral Compose database and never purge the development database.

| Command | Gate |
|---|---|
| `make test-unit` | Policy, state/action matrix, snapshots, CSV, serializer, configuration |
| `make test-integration` | Transactions, process conflicts, idempotency, real retries, upgrades, privileges |
| `make test-api` | Roles, input, tokens, replay, reports, OpenAPI exchanges |
| `make analyse` | PHPStan level 10 with Symfony/Doctrine, no baseline |
| `make style-check` | Symfony style, no risky rules |
| `make validate` | Composer, PHP syntax, container, YAML, schema, Compose |
| `make docs-check` | Contract/route parity, required docs, local links, licensing |
| `make audit` | Locked Composer advisories |
| `make scan` | Redacted Gitleaks current indexed files and commit range |
| `make walkthrough` | Actual loopback HTTP, proxy, CLI tokens, database and worker |
| `make docker-build` / `make smoke` | Production image and offline non-root/runtime checks |
| `make check` | All non-formatting gates and staged/working diff checks |

Run init/install first. Stage intended new public files before scanning: ignored config, vendor, and tokens are deliberately not exported. No real provider is used.

Exact assertions use frozen time and deterministic IDs. Concurrent assignment contenders both hydrate one version before a barrier opens. Concurrent idempotency verifies database blocking before releasing the first transaction; it does not rely on hopeful sleeps.

Worker tests use the actual Doctrine transport and configured retry policy. A pre-commit failpoint proves rollback; repeated delivery after commit proves immutable output and a single completion audit. Migration tests create their own database, seed core data, upgrade, and compare preserved records.

Successful requests and every test response validate against OpenAPI; negative requests intentionally need not satisfy the request schema. Production has a separate image smoke gate.

Local results do not prove hosted CI, production scale, external service status, or browser UI quality. Scanner/network/infrastructure failures remain failures or unavailable checks. Additional private privacy scans are publication checks and are not copied into source.
