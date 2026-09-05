# Security boundaries

A single-organization synthetic application: no public identity administration, cookies, cross-origin browser client, provider integrations, uploads, or executable user content.

| Threat | Control and evidence |
|---|---|
| Token theft/replay | Random 256-bit opaque token; stored digest; expiry/revocation/active checks; header only |
| IDOR | Scoped lists/details/history and object authorization before replay; negative role matrix |
| Mass assignment | Explicit DTOs and unknown-field rejection |
| Lost updates | ETag plus database version condition; two independent processes |
| Duplicate commands | Principal/key uniqueness and transactional response; observed blocking regression |
| SQL injection | Bound values; allowlisted filtering/ordering |
| Queue injection | Exact versioned JSON type and ID; no PHP-native deserialization |
| Authority drift | Fresh active-coordinator worker check and account lock |
| Partial report | Row lock and atomic bounded CSV/audit; failpoint and redelivery tests |
| CSV injection | Fixed columns, quoting, formula neutralization |
| Secret leakage | Generated ignored config, owner-only token files, sanitized logs, scans |
| Resource exhaustion | Body/page/date/row/byte/rate/pending-job limits |
| Dependency risk | Locked stable packages, no Composer plugins, audits, pinned images/actions |

API and worker receive runtime credentials, not migration credentials. Runtime cannot create schema objects or update/delete audit entries. The local bootstrap/migration account is privileged and never belongs in the API environment.

HTTP is exposed only on loopback; internal traffic stays on the local Compose network. Nonlocal access requires TLS and a separately reviewed network/deployment configuration. Do not port-forward this stack publicly.

Configuration validation permits only the local Compose database host and documented database names. Arbitrary connection-query overrides and external database destinations fail closed before connection.

Proxy logs contain method/status/request ID, not URLs, bodies, or headers. Native proxy error logging is suppressed because it can include raw URLs. Application error logs retain classification, exception class, and correlation ID only. Audit details are allowlisted and exclude free-form request content.

Local filesystem limiters assume one API instance and a trusted, quiescent checkout. They are not distributed quotas or a hostile-host sandbox. Audit history is not tamper-proof, and a database administrator or compromised runtime process remains outside these protections. Retention and sustained-deployment sizing are outside v0.1.

Trusted console account creation/disable and token issue/revocation record `local.*` audit events in the same database flush/transaction as the change. Their null actor identifies the local operator boundary without impersonating an application user; no workstation identity, token value or digest is recorded. Token-file creation and the database are not a distributed transaction: a handled issuance failure removes its newly created file, while an abrupt process/host failure can leave an unused local file requiring operator reconciliation.
