# Domain and invariants

Equipment has an immutable UUID, unique uppercase tag, name, criticality, and registration time. There is no editing, retirement, deletion, inventory, or procurement subsystem.

| Role | Visibility | Commands |
|---|---|---|
| Requester | Equipment and own requests/history | Submit; withdraw own submitted request |
| Coordinator | All equipment, requests, history, reports | Register; triage; assign/reassign; cancel nonterminal requests; report |
| Technician | Equipment and current assignments, including completed work | Resolve assigned request |

Coordinators cannot resolve for technicians; technicians cannot self-assign. Accounts have one role and are provisioned through trusted console commands, not public administration endpoints.

| Action | From | To |
|---|---|---|
| Submit | New | submitted |
| Triage | submitted | triaged |
| Assign | triaged | assigned |
| Reassign | assigned | assigned, different technician |
| Resolve | assigned | resolved |
| Cancel | submitted, triaged, assigned | cancelled |

Only an owning requester can withdraw, only while submitted. Coordinators can cancel any nonterminal request. Resolution/cancellation require nonblank bounded prose. Terminal states never reopen. Symfony Workflow defines the graph; handlers enforce authority and required data.

## Triage

| Criticality | Confirmed impact | Priority | Target after submission |
|---|---|---|---|
| Critical | Stopped | High | 4 hours |
| Normal | Stopped | Medium | 24 hours |
| Critical | Degraded | Medium | 24 hours |
| Normal | Degraded | Low | 72 hours |

`triage-v1` calculates elapsed UTC seconds, not business hours. Late triage may already be overdue. The coordinator confirms or corrects impact. Version, inputs, duration, priority, and due time persist together; reassignment cannot reset them. A test-only alternate policy demonstrates immutable existing snapshots. Targets are fictional, not safety guarantees or contractual SLAs.

## Reports

Coordinator criteria are a submission interval `[from,to)` of at most 31 days. Results reflect current state during generation, not reconstructed interval-end state. One pending report per coordinator is enforced.

CSV ordering is submission time then ID. Limits are 10,000 rows and 5 MiB, without silent truncation. No descriptions, resolution prose, names, or tokens are exported. Duration uses submission/resolution timestamps; overdue-at-generation excludes terminal requests. Lateness means strictly after the due timestamp.

Ready bytes never change. A failed report requires a new request and key. Retention must be designed before sustained operation.
