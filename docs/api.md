# API usage

The canonical [OpenAPI 3.0.4 document](../openapi/openapi.yaml) is served at `/openapi.yaml`. Controllers expose explicit operations, not generic entities.

Use only the Authorization bearer header. Query/body tokens do not authenticate. Default lifetime is one hour, maximum 24 hours.

Every business POST requires a UUID-form `Idempotency-Key`. Keep the key for exact retries. Different input or preconditions with the same principal/key return 409. Records are retained in v0.1.

Transitions require the exact quoted ETag: `"<request-uuid>:<version>"`. Missing If-Match returns 428; stale versions return 412. A successful replay returns stored bytes and `Idempotency-Replayed: true`, subject to current access.

## Input and output

Business input is a JSON object of explicitly allowlisted string fields. Unknown fields, whitespace-only required values, invalid enums, and oversized fields fail validation. Requesters cannot supply ownership, state, priority, assignment, or timestamps.

Lists use page/limit: 25 default, 100 maximum, bounded page number, deterministic ordering. Request lists accept an exact state filter; ownership always applies. Creation returns 201 with Location, report creation 202, and transitions an updated representation with ETag.

| Status | Meaning |
|---|---|
| 400 / 415 / 422 | Malformed / wrong media / invalid fields |
| 401 / 403 / 404 | Unauthenticated / forbidden visible action / absent or invisible |
| 409 | Invalid transition, duplicate constraint, pending report, or key conflict |
| 412 / 428 | Stale / missing version |
| 413 / 429 | Body / rate limit |
| 503 | Temporary storage/readiness failure |

Application errors and proxy body/rate-limit rejections use RFC 9457 problem JSON with codes and correlation IDs. Application field violations identify fields, not rejected values or exception details. Malformed HTTP rejected before request routing is outside the JSON business contract.

Limits: 120 API calls per principal/minute; five new report attempts/hour; proxy 5 requests/second with burst 20. These are single-instance protections, not DDoS protection.

See [the walkthrough](walkthrough.md). Tests validate successful requests and both successful and rejected responses against the contract.
