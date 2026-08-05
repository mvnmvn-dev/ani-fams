<?php

namespace App\Services;

use App\Support\TopbarData;
use Illuminate\Support\Facades\Auth;
class DashboardService
{
    public function data(): array
    {
        $resService   = app(ReservationService::class);
        $visService   = app(VisitorService::class);
        $docService   = app(DocumentService::class);
        $retService   = app(RetentionService::class);
        $legalService = app(LegalCaseService::class);
        $conService   = app(ContractService::class);

        $reservations = Auth::user()?->canPermission('reservation.view') ? $resService->listForCurrentUser() : [];
        $visits       = Auth::user()?->canPermission('visitor.view') ? $visService->listVisits() : [];
        $documents    = Auth::user()?->canPermission('document.view') ? $docService->listDocuments() : [];
        $cases        = Auth::user()?->canPermission('legal.view') ? $legalService->listCases() : [];
        $contracts    = Auth::user()?->canPermission('contract.view') ? $conService->listContracts() : [];
        $assignments  = Auth::user()?->canPermission('retention.view') ? $retService->listAssignments() : [];

        $stats = [
            'pending_reservations' => Auth::user()?->canPermission('reservation.view') ? $resService->pendingCountForCurrentUser() : 0,
            'visitors_on_site'     => Auth::user()?->canPermission('visitor.view') ? $visService->onSiteCount() : 0,
            'active_documents'     => Auth::user()?->canPermission('document.view') ? $docService->activeCount() : 0,
            'pending_disposals'    => Auth::user()?->canPermission('retention.view') ? $retService->pendingDisposalCount() : 0,
            'active_legal_cases'   => Auth::user()?->canPermission('legal.view') ? $legalService->activeCount() : 0,
            'active_contracts'     => Auth::user()?->canPermission('contract.view') ? $conService->activeCount() : 0,
        ];

        return [
            'pageTitle' => 'Dashboard',
            'stats'     => $stats,
            'charts'    => [
                'reservations' => $this->countBy($reservations, 'status', ['pending', 'approved', 'rejected', 'cancelled']),
                'visits'       => $this->countBy($visits, 'status', ['scheduled', 'checked_in', 'checked_out', 'no_show']),
                'contracts'    => $this->countBy($contracts, 'status', ['draft', 'in_review', 'pending_signature', 'active', 'expired', 'terminated']),
                'legal'        => $this->countBy($cases, 'status', ['open', 'in_progress', 'on_hold', 'closed']),
                'activity'     => $this->weeklyActivity($reservations, $visits, $contracts),
            ],
            'recentReservations' => array_slice($reservations, 0, 6),
            'recentVisits'       => array_slice($visits, 0, 6),
            'recentContracts'    => array_slice($contracts, 0, 5),
            'attentionItems'     => $this->attentionItems($reservations, $visits, $assignments, $cases, $contracts),
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

    private function weeklyActivity(array $reservations, array $visits, array $contracts): array
    {
        $labels = [];
        $buckets = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('D', strtotime($day));
            $buckets[$day] = ['reservations' => 0, 'visits' => 0, 'contracts' => 0];
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

        return [
            'labels'       => $labels,
            'reservations' => array_column(array_values($buckets), 'reservations'),
            'visits'       => array_column(array_values($buckets), 'visits'),
            'contracts'    => array_column(array_values($buckets), 'contracts'),
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
