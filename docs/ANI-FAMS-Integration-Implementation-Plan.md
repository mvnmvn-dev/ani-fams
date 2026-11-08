# ANI-FAMS — Cross-Module Integration Plan (Group 8)

**System:** Facilities & Administrative Management (Laravel 12, PostgreSQL)
**Scope:** how Group 8 connects to the other 9 groups, what the panel asked for first, and how to build it.
**Basis:** the panel's checklist image + the `ani-fams.zip` codebase (routes, services, schema, existing `integration/` folder).

> Anything about *other groups' systems* is a proposal inferred from the module names in the checklist. I have not seen their code. Agree on the payload contracts in section 6 with those groups before building against them.

---

## 1. Panel notes — translated

| # | Original (Taglish) | English |
|---|---|---|
| 4 | *Records retention & compliance — documents HR1 to HR4. Documents payroll: kukunin natin ang data mula sa HR then ilagay sa records* | **Records Retention & Compliance** must hold the documents of **HR1 to HR4**. For payroll documents, we **pull the data from HR and store it in Records**. |
| 2 | *Visitors management — HR1. Need kung saan galing ang details ng visitors or applicants. Need a list of visitors and history* | **Visitor Management** connects to **HR1**. We must define **where the visitor details come from — walk-in visitors or applicants**. We need **a list of visitors and their visit history**. |

**Reading of "HR1–HR4"** (from the numbered groups in the image — confirm with the panel):

| Label | Group in checklist | Typical documents |
|---|---|---|
| HR1 | #1 Recruitment & Onboarding + Core HR | resumes, job offers, signed employment contracts, onboarding checklists, 201-file / employee records |
| HR2 | #2 Workforce Management | attendance/DTR, timesheets, leave records, shift schedules |
| HR3 | #3 Performance & Development | appraisals, training records/certificates, succession plans |
| HR4 | #4 Payroll & Benefits | payroll registers, payslips, compensation, claims/reimbursements, HMO/benefits records |

---

## 2. Where the system stands today

What already exists (verified in code):

- Local `employees`, `departments`, `visitors`, `visits`, `documents`, `document_versions`, `retention_policies`, `retention_assignments`, `legal_holds` tables.
- `VisitorService::preRegister()` already does watchlist matching, host notification, audit + automation logging. It does **not** itself require `id_number` — only the API validator does.
- `RetentionService::assignPolicy()` / `executeDisposal()` with legal-hold protection, and retention policy templates (BIR, SEC, DPA, DOLE).
- `DocumentService::uploadDocument()` with versions, checksum, OCR flag, AI classification, access control.
- `api/integration/v1` with reads and a few writes + an allowlisted `GET /data/{resource}` feed.

**Gaps that block the panel's requests:**

| # | Gap | Why it matters |
|---|---|---|
| G1 | Integration routes sit inside the `auth` (session) group. | Another group's server has no browser session, so it cannot call you. You need service-to-service auth. |
| G2 | No inbound endpoints for HR data, and no `source_system` / `source_ref` columns. | You can't tell if a visitor is an applicant, or if a document came from HR4, or avoid duplicates on re-send. |
| G3 | `employees` is a local table with no external ID. | Visits require `host_employee_id`; HR1 must be able to name the host. |
| G4 | `POST /visitors` and `POST /visits` require `id_number`. | Applicants often have no ID on file before the interview. |
| G5 | No per-visitor history view/endpoint. | Panel explicitly asked for "list of visitors and history". |
| G6 | `retention_policies.trigger_event` has no "employee separation". | Labor/payroll retention normally counts from separation or period end, not upload date. |
| G7 | Uploads assume a logged-in owner (`documents.owner_id`). | System-to-system ingest needs a service owner account. |

---

## 3. Integration map and priority

**Priority key**
- **P0** — requested by the panel. Build first, demo first.
- **P1** — foundation P0 cannot work without.
- **P2** — high-value links, build after P0/P1 are stable.
- **P3** — optional / read-only / later.

