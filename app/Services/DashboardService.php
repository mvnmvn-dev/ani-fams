<?php

namespace App\Services;

use App\Support\TopbarData;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;

class DashboardService
{
    public function data(?ArnelAiService $arnel = null): array
    {
        $arnel ??= app(ArnelAiService::class);
        $resService   = app(ReservationService::class);
        $visService   = app(VisitorService::class);
        $docService   = app(DocumentService::class);
        $retService   = app(RetentionService::class);
        $legalService = app(LegalCaseService::class);
        $conService   = app(ContractService::class);
        $auditService = app(AuditLogService::class);

        $user = Auth::user();

        $reservations = $user?->canPermission('reservation.view') ? $resService->listForCurrentUser() : [];
        $visits       = $user?->canPermission('visitor.view') ? $visService->listVisits() : [];
        $documents    = $user?->canPermission('document.view') ? $docService->listDocuments() : [];
        $cases        = $user?->canPermission('legal.view') ? $legalService->listCases() : [];
        $contracts    = $user?->canPermission('contract.view') ? $conService->listContracts() : [];
        $assignments  = $user?->canPermission('retention.view') ? $retService->listAssignments() : [];
        $auditLogs    = $user?->canPermission('audit.view') ? $auditService->list([], 500) : [];

        $stats = [
            'pending_reservations' => $user?->canPermission('reservation.view') ? $resService->pendingCountForCurrentUser() : 0,
            'visitors_on_site'     => $user?->canPermission('visitor.view') ? $visService->onSiteCount() : 0,
            'active_documents'     => $user?->canPermission('document.view') ? $docService->activeCount() : 0,
            'pending_disposals'    => $user?->canPermission('retention.view') ? $retService->pendingDisposalCount() : 0,
            'active_legal_cases'   => $user?->canPermission('legal.view') ? $legalService->activeCount() : 0,
            'active_contracts'     => $user?->canPermission('contract.view') ? $conService->activeCount() : 0,
        ];

        // Completion/Success rates for Executive Trends
        $totalRes = count($reservations);
        $approvedRes = count(array_filter($reservations, fn ($r) => ($r['status'] ?? '') === 'approved'));
        $resRate = $totalRes > 0 ? round(($approvedRes / $totalRes) * 100) : 0;

        $totalVis = count($visits);
        $checkedInVis = count(array_filter($visits, fn ($v) => in_array(($v['status'] ?? ''), ['checked_in', 'checked_out'], true)));
        $visRate = $totalVis > 0 ? round(($checkedInVis / $totalVis) * 100) : 0;

        $totalContracts = count($contracts);
        $activeCon = count(array_filter($contracts, fn ($c) => in_array(($c['status'] ?? ''), ['active', 'renewed'], true)));
        $conRate = $totalContracts > 0 ? round(($activeCon / $totalContracts) * 100) : 0;

        $totalCases = count($cases);
        $resolvedCases = count(array_filter($cases, fn ($c) => in_array(($c['status'] ?? ''), ['won', 'lost', 'settled', 'closed'], true)));
        $legalRate = $totalCases > 0 ? round(($resolvedCases / $totalCases) * 100) : 0;

        $totalDocs = count($documents);
        $activeDocs = count(array_filter($documents, fn ($d) => ($d['status'] ?? '') === 'active'));
        $docRate = $totalDocs > 0 ? round(($activeDocs / $totalDocs) * 100) : 0;

        $totalAssignments = count($assignments);
        $compliantAssignments = count(array_filter($assignments, fn ($a) => ($a['status'] ?? '') === 'active'));
        $retRate = $totalAssignments > 0 ? round(($compliantAssignments / $totalAssignments) * 100) : 0;

        $trends = [
            'reservation_rate' => $resRate,
            'visitor_rate'     => $visRate,
            'contract_rate'    => $conRate,
            'legal_rate'       => $legalRate,
            'document_rate'    => $docRate,
            'retention_rate'   => $retRate,
        ];

        $attentionItems = $this->attentionItems($reservations, $visits, $assignments, $cases, $contracts);
        $aiInsights = $arnel->cachedDashboardInsights($stats, [
            'document_categories' => $this->topCountsBy($documents, 'category_name', 5),
            'reservation_status' => $this->countBy($reservations, 'status', ['pending', 'approved', 'rejected', 'cancelled']),
            'contract_status' => $this->countBy($contracts, 'status', ['draft', 'in_review', 'pending_signature', 'active', 'expired', 'terminated', 'renewed']),
        ], $attentionItems);
        AuditLogger::log('arnel', 'dashboard_insights', 'dashboard', Auth::id() ?? 0, null, [
            'summary_generated' => true,
            'attention_count' => count($attentionItems),
            'ai_configured' => $arnel->isConfigured(),
        ]);

        return [
            'pageTitle' => 'Dashboard',
            'stats'     => $stats,
            'trends'    => $trends,
            'charts'    => [
                'reservations'              => $this->countBy($reservations, 'status', ['pending', 'approved', 'rejected', 'cancelled']),
                'visits'                    => $this->countBy($visits, 'status', ['scheduled', 'checked_in', 'checked_out', 'no_show', 'denied']),
                'contracts'                 => $this->countBy($contracts, 'status', ['draft', 'in_review', 'pending_signature', 'active', 'expired', 'terminated', 'renewed']),
                'contract_types'            => $this->topCountsBy($contracts, 'type_name', 5),
                'documents'                 => $this->countBy($documents, 'status', ['draft', 'active', 'archived']),
                'documents_confidentiality' => $this->countBy($documents, 'confidentiality', ['public', 'internal', 'confidential', 'restricted']),
                'document_categories'       => $this->topCountsBy($documents, 'category_name', 5),
                'retention'                 => $this->countBy($assignments, 'status', ['active', 'due_for_review', 'on_legal_hold', 'disposed']),
                'legal'                     => $this->countBy($cases, 'status', ['open', 'in_progress', 'on_hold', 'closed', 'won', 'lost', 'settled']),
                'legal_risk'                => $this->countBy($cases, 'risk_level', ['low', 'medium', 'high', 'critical']),
                'legal_types'               => $this->countBy($cases, 'case_type', ['litigation', 'regulatory', 'labor', 'ip', 'corporate', 'other']),
                'audit_modules'             => $this->countByDynamic($auditLogs, 'module'),
                'activity'                  => $this->weeklyActivity($reservations, $visits, $contracts, $documents, $cases, $auditLogs),
            ],
            'recentReservations' => array_slice($reservations, 0, 6),
            'recentVisits'       => array_slice($visits, 0, 6),
            'recentContracts'    => array_slice($contracts, 0, 5),
            'attentionItems'     => $attentionItems,
            'aiInsights'         => $aiInsights,
            'quickActions'       => TopbarData::quickActions(),
        ];
    }

