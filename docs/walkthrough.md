# Synthetic walkthrough

Run init, install, then `make walkthrough`. It creates a uniquely named disposable Compose stack with temporary database storage and a loopback HTTP port; it never reuses or purges development data.

The script provisions fixtures and gets short-lived tokens through the real console command, then verifies:

1. Readiness.
2. Request submission against synthetic equipment.
3. Identical retry response.
4. Another requester's denied read.
5. Coordinator triage and assignment.
6. Rejection of an old assignment version.
7. Resolution by the assigned technician.
8. Asynchronous report generation with bounded polling.
9. Download contents without request or resolution prose.

Success is printed only after real HTTP and worker checks pass. Cleanup removes only this stack and its exact temporary token files.

For manual inspection use the [README setup](../README.md), then `make token ACTOR=coordinator-a`. Pass the reported curl file:

```sh
curl --config <reported-file> http://127.0.0.1:8089/api/v1/me
```

The angle-bracket filename is an instruction placeholder, not a literal executable filename or credential. The [API contract](api.md) defines synthetic payloads, UUID keys, and ETags.
