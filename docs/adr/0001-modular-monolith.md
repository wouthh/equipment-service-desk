# ADR 0001: Modular monolith

Status: accepted.

One organization and one service-request workflow do not justify distributed services. Named modules separate access, equipment, requests, reporting, and audit while sharing PostgreSQL transactions.

Doctrine attributes are accepted on records; the policy remains independently testable. Explicit handlers avoid generic entity exposure and a second application framework.

Capacity and deployment remain coupled. Independent scaling, tenancy, and a broker are not promised future work.
