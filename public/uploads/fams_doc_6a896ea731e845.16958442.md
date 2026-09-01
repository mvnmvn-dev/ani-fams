# Archon Nell Incorporated FAMS — The Complete Guide (Explained Like You're 10, Built Like a Pro)

**FAMS** = **F**acilities & **A**dministrative **M**anagement **S**ystem.

Think of it as the "office brain" of a company. It remembers who booked the meeting room, who visited the building, where important papers are, which contracts are still active, and which legal cases are being fought. Instead of one person keeping all that in their head (or in messy folders), this website keeps it organized, safe, and searchable.

It's built with **Laravel** (a popular toolkit for building websites with the PHP programming language) and stores its data in **PostgreSQL** (a filing cabinet program for computers, called a "database").

---

## 1. The Big Picture — Imagine a Toy Factory

Picture a toy factory with different departments:

| Real-world department | FAMS Module |
|---|---|
| The room-booking desk | **Reservations** |
| The front-desk guard | **Visitor Management** |
| The filing room | **Document Management** |
| The "how long do we keep this" rulebook | **Records Retention & Compliance** |
| The company's lawyers | **Legal Case Management** |
| The deal-making office | **Contract Management** |
| The HR office (badges & permissions) | **Users & Roles** |
| The security camera footage log | **Audit Log** |
| The office intercom | **Notifications** |

Every department has its own "counter" (a web page), its own "rulebook" (permissions), and its own "filing cabinet" (database tables). But they all share the same building (the Laravel app) and the same security guard (login system).

---

## 2. How the Whole Building is Organized (Architecture)

Laravel apps are built in layers, like a sandwich. When you click a button on the website, your click travels through these layers, top to bottom:

```
 YOU click a button in the browser
        │
        ▼
 1. ROUTES         (routes/web.php)     "Which department handles this?"
        │
        ▼
 2. MIDDLEWARE      (app/Http/Middleware)  "Are you allowed in here?"
        │
        ▼
 3. CONTROLLER      (app/Http/Controllers) "Take the request, hand it to an expert"
        │
        ▼
 4. SERVICE         (app/Services)         "Do the actual thinking/business rules"
        │
        ▼
 5. REPOSITORY/MODEL (app/Repositories, app/Models) "Talk to the filing cabinet (database)"
        │
        ▼
 6. DATABASE        (PostgreSQL tables)    "The actual filing cabinet"
        │
        ▼
 Response travels back UP through the same layers to a
 7. VIEW (Blade template in resources/views) which draws the webpage you see
```

This is called **separation of concerns** — a fancy way of saying "everyone has one job, so it's easy to fix things without breaking other things." A **Controller** is like a receptionist: it doesn't do the actual work, it just takes your request and calls the right expert (**Service**). The **Service** is the expert who knows the business rules (e.g., "you can't book a room that's already taken"). The **Repository**/**Model** is the filing clerk who actually pulls folders out of the cabinet (**database**).

### Real example: booking a meeting room

1. You visit `/reservations` in your browser.
2. `routes/web.php` says: *"GET requests to `/reservations` go to `ReservationController@index`, but only if you have the `reservation.view` permission."*
3. The `EnsurePermission` middleware checks your badge (role) before letting you through.
4. `ReservationController::index()` calls `ReservationService` to fetch the data.
5. `ReservationService` asks the `Reservation` Eloquent model (or `ReservationRepository`) to pull rows from the `reservations` table in PostgreSQL.
6. The Controller hands that data to a Blade view (`resources/views/reservations/index.blade.php`), which turns it into HTML you see on screen.

Here's the actual route definition (this is real code from the project):

```php
Route::middleware('permission:reservation.view')
    ->get('/reservations', [ReservationController::class, 'index'])
    ->name('reservations.index');
```

Translation: *"To visit `/reservations`, you must have the badge sticker called `reservation.view`. If you do, call the `index` method inside `ReservationController`."*

---

## 3. The Security Guard — Login, Roles & Permissions

### 3.1 Logging in

- Login form: `GET /login` and `POST /login` → `Auth\LoginController`
- Optional extra lock: **Two-Factor Authentication (2FA)** — `Auth\TwoFactorController`. After your password, it can ask for a one-time code sent another way, like a second lock on your bike.
- Forgot password flow: `Auth\PasswordResetController` (`/forgot-password`, `/reset-password/{token}`).
- Sessions (basically "you're currently logged in" tickets) can be reviewed and force-logged-out from other devices: `SessionController` at `/settings/sessions`. There's even a `SessionAnomalyService` that watches for weird login behavior (like someone using your ticket from a strange place).

### 3.2 Roles — three badge colors

The seed data creates exactly three roles:

