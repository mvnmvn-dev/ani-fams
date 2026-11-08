<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class IntegrationPreviewController extends Controller
{
    public function index(): View
    {
        return view('integrations.preview', [
            'pageTitle' => 'Integration Preview',
            'connections' => [
                ['code' => 'HR1', 'name' => 'Recruitment & Core HR', 'status' => 'Inbound endpoints implemented; external connection pending', 'tone' => 'blue', 'description' => 'Applicants, interview schedules, hosts, employee master records, and HR1 documents.'],
                ['code' => 'HR2', 'name' => 'Workforce Management', 'status' => 'Document ingest implemented; external connection pending', 'tone' => 'slate', 'description' => 'Attendance, leave, shifts, workforce records, and separation events.'],
                ['code' => 'HR3', 'name' => 'Performance & Development', 'status' => 'Document ingest implemented; external connection pending', 'tone' => 'purple', 'description' => 'Appraisals, training certificates, succession records, and employee documents.'],
                ['code' => 'HR4', 'name' => 'Payroll & Benefits', 'status' => 'Document ingest implemented; external connection pending', 'tone' => 'peach', 'description' => 'Payroll registers, payslips, remittances, benefits records, and restricted records retention.'],
                ['code' => 'SCM', 'name' => 'Supplier & Vendor Management', 'status' => 'Planned — P2', 'tone' => 'blue', 'description' => 'Vendor representatives and contacts as visitor profiles; supplier contracts and procurement references.'],
                ['code' => 'FVM / VRDS', 'name' => 'Fleet & Vehicle Systems', 'status' => 'Planned — P2', 'tone' => 'slate', 'description' => 'Visitor vehicle and plate details, parking allocation, fleet bays, and reservation availability.'],
                ['code' => 'SWS', 'name' => 'Smart Warehousing', 'status' => 'Planned — P2', 'tone' => 'purple', 'description' => 'Shared staging areas and warehouse resources for facilities reservation conflict checks.'],
                ['code' => 'FIN', 'name' => 'Finance — AP, Budget & GL', 'status' => 'Planned — P2', 'tone' => 'peach', 'description' => 'Contract obligations, legal case costs, reservation costs, tax and financial records.'],
                ['code' => '#10', 'name' => 'Subscription & Commission Management', 'status' => 'Planned — P2', 'tone' => 'blue', 'description' => 'Merchant and commission agreements routed to Contract Management.'],
                ['code' => '#9', 'name' => 'Returns, Refunds & Orders', 'status' => 'Planned — P3', 'tone' => 'slate', 'description' => 'Transaction records for retention and disputes escalated to Legal Management.'],
                ['code' => '#10 CS', 'name' => 'Customer Support', 'status' => 'Planned — P3', 'tone' => 'purple', 'description' => 'Customer disputes and support cases escalated to Legal Management.'],
                ['code' => 'DTRS', 'name' => 'Document Tracking & Records System', 'status' => 'Planned — P3', 'tone' => 'peach', 'description' => 'Document tracking references shared with Document Management.'],
                ['code' => 'ANALYTICS', 'name' => 'HR Analytics & Admin Dashboard', 'status' => 'Read feed available; consumer connection pending', 'tone' => 'blue', 'description' => 'Read-only FAMS data feeds for authorized HR analytics and administration clients.'],
            ],
            'inboxes' => [
                [
                    'code' => 'HR1', 'title' => 'Applicant / interview intake', 'subtitle' => 'Becomes a Visitor Profile + Visit after HR1 sends it',
                    'endpoint' => 'POST /api/integration/v1/inbound/hr1/interviews', 'badge' => 'Visitor Management',
                    'fields' => [
                        ['label' => 'Applicant external ID', 'value' => 'APP-2026-0142'],
                        ['label' => 'Applicant name', 'value' => 'Juan Dela Cruz'],
                        ['label' => 'Interview external ID', 'value' => 'INT-2026-0088'],
                        ['label' => 'Host employee no.', 'value' => 'EMP-0021'],
                        ['label' => 'Destination', 'value' => 'HQ Building A · 2nd Floor'],
                    ],
                    'json' => "{\n  \"applicant\": {\n    \"external_id\": \"APP-2026-0142\",\n    \"full_name\": \"Juan Dela Cruz\",\n    \"email\": \"juan@example.com\"\n  },\n  \"interview\": {\n    \"external_id\": \"INT-2026-0088\",\n    \"host_employee_no\": \"EMP-0021\",\n    \"building_name\": \"HQ Building A\",\n    \"floor_designation\": \"2nd Floor\"\n  }\n}",
                ],
                [
                    'code' => 'HR4', 'title' => 'Payroll document intake', 'subtitle' => 'Becomes a restricted Document + Retention Assignment',
                    'endpoint' => 'POST /api/integration/v1/inbound/hr4/documents (multipart: metadata + file)', 'badge' => 'Records & Retention',
                    'fields' => [
                        ['label' => 'Document external ID', 'value' => 'PAY-2026-10-CUTOFF-2'],
                        ['label' => 'Document type', 'value' => 'payroll_register'],
                        ['label' => 'Payroll period end', 'value' => '2026-10-31'],
                        ['label' => 'Confidentiality', 'value' => 'restricted'],
                        ['label' => 'Retention rule', 'value' => 'HR4 Payroll & Benefits'],
                    ],
                    'json' => "{\n  \"external_id\": \"PAY-2026-10-CUTOFF-2\",\n  \"doc_type\": \"payroll_register\",\n  \"title\": \"Payroll Register — October 2nd Cut-off\",\n  \"period_end\": \"2026-10-31\",\n  \"file\": \"payroll-register.pdf\"\n}",
                ],
                [
                    'code' => 'HR1', 'title' => 'Employee master sync', 'subtitle' => 'Provides employee_no values used by hosts and record ownership',
                    'endpoint' => 'POST /api/integration/v1/inbound/hr1/employees', 'badge' => 'Master Data',
                    'fields' => [
                        ['label' => 'Employee no.', 'value' => 'EMP-0021'],
                        ['label' => 'Employee', 'value' => 'Maria Santos'],
                        ['label' => 'Department', 'value' => 'Human Resources'],
                        ['label' => 'Position', 'value' => 'Recruitment Manager'],
                        ['label' => 'Sync behavior', 'value' => 'Create or update by employee_no'],
                    ],
                    'json' => "{\n  \"employee_no\": \"EMP-0021\",\n  \"full_name\": \"Maria Santos\",\n  \"email\": \"maria@example.com\",\n  \"department\": \"Human Resources\"\n}",
                ],
                [
                    'code' => 'SCM', 'title' => 'Supplier representative visit', 'subtitle' => 'Proposed vendor visitor intake; confirm vendor identifiers and visit workflow with SCM',
                    'endpoint' => 'Proposed: inbound vendor visitor contract (not implemented)', 'badge' => 'Visitor Management · P2',
                    'fields' => [
                        ['label' => 'Supplier reference', 'value' => 'SUP-00127'],
                        ['label' => 'Visitor', 'value' => 'Ana Reyes · Vendor representative'],
                        ['label' => 'Host employee no.', 'value' => 'EMP-0021'],
                        ['label' => 'Visit purpose', 'value' => 'Quarterly supplier review'],
                        ['label' => 'Classification', 'value' => 'vendor · source SCM'],
                    ],
                    'json' => "{\n  \"supplier_external_id\": \"SUP-00127\",\n  \"visitor_external_id\": \"VND-CONTACT-883\",\n  \"full_name\": \"Ana Reyes\",\n  \"email\": \"ana@example.com\",\n  \"host_employee_no\": \"EMP-0021\",\n  \"purpose\": \"Quarterly supplier review\"\n}",
                ],
                [
                    'code' => 'FVM / VRDS', 'title' => 'Visitor vehicle and parking', 'subtitle' => 'Proposed vehicle reference exchange; parking conflict rules need agreement',
                    'endpoint' => 'Proposed: visitor vehicle / parking contract (not implemented)', 'badge' => 'Visitor Vehicles · P2',
                    'fields' => [
                        ['label' => 'Visitor / visit ref.', 'value' => 'INT-2026-0088'],
                        ['label' => 'Plate number', 'value' => 'ABC-1234'],
                        ['label' => 'Vehicle', 'value' => 'Sedan · blue'],
                        ['label' => 'Parking resource', 'value' => 'Visitor Bay V-12'],
                        ['label' => 'Direction', 'value' => 'FAMS ↔ Fleet'],
                    ],
                    'json' => "{\n  \"visit_external_id\": \"INT-2026-0088\",\n  \"plate_number\": \"ABC-1234\",\n  \"vehicle_make\": \"Toyota\",\n  \"vehicle_color\": \"Blue\",\n  \"parking_bay_ref\": \"V-12\"\n}",
                ],
                [
                    'code' => 'FVM / SWS', 'title' => 'Facility and staging availability', 'subtitle' => 'Proposed read-only availability feed before reservation conflict checks',
                    'endpoint' => 'Proposed: availability read contract (not implemented)', 'badge' => 'Facilities Reservation · P2',
                    'fields' => [
                        ['label' => 'Resource ref.', 'value' => 'BAY-STAGE-04'],
                        ['label' => 'Resource type', 'value' => 'Staging bay'],
                        ['label' => 'Requested interval', 'value' => '2026-11-12 09:00–12:00'],
                        ['label' => 'Availability', 'value' => 'Available / conflicts returned by source'],
                        ['label' => 'Direction', 'value' => 'FAMS reads Fleet / SWS'],
                    ],
                    'json' => "{\n  \"resource_external_id\": \"BAY-STAGE-04\",\n  \"start_datetime\": \"2026-11-12 09:00:00\",\n  \"end_datetime\": \"2026-11-12 12:00:00\",\n  \"availability\": \"available\"\n}",
                ],
                [
                    'code' => 'SCM / HR1 / #10', 'title' => 'External contract intake', 'subtitle' => 'Proposed supplier, purchase-order, employment, and commission agreement references',
                    'endpoint' => 'Proposed: contract upsert contract (not implemented)', 'badge' => 'Contract Management · P2',
                    'fields' => [
                        ['label' => 'External contract ref.', 'value' => 'CTR-EXT-2026-0042'],
                        ['label' => 'Agreement type', 'value' => 'Supplier / employment / commission'],
                        ['label' => 'Counterparty', 'value' => 'Example Supply Co.'],
                        ['label' => 'Related PO / source ref.', 'value' => 'PO-2026-0088'],
                        ['label' => 'Target', 'value' => 'FAMS Contract Management'],
                    ],
                    'json' => "{\n  \"external_id\": \"CTR-EXT-2026-0042\",\n  \"contract_type\": \"supplier\",\n  \"title\": \"Warehouse Supply Agreement\",\n  \"counterparty_name\": \"Example Supply Co.\",\n  \"purchase_order_ref\": \"PO-2026-0088\",\n  \"start_date\": \"2026-11-01\",\n  \"end_date\": \"2027-10-31\"\n}",
                ],
                [
                    'code' => 'FIN', 'title' => 'Finance cost and payable events', 'subtitle' => 'Proposed FAMS-to-Finance events; receiving endpoint and accounting codes are TBD',
                    'endpoint' => 'Proposed callback: Finance event receiver (not implemented)', 'badge' => 'AP · Budget · GL · P2',
                    'fields' => [
                        ['label' => 'Event types', 'value' => 'obligation due · legal case cost · reservation cost'],
                        ['label' => 'Source record', 'value' => 'Contract / Legal / Reservation'],
                        ['label' => 'Amount and currency', 'value' => 'PHP 125,000.00'],
                        ['label' => 'Cost center', 'value' => 'CC-OPS-01'],
                        ['label' => 'Direction', 'value' => 'FAMS → Finance'],
                    ],
                    'json' => "{\n  \"event_type\": \"contract.obligation_due\",\n  \"source_record_ref\": \"CTR-2026-0042\",\n  \"amount\": 125000.00,\n  \"currency\": \"PHP\",\n  \"cost_center\": \"CC-OPS-01\",\n  \"due_date\": \"2026-12-01\"\n}",
                ],
                [
                    'code' => 'FIN', 'title' => 'Finance tax and GL records', 'subtitle' => 'Proposed Finance-to-Records document intake; retention categories and periods require approval',
                    'endpoint' => 'Proposed: finance document intake (not implemented)', 'badge' => 'Records & Retention · P2',
                    'fields' => [
                        ['label' => 'Source', 'value' => 'Finance Tax / GL'],
                        ['label' => 'Document type', 'value' => 'tax_return / journal_report'],
                        ['label' => 'Period end', 'value' => '2026-12-31'],
                        ['label' => 'Confidentiality', 'value' => 'Restricted by policy'],
                        ['label' => 'Retention', 'value' => 'Approved policy mapping required'],
                    ],
                    'json' => "{\n  \"external_id\": \"FIN-TAX-2026-12\",\n  \"doc_type\": \"tax_return\",\n  \"title\": \"FY2026 Tax Return\",\n  \"period_end\": \"2026-12-31\",\n  \"confidentiality\": \"restricted\",\n  \"file\": \"multipart upload required by current HR ingest API\"\n}",
                ],
                [
                    'code' => '#9', 'title' => 'Orders, payments, and transaction records', 'subtitle' => 'Proposed records-retention feed; ecommerce source contract and lawful retention rules are TBD',
                    'endpoint' => 'Proposed: transaction record intake (not implemented)', 'badge' => 'Records & Retention · P3',
                    'fields' => [
                        ['label' => 'Order ref.', 'value' => 'ORD-2026-008811'],
                        ['label' => 'Record types', 'value' => 'order · payment · refund'],
                        ['label' => 'Period / event date', 'value' => '2026-11-08'],
                        ['label' => 'Personal data', 'value' => 'Minimize fields; policy review required'],
                        ['label' => 'Target', 'value' => 'FAMS Records & Retention'],
                    ],
                    'json' => "{\n  \"external_id\": \"ORD-2026-008811\",\n  \"record_type\": \"payment\",\n  \"transaction_date\": \"2026-11-08\",\n  \"currency\": \"PHP\",\n  \"amount\": 2499.00\n}",
                ],
                [
                    'code' => '#9 / #10 CS', 'title' => 'Customer dispute escalation', 'subtitle' => 'Proposed support or returns escalation into Legal Management',
                    'endpoint' => 'Proposed: legal case referral contract (not implemented)', 'badge' => 'Legal Management · P3',
                    'fields' => [
                        ['label' => 'External case ref.', 'value' => 'SUPPORT-CASE-2026-0317'],
                        ['label' => 'Origin', 'value' => 'Returns / Refunds / Customer Support'],
                        ['label' => 'Dispute type', 'value' => 'Refund dispute'],
                        ['label' => 'Evidence refs.', 'value' => 'Document references only; no public file paths'],
                        ['label' => 'Target', 'value' => 'FAMS Legal Management'],
                    ],
                    'json' => "{\n  \"external_id\": \"SUPPORT-CASE-2026-0317\",\n  \"origin_system\": \"customer_support\",\n  \"dispute_type\": \"refund_dispute\",\n  \"summary\": \"Customer disputes a refund decision\",\n  \"evidence_refs\": [\"DOC-2026-0012\"]\n}",
                ],
                [
                    'code' => 'DTRS', 'title' => 'Document tracking reference', 'subtitle' => 'Proposed tracking reference exchange with Document Management',
                    'endpoint' => 'Proposed: DTRS document status contract (not implemented)', 'badge' => 'Document Management · P3',
                    'fields' => [
                        ['label' => 'FAMS document ref.', 'value' => 'DOC-2026-0012'],
                        ['label' => 'DTRS tracking ref.', 'value' => 'DTRS-009441'],
                        ['label' => 'Tracking state', 'value' => 'Received / routed / filed'],
                        ['label' => 'Direction', 'value' => 'FAMS ↔ DTRS'],
                        ['label' => 'File path exposure', 'value' => 'Not shared'],
                    ],
                    'json' => "{\n  \"document_external_id\": \"DOC-2026-0012\",\n  \"tracking_external_id\": \"DTRS-009441\",\n  \"status\": \"filed\",\n  \"updated_at\": \"2026-11-08T08:15:00Z\"\n}",
                ],
                [
                    'code' => 'HR2', 'title' => 'Host availability warning', 'subtitle' => 'Possible later read-only shift and leave signal for visits and reservations',
                    'endpoint' => 'Proposed: HR2 availability feed (not implemented)', 'badge' => 'Visitor / Reservation · P3',
                    'fields' => [
                        ['label' => 'Employee no.', 'value' => 'EMP-0021'],
                        ['label' => 'Availability window', 'value' => '2026-11-12 09:00–12:00'],
                        ['label' => 'Signal', 'value' => 'On leave / shift conflict'],
                        ['label' => 'Data rule', 'value' => 'Warning only; HR remains source of truth'],
                        ['label' => 'Direction', 'value' => 'FAMS reads HR2'],
                    ],
                    'json' => "{\n  \"employee_no\": \"EMP-0021\",\n  \"unavailable_from\": \"2026-11-12 09:00:00\",\n  \"unavailable_until\": \"2026-11-12 12:00:00\",\n  \"reason_code\": \"leave\"\n}",
                ],
                [
                    'code' => 'FAMS', 'title' => 'Authorized analytics data feeds', 'subtitle' => 'Read-only exports for authorized analytics and admin clients; scope enforcement applies',
                    'endpoint' => 'GET /api/integration/v1/data/{resource} (resource-specific read scopes)', 'badge' => 'Analytics / Admin',
                    'fields' => [
                        ['label' => 'Example resources', 'value' => 'employees · visitors · documents · reservations'],
                        ['label' => 'Access', 'value' => 'Per-resource scopes required'],
                        ['label' => 'Document files', 'value' => 'File paths excluded from version feed'],
                        ['label' => 'Consumer', 'value' => 'HR Analytics / Admin Dashboard'],
                        ['label' => 'Connection', 'value' => 'Consumer credentials not yet configured'],
                    ],
                    'json' => "{\n  \"resource\": \"employees\",\n  \"limit\": 100,\n  \"updated_since\": \"2026-11-01T00:00:00Z\",\n  \"required_scope\": \"employees:read\"\n}",
                ],
            ],
            'eventRows' => [
                ['source' => 'HR1 / HR2', 'event' => 'employee.separated', 'target' => 'Retention + Legal', 'state' => 'Inbound endpoint implemented; source agreement and event pending'],
                ['source' => 'FAMS', 'event' => 'visit.arrived', 'target' => 'HR1 callback', 'state' => 'Queued callback implemented; HR1 URL/token configuration required'],
                ['source' => 'FAMS', 'event' => 'visit.checked_out', 'target' => 'HR1 callback', 'state' => 'Callback handler available; verify lifecycle dispatch and configure HR1 receiver'],
                ['source' => 'SCM', 'event' => 'vendor.visit_scheduled', 'target' => 'Visitor Management', 'state' => 'Planned P2 — contract not agreed'],
                ['source' => 'Fleet / VRDS', 'event' => 'visitor.vehicle_registered', 'target' => 'Visitor Management / Parking', 'state' => 'Planned P2 — contract not implemented'],
                ['source' => 'Fleet / SWS', 'event' => 'resource.availability_changed', 'target' => 'Facilities Reservation', 'state' => 'Planned P2 — read-feed contract not agreed'],
                ['source' => 'SCM / HR1 / #10', 'event' => 'contract.created_or_updated', 'target' => 'Contract Management', 'state' => 'Planned P2 — upsert contract not implemented'],
                ['source' => 'FAMS', 'event' => 'contract.obligation_due / cost.recorded', 'target' => 'Finance AP / Budget / GL', 'state' => 'Planned P2 — Finance receiver not configured'],
                ['source' => 'Finance', 'event' => 'financial_record.created', 'target' => 'Records & Retention', 'state' => 'Planned P2 — source rules and contract required'],
                ['source' => '#9 / #10 CS', 'event' => 'customer.dispute_escalated', 'target' => 'Legal Management', 'state' => 'Planned P3 — referral contract not implemented'],
                ['source' => 'DTRS', 'event' => 'document.tracking_updated', 'target' => 'Document Management', 'state' => 'Planned P3 — reference contract not agreed'],
                ['source' => 'FAMS', 'event' => 'authorized_data_feed.read', 'target' => 'HR Analytics / Admin Dashboard', 'state' => 'Read feed exists; resource-specific scope and consumer setup required'],
            ],
        ]);
    }
}