    /** @param list<string> $keys */
    private function countBy(array $rows, string $field, array $keys): array
    {
        $counts = array_fill_keys($keys, 0);
        foreach ($rows as $row) {
            $value = (string) ($row[$field] ?? '');
            if (array_key_exists($value, $counts)) {
                $counts[$value]++;
            }
        }

        return $counts;
    }

    private function countByDynamic(array $rows, string $field): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value === '') {
                $value = 'other';
            }
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }

    private function topCountsBy(array $rows, string $field, int $limit = 5): array
    {
        $counts = $this->countByDynamic($rows, $field);
        if ($counts === []) {
            return [];
        }

        $top = array_slice($counts, 0, $limit, true);
        $rest = array_slice($counts, $limit, null, true);
        if ($rest !== []) {
            $top['Other'] = array_sum($rest);
        }

        return $top;
    }

    private function weeklyActivity(
        array $reservations,
        array $visits,
        array $contracts,
        array $documents,
        array $cases,
        array $auditLogs
    ): array {
        $labels = [];
        $buckets = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('D', strtotime($day));
            $buckets[$day] = [
                'reservations' => 0,
                'visits'       => 0,
                'contracts'    => 0,
                'documents'    => 0,
                'legal'        => 0,
                'audit'        => 0,
            ];
        }

        foreach ($reservations as $row) {
            $day = substr((string) ($row['created_at'] ?? $row['start_datetime'] ?? ''), 0, 10);
            if (isset($buckets[$day])) {
                $buckets[$day]['reservations']++;
            }
        }
        foreach ($visits as $row) {
            $day = substr((string) ($row['scheduled_at'] ?? $row['created_at'] ?? ''), 0, 10);
            if (isset($buckets[$day])) {
                $buckets[$day]['visits']++;
            }
        }
        foreach ($contracts as $row) {
            $day = substr((string) ($row['created_at'] ?? ''), 0, 10);
            if (isset($buckets[$day])) {
                $buckets[$day]['contracts']++;
            }
        }
        foreach ($documents as $row) {
            $day = substr((string) ($row['created_at'] ?? ''), 0, 10);
            if (isset($buckets[$day])) {
                $buckets[$day]['documents']++;
            }
        }
        foreach ($cases as $row) {
            $day = substr((string) ($row['created_at'] ?? $row['filed_date'] ?? ''), 0, 10);
            if (isset($buckets[$day])) {
                $buckets[$day]['legal']++;
            }
        }
        foreach ($auditLogs as $row) {
            $day = substr((string) ($row['created_at'] ?? ''), 0, 10);
            if (isset($buckets[$day])) {
                $buckets[$day]['audit']++;
            }
        }

        $values = array_values($buckets);

        return [
            'labels'       => $labels,
            'reservations' => array_column($values, 'reservations'),
            'visits'       => array_column($values, 'visits'),
            'contracts'    => array_column($values, 'contracts'),
            'documents'    => array_column($values, 'documents'),
            'legal'        => array_column($values, 'legal'),
            'audit'        => array_column($values, 'audit'),
        ];
    }

    private function attentionItems(array $reservations, array $visits, array $assignments, array $cases, array $contracts): array
    {
        $items = [];

        foreach ($reservations as $r) {
            if (($r['status'] ?? '') === 'pending') {
                $items[] = [
                    'module' => 'Reservation',
                    'title'  => $r['facility_name'] ?? 'Facility request',
                    'meta'   => ($r['requester_name'] ?? 'User') . ' · ' . date('M j, g:ia', strtotime($r['start_datetime'])),
                    'status' => 'Pending approval',
                    'tone'   => 'amber',
                    'href'   => '/reservations',
                ];
            }
            if (count($items) >= 8) break;
        }

        foreach ($visits as $v) {
            if (($v['status'] ?? '') === 'checked_in') {
                $items[] = [
                    'module' => 'Visitor',
                    'title'  => $v['visitor_name'] ?? 'Visitor',
                    'meta'   => 'Host: ' . ($v['host_name'] ?? '—'),
                    'status' => 'On site',
                    'tone'   => 'blue',
                    'href'   => '/visitors',
                ];
            }
            if (count($items) >= 8) break;
        }

        foreach ($assignments as $a) {
            if (in_array($a['status'] ?? '', ['due_for_review', 'on_legal_hold'], true)) {
                $items[] = [
                    'module' => 'Retention',
                    'title'  => $a['document_title'] ?? $a['policy_name'] ?? 'Retention item',
                    'meta'   => 'Review disposal readiness',
                    'status' => ucfirst(str_replace('_', ' ', $a['status'])),
                    'tone'   => 'yellow',
                    'href'   => '/retention',
                ];
            }
            if (count($items) >= 8) break;
        }

        foreach ($cases as $c) {
            if (in_array($c['status'] ?? '', ['open', 'in_progress', 'on_hold'], true)) {
                $items[] = [
                    'module' => 'Legal',
                    'title'  => $c['title'] ?? 'Legal case',
                    'meta'   => $c['case_number'] ?? '',
                    'status' => ucfirst(str_replace('_', ' ', $c['status'])),
                    'tone'   => 'red',
                    'href'   => '/legal/' . $c['id'],
                ];
            }
            if (count($items) >= 8) break;
        }

        foreach ($contracts as $c) {
            if (in_array($c['status'] ?? '', ['draft', 'in_review', 'pending_signature'], true)) {
                $items[] = [
                    'module' => 'Contract',
                    'title'  => $c['title'] ?? 'Contract',
                    'meta'   => $c['counterparty_name'] ?? '',
                    'status' => ucfirst(str_replace('_', ' ', $c['status'])),
                    'tone'   => 'purple',
                    'href'   => '/contracts/' . $c['id'],
                ];
            }
            if (count($items) >= 8) break;
        }

        return array_slice($items, 0, 8);
    }
}