| Pri | Source → Target | Group 8 module | Data exchanged |
|---|---|---|---|
| **P0-A** | **HR1** Applicant & Recruitment Mgmt → | **Visitor Management** | applicant profile, interview schedule, host (recruiter/interviewer), building/floor; status callback (arrived / no-show) |
| **P0-B** | **HR1–HR4** → | **Records Retention & Compliance** (through Document Mgmt) | HR documents + metadata; **HR4 payroll** records; employee-separation event |
| **P1-A** | HR1 Core HR / Employee Records → | `employees`, `departments`, `users` | employee master (hosts, document owners, reservation requesters) |
| **P1-B** | *(cross-cutting)* | Integration foundation | API-key auth, idempotency, sync log, retry |
| **P1-C** | HR → | Document Management | ingest funnel: Records sits on top of `documents`, so HR files land there first |
| **P1-D** | HR1/HR2 separation event → | Retention + Legal | retention start date; `legal_employee_departures` |
| P2 | SCM Supplier/Vendor Mgmt → | Visitor Mgmt | vendor/supplier visitors and contacts (`visitor_type = vendor`) |
| P2 | Visitor Mgmt ↔ Fleet (FVM/VRDS) | Visitor vehicles | plate numbers, visitor parking vs fleet bays |
| P2 | Fleet VRDS, SCM Smart Warehousing → | Facilities Reservation | shared bays / staging areas; avoid double booking |
| P2 | SCM Procurement, PO, Supplier → | Contract Management | supplier contracts, PO reference, vendor scores |
| P2 | HR1 New Hire Onboarding → | Contract Management | employment contracts |
| P2 | Contract obligations, Legal case costs, Reservation costs → Finance (AP, Budget, GL) | Contract / Legal / Reservation | payable obligations, case budget, facility cost |
| P2 | Finance Tax Mgmt, GL → | Records Retention | tax/financial records (BIR template already seeded) |
| P2 | #10 Subscription & Commission Mgmt → | Contract Management | merchant/commission agreements |
| P3 | #9 Returns/Refund, #10 Customer Support → | Legal Management | disputes escalated to a legal case |
| P3 | #9/#10 Order and Payment records → | Records Retention | transaction record retention |
| P3 | SCM DTRS ↔ | Document Management | document tracking reference |
| P3 | HR2 Shift/Leave → | Reservation / Visitor | host availability warnings |
| P3 | Group 8 → HR Analytics, Admin Dashboard | all | already covered by `GET /data/{resource}` |

**Why P0 is ordered this way:** both panel items depend on the same foundation (P1-A, P1-B). Visitors (P0-A) is the smaller change and proves the pattern; Records (P0-B) reuses it with file handling on top.

---

## 4. Target architecture

```
 HR1 ─┐                       ┌──────────────────────────────────────┐
 HR2 ─┤  push (webhook)       │  FAMS  /api/integration/v1/inbound/* │
 HR3 ─┼──────────────────────►│  1. AuthenticateIntegrationClient    │
 HR4 ─┘  or pull (scheduler)  │  2. Idempotency via external_refs    │
        ▲                     │  3. Domain service (existing)        │
        │ status callback     │  4. integration_log + AuditLogger    │
        └─────────────────────┴──────────────────────────────────────┘
                                   │                    │
                          VisitorService         DocumentService → RetentionService
```

Rules:

1. **Single source of truth per entity.** HR owns applicants/employees/payroll. FAMS stores a *reference + snapshot*, never edits HR data.
2. **Reuse existing services.** Inbound controllers call `VisitorService`, `DocumentService`, `RetentionService` so watchlist, audit, notifications and legal-hold rules still apply.
3. **Idempotent.** Same `(source_system, resource, external_id)` twice = update, not duplicate.
4. **Push and pull share one code path.** Other groups may only expose a read API; a scheduled pull calls the same ingest services.

---

## 5. Implementation steps

### Phase 0 — Foundation (P1-A, P1-B, P1-C)

**5.0.1 Migration `2026_11_09_000001_integration_foundation.php`**

