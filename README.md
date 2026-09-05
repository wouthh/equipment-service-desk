# Equipment Service Desk

Equipment Service Desk is a synthetic PHP/Symfony backend for equipment requests, policy-based triage, technician assignment, and operational reporting.

**Status: maintained reference implementation.** Original synthetic software for a local demonstration; no production deployment, real users, or measured-scale claim.

The useful part is what happens when requests are retried, assignments conflict, authorization fails, or a worker stops—not just the happy path.

## The complete workflow

Register equipment → submit a request → confirm triage → assign a technician → record resolution → inspect history and download an asynchronous report.

- Requesters see their own requests; technicians see current assignments, including completed work.
- Coordinators triage and assign, but cannot resolve on a technician's behalf.
- Policy decisions retain their version and original inputs.
- Idempotency records, state changes, audit entries, and successful responses commit together.
- Optimistic versions prevent silent assignment overwrites.
- Report creation and queue insertion share one PostgreSQL transaction. Redelivery does not duplicate completed results.

## Run locally

Requirements: Docker Engine with Compose v2 or newer, Git, Make, and Python 3. Containers supply PHP and Composer. Make passes the current Linux UID/GID to bind-mounted development services; see [operations](docs/operations.md) for direct Compose use.

```sh
make init
make install
make db-up
make migrate
make fixtures
make up
make token ACTOR=coordinator-a
```

The API listens only on `http://127.0.0.1:8089`. Token issuance writes an ignored, owner-only curl configuration file and prints only its relative filename and token ID. Use the reported file with `curl --config <reported-file>`; do not paste tokens into commands, logs, or source.

`.env.example` documents the variables. `make init` generates `.env.local` without overwriting existing configuration; Compose and Symfony then load it. The database has no host port. No real equipment or external service is contacted.

## Try the behavior

```sh
make walkthrough
make check
```

The walkthrough creates a separate disposable database and loopback-only HTTP stack. It exercises the complete workflow, replay, cross-user denial, stale assignment, an actual worker, and CSV download, then removes only its own containers and temporary token files.

`make check` runs unit, PostgreSQL integration, API/OpenAPI contract, concurrency, worker, migration, static-analysis, style, configuration, documentation, dependency-audit, redacted-scan, walkthrough, and production-image smoke gates. It does not format, push Git, publish images, or deploy. See [testing](docs/testing.md) for commands and evidence limits.

## Architecture and contract

A modular monolith: **Access**, **Equipment**, **ServiceRequests**, **Reporting**, and **Audit**, with explicit application handlers and one database transaction boundary.

PHP 8.4, Symfony 7.4 LTS, Doctrine ORM 3.6 / DBAL 4.4 / Migrations 3.9, PostgreSQL 18, PHPUnit 13, PHPStan level 10, and PHP-CS-Fixer. Compatible patches are locked; base images and CI actions are digest/SHA-pinned.

- [Architecture](docs/architecture.md) and [domain rules](docs/domain.md)
- [OpenAPI 3.0.4](openapi/openapi.yaml) and [API usage](docs/api.md)
- [Security](docs/security.md), [operations](docs/operations.md), and [walkthrough](docs/walkthrough.md)
- [Contributing](CONTRIBUTING.md) and [agent guidance](AGENTS.md)

## Deliberate limits

One organization, three roles, no frontend, uploads, email, external identity provider, inventory, or configurable rules language. Reports are bounded to 31 days, 10,000 rows, and 5 MiB. Reports and idempotency records have no automatic retention policy.

Audit history is application-level, not tamper-proof. Queue delivery is at least once, not exactly once. Nonlocal deployment requires TLS and a separately reviewed configuration.

Original code and documentation use [Apache-2.0](LICENSE). Dependencies retain their own [licenses and notices](docs/dependencies.md).
