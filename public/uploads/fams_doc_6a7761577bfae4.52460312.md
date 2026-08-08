# LACS Capstone — Not-Yet-Implemented Content Report
**Source paper:** *Local Government Unit 2: Legislative Agenda and Calendar Management System with Automated Document Analysis and Summarization Using AI-Classification* (G19-updated.pdf)
**Source system:** LACS-aa.zip codebase

This report lists every backlog item in **Chapter 3, Section 3.4 (Scrum Artifacts / Product Backlog)** that the paper itself does **not** mark as "Done" — i.e. items tagged **Pending, Partial, Deferred, or N/A**. These represent documented requirements/features that are not yet fully reflected in the running system. Items are grouped exactly as they appear in the paper's tables (3.4.1–3.4.5), and only non‑Done rows are listed. Fully "Done" items are omitted since they are already reflected in the document.

A short **Code Spot-Check** note is added where I could quickly confirm or contradict the paper's self-reported status against the actual `LACS/` codebase.

---

## 3.4.2 — Product Backlog for EIS Information Security (Table 4)

| ID | Feature | Priority | Status | Notes |
|---|---|---|---|---|
| EIS-001 | Role-based access control (RBAC) — restrict features by role (Legislative Member, Committee Member, Admin Staff, Public) | High | **Pending** | Code has a basic `lacs_is_admin_role()` helper in `includes/auth_guard.php`, but no full 4-tier RBAC matrix — paper's "Pending" looks accurate. |
| EIS-002 | Enforced password policy (min. 8 chars, alphanumeric) on login/registration | High | **Pending** | |
| EIS-008 | Regular (daily) automated database backups | High | **Partial** | No backup automation found in codebase (`cron/` only has reminder jobs). |
| EIS-010 | Audit logs retained for ≥1 year | Medium | **Partial** | |
| EIS-013 | Notify item owner when their agenda/session is modified by someone else | Medium | **Partial** | |
| EIS-014 | Version history for agenda items (track/revert changes) | Medium | **Pending** | |
| EIS-015 | Calendar viewable by all authenticated users, editable only by admins | High | **Deferred** | |
| EIS-017 | Agenda creation: members can propose, but publishing requires approval | High | **Partial** | |
| EIS-018 | Voting records viewable only by authorized members, immutable after submission | High | **Deferred** | |
| EIS-024 | Uploaded files scanned for viruses before storage | High | **Deferred** | |
| EIS-025 | Automated backups stored in a secure off-system location | High | **Partial** | |
| EIS-026 | Quarterly test-restore of backups | Medium | **Deferred** | |
| EIS-027 | Documented incident response procedure | Medium | **Partial** | |
| EIS-028 | Automated data retention/archival policy (archive data after 5 years) | Medium | **Deferred** | |
| EIS-029 | Data-subject access requests (users can request their personal data) | Low | **Deferred** | |
| EIS-030 | Anonymize personal information in public-facing reports | Medium | **Partial** | |

**Sub-total not done: 16 of 30 Information Security items.**

---

## 3.4.3 — Product Backlog for EIS Standards / UI-UX (Table 5)

| ID | Feature | Priority | Status | Notes |
|---|---|---|---|---|
| EIS-064 | Modals closeable via an X button or Cancel button | High | **Pending** | |
| EIS-065 | Loading spinners during data fetch | High | **Pending** | ⚠️ Codebase already references a spinner in `modules/main-features.js` — worth re-checking whether this is actually implemented across all views, since the paper may be out of date here. |
| EIS-066 | Success messages shown as green toast notifications | High | **Pending** | ⚠️ `toast` logic exists in `main-features.js`, `session-actions.js`, `session-features.js`, `visual-enhancements.js` — likely partially or fully implemented; verify color/state coding matches spec. |
| EIS-067 | Error messages shown as red toast notifications | High | **Pending** | Same note as above — check styling states. |
| EIS-068 | Sufficient color contrast on all text (accessibility) | High | **Partial** | |
| EIS-069 | Full keyboard accessibility for interactive elements | Medium | **Pending** | |
| EIS-071 | Dashboard calendar widget showing current month's activities | Medium | **Pending** | |
| EIS-073 | Consistent font sizing for headings/body text | Medium | **Pending** | |
| EIS-074 | Consistent icon set used throughout the system | Medium | **Pending** | |
| EIS-075 | Clear status icon symbols (checkmarks, warnings) | Medium | **Pending** | |
| EIS-076 | Empty-state screens with guidance and action buttons | — | **No status recorded** | The paper's own table leaves the Status cell blank for this row — a gap in the document itself, not just the system. Confirm actual implementation and fill in the status. |

