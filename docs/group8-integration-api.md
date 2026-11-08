# Group 8 Integration API v1

Base path: `/api/integration/v1`

The endpoints are JSON endpoints protected by the existing authenticated session and Group 8 permissions. They are rate-limited to 120 requests per minute. A future external gateway can add bearer/API-key authentication without changing the resource contract.

## Reference data

- `GET /departments` — departments available for reservations.
- `GET /facilities` — active facilities with `building_name`, `floor_designation`, `location`, capacity, and status.

## Visitor Management

- `GET /visitors?q=` — registered visitor profiles only; supports search by name, registered ID number, email, or company.
- `GET /visitors/{id}` — one registered visitor profile.
- `POST /visitors` — create a registered profile. Required: `full_name`, `id_number`. Optional: `company`, `email`, `phone`.
- `GET /visits?status=` — visit records with visitor ID number, purpose, host, exact building, floor, badge code, and lifecycle status.
- `POST /visits` — pre-register a visit. Use either `visitor_id` for an existing registered profile or `full_name` plus `id_number` to create a profile as part of the request. Required visit fields: `host_employee_id`, `purpose`, `building_name`, `floor_designation`, and `scheduled_at`.

A successful check-in creates the system-generated `BADGE-*` code and stores the badge record with the registered visitor identity, government ID number, purpose of visit, building, floor, and expiry. The same data is shown on the printable visitor ID.

## Facilities Reservation

- `GET /reservations?status=` — reservations with facility location, department, purpose, schedule, requester, and status.
- `POST /reservations` — submit a reservation request. Required: `facility_id`, `department_id`, `purpose`, `start_datetime`, `end_datetime`. Optional: `recurrence_rule`, `recurrence_until`, `attendee_count`.

## All-module read feeds

The allowlisted endpoint below provides synchronized read access for the remaining modules. It does not expose arbitrary database tables, password hashes, or session data:

`GET /data/{resource}`

Supported resources:

`users`, `employees`, `equipment`, `visit-areas`, `vehicles`, `badges`, `reservation-equipment`, `reservation-waitlist`, `documents`, `document-versions`, `document-categories`, `contracts`, `contract-obligations`, `legal-cases`, `legal-deadlines`, `legal-milestones`, `retention-policies`, `retention-assignments`, `notifications`, and `audit-logs`.

Optional query parameters:

- `id` — return a single record by ID where the resource has an ID column.
- `limit` — maximum 500 records; default 100.
- `updated_since` — incremental synchronization filter for resources with `updated_at`.

Each response contains `resource`, `count`, and `data`. Permission checks are applied per resource using the existing RBAC permissions. For example: `GET /api/integration/v1/data/documents?limit=100` or `GET /api/integration/v1/data/legal-cases?updated_since=2026-11-01`.

## Integration notes

IDs are stable database IDs. Datetimes are accepted as date-compatible strings and returned in the application/database format. All write endpoints enforce validation, conflict checks, watchlist controls, and audit/notification behavior. The API does not accept placeholder/sample visitor names: visitor writes require a real system record or a full profile including a registered identification number.

## Server-to-server authentication

Inbound routes are outside the browser session group and require a scoped bearer token:

```http
Authorization: Bearer fams_<prefix>.<secret>
```

Create a client on the server with:

```bash
php artisan integration:client hr1 "HR1 Recruitment" employees:write visitors:write documents:write events:write --hmac
```

If HMAC is enabled, send `X-FAMS-Timestamp` as a Unix timestamp and `X-FAMS-Signature` as `HMAC-SHA256(timestamp + '.' + raw_request_body, signing_secret)`. Requests older than five minutes are rejected. Optional IP allowlists are configured with `--ip=203.0.113.10`.

## HR inbound endpoints

- `POST /inbound/hr1/employees` — upsert employee master records by `employee_no`.
- `POST /inbound/hr1/interviews` — create or update an applicant visitor and interview visit by external IDs. Unknown host employee numbers are rejected.
- `POST /inbound/hr{1-4}/documents` — multipart document ingest with checksum validation and retention-source rules.
- `POST /inbound/hr1/events` — process `employee.separated` and recalculate retention starts for the employee's documents.
- `POST /visitors/{id}/verify-id` — record an applicant's ID verification at the lobby.
- `GET /visitors/{id}/history` — return the visitor profile, visit history, badges, areas, vehicles, NDA state, and summary counts.

The HR document endpoint requires `external_id`, `doc_type`, `title`, and `file`. Payroll document types also require `period_end`. The migration seeds editable starter rules for HR1–HR4 document types; confirm the actual retention periods with the legal/compliance owner before production use.