| Role code | Badge meaning |
|---|---|
| `admin` | Gold badge — opens every door in the building |
| `staff` | Blue badge — can process and approve day-to-day work |
| `employee` | Green badge — can only submit requests and see their own stuff |

### 3.3 Permissions — the actual door locks

Each door (feature) has its own lock, named `module.action`, for example:

```
reservation.view, reservation.create, reservation.approve, reservation.manage
visitor.view, visitor.register, visitor.checkin, visitor.manage
document.view, document.upload, document.manage
retention.view, retention.manage
legal.view, legal.manage
contract.view, contract.create, contract.approve, contract.manage
users.manage
audit.view
```

A **role** is just a bundle of permission-keys. The `role_permissions` table is the "keyring" that links a role to all the doors it can open. When code needs to check "can this person do X?", it calls:

```php
$user->canPermission('reservation.approve');
```

which (from `app/Models/User.php`) checks whether that permission code is inside the user's role's list of permissions:

```php
public function canPermission(string $code): bool
{
    return in_array($code, $this->permissionCodes(), true);
}
```

And every protected route is wrapped with the `EnsurePermission` middleware:

```php
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $user || ! $user->canPermission($permission)) {
            abort(403); // "403" = "I understand you, but you're not allowed."
        }
        return $next($request);
    }
}
```

Admins can create custom roles too, through `/settings/roles` (`RoleController`), so the company isn't stuck with only 3 forever.

### 3.4 A neat trick: Department Scoping

Some models (like `Reservation`) automatically hide other departments' data using a **Global Scope**:

```php
protected static function booted()
{
    static::addGlobalScope(new \App\Models\Scopes\DepartmentScope('requester'));
}
```

Think of it as invisible glasses: an employee only *sees* rows belonging to their own department unless they have a higher badge — the code applies this filter automatically to every query, so nobody has to remember to add it by hand.

---

## 4. Module-by-Module Deep Dive

For every module below you'll get: **the kid analogy → the database tables → the workflow story → the real routes and one real code snippet.**

---

### 4.1 📅 Facilities Reservation (booking rooms & equipment)

**Analogy:** Like booking a birthday party room at a community center. You ask, someone in charge says yes or no, and if the room's already taken you can join a waiting list in case someone cancels.

**Key tables:** `facilities`, `reservations`, `equipment_items`, `reservation_equipment`, `reservation_waitlist`

**Model:** `app/Models/Reservation.php` — belongs to a `facility`, a `requester` (User), an `approver` (User), and optionally a `chargebackDepartment`.

**Workflow story:**
1. An employee fills a form: which room, what time, why → `POST /reservations` → `ReservationController::store()` → `ReservationService::submitRequest()`. Status starts as `pending`.
2. Staff/Admin opens the list, sees pending requests, clicks Approve/Reject → `POST /reservations/{id}/decide` → `ReservationService::decide()` updates `status`, `approved_by`, `approved_at`.
3. If the room's full, people can `POST /reservations/waitlist` to join a line; when a slot frees up staff can `offerWaitlistSlot`, and the person accepts/declines with `respondToWaitlistOffer`.
4. Staff can also block time for **maintenance** (`storeMaintenance`) so nobody can book a room while it's being cleaned or fixed — these show `is_maintenance = true`.
5. Extra features bolted on: **cost tracking** (who pays, `cost_amount`, `chargeback_department_id`), **no-show tracking** (`markNoShow`), **cancellations** with a required reason, **feedback ratings** after the event, an **iCal export** (`.ics` file you can add to Google/Outlook calendar), and CSV/PDF export + a `/calendar` JSON feed for a calendar view widget.

**Real code — the "decide" endpoint:**
```php
Route::middleware('permission:reservation.approve')
    ->post('/reservations/{id}/decide', [ReservationController::class, 'decide'])
    ->name('reservations.decide');
```
```php
public function decide(Request $request, string $id, ReservationService $service)
{
    $decision = (string) $request->input('decision', ''); // "approved" or "rejected"
    $result = $service->decide((int) $id, $decision);
    ...
}
```

---

### 4.2 🪪 Visitor Management (front-desk guest book)

**Analogy:** The notebook a school guard uses to write down every visitor's name, why they're here, and what time they left — except it's digital, can print a name badge, and can flag "known troublemakers."

**Key tables:** `visitors`, `visits`, `visitor_vehicles`, `visit_areas`, `visit_area_access`

