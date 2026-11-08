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
                ['code' => 'HR2', 'name' => 'Workforce Management', 'status' => 'Document and availability intake available; HR2 connection pending', 'tone' => 'slate', 'description' => 'Attendance, leave, shifts, workforce records, and separation events.'],
                ['code' => 'HR3', 'name' => 'Performance & Development', 'status' => 'Document ingest implemented; external connection pending', 'tone' => 'purple', 'description' => 'Appraisals, training certificates, succession records, and employee documents.'],
                ['code' => 'HR4', 'name' => 'Payroll & Benefits', 'status' => 'Document ingest implemented; external connection pending', 'tone' => 'peach', 'description' => 'Payroll registers, payslips, remittances, benefits records, and restricted records retention.'],
                ['code' => 'SCM', 'name' => 'Supplier & Vendor Management', 'status' => 'Vendor visit and contract endpoints available; SCM connection pending', 'tone' => 'blue', 'description' => 'Vendor representatives and contacts as visitor profiles; supplier contracts and procurement references.'],
                ['code' => 'FVM / VRDS', 'name' => 'Fleet & Vehicle Systems', 'status' => 'Vehicle intake available; fleet mapping pending', 'tone' => 'slate', 'description' => 'Visitor vehicle and plate details, parking allocation, fleet bays, and reservation availability.'],
                ['code' => 'SWS', 'name' => 'Smart Warehousing', 'status' => 'Availability snapshot intake available; reservation lock pending', 'tone' => 'purple', 'description' => 'Shared staging areas and warehouse resources for facilities reservation conflict checks.'],
                ['code' => 'FIN', 'name' => 'Finance — AP, Budget & GL', 'status' => 'Documents and outbound cost callbacks available; receiver configuration pending', 'tone' => 'peach', 'description' => 'Contract obligations, legal case costs, reservation costs, tax and financial records.'],
                ['code' => '#10', 'name' => 'Subscription & Commission Management', 'status' => 'Contract intake available; external connection pending', 'tone' => 'blue', 'description' => 'Merchant and commission agreements routed to Contract Management.'],
                ['code' => '#9', 'name' => 'Returns, Refunds & Orders', 'status' => 'Document intake available; policy approval pending', 'tone' => 'slate', 'description' => 'Transaction records for retention and disputes escalated to Legal Management.'],
                ['code' => '#10 CS', 'name' => 'Customer Support', 'status' => 'Legal dispute referral available; external connection pending', 'tone' => 'purple', 'description' => 'Customer disputes and support cases escalated to Legal Management.'],
                ['code' => 'DTRS', 'name' => 'Document Tracking & Records System', 'status' => 'Tracking reference intake available; external connection pending', 'tone' => 'peach', 'description' => 'Document tracking references shared with Document Management.'],
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
                    'json' => "{\"applicant\": {\"external_id\": \"APP-2026-0142\", \"full_name\": \"Juan Dela Cruz\", \"email\": \"juan@example.com\"}, \"interview\": {\"external_id\": \"INT-2026-0088\", \"scheduled_at\": \"2026-11-12 10:30:00\", \"host_employee_no\": \"EMP-0021\", \"purpose\": \"Recruitment interview\", \"building_name\": \"HQ Building A\", \"floor_designation\": \"2nd Floor\"}}",
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
                    'json' => "{\"external_id\": \"PAY-2026-10-CUTOFF-2\", \"doc_type\": \"payroll_register\", \"title\": \"Payroll Register — October 2nd Cut-off\", \"period_end\": \"2026-10-31\", \"checksum_sha256\": \"(64-character SHA-256 of uploaded file)\", \"file\": \"(multipart file part: payroll-register.pdf)\"}",
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
                    'code' => 'SCM', 'title' => 'Supplier representative visit', 'subtitle' => 'Creates or updates a vendor visitor and pre-registered visit; confirm field contract with SCM',
                    'endpoint' => 'POST /api/integration/v1/inbound/scm/vendor-visits', 'badge' => 'Visitor Management · P2',
                    'fields' => [
                        ['label' => 'Supplier reference', 'value' => 'SUP-00127'],
                        ['label' => 'Visitor', 'value' => 'Ana Reyes · Vendor representative'],
                        ['label' => 'Host employee no.', 'value' => 'EMP-0021'],
                        ['label' => 'Visit purpose', 'value' => 'Quarterly supplier review'],
                        ['label' => 'Classification', 'value' => 'vendor · source SCM'],
                    ],
                    'json' => "{\n  \"supplier_external_id\": \"SUP-00127\",\n  \"visitor_external_id\": \"VND-CONTACT-883\",\n  \"full_name\": \"Ana Reyes\",\n  \"email\": \"ana@example.com\",\n  \"company\": \"Example Supply Co.\",\n  \"host_employee_no\": \"EMP-0021\",\n  \"interview_external_id\": \"INT-SCM-2026-0041\",\n  \"scheduled_at\": \"2026-11-12 09:00:00\",\n  \"purpose\": \"Quarterly supplier review\",\n  \"building_name\": \"HQ Building A\",\n  \"floor_designation\": \"2nd Floor\"\n}",
                ],
                [
                    'code' => 'FVM / VRDS', 'title' => 'Visitor vehicle and parking', 'subtitle' => 'Vehicle details are stored against an existing FAMS visit; external parking allocation remains separate',
                    'endpoint' => 'POST /api/integration/v1/inbound/fvm/vehicles', 'badge' => 'Visitor Vehicles · P2',
                    'fields' => [
                        ['label' => 'Visitor / visit ref.', 'value' => 'INT-2026-0088'],
                        ['label' => 'Plate number', 'value' => 'ABC-1234'],
                        ['label' => 'Vehicle', 'value' => 'Sedan · blue'],
                        ['label' => 'Parking resource', 'value' => 'Visitor Bay V-12'],
                        ['label' => 'Direction', 'value' => 'FAMS ↔ Fleet'],
                    ],
                    'json' => "{\"external_id\": \"VEH-2026-0009\", \"visit_source_system\": \"hr1\", \"visit_external_id\": \"INT-2026-0088\", \"plate_number\": \"ABC-1234\", \"vehicle_make\": \"Toyota\", \"vehicle_model\": \"Corolla\", \"vehicle_color\": \"Blue\"}",
                ],
                [
                    'code' => 'FVM / SWS', 'title' => 'Facility and staging availability', 'subtitle' => 'Stores time-bounded source snapshots; no reservation is blocked until external resources map to FAMS facilities',
                    'endpoint' => 'POST /api/integration/v1/inbound/sws/availability (snapshot only)', 'badge' => 'Facilities Reservation · P2',
                    'fields' => [
                        ['label' => 'Resource ref.', 'value' => 'BAY-STAGE-04'],
                        ['label' => 'Resource type', 'value' => 'Staging bay'],
                        ['label' => 'Requested interval', 'value' => '2026-11-12 09:00–12:00'],
                        ['label' => 'Availability', 'value' => 'Available / conflicts returned by source'],
                        ['label' => 'Direction', 'value' => 'FAMS reads Fleet / SWS'],
                    ],
                    'json' => "{\"external_id\": \"AVAIL-2026-0088\", \"resource_type\": \"staging_bay\", \"resource_name\": \"BAY-STAGE-04\", \"start_datetime\": \"2026-11-12 09:00:00\", \"end_datetime\": \"2026-11-12 12:00:00\", \"availability\": \"available\"}",
                ],
                [
                    'code' => 'SCM / HR1 / #10', 'title' => 'External contract intake', 'subtitle' => 'Proposed supplier, purchase-order, employment, and commission agreement references',
                    'endpoint' => 'POST /api/integration/v1/inbound/scm/contracts', 'badge' => 'Contract Management · P2',
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
                    'code' => 'FIN', 'title' => 'Finance cost and payable events', 'subtitle' => 'FAMS queues contract-obligation creation, legal-cost, and reservation-cost callbacks; configure and confirm Finance receiver',
                    'endpoint' => 'Outbound callback: configure INTEGRATION_FIN_CALLBACK_URL and queue worker', 'badge' => 'AP · Budget · GL · P2',
                    'fields' => [
                        ['label' => 'Event types', 'value' => 'obligation due · legal case cost · reservation cost'],
                        ['label' => 'Source record', 'value' => 'Contract / Legal / Reservation'],
                        ['label' => 'Amount and currency', 'value' => 'PHP 125,000.00'],
                        ['label' => 'Cost center', 'value' => 'CC-OPS-01'],
                        ['label' => 'Direction', 'value' => 'FAMS → Finance'],
                    ],
                    'json' => "{\"event_id\": \"reservation-cost-2026-0088\", \"event_type\": \"reservation.cost_recorded\", \"data\": {\"reservation_id\": 88, \"amount\": 125000.0, \"currency\": \"PHP\", \"cost_note\": \"Example only\"}}",
                ],
                [
                    'code' => 'FIN', 'title' => 'Finance tax and GL records', 'subtitle' => 'Intake endpoint is active, but documents are rejected until Finance and Records approve source retention rules',
                    'endpoint' => 'POST /api/integration/v1/inbound/fin/documents (multipart + SHA-256 checksum)', 'badge' => 'Records & Retention · P2',
                    'fields' => [
                        ['label' => 'Source', 'value' => 'Finance Tax / GL'],
                        ['label' => 'Document type', 'value' => 'tax_return / journal_report'],
                        ['label' => 'Period end', 'value' => '2026-12-31'],
                        ['label' => 'Confidentiality', 'value' => 'Restricted by policy'],
                        ['label' => 'Retention', 'value' => 'Approved policy mapping required'],
                    ],
                    'json' => "{\"external_id\": \"FIN-TAX-2026-12\", \"doc_type\": \"tax_return\", \"title\": \"FY2026 Tax Return\", \"period_end\": \"2026-12-31\", \"checksum_sha256\": \"(64-character SHA-256 of uploaded file)\", \"file\": \"(multipart file part: tax-return.pdf)\"}",
                ],
                [
                    'code' => '#9', 'title' => 'Orders, payments, and transaction records', 'subtitle' => 'Intake endpoint is active, but transaction documents require approved retention rules and data minimization',
                    'endpoint' => 'POST /api/integration/v1/inbound/ecom/documents (multipart + SHA-256 checksum)', 'badge' => 'Records & Retention · P3',
                    'fields' => [
                        ['label' => 'Order ref.', 'value' => 'ORD-2026-008811'],
                        ['label' => 'Record types', 'value' => 'order · payment · refund'],
                        ['label' => 'Period / event date', 'value' => '2026-11-08'],
                        ['label' => 'Personal data', 'value' => 'Minimize fields; policy review required'],
                        ['label' => 'Target', 'value' => 'FAMS Records & Retention'],
                    ],
                    'json' => "{\"external_id\": \"ORD-2026-008811\", \"doc_type\": \"payment_record\", \"title\": \"Order ORD-2026-008811 Payment Record\", \"period_end\": \"2026-11-08\", \"checksum_sha256\": \"(64-character SHA-256 of uploaded file)\", \"file\": \"(multipart file part: payment-record.pdf)\"}",
                ],
                [
                    'code' => '#9 / #10 CS', 'title' => 'Customer dispute escalation', 'subtitle' => 'Creates a FAMS Legal case from a normalized support or returns referral',
                    'endpoint' => 'POST /api/integration/v1/inbound/support/disputes', 'badge' => 'Legal Management · P3',
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
                    'code' => 'DTRS', 'title' => 'Document tracking reference', 'subtitle' => 'Links a DTRS tracking reference to an existing source document in FAMS',
                    'endpoint' => 'POST /api/integration/v1/inbound/dtrs/tracking', 'badge' => 'Document Management · P3',
                    'fields' => [
                        ['label' => 'FAMS document ref.', 'value' => 'DOC-2026-0012'],
                        ['label' => 'DTRS tracking ref.', 'value' => 'DTRS-009441'],
                        ['label' => 'Tracking state', 'value' => 'Received / routed / filed'],
                        ['label' => 'Direction', 'value' => 'FAMS ↔ DTRS'],
                        ['label' => 'File path exposure', 'value' => 'Not shared'],
                    ],
                    'json' => "{\"document_source_system\": \"fin\", \"document_external_id\": \"FIN-TAX-2026-12\", \"tracking_external_id\": \"DTRS-009441\", \"status\": \"filed\", \"updated_at\": \"2026-11-08T08:15:00Z\"}",
                ],
                [
                    'code' => 'HR2', 'title' => 'Host availability warning', 'subtitle' => 'Stores HR2 availability snapshots; visit warnings and reservation checks are not connected yet',
                    'endpoint' => 'POST /api/integration/v1/inbound/hr2/availability', 'badge' => 'Visitor / Reservation · P3',
                    'fields' => [
                        ['label' => 'Employee no.', 'value' => 'EMP-0021'],
                        ['label' => 'Availability window', 'value' => '2026-11-12 09:00–12:00'],
                        ['label' => 'Signal', 'value' => 'On leave / shift conflict'],
                        ['label' => 'Data rule', 'value' => 'Warning only; HR remains source of truth'],
                        ['label' => 'Direction', 'value' => 'FAMS reads HR2'],
                    ],
                    'json' => "{\"external_id\": \"HR2-EMP-0021-20261112-AM\", \"resource_type\": \"employee\", \"resource_name\": \"EMP-0021\", \"employee_no\": \"EMP-0021\", \"start_datetime\": \"2026-11-12 09:00:00\", \"end_datetime\": \"2026-11-12 12:00:00\", \"availability\": \"unavailable\", \"reason_code\": \"leave\"}",
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
                ['source' => 'SCM', 'event' => 'vendor.visit_scheduled', 'target' => 'Visitor Management', 'state' => 'FAMS intake implemented; SCM credentials and contract confirmation required'],
                ['source' => 'FVM / VRDS', 'event' => 'visitor.vehicle_registered', 'target' => 'Visitor Vehicles', 'state' => 'FAMS vehicle intake implemented; does not allocate external parking'],
                ['source' => 'FVM / SWS', 'event' => 'resource.availability_changed', 'target' => 'Availability snapshots', 'state' => 'Stored by FAMS; does not yet block reservation submissions'],
                ['source' => 'SCM / HR1 / #10', 'event' => 'contract.created_or_updated', 'target' => 'Contract Management', 'state' => 'FAMS draft upsert implemented; external connection and final field mapping pending'],
                ['source' => 'FAMS', 'event' => 'contract.obligation_created / legal.case_cost_added / reservation.cost_recorded', 'target' => 'Finance AP / Budget / GL', 'state' => 'Queued callbacks implemented; configure Finance receiver and worker'],
                ['source' => 'FIN', 'event' => 'financial_document.created', 'target' => 'Records & Retention', 'state' => 'Endpoint available; approved retention rules required before acceptance'],
                ['source' => 'ecom / support', 'event' => 'customer.dispute_escalated', 'target' => 'Legal Management', 'state' => 'Legal case referral implemented; partner credentials required'],
                ['source' => 'DTRS', 'event' => 'document.tracking_updated', 'target' => 'Document tracking references', 'state' => 'FAMS intake implemented; document must already exist in FAMS'],
                ['source' => 'FAMS', 'event' => 'authorized_data_feed.read', 'target' => 'HR Analytics / Admin Dashboard', 'state' => 'Read feed exists; resource scope and consumer setup required'],
            ],
        ]);
    }
}
