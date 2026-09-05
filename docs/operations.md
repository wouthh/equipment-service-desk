# Local operations

## Setup and ownership

Init creates a regular ignored 0600 configuration file only if absent, refuses unsafe existing files, and never overwrites. Keep the checkout and generated tokens under your control; local backups need equivalent protection.

The production image runs as non-root UID/GID 1000. Make supplies the current host UID/GID for development bind mounts; direct Compose calls on other identities must set LOCAL_UID and LOCAL_GID. Never make the checkout world-writable. Fedora mounts use SELinux relabel options. Containers supply PHP and Composer.

Up starts the API, proxy, database, and worker; down preserves the development database volume. PostgreSQL 18 mounts at `/var/lib/postgresql` with no host database port.

## Accounts and tokens

Prefix console operations with `docker compose --env-file .env.local run --rm --no-deps app php bin/console`:

- `app:user:create <handle> <requester|coordinator|technician> <label>`
- `app:user:disable <handle>`
- `app:token:issue <handle> --ttl=3600`
- `app:token:revoke <token-id>`

Issuance prints an ID and protected curl-file path, not the token. Avoid verbose curl, shell tracing, and raw header command arguments. Revoke before removing the local token file when access should end immediately. Synthetic fixtures are dev/test-only, idempotent, refuse changed identities, and never purge.

## Migrations and rollback

Migrate uses separate privileged credentials in a one-off container. Runtime auto-setup is disabled; migrations create the queue, constraints, indexes, and grants.

Before migrating data worth preserving, stop writers and create a protected local `pg_dump --format=custom` backup. Verify with `pg_restore --list`, restore into a new disposable database, and compare records. Never put backups in tracked paths or CI artifacts.

Data-creating migrations deliberately refuse destructive down operations. Revert code only when schema compatibility is established; otherwise stop writes and restore a verified backup into a new database while preserving the failed state. No automatic destructive rollback or volume deletion is supplied.

## Worker recovery

The worker consumes reports. Inspect bounded counts with `messenger:stats reports failed` and job status through the API. A crash before commit leaves no ready result; a crash after commit can redeliver safely.

Three retries wait 1, 2, and 4 seconds. Terminal failures record a generic code; correct the cause and request a new report/key. Do not regenerate terminally failed jobs. Transport payloads retain typed ID/retry metadata, not exception text.

A database outage can also prevent the terminal-status update. After restoring connectivity, inspect pending jobs and both queues and reconcile deliberately. The failure transport is not a guarantee during database failure.

Redelivery timeout is 120 seconds; SQL statements have a 20-second timeout and lock waits are bounded. This is not a real-time guarantee on a paused or hostile host.

## Observability and retention

Liveness checks process handling; readiness checks required database tables, not worker availability. Correlation IDs connect HTTP failures to sanitized logs; audit history explains domain changes.

Reports and idempotency records are retained in this bounded demo. Sustained operation needs growth monitoring and retention design. Filesystem rate limits are not shared across machines.

No production deployment, remote backup service, scheduler, external monitoring stack, or destructive maintenance is installed.