```php
Schema::create('integration_clients', function (Blueprint $t) {
    $t->increments('id');
    $t->string('system_code', 20)->unique();      // hr1 hr2 hr3 hr4 fin scm fleet ecom
    $t->string('name', 150);
    $t->string('key_prefix', 12)->index();
    $t->string('key_hash');                        // Hash::make(secret)
    $t->text('signing_secret')->nullable();        // encrypted cast; optional HMAC
    $t->json('scopes');                            // ["visitors:write","documents:write",...]
    $t->string('allowed_ips', 500)->nullable();
    $t->boolean('is_active')->default(true);
    $t->timestamp('last_used_at')->nullable();
    $t->timestamps();
});

Schema::create('external_refs', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->string('source_system', 20);
    $t->string('resource', 50);                    // applicant, interview, employee, document
    $t->string('external_id', 100);
    $t->string('local_entity', 50);                // visitors, visits, employees, documents
    $t->unsignedBigInteger('local_id');
    $t->char('payload_hash', 64)->nullable();
    $t->timestamp('last_synced_at')->nullable();
    $t->unique(['source_system', 'resource', 'external_id']);
    $t->index(['local_entity', 'local_id']);
});

Schema::create('integration_log', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->string('source_system', 20);
    $t->string('direction', 3);                    // in | out
    $t->string('resource', 50);
    $t->string('external_id', 100)->nullable();
    $t->string('status', 20);                      // ok | duplicate | rejected | error
    $t->text('error')->nullable();
    $t->timestamp('created_at')->useCurrent();
});

Schema::table('employees', function (Blueprint $t) {
    $t->string('source_system', 20)->nullable();
    $t->string('employee_no', 50)->nullable();
    $t->unique(['source_system', 'employee_no']);
});
```

Use the Schema builder, not raw MySQL, so it runs on PostgreSQL and the SQLite test DB. Follow the `Schema::hasColumn` guards used in `2026_11_07_000001_group8_integration_fields.php`.

**5.0.2 Middleware `AuthenticateIntegrationClient`** (alias `integration`, usage `integration:visitors:write`)

```php
public function handle(Request $request, Closure $next, string $scope)
{
    $token = $request->bearerToken();                       // fams_<prefix>.<secret>
    abort_unless($token && str_contains($token, '.'), 401);
    [$prefix, $secret] = explode('.', $token, 2);

    $client = IntegrationClient::where('key_prefix', $prefix)->where('is_active', true)->first();
    abort_unless($client && Hash::check($secret, $client->key_hash), 401);
    abort_unless(in_array($scope, $client->scopes, true), 403);

    if ($client->allowed_ips && ! in_array($request->ip(), array_map('trim', explode(',', $client->allowed_ips)))) {
        abort(403);
    }
    if ($client->signing_secret) {                           // optional HMAC + replay window
        $ts = (int) $request->header('X-FAMS-Timestamp');
        abort_unless(abs(time() - $ts) <= 300, 401);
        $expected = hash_hmac('sha256', $ts . '.' . $request->getContent(), decrypt($client->signing_secret));
        abort_unless(hash_equals($expected, (string) $request->header('X-FAMS-Signature')), 401);
    }

    $client->forceFill(['last_used_at' => now()])->save();
    $request->attributes->set('integration_client', $client);
    return $next($request);
}
```

**5.0.3 Routes.** Put inbound routes in a new `routes/integration.php`, registered in `bootstrap/app.php` **outside** the `auth` group, with `throttle:120,1`. CSRF is already exempted for `api/integration/v1/*`.

**5.0.4 System user.** Create a seeded service account (e.g. `integration@system.local`, role with only document upload). Set `config('integration.system_user_id')`. `DocumentService` and `AuditLogger` use `Auth::id()` today — add an optional actor override (and confirm `AuditLogger` tolerates a null user; I did not open that file).

**5.0.5 Employee sync (P1-A).** `POST /inbound/hr1/employees` upserts `employees` + `departments` by `employee_no`. Link to `users` by email when one exists. This is what lets HR1 name a host by `employee_no`.

**5.0.6 Config.** `config/integration.php` with per-system `base_url`, `token`, and pull toggles; secrets only in `.env`.

---

### Phase 1 — Visitor Management ← HR1 (P0-A)

**Business rule.** A visitor has a **type** and a **source**:

| `visitor_type` | `source_system` | Example |
|---|---|---|
| `guest` | `manual` | walk-in, pre-registered by receptionist |
| `applicant` | `hr1` | interviewee scheduled in Recruitment Mgmt |
| `vendor` | `scm` / `manual` | supplier rep (P2) |

This answers the panel's "where do visitor details come from" directly, and it is visible in the UI and the API.

**1.1 Migration**

