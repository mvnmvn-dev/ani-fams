<?php

namespace App\Support;

use App\Services\ContractService;
use App\Services\ReservationService;
use App\Services\RetentionService;
use App\Services\VisitorService;
use App\Repositories\ContractRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\RetentionRepository;
use App\Repositories\VisitorRepository;
use Illuminate\Support\Facades\Auth;

/**
 * Lightweight top-bar counters and shortcuts shared across authenticated pages.
 */
class TopbarData
{
    /**
     * Traditional notification-style messages for the top-bar inbox.
     *
     * @return list<array{
     *   id: string,
     *   title: string,
     *   message: string,
     *   module: string,
     *   path: string,
     *   time: string,
     *   unread: bool,
     *   tone: string
     * }>
     */
    public static function alerts(): array
    {
        $notifications = [];

        try {
            if (Auth::user()?->canPermission('reservation.view')) {
                $service = app(ReservationService::class);
                $pending = array_values(array_filter(
                    $service->listForCurrentUser(),
                    static fn(array $r): bool => ($r['status'] ?? '') === 'pending'
                ));
                foreach (array_slice($pending, 0, 3) as $r) {
                    $who = $r['requester_name'] ?? $r['requested_by_name'] ?? 'A staff member';
                    $facility = $r['facility_name'] ?? 'a facility';
                    $notifications[] = [
                        'id'      => 'reservation-' . ($r['id'] ?? uniqid()),
                        'title'   => 'Reservation awaiting approval',
                        'message' => "{$who} requested {$facility}" . (!empty($r['purpose']) ? ' — ' . $r['purpose'] : '') . '.',
                        'module'  => 'Facilities',
                        'path'    => route('reservations.index'),
                        'time'    => self::relativeTime($r['created_at'] ?? $r['start_datetime'] ?? null),
                        'unread'  => true,
                        'tone'    => 'amber',
                    ];
                }
                $extra = count($pending) - 3;
                if ($extra > 0) {
                    $notifications[] = [
                        'id'      => 'reservation-more',
                        'title'   => 'More pending reservations',
                        'message' => $extra === 1
                            ? '1 additional reservation is waiting for your review.'
                            : "{$extra} additional reservations are waiting for your review.",
                        'module'  => 'Facilities',
                        'path'    => route('reservations.index'),
                        'time'    => 'Just now',
                        'unread'  => true,
                        'tone'    => 'amber',
                    ];
                }
            }

            if (Auth::user()?->canPermission('visitor.view')) {
                $service = app(VisitorService::class);
                $onSite = array_values(array_filter(
                    $service->listVisits(),
                    static fn(array $v): bool => ($v['status'] ?? '') === 'checked_in'
                ));
                foreach (array_slice($onSite, 0, 3) as $v) {
                    $name = $v['visitor_name'] ?? 'A visitor';
                    $host = $v['host_name'] ?? 'an employee';
                    $notifications[] = [
                        'id'      => 'visitor-' . ($v['id'] ?? uniqid()),
                        'title'   => 'Visitor currently on site',
                        'message' => "{$name} checked in and is visiting {$host}.",
                        'module'  => 'Visitors',
                        'path'    => '/visitors',
                        'time'    => self::relativeTime($v['check_in_time'] ?? $v['scheduled_at'] ?? null),
                        'unread'  => true,
                        'tone'    => 'sky',
                    ];
                }
            }

            if (Auth::user()?->canPermission('retention.view')) {
                $count = (app(RetentionService::class))->pendingDisposalCount();
                if ($count > 0) {
                    $notifications[] = [
                        'id'      => 'retention-due',
                        'title'   => 'Retention review required',
                        'message' => $count === 1
                            ? '1 record is due for retention review or disposal.'
                            : "{$count} records are due for retention review or disposal.",
                        'module'  => 'Retention',
                        'path'    => '/retention',
                        'time'    => 'Today',
                        'unread'  => true,
                        'tone'    => 'red',
                    ];
                }
            }

            if (Auth::user()?->canPermission('contract.view')) {
                $contracts = (app(ContractService::class))->listContracts();
                $awaiting = array_values(array_filter(
                    $contracts,
                    static fn(array $c): bool => in_array($c['status'] ?? '', ['in_review', 'pending_signature', 'pending_approval'], true)
                ));
                foreach (array_slice($awaiting, 0, 2) as $c) {
                    $title = $c['title'] ?? 'Contract';
                    $party = $c['counterparty_name'] ?? 'counterparty';
                    $status = str_replace('_', ' ', $c['status'] ?? 'in review');
                    $notifications[] = [
                        'id'      => 'contract-' . ($c['id'] ?? uniqid()),
                        'title'   => 'Contract needs your attention',
                        'message' => "“{$title}” with {$party} is {$status}.",
                        'module'  => 'Contracts',
                        'path'    => '/contracts/' . ($c['id'] ?? ''),
                        'time'    => self::relativeTime($c['updated_at'] ?? $c['created_at'] ?? null),
                        'unread'  => true,
                        'tone'    => 'purple',
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('TopbarData::alerts failed: ' . $e->getMessage());
        }

        return array_slice($notifications, 0, 8);
    }

    /** @return list<array{label: string, path: string, icon: string}> */
    public static function quickActions(): array
    {
        $actions = [];

        if (Auth::user()?->canPermission('reservation.create')) {
            $actions[] = [
                'label' => 'New reservation',
                'path'  => route('reservations.index'),
                'icon'  => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                'tone'  => 'navy',
            ];
        }
        if (Auth::user()?->canPermission('visitor.register')) {
            $actions[] = [
                'label' => 'Register visitor',
                'path'  => '/visitors',
                'icon'  => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
                'tone'  => 'navy',
            ];
        }
        if (Auth::user()?->canPermission('document.upload')) {
            $actions[] = [
                'label' => 'Upload document',
                'path'  => '/documents',
                'icon'  => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                'tone'  => 'peach',
            ];
        }
        if (Auth::user()?->canPermission('contract.create')) {
            $actions[] = [
                'label' => 'Draft contract',
                'path'  => '/contracts',
                'icon'  => 'M9 17v-2a4 4 0 014-4h4m0 0l-3-3m3 3l-3 3M7 7h.01M7 3h5a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z',
                'tone'  => 'terracotta',
            ];
        }
        if (Auth::user()?->canPermission('legal.manage')) {
            $actions[] = [
                'label' => 'New legal case',
                'path'  => '/legal',
                'icon'  => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
                'tone'  => 'brown',
            ];
        }

        return $actions;
    }

    private static function relativeTime(?string $datetime): string
    {
        if (!$datetime) {
            return 'Recently';
        }

        $ts = strtotime($datetime);
        if ($ts === false) {
            return 'Recently';
        }

        $diff = time() - $ts;
        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return $m === 1 ? '1 min ago' : "{$m} mins ago";
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h === 1 ? '1 hour ago' : "{$h} hours ago";
        }
        if ($diff < 172800) {
            return 'Yesterday';
        }

        return date('M j', $ts);
    }
}