**Sub-total not done: 11 of 46 UI/UX items.**

---

## 3.4.4 — Product Backlog for EIS Integration (Table 6)

| ID | Integration Link | Priority | Status |
|---|---|---|---|
| EIS-079 | Calendar Management ⇄ Agenda Creation & Management (bidirectional sync) | High | **Partial** |
| EIS-080 | Calendar Management ← Reminder System (notification triggers) | Medium | **Partial** |
| EIS-082 | Session Scheduling ⇄ Deadline Tracking & Monitoring | High | **Partial** |
| EIS-083 | Session Scheduling → Calendar Management | High | **Pending** |
| EIS-084 | Session Scheduling → Reminder System | Medium | **Partial** |
| EIS-085 | Agenda Creation ⇄ Session Scheduling | High | **Pending** |
| EIS-086 | Agenda Creation ⇄ Calendar Management | High | **Pending** |
| EIS-090 | *(row's user story text missing in the source document — undefined item)* | High | **Pending** |
| EIS-092 | Reminder System ← Session Scheduling | Medium | **Partial** |
| EIS-093 | Reminder System ← Agenda Creation & Management | Medium | **Pending** |
| EIS-094 | Reminder System ← Deadline Tracking & Monitoring | Medium | **Pending** |
| EIS-095 | Reminder System → Calendar Management | Medium | **Pending** |
| EIS-096 | Session Scheduling ⇄ Document Storage & Versioning *(module not yet built)* | High | **N/A / Deferred** |
| EIS-097 | Session Scheduling ← Legislative Trend Analysis & Tagging *(module not yet built)* | Medium | **N/A / Deferred** |
| EIS-098 | Agenda Creation ⇄ Document Storage & Versioning *(module not yet built)* | High | **N/A / Deferred** |
| EIS-099 | Agenda Creation ← Advanced Search/Comparative Document Analysis *(module not yet built)* | Medium | **N/A / Deferred** |
| EIS-100 | Calendar Management ⇄ Hearing Management/Public Live Stream *(module not yet built)* | High | **N/A / Deferred** |
| EIS-101 | Session Scheduling ⇄ Scheduling Management *(module not yet built)* | High | **N/A / Deferred** |
| EIS-102 | Session Scheduling → Hearing Management/Public Live Stream *(module not yet built)* | Medium | **N/A / Deferred** |
| EIS-103 | Agenda Creation → Hearing Management/Public Live Stream *(module not yet built)* | Medium | **N/A / Deferred** |
| EIS-104 | Agenda Creation ← Scheduling Management (public hearing topics) *(module not yet built)* | Medium | **N/A / Deferred** |
| EIS-105 | Session Scheduling ← Committee Profiles & Membership *(module not yet built)* | High | **Partial / N/A** |
| EIS-106 | Session Scheduling ⇄ Committee Meetings Management *(module not yet built)* | High | **Partial / N/A** |
| EIS-107 | Agenda Creation → Referral Tracking & Handling *(module not yet built)* | High | **Partial / N/A** |
| EIS-108 | Agenda Creation ← Agenda & Deliberation Management *(module not yet built)* | Medium | **Partial / N/A** |
| EIS-109 | Agenda Creation ⇄ Action Items & Follow-Ups *(module not yet built)* | Medium | **Partial / N/A** |
| EIS-110 | Session Scheduling → Reading & Workflow Tracking *(module not yet built)* | High | **Partial / N/A** |
| EIS-111 | Session Scheduling ⇄ Voting & Enactment *(module not yet built)* | High | **Partial / N/A** |
| EIS-112 | Agenda Creation → Document Finalization *(module not yet built)* | High | **Partial / N/A** |
| EIS-113 | Agenda Creation ← Document Status & Location *(module not yet built)* | Medium | **Partial / N/A** |
| EIS-114 | Session Scheduling ⇄ Consultation Management *(module not yet built)* | Medium | **N/A / Deferred** |
| EIS-115 | Session Scheduling → Archive Intake & Classification *(module not yet built)* | Medium | **N/A / Deferred** |
| EIS-116 | Agenda Creation → Archive Intake & Classification *(module not yet built)* | Medium | **N/A / Deferred** |
| EIS-117 | Calendar Management ← Voting Sessions Management *(module not yet built)* | High | **Partial** |
| EIS-118 | Session Scheduling → Voting Sessions Management *(module not yet built)* | High | **Partial** |
| EIS-119 | Deadline Tracking ← Voting Sessions Management *(module not yet built)* | High | **Partial** |
| EIS-120 | Session Scheduling ⇄ Meeting Coordination & Scheduling *(module not yet built)* | High | **Partial** |
| EIS-121 | Agenda Creation → Meeting Coordination & Scheduling *(module not yet built)* | High | **Partial** |
| EIS-123 | Uniform JSON response format (status codes, data, error messages) across APIs | High | **Pending** | |
| EIS-124 | Standard error handling + retry logic on all API integrations | High | **Pending** | |
| EIS-125 | API endpoint versioning (e.g. `/api/v1/`) | Medium | **Pending** | ⚠️ The codebase already has a fully populated `api/v1/` directory (`bootstrap.php`, `middleware.php`, `sessions.php`, `sync.php`, `events.php`, `calendar.php`, `clients.php`, `confirmations.php`, `health.php`, etc.) alongside a separate legacy `api/` folder. Versioning appears to already be implemented — this item's "Pending" status in the paper looks outdated and should be revised to "Done."|
| EIS-126 | Conflict-resolution logic for bidirectional data sync | High | **Partial** | |
| EIS-128 | Alerts to admins on failed integration attempts | Medium | **Partial** | |
| EIS-129 | Integration test cases verifying data flow across all connected modules | High | **Partial** | |
| EIS-130 | Integration tests covering both success and failure exchange scenarios | Medium | **Pending** | |

**Sub-total not done: 44 of 54 Integration items** (the large "N/A / Deferred" cluster corresponds to modules the paper describes — Document Storage & Versioning, Hearing Management, Committee Profiles, Voting Sessions, Meeting Coordination, Archive Intake, Legislative Trend Analysis, Advanced Search/Comparative Analysis, Referral Tracking, Reading & Workflow Tracking, Consultation Management — that are referenced throughout Chapter 3 but have **no corresponding module in the current `LACS/` codebase** at all).

---

## 3.4.5 — Product Backlog for Analytics

### 3.4.5.1 Session, Agenda, Deadline & Reminder Analytics
| ID | Feature | Priority | Status |
|---|---|---|---|
| ASA-8 | Average session duration | Medium | **Partial** |
| ASA-12 | Average time from agenda creation to approval | Medium | **Partial** |
| ASA-14 | Count of agenda items authored per member | Low | **Partial** |
| ASA-15 | Deadline compliance rate (met vs missed) | High | **Done / Partial** |
| ASA-16 | Overdue deadlines by user | High | **Partial** |
| ASA-17 | Average days to deadline completion | Medium | **Partial** |
| ASA-18 | Upcoming deadlines for next 7 days | High | **Done / Partial** |
| ASA-19 | Total reminders sent per month | Medium | **Pending** |
| ASA-20 | Reminder response rate (acted vs ignored) | Medium | **Deferred** |
| ASA-21 | Most frequent reminder types per member | Low | **Deferred** |

### 3.4.5.2 Calendar Utilization & User Analytics
| ID | Feature | Priority | Status |
|---|---|---|---|
| ASA-22 | Calendar occupancy rate (scheduled vs available days) | Medium | **Deferred** |
| ASA-23 | Peak scheduling periods | Medium | **Deferred** |
| ASA-24 | Scheduling conflicts (overlapping sessions/hearings) | High | **Partial** |
| ASA-25 | Most active users (sessions, agendas, tasks) | Medium | **Partial** |
| ASA-26 | User login frequency | Low | **Partial** |
| ASA-27 | Average reminder response time by user | Low | **Deferred** |

### 3.4.5.3 Integration, Report/Export & Visualization Analytics
| ID | Feature | Priority | Status |
|---|---|---|---|
| ASA-28 | Count of agenda items sent to committees *(depends on unbuilt committee/referral modules)* | Medium | **Partial / N/A** |
| ASA-29 | Sessions linked to public hearings *(depends on unbuilt hearing module)* | Medium | **Partial / N/A** |
| ASA-30 | Document attachment rate on agendas | Low | **Partial / N/A** |
| ASA-31 | Monthly PDF summary reports (sessions, agendas, deadlines) | High | **Partial** |
| ASA-33 | Scheduled/automated monthly emailed reports | Low | **Deferred** |
| ASA-35 | Agenda status distribution — pie chart | Medium | **Pending** |
| ASA-37 | Calendar heatmap (activity density) | Low | **Deferred** |
| ASA-39 | Filter analytics by user/department | Medium | **Partial** |
| ASA-40 | Drill-down from summary metrics to detailed records | Low | **Partial** |

**Sub-total not done: 19 of 40 Analytics items.**

---

## Summary counts (paper's self-reported status)

| Section | Total items | Done | Not Done (Pending/Partial/Deferred/N/A) |
|---|---|---|---|
| 3.4.2 Information Security | 30 | 14 | **16** |
| 3.4.3 UI/UX Standards | 46 | 35 | **11** |
| 3.4.4 Integration | 54 | 10 | **44** |
| 3.4.5 Analytics | 40 | 21 | **19** |
| **Total** | **170** | **80** | **90** |

*(3.4.1 Core Module backlog — Calendar, Sessions, Agenda, Deadlines core CRUD — is 100% "Done" per the paper and is not repeated here.)*

## Biggest structural gap
The Integration table (3.4.4) is where most of the undelivered content concentrates, and it's not incidental: **EIS-096 through EIS-116 all reference modules that don't exist yet in the `LACS/` codebase** — Document Storage & Versioning, Hearing Management/Public Live Stream, Committee Profiles & Membership, Committee Meetings Management, Referral Tracking & Handling, Agenda & Deliberation Management, Action Items & Follow-Ups, Reading & Workflow Tracking, Voting & Enactment, Document Finalization, Document Status & Location, Consultation Management, Archive Intake & Classification, Legislative Trend Analysis & Tagging, and Advanced Search/Comparative Document Analysis. These are named throughout Chapter 3 as if they are sibling systems LACS integrates with, but the zip only contains the Calendar/Session/Agenda/Deadline/Reminder core plus a `docs/manuscript_tables.*` generator — none of those other modules or their APIs are present. If they're meant to be separate systems built by other groups, that should be stated explicitly in the document; if they're meant to be part of this capstone, they're the largest missing piece.

## Recommended next steps
1. Re-verify EIS-065, EIS-066, EIS-067, EIS-125 against the live app — code references exist that suggest these may already be "Done," so the paper's status table needs a refresh pass.
2. Fill in the blank status cell for EIS-076 and the missing user-story text for EIS-090 — these are gaps in the document itself.
3. Decide whether the ~20 EIS-09x/EIS-1xx integration items tied to not-yet-built modules should be marked "Out of Scope" rather than "Pending/Deferred," since there's no module to integrate with yet.
4. Prioritize EIS-001/002 (RBAC + password policy) and EIS-008 (backups) — these are High-priority security items still Pending/Partial.