```php
Schema::table('visitors', function (Blueprint $t) {
    $t->string('visitor_type', 20)->default('guest');     // guest|applicant|vendor|contractor|delivery
    $t->string('source_system', 20)->default('manual');
    $t->string('source_ref', 100)->nullable();            // HR1 applicant id
    $t->timestamp('id_verified_at')->nullable();
    $t->unsignedInteger('id_verified_by')->nullable();
    $t->index(['source_system', 'source_ref']);
});
Schema::table('visits', function (Blueprint $t) {
    $t->string('visit_category', 30)->default('general'); // general|interview|onboarding
    $t->string('source_system', 20)->default('manual');
    $t->string('source_ref', 100)->nullable();            // HR1 interview schedule id
    $t->index(['source_system', 'source_ref']);
});
```

**1.2 Inbound endpoint** — `POST /api/integration/v1/inbound/hr1/interviews` (scope `visitors:write`)

```json
{
  "applicant": { "external_id": "APP-2026-0142", "full_name": "Juan Dela Cruz",
                 "email": "juan@example.com", "phone": "09171234567",
                 "position_applied": "Warehouse Supervisor" },
  "interview": { "external_id": "INT-2026-0088", "scheduled_at": "2026-11-12 09:30:00",
                 "host_employee_no": "EMP-0021", "purpose": "Job interview — Warehouse Supervisor",
                 "building_name": "HQ Building A", "floor_designation": "2nd Floor" }
}
```

Handler `HrInterviewIngestService`:

1. Resolve visitor: `external_refs(hr1, applicant, external_id)` → else match by email → else create with `visitor_type=applicant`, `source_system=hr1`, `source_ref`.
2. Resolve host: `employees.employee_no = host_employee_no` (reject 422 if unknown).
3. Call `VisitorService::preRegister([... 'visitor_id' => $id, 'visit_category' => 'interview'])`. Watchlist logic stays intact.
4. Save `external_refs` for the applicant and the interview; write `integration_log`.
5. Re-send with the same `interview.external_id` = update schedule/host, not a new visit. Cancelled → `DELETE`/`status: cancelled` event moves the visit to `no_show`/cancelled.

Return `{ "success": true, "visit_id": 123, "visitor_id": 45, "status": "pending_approval" }`.

Decision for the panel: by default the host still has to approve (existing flow). Add `config('integration.auto_approve_sources', [])` if HR1 scheduling should count as approval.

**1.3 Applicant ID rule (G4).** Keep `id_number` optional when `visitor_type = applicant`. Enforce it at **check-in**: in `VisitorService::checkIn()`, block with "Verify the applicant's ID at the lobby" unless `id_number` is set; add a lobby action that records it and sets `id_verified_at/by`. Visitors and guests keep the current rule.

**1.4 Status callback to HR1.** Fire an event `VisitStatusChanged` from `checkIn`, `checkOut`, no-show; a queued listener `POST`s to the HR1 callback URL (`config('integration.systems.hr1.callback_url')`) with `{interview_external_id, status, check_in_time, check_out_time}`. Failures go to `integration_log` and retry through the existing `jobs` table.

**1.5 List and history (G5)** — the panel's explicit request

- `GET /visitors/{id}/history` (permission `visitor.view`) and `GET /api/integration/v1/visitors/{id}/history`.
- Query: all `visits` for the visitor joined to host, badge, areas, vehicles, NDA status; newest first.
- Summary block: `total_visits`, `first_visit`, `last_visit`, `no_shows`, `last_host`.
- UI (`resources/views/visitors/index.blade.php`): add **Type** and **Source** filters, an **Applicants** tab, and a history drawer per visitor.
- Export: extend `exportCsv`/`exportPdf` with `type`, `source`, and per-visitor history.

**1.6 Privacy.** Applicant data is personal data. Link applicant visitor records to the existing **Data Privacy Act** retention template through Phase 2 so they are disposed on schedule.

---

### Phase 2 — Records Retention ← HR1–HR4 (P0-B)

**Design.** HR documents enter through Document Management, then retention policies attach. This keeps one document store (versions, checksum, access control) and lets Records show everything without a second copy.

**2.1 Migration**

```php
Schema::table('documents', function (Blueprint $t) {
    $t->string('source_system', 20)->default('manual');    // hr1..hr4
    $t->string('source_ref', 100)->nullable();
    $t->string('source_doc_type', 50)->nullable();         // payroll_register, appraisal, ...
    $t->string('employee_no', 50)->nullable();
    $t->date('period_start')->nullable();
    $t->date('period_end')->nullable();
    $t->unique(['source_system', 'source_ref']);
    $t->index('employee_no');
});

Schema::create('retention_source_rules', function (Blueprint $t) {
    $t->increments('id');
    $t->string('source_system', 20);
    $t->string('doc_type', 50);
    $t->unsignedInteger('category_id')->nullable();
    $t->unsignedInteger('policy_id');
    $t->string('confidentiality', 20)->default('restricted');
    $t->boolean('is_active')->default(true);
    $t->unique(['source_system', 'doc_type']);
});
```

