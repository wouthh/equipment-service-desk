# ADR 0003: Transactional report delivery

Status: accepted.

Use Messenger's Doctrine transport on the same DBAL connection as report creation. Persist job, audit, and queue insertion together. A broker and relay would add infrastructure without strengthening this single-database demonstration.

The worker locks the job and atomically publishes bounded CSV, metadata, and completion audit. Ready results are immutable; redelivery is a no-op. Versioned JSON contains only a report ID.

Database and worker capacity remain coupled. A failure transport cannot eliminate a database outage; terminal-status reconciliation may be necessary. CSV storage is limited to the stated 10,000-row/5-MiB bound.