**Workflow story:**
1. Front desk registers a visitor (`POST /visitors`) — name, company, purpose.
2. When they arrive, guard clicks **Check In** (`POST /visitors/{id}/check-in`); when they leave, **Check Out**.
3. A **watchlist** flag can be toggled on troublesome/banned visitors (`toggle-watchlist`), with extra notes (`watchlist-details`).
4. Vehicles can be logged if visitors drive in (`logVehicle` / `getVehicles`) — handy for parking or security.
5. Sensitive visits can require signing an **NDA** (Non-Disclosure Agreement — a promise not to tell company secrets) before entry: `setNdaRequired` then the visitor `signNda`.
6. In an emergency (like a fire drill), `GET /visitors/emergency-roster` prints out **everyone currently in the building** so nobody gets left behind — plus an export version.
7. A printable **visitor badge** can be generated (`GET /visitors/{id}/badge`).

**Real code — checking someone in:**
```php
Route::middleware('permission:visitor.checkin')
    ->post('/visitors/{id}/check-in', [VisitorController::class, 'checkIn'])
    ->name('visitors.checkin');
```

---

### 4.3 📁 Document Management (the filing cabinet)

**Analogy:** A magical filing cabinet where every paper has a history — you can see every past version, "lock" a paper so nobody edits it while you're working on it, get people to sign papers electronically, and even have the computer "read" scanned images (OCR).

**Key tables:** `document_categories`, `documents`, `document_versions`, `document_tags`, `document_tag_map`, `document_access`, `bulk_uploads`, `document_signature_requests`

**Workflow story:**
1. Upload a file → `POST /documents` → creates a `Document` + first `DocumentVersion` row. Files themselves are saved to disk/storage; the database just remembers *where* the file is and *who* uploaded it.
2. Upload a **new version** of an existing document (`POST /documents/{id}/version`) — old versions aren't deleted, they're kept so you can **compare versions** (`/documents/{id}/compare`) and see a text diff (what changed, line by line) — this uses `textDiffFromPaths()` in `DocumentService`.
3. **Check-out / check-in (locking):** Like signing out a library book — `checkOut` marks a doc as "being edited by me" so nobody else can also edit it; `checkIn` releases the lock.
4. **Access control:** `updateAccess` decides who besides the uploader is allowed to view a document.
5. **Review reminders:** important documents (like policies) can have a review date; `documentsNeedingReview` finds documents due for a check-up, and `sendReviewReminders` emails/nudges the owner.
6. **E-signatures:** `requestSignature` emails someone a unique signing link/token; they open `/documents/sign/{token}`, draw or type their signature, `submitSignature` records it (or they can `declineSignature`).
7. **Bulk upload tracking:** when many files are dragged in at once, `startBulkUpload`/`completeBulkUpload` keep a summary log so you know how many succeeded/failed.
8. **OCR processing:** `processOcr` turns scanned image-documents into searchable text.
9. Every document can be exported to CSV or PDF for reporting.

**Real code — versioning + diff idea:**
```php
public function compareVersions(int $documentId, int $v1, int $v2): array
public function textDiffFromPaths(string $leftPath, string $rightPath, array $meta = []): array
```
Translation: *"Take version #1 and version #2 of a file, and tell me exactly what text changed."*

---

### 4.4 🗄️ Records Retention & Compliance (the "how long do we keep this" rulebook)

**Analogy:** Just like your school doesn't keep every kid's homework forever — some things get thrown away after a year, others (like your diploma) are kept forever, and if there's ever a "no throwing away" order (a **legal hold**, like when there's an investigation), nothing on hold can be shredded no matter what the calendar says.

**Key tables:** `retention_policies`, `retention_policy_templates`, `retention_assignments`, `legal_holds`, `legal_hold_documents`, `legal_hold_notifications`, `disposal_logs`

**Workflow story:**
1. Admin defines a **Retention Policy** — e.g., "HR records: keep 7 years, then destroy" (`POST /retention/policies`), or picks a ready-made **policy template** (`createFromTemplate`) instead of typing it from scratch.
2. That policy gets **assigned** to specific documents/records (`POST /retention/assign`) — like sticking an expiry-date sticker on a file.
3. When the policy's clock runs out, staff can **dispose** of the record (`POST /retention/{id}/dispose`) — this writes a `disposal_logs` row, and a **disposal certificate** (an official proof-of-destruction document) can be generated (`generateCertificate`). `disposalsWithoutCertificates` finds any disposals missing that proof, so nothing slips through undocumented.
4. A **Legal Hold** can be placed on records related to a lawsuit or investigation (`POST /retention/holds`) — this *overrides* the expiry sticker, meaning "do NOT destroy, no matter what," and the record's custodian (owner) gets notified (`holds-with-notifications`). When the investigation ends, the hold is lifted (`release`).
5. A **compliance calendar** view (`/retention/compliance-calendar`) shows upcoming destruction dates and active holds at a glance — like a big wall calendar of "things due soon."

**Real code — the safety rule (conceptual):** a record can only be disposed of if it has **no active legal hold** attached — this is the single most important rule in the whole module, because destroying evidence under a legal hold is illegal.