Add sub-categories under the existing **HR Documents** category: *HR1 Recruitment & Onboarding, HR1 Core HR, HR2 Workforce, HR3 Performance & Development, HR4 Payroll & Benefits.*

**2.2 Inbound endpoint** — `POST /api/integration/v1/inbound/{hr1|hr2|hr3|hr4}/documents` (multipart, scope `documents:write`)

| Field | Notes |
|---|---|
| `external_id` | HR-side document ID (idempotency key) |
| `doc_type` | must exist in `retention_source_rules` for that system |
| `title`, `description` | |
| `employee_no` | optional; required for per-employee records |
| `period_start`, `period_end` | required for payroll |
| `file` | PDF/CSV/XLSX, or `file_url` for pull mode |
| `checksum_sha256` | verified against the received file |

Handler `HrDocumentIngestService`:

1. Verify checksum; reject mismatches (log `rejected`).
2. Look up `external_refs`. New → `DocumentService::uploadDocument()` with the rule's category + confidentiality. Existing with a different checksum → `uploadNewVersion()`. Same checksum → `duplicate`, no change.
3. Pick the rule by `(source_system, doc_type)`; call `RetentionService::assignPolicy()` with the start date derived from the policy's `trigger_event` (`period_end` for payroll, creation date otherwise).
4. Restrict access with `document_access` (HR + records custodian only) when `confidentiality = restricted`.
5. Audit and `integration_log`.

**2.3 Payroll specifics (HR4)** — "kukunin natin ang data mula sa HR then ilagay sa records"

- Two accepted forms: **(A, recommended)** HR4 sends the generated file (register, payslip batch, remittance report); **(B)** HR4 sends a JSON snapshot and FAMS renders a PDF with the existing dompdf setup (`exports/table_pdf.blade.php`).
- Store **one document per payroll run/cut-off** (not one per employee) plus an index of employee numbers; per-employee records (201 file, contracts) are one document per employee.
- Always `restricted`. Never expose through the `GET /data/documents` feed without checking `document.view` + confidentiality (the feed currently returns document metadata only — keep it that way, never file paths to other groups).
- Suggested starter rules (use the seeded templates already in `retention_policy_templates`; **confirm the actual periods with your adviser/legal — they are configuration, not hard-coded**):

| Source | `doc_type` | Policy template | Trigger |
|---|---|---|---|
| HR4 | `payroll_register`, `payslip_batch` | Labor Records (DOLE) | `period_end` |
| HR4 | `remittance_report`, `bir_2316` | BIR Tax Records | `fiscal_year_end` |
| HR1 | `employment_contract`, `employee_201` | Labor Records (DOLE) | employee separation |
| HR1 | `applicant_resume`, `interview_notes` | Data Privacy Act | `last_activity` |
| HR2 | `attendance_dtr`, `leave_record` | Labor Records (DOLE) | `period_end` |
| HR3 | `appraisal`, `training_certificate` | Labor Records (DOLE) | employee separation |

**2.4 Employee separation trigger (G6, P1-D)**

- Add a `separation_date` trigger option to `retention_policies.trigger_event`. It is an ENUM, so the change needs per-driver SQL (MySQL `MODIFY COLUMN`; PostgreSQL drop/recreate the CHECK constraint; SQLite tests need a rebuild). The simplest route is to convert the column to `string(30)` once and validate in the request.
- `POST /inbound/hr1/events` with `{type: "employee.separated", employee_no, separation_date}` → set `retention_start_date` for that employee's documents and insert a `legal_employee_departures` row so Legal is notified.

**2.5 Records UI.** In `retention/index.blade.php`: filter by source (HR1–HR4), a **Payroll** view, and a "ingested from HR" badge with the HR reference. The existing compliance calendar and upcoming-disposals views work unchanged.

**2.6 Pull mode.** `php artisan integration:pull-hr {--system=hr4}` calls the HR group's read API (`Http::withToken(...)`), maps records, and runs the same `HrDocumentIngestService`. Schedule hourly; guard with `withoutOverlapping()`.

