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

## Integration notes

IDs are stable database IDs. Datetimes are accepted as date-compatible strings and returned in the application/database format. All write endpoints enforce the existing RBAC permissions, validation, conflict checks, watchlist controls, and audit/notification behavior. The API does not accept placeholder/sample visitor names: visitor writes require a real system record or a full profile including a registered identification number.