---

### 4.5 ⚖️ Legal Case Management (the lawyer's office)

**Analogy:** Imagine a big lawsuit like a group project with deadlines, teammates (parties), a to-do checklist (milestones), a budget, and rules about who's allowed on the team (conflict of interest checks — like not letting your rival's best friend be your teammate).

**Key tables:** `legal_cases`, `legal_case_parties`, `legal_case_milestones`, `legal_case_deadlines`, `legal_case_documents`, `legal_case_costs`, `legal_vendors`, `legal_case_vendors`, `legal_case_outcomes`, `legal_case_party_conflicts`, `legal_case_counsel_conflicts`

**Workflow story:**
1. Create a case (`POST /legal`) — gives it a name, type, and status.
2. Add the **parties** involved (`addParty`) — who's suing whom.
3. Add **milestones** (big steps, `addMilestone`, with `toggleMilestone` to mark done) and **deadlines** (`addDeadline` / `toggleDeadline`) — like a checklist with due dates for court filings.
4. Link related **documents** (`linkDocument`) — evidence, contracts, filings — connecting straight into the Document module above.
5. Track **costs** (`addCost`) and **outside law firm vendors** (`storeVendor`, `linkVendor`), then compare against a **budget** (`updateBudget`) so nobody overspends.
6. **Conflict-of-interest checks**: `flagPartyConflict` / `flagCounselConflict` mark a possible conflict (e.g., a lawyer previously represented the opposing side); `runConflictScan` automatically searches for these; conflicts then get `resolve`d or `verify`d by a human.
7. When the case ends, `recordOutcome` logs whether it was won, lost, or settled, and for how much.

**Real code — the route family (notice the nesting pattern used everywhere):**
```php
Route::post('/legal/{id}/milestones', [LegalCaseController::class, 'addMilestone']);
Route::post('/legal/{caseId}/milestones/{milestoneId}/toggle', [LegalCaseController::class, 'toggleMilestone']);
```

---

### 4.6 📝 Contract Management (the deal-making office)

**Analogy:** Like a chain of grown-ups who all need to sign a permission slip before a school trip can happen — the trip can't go until *everyone* on the list has said yes, in order. This is the richest, most "workflow-y" module in the whole app.

**Key tables:** `contract_types`, `contract_templates`, `contract_approval_chains`, `contract_approval_chain_steps`, `contracts`, `contract_approvals`, `contract_documents`, `contract_amendments`, `contract_obligations`, `contract_vendor_scores`

**Workflow story — the full lifecycle:**
1. **Draft:** Someone drafts a contract (`POST /contracts`), maybe starting from a saved **template** (pre-written boilerplate text) and picking a **contract type** (e.g., "Vendor Agreement"). Status = `draft`.
2. **Approval Chain:** Before drafting even happens, an admin can define reusable **approval chains** (`POST /contracts/chains`) — an ordered list of approvers, e.g., "Manager → Finance → Legal." Each contract type can have a default chain.
3. **In Review:** When submitted, `contract_approvals` rows are created — one per approver in the chain, all starting `pending`. Status = `in_review`.
4. **Deciding, one approver at a time:** `POST /contracts/{id}/approve` calls `ContractService::decideApproval()`. Here's the real logic, simplified:
   - If **rejected** → whole contract bounces back to `draft` and the owner is notified.
   - If **approved** → check if *every* approval step is now approved.
     - If yes → automatically move the contract to `pending_signature` (via `moveToPendingSignature()`), and generate a unique signing link.
     - If no → notify the **next** person in line that it's their turn (nobody is bothered until it's actually their turn — just like a chain of permission slips passed down a row of desks).