---

### Phase 3 — P2 links (after P0/P1 are stable)

Implement one at a time, each with its own contract and tests:

1. **Vendors → Visitors** (`visitor_type = vendor`, `source_system = scm`) — same pattern as Phase 1.
2. **Contracts ← SCM / HR1 / #10** — `contracts.source_system/source_ref`, create-or-update by external ID, attach to Document Mgmt.
3. **Reservation ↔ Fleet/SWS** — read-only availability feed first, then conflict check on `ReservationService::submitRequest()`.
4. **Finance outbound** — publish `contract.obligation_due`, `legal.case_cost_added`, `reservation.cost_recorded` events through the callback mechanism built in 1.4.
5. **Finance → Records** — reuse Phase 2 with `source_system = fin`.

---

## 6. Contract summary (hand this to the other groups)

Base: `/api/integration/v1/inbound` — `Authorization: Bearer fams_<prefix>.<secret>` (+ optional `X-FAMS-Timestamp`, `X-FAMS-Signature`). JSON, UTC-agnostic `Y-m-d H:i:s`. Responses: `201 created`, `200 updated/duplicate`, `401/403 auth`, `422 validation`.

| Method + path | Scope | From | Purpose |
|---|---|---|---|
| `POST /hr1/employees` | `employees:write` | HR1 | employee master upsert |
| `POST /hr1/interviews` | `visitors:write` | HR1 | applicant + interview → visit |
| `POST /hr1/events` | `events:write` | HR1/HR2 | `employee.separated` etc. |
| `POST /hr{1-4}/documents` | `documents:write` | HR1–HR4 | document + metadata → Records |
| `GET /visitors/{id}/history` | session / `visitors:read` | any | visit history |
| callback `POST <hr1 url>` | — | FAMS → HR1 | visit status |

---

## 7. Tests (match the style in `tests/Feature`)

- `InboundAuthTest` — no/invalid token 401, wrong scope 403, IP block, bad signature, replay older than 5 min.
- `HrInterviewIngestTest` — creates applicant visitor + visit; re-send does not duplicate; unknown host 422; watchlist match → denied; check-in blocked until ID verified.
- `VisitorHistoryTest` — history order, summary counts, filter by type/source.
- `HrDocumentIngestTest` — new doc → document + version + retention assignment; same checksum → duplicate; changed checksum → new version; bad checksum rejected; payroll saved as `restricted`.
- `EmployeeSeparationTest` — event updates retention start date and creates a legal departure row; legal hold still blocks disposal.
- Keep existing `VisitorFlowTest`, `RetentionFlowTest`, `DocumentFlowTest` green.

---

## 8. Build order (checklist)

- [ ] 0. Agree contracts (section 6) with HR1–HR4 groups; confirm HR1–HR4 mapping with the panel
- [ ] 1. Foundation migration, `integration_clients` seeder, middleware, `integration_log`
- [ ] 2. Employee sync (`/hr1/employees`) + system user
- [ ] 3. Visitor columns, ingest service, applicant-ID rule, history endpoint + UI filters/tab
- [ ] 4. Document source columns, `retention_source_rules` seed, ingest service
- [ ] 5. Payroll (HR4) rules + restricted access
- [ ] 6. Separation event + trigger option
- [ ] 7. Status callback to HR1, pull command
- [ ] 8. Tests, update `integration/docs/group8-integration-api.md`, demo data
- [ ] 9. P2 items one by one

---

## 9. Panel demo script

1. HR1 schedules an interview → visitor appears in FAMS tagged **Applicant / HR1**, host notified.
2. Lobby verifies the applicant's ID, checks in, badge printed → HR1 receives the arrival status.
3. Open the visitor's **history** and export it.
4. HR4 sends the payroll register → appears in **Records**, category *HR4 Payroll & Benefits*, `restricted`, retention policy and disposal date assigned.
5. HR marks an employee as separated → retention dates recalculate; a legal hold still blocks disposal.

---

## 10. Open questions for the panel / other groups

1. Does "HR1–HR4" match groups 1–4 in the checklist?
2. Should an HR1-scheduled interview be auto-approved, or does the host still approve?
3. Is HR4 sending files or data (form A or B in 2.3)?
4. Which retention periods apply to each HR document type? (They stay as editable policy data.)
5. Will the other groups push to you, or do you pull from them?
