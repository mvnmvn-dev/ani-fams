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
                ['code' => 'HR1', 'name' => 'Recruitment & Core HR', 'status' => 'Awaiting connection', 'tone' => 'blue', 'description' => 'Applicants, interview schedules, hosts, employee master records'],
                ['code' => 'HR2', 'name' => 'Workforce Management', 'status' => 'Awaiting connection', 'tone' => 'slate', 'description' => 'Attendance, leave, shifts, and workforce records'],
                ['code' => 'HR3', 'name' => 'Performance & Development', 'status' => 'Awaiting connection', 'tone' => 'purple', 'description' => 'Appraisals, training certificates, and succession records'],
                ['code' => 'HR4', 'name' => 'Payroll & Benefits', 'status' => 'Awaiting connection', 'tone' => 'peach', 'description' => 'Payroll registers, payslips, remittances, and benefits records'],
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
                    'endpoint' => 'POST /api/integration/v1/inbound/hr4/documents', 'badge' => 'Records & Retention',
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
                    'code' => 'HR1–HR4', 'title' => 'Employee master sync', 'subtitle' => 'Provides employee_no values used by hosts and record ownership',
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
            ],
            'eventRows' => [
                ['source' => 'HR1', 'event' => 'employee.separated', 'target' => 'Retention + Legal', 'state' => 'Waiting for event'],
                ['source' => 'FAMS', 'event' => 'visit.arrived', 'target' => 'HR1 callback', 'state' => 'Callback configured after connection'],
                ['source' => 'FAMS', 'event' => 'visit.checked_out', 'target' => 'HR1 callback', 'state' => 'Callback configured after connection'],
            ],
        ]);
    }
}