5. **Signing:** The counter-party gets a link `GET /contracts/sign/{token}` (no login needed — it's a secure one-time token), draws/types a signature, `submitSign` records it plus their IP address as proof, and a **signature certificate** (`signature-certificate`) can be generated as legal proof of who signed and when.
6. **Active / Amendments:** Once signed, the contract is `active`. Changes later go through **amendments** (`POST /contracts/{id}/amend`) rather than editing the original — keeping a full history — and you can view an **amendment diff** (what exactly changed between versions), just like the Document module's compare feature.
7. **Obligations:** Ongoing promises inside the contract (e.g., "deliver a report every quarter") are tracked as **obligations** (`addObligation`, `toggleObligation`) — a checklist that outlives the signing moment.
8. **Renewal:** Near expiry, `autoDraftRenewal` can auto-create a new draft contract copied from the old one, so nobody forgets to renew.
9. **Vendor scoring:** After working with a vendor, staff can `recordVendorScore` — rating their performance, which rolls up into an overall vendor score used for future decisions.

**Real code — the heart of the approval chain (from `ContractService::decideApproval`):**
```php
if ($allApproved) {
    $this->moveToPendingSignature($contractId, $number, $title, $ownerId);
    $contractStatus = 'pending_signature';
} elseif ($nextApproverId && $contractModel) {
    $notificationService->send(
        $nextApproverId,
        'contracts',
        "Contract Approval Required: {$contractModel->contract_number}",
        "It is your turn to review contract '{$contractModel->title}'.",
        "/contracts?open={$contractId}",
        true
    );
}
```
This is the "pass the permission slip to the next kid" moment, written in code.

---

### 4.7 👥 Users, Roles & Sessions (the HR office)

- `UserController` — create accounts, bulk-import users from a spreadsheet (`import`), bulk-assign roles (`bulk-role`), activate/deactivate (`toggle-status`), remove accounts.
- `RoleController` — create custom roles and decide which permission-keys belong to each (only visible under `/settings/roles`, only to people with `users.manage`).
- `SessionController` — see every device currently logged in as you, and kick out ("revoke") any of them — handy if you left yourself logged in on a public computer.

---

### 4.8 🔔 Notifications & 🕵️ Audit Log (the intercom + the security cameras)

- **Notifications** (`app/Services/NotificationService.php`, `NotificationController`) — little bell-icon messages, like "It's your turn to approve Contract #42." The frontend `poll`s (asks "anything new?") every so often, and users can mark them read.
- **Audit Log** (`AuditLogController`, `app/Support/AuditLogger.php`) — every important action in the whole system (approve, reject, delete, dispose, sign...) writes a permanent, unchangeable record: *who* did *what*, to *which* record, *when*, from *what IP address*, and (redacting sensitive fields like passwords) what changed. This is the security camera footage — nobody, not even an admin, can act invisibly. Any user with `audit.view` can browse and export it.

```php
AuditLogger::log(
    'contracts', 'approve', 'contract', $contractId,
    null, ['approval_id' => $approvalId, 'decision' => $decision]
);
```
Translation: *"Someone just approved contract #$contractId — write that down, forever."*

---

## 5. How the Departments Talk to Each Other (Cross-Module Integration)

So far each module was explained like a separate room. But in real life, an office isn't separate rooms with locked doors between them — the mailroom delivers to every floor, and the security office watches all of them. FAMS works the same way: a handful of **shared tables and shared services act like hallways connecting the rooms.**

### 5.1 The "hallway" tables — where modules physically link up

Looking at the real database schema, three tables exist for *no other reason* than to connect two modules together:

```sql
-- A document can be attached to a legal case
legal_case_documents (case_id → legal_cases, document_id → documents)

-- A document can be attached to a contract
contract_documents (contract_id → contracts, document_id → documents)

-- A document can be placed under a legal hold
legal_hold_documents (legal_hold_id → legal_holds, document_id → documents)
```

Notice the pattern: **the Document Management module is the hub.** Almost every other module points *at* a document rather than storing files itself. Legal Cases don't have their own file storage — they borrow the Documents module. Contracts don't either — a signed PDF lives in `documents`, and `contract_documents` just draws a line connecting a contract to it. This is like how a school doesn't make every classroom keep its own copy of a student's file — there's one central records room, and every classroom just has a *reference* to "student file #482."

There's also a generic **`attachments`** table that any module can use, tagged with `module` + `entity_type` + `entity_id` (e.g. `module = 'visitors', entity_type = 'visit', entity_id = 17`) — a flexible "sticky note with a photo" any department can attach to any record without needing its own special file table.

### 5.2 The real integration story: Retention ↔ Documents ↔ Legal Holds

This is the single most important cross-module rule in the entire system, and it's worth walking through in detail because it shows *why* integration matters — not just that it exists.

1. **Retention** assigns a policy to a **document**: `retention_assignments.document_id → documents.id`. This calculates an expiry date (`calculated_disposal_date`) — e.g. "shred this on March 1st."
2. Separately, **Legal** can place a **hold** on that *same* document if it becomes relevant to a court case: `legal_hold_documents.document_id → documents.id`, and the hold optionally links to a specific case via `legal_holds.related_case_id → legal_cases.id`.
3. When Retention staff try to actually destroy the document (`executeDisposal()`), the code doesn't just check "has the expiry date passed?" — it asks the Documents/Legal side of the house first:

```php
$docId = (int) $assignment['document_id'];
if ($this->repo->isDocumentOnHold($docId)) {
    return ['success' => false, 'error' =>
        'This document is on an active legal hold and cannot be disposed.'];
}
```

This one `if` statement is the whole point of building these modules *together* instead of as separate disconnected apps. If Legal and Retention didn't share the same `documents` table, this safety check would be impossible — someone could shred evidence a lawsuit still needs, purely because "the retention module didn't know about the legal case." Integration here isn't a nice-to-have, it's what keeps the company out of serious legal trouble.

### 5.3 Contracts ↔ Documents ↔ Notifications

The Contract module's approval-chain workflow (Section 4.6) touches three other modules on every single step:

- **Documents:** the signed contract file itself is stored as a `Document`, linked via `contract_documents` — and later, amendments generate their own linked documents too (`uploadContractDocument()`).
- **Notifications:** every time an approval decision is made, `ContractService` calls the shared `NotificationService` to ping either the *next approver in line* or the *contract owner* (if rejected) — the Contracts module never draws its own notification bell, it borrows the same one every other module uses.
- **Audit Log:** every approve/reject/sign/amend action calls the shared `AuditLogger::log()` — so a contract's entire life story ends up automatically recorded in the same central diary as reservations, disposals, and legal case changes.

### 5.4 Legal Cases ↔ Documents ↔ Vendors ↔ Retention

A legal case is almost a "mini dashboard" that pulls in other modules:

- Evidence and filings → linked from **Documents** (`legal_case_documents`)
- Outside law firms working the case → tracked via **`legal_vendors`** / `legal_case_vendors` (its own tiny vendor directory, separate from Contracts' vendor scoring, but same *idea*)
- If the case requires preserving evidence → a **Retention** legal hold is placed on the same documents (`related_case_id`)
- Every cost, milestone, deadline, and outcome → written to the **Audit Log**

### 5.5 Notifications & Audit Log — the two modules *every other module* depends on

If you trace `app(NotificationService::class)` and `AuditLogger::log()` through the codebase, you'll find them called from **every single business service** — Reservations, Visitors, Documents, Retention, Legal, Contracts, even the login security watchdog (`SessionAnomalyService`):

```
ReservationService  ──┐
VisitorService       ─┤
DocumentService       ├──►  NotificationService.send()   (the shared "intercom")
LegalCaseService      │
ContractService       │
SessionAnomalyService─┘

ReservationService  ──┐
VisitorService/       │
DocumentService       ├──►  AuditLogger::log()           (the shared "security camera")
RetentionService      │
LegalCaseService      │
ContractService      ─┘
```

Think of these two as **utility pipes** running through the whole building, like electricity and water — no department builds its own power plant; they all tap into the same line. This is *why* the audit log can show one unified timeline across the entire company instead of six separate, disconnected logs.

### 5.6 A full cross-module story, start to finish

Here's what actually happens, module by module, when a contract with a vendor goes through its whole life — notice how many "hallways" get used:

1. **Contracts** — a contract is drafted and routed through an approval chain (`contract_approvals`).
2. **Notifications** — each approver is pinged in turn; the vendor is notified when it's ready to sign.
3. **Documents** — the signed PDF is saved as a `Document` and linked via `contract_documents`; its `document_versions` history begins.
4. **Retention** — someone assigns a retention policy to that same document (e.g., "keep signed contracts 10 years").
5. **Legal** — two years later, a dispute arises with that vendor; a `LegalCase` is opened and the *same* contract document is linked in via `legal_case_documents`, and a **legal hold** is placed on it.
6. **Retention (again)** — even though the 10-year clock hasn't run out yet, the hold means `isDocumentOnHold()` would block disposal anyway — belt-and-suspenders protection.
7. **Audit Log** — every single step above (draft, approve, sign, policy-assign, case-open, hold-place) is independently and permanently recorded, so if anyone ever asks "who did what, and when," there's one single place to look.

That's true integration: not just "the modules exist in the same app," but "the modules actively protect and inform each other."

---

## 6. User Roles & Functions — Who Can Do What, In Detail

Section 3 introduced the three badge colors. Here's the deeper, practical breakdown of what each role can actually *do* inside every module — this is the real permission map pulled straight from the database seed data.

### 6.1 The permission model, quickly recapped

Every feature is locked behind a `module.action` key (e.g. `document.upload`). A **Role** is just a named bundle of these keys. A **User** has exactly one Role. So "can Sarah upload a document?" always boils down to: *"Does Sarah's role include the key `document.upload`?"*

### 6.2 🥇 Admin — the Administrator

**Mindset:** "I own the whole building." Gets **every single permission that exists**, automatically — the seed data literally does `SELECT r.id, p.id FROM roles r, permissions p WHERE r.code = 'admin'` (every permission, no filtering).

| Can do | Examples |
|---|---|
| Everything Staff can do | see below |
| Manage users | create accounts, deactivate/reactivate people, bulk-import from spreadsheet, bulk-assign roles |
| Manage roles | create custom roles beyond the default 3, choose exactly which permission keys each one holds |
| Manage sessions | view/revoke anyone's active login sessions |
| Full retention control | create policies, assign them, execute disposals, place/release legal holds |
| Full legal control | open/close cases, run conflict scans, record outcomes |
| Full contract control | build approval chains and templates, approve at any chain step, force amendments |
| View the audit log | the only role guaranteed `audit.view` by default |

**In short:** Admin is the only role that can *change the rules themselves* (roles, permissions, policies, approval chains) — not just operate within them.

### 6.3 🥈 Staff — day-to-day operators

**Mindset:** "I run the front desk, the filing room, and I say yes/no to requests — but I don't rewrite company policy or manage employee accounts."

Staff's permission list from the seed data:
```
reservation.view, reservation.create, reservation.approve, reservation.manage
visitor.view, visitor.register, visitor.checkin, visitor.manage
document.view, document.upload, document.manage
retention.view                                    ← view only, NOT manage
legal.view                                         ← view only, NOT manage
contract.view, contract.create, contract.approve
```

| Module | What Staff can do | What Staff can NOT do |
|---|---|---|
| Reservations | Create, **approve/reject**, manage facilities, mark no-shows, offer waitlist slots | — (full access) |
| Visitors | Register, check in/out, manage watchlist & NDA requirements | — (full access) |
| Documents | Upload, version, manage categories/access/review reminders | — (full access) |
| Retention | **View** policies, holds, and the compliance calendar | Cannot create policies, execute disposals, or place/release legal holds |
| Legal | **View** cases and their details | Cannot open a case, add milestones, record an outcome, or run a conflict scan |
| Contracts | Draft new contracts, **approve** chain steps they're assigned to | Cannot manage templates or approval chains, cannot force an amendment through management-only fields |
| Users/Roles | — | No access at all (needs `users.manage`, admin-only) |

**Why this split makes sense:** Staff run the *operations* (approving a room booking, checking in a guest, uploading a file), but two of the most legally-sensitive modules — **Retention disposal** and **Legal case creation** — are deliberately locked to Admin-only "manage" rights. That's intentional: destroying records or opening a lawsuit are big, risky, hard-to-undo actions, so the system requires the highest badge for them, even though Staff can see everything happening.

### 6.4 🥉 Employee — self-service only

**Mindset:** "I submit my own requests and check on my own stuff. I don't approve anyone, including myself."

Employee's permission list from the seed data:
```
reservation.view, reservation.create
visitor.view, visitor.register
document.view
contract.view
```

| Module | What Employee can do | What Employee can NOT do |
|---|---|---|
| Reservations | View the schedule, submit a booking request | Cannot approve their own or anyone's request, cannot manage facilities |
| Visitors | View the visitor log, pre-register an expected guest | Cannot check people in/out, cannot touch the watchlist |
| Documents | View documents they're allowed to see | Cannot upload new documents or new versions |
| Contracts | View contracts | Cannot draft, approve, or amend a contract |
| Retention | — | No access at all |
| Legal | — | No access at all |
| Users/Roles | — | No access at all |

**Why this matters for the workflow:** Because `reservation.approve` isn't in Employee's list, an Employee can submit a booking but literally cannot click an "approve" button on their own request even if they tried — the button wouldn't even render, and if they hand-crafted the network request anyway, `EnsurePermission` middleware would slam the door with a 403 before the Controller ever ran. This is what makes the reservation workflow in Section 4.1 a genuine two-person process instead of an honor system.

### 6.5 Beyond the three defaults: custom roles

Because Admins can create new roles at `/settings/roles` and choose any combination of permission keys, a company could add, say, a **"Facilities Manager"** role that gets every `reservation.*` and `visitor.*` key but nothing from Legal or Contracts — the permission system doesn't hard-code just three roles, it hard-codes the **individual door keys**; the three roles you see by default are just one *sensible starting bundle* of those keys.

### 6.6 Quick-reference permission matrix

| Permission key | Admin | Staff | Employee |
|---|:---:|:---:|:---:|
| `reservation.view / create` | ✅ | ✅ | ✅ |
| `reservation.approve / manage` | ✅ | ✅ | ❌ |
| `visitor.view / register` | ✅ | ✅ | ✅ |
| `visitor.checkin / manage` | ✅ | ✅ | ❌ |
| `document.view` | ✅ | ✅ | ✅ |
| `document.upload / manage` | ✅ | ✅ | ❌ |
| `retention.view` | ✅ | ✅ | ❌ |
| `retention.manage` | ✅ | ❌ | ❌ |
| `legal.view` | ✅ | ✅ | ❌ |
| `legal.manage` | ✅ | ❌ | ❌ |
| `contract.view / create` | ✅ | ✅ | view only |
| `contract.approve` | ✅ | ✅ | ❌ |
| `contract.manage` | ✅ | ❌ | ❌ |
| `users.manage` | ✅ | ❌ | ❌ |
| `audit.view` | ✅ | ❌ | ❌ |

*(Employee has `contract.view` but not `contract.create`, so "view only" above — double-check against the seed list in 6.4 if building new roles.)*

---

## 7. Small "Superpowers" Used Everywhere (Cross-Cutting Features)

These aren't their own module — they're shared tools every module borrows, like a shared box of crayons every classroom can use:

- **Exports:** Every module can export its list to **CSV** (a spreadsheet file) or **PDF** (a printable report), via a shared `ExportsData` trait mixed into each controller.
- **Attachments:** `AttachmentService` handles the actual saving of uploaded files to disk safely.
- **Soft Deletes:** Deleting a record (like a Reservation or Document) doesn't erase it forever — it just hides it (sets a `deleted_at` timestamp) so it can be recovered or still show up in historical audit trails. This is the `SoftDeletes` trait you see on models like `Reservation`.
- **Two-Factor Authentication & Session Anomaly Detection:** Extra locks and a "watchdog" that notices if a login looks suspicious.
- **Dashboard:** `DashboardService::data()` pulls small summaries from *every* module (pending approvals, upcoming disposals, active cases, expiring contracts...) so the homepage feels like a cockpit with all the important gauges in one place.

---

## 8. Vocabulary Corner — Coding Words Explained Simply

| Word | What it really means |
|---|---|
| **Route** | A web address (URL) and a rule for what happens when you visit it |
| **Middleware** | A checkpoint that runs *before* your request reaches its destination (like a hallway pass check) |
| **Controller** | The receptionist — receives the request, doesn't do deep thinking, hands it off |
| **Service** | The expert who knows the business rules ("you can't approve your own contract") |
| **Repository / Model** | The filing clerk that talks to the database |
| **Migration** | A recipe for building or changing a database table |
| **Eloquent** | Laravel's tool that lets PHP code talk to database tables like they're plain objects |
| **RBAC** | "Role-Based Access Control" — badges (roles) that come with a bundle of door keys (permissions) |
| **Soft delete** | "Hiding" a record instead of truly erasing it |
| **Token** | A random secret code used once, like a one-time hall pass (used for password reset links, e-signature links, contract-signing links) |
| **CSV/PDF export** | Turning on-screen data into a downloadable spreadsheet or printable report |
| **Audit log** | A permanent diary of everything important that happened in the system |
| **Seeder** | A script that fills a brand-new, empty database with starter data (like the 3 demo accounts) |

---

## 9. Try It Yourself — Demo Accounts

The project ships with three ready-made accounts (password `Password123!` for all):

| Role | Email | What they can do |
|---|---|---|
| Admin | `admin@facilitiesadmin.local` | Everything, including managing users and roles |
| Staff | `staff@facilitiesadmin.local` | Approve reservations/contracts, manage visitors & documents, no user management |
| Employee | `employee@facilitiesadmin.local` | Submit reservations, register visitors, view documents/contracts only |

A great way to *really* learn this system: log in as **Employee** and submit a reservation request, then log out and log back in as **Staff** to approve it, then check the **Audit Log** as **Admin** to see the whole story recorded automatically. That one small journey touches almost every layer described in Section 2.

---

## 10. One-Page Cheat Sheet (Module → Controller → Service → Key Tables)

| Module | Controller | Service | Main tables |
|---|---|---|---|
| Reservations | `ReservationController` | `ReservationService` | `reservations`, `facilities`, `equipment_items`, `reservation_waitlist` |
| Visitors | `VisitorController` | `VisitorService` | `visitors`, `visits`, `visitor_vehicles`, `visit_areas` |
| Documents | `DocumentController` | `DocumentService` | `documents`, `document_versions`, `document_signature_requests` |
| Retention | `RetentionController` | `RetentionService` | `retention_policies`, `retention_assignments`, `legal_holds`, `disposal_logs` |
| Legal Cases | `LegalCaseController` | `LegalCaseService` | `legal_cases`, `legal_case_parties`, `legal_case_milestones`, `legal_case_costs` |
| Contracts | `ContractController` | `ContractService` | `contracts`, `contract_approvals`, `contract_amendments`, `contract_obligations` |
| Users/Roles | `UserController`, `RoleController` | `UserService` | `users`, `roles`, `permissions`, `role_permissions` |
| Audit | `AuditLogController` | `AuditLogService` | `audit_logs` |
| Notifications | `NotificationController` | `NotificationService` | `notifications` |
| Dashboard | `DashboardController` | `DashboardService` | (reads a bit from every table above) |

---

*Written by walking through the actual `ani-fams` Laravel codebase: routes, controllers, services, models, and the PostgreSQL schema.*
