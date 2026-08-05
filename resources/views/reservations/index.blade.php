@extends('layouts.app')

@section('content')
<?php

/** @var array $reservations */
/** @var array $facilities */
/** @var bool $canApprove */

$statusColors = [
    'pending'   => 'bg-accent-50 text-accent-700 border-accent-200',
    'approved'  => 'bg-brand-50 text-brand-700 border-brand-300',
    'rejected'  => 'bg-accent-50 text-accent-700 border-accent-200',
    'cancelled' => 'bg-gray-100 text-gray-500 border-gray-200',
];
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$todayBookings = 0;
$todayKey = date('Y-m-d');
foreach ($reservations as $r) {
    if ($r['status'] === 'pending') $pendingCount++;
    elseif ($r['status'] === 'approved') $approvedCount++;
    elseif ($r['status'] === 'rejected') $rejectedCount++;
    if (str_starts_with((string) ($r['start_datetime'] ?? ''), $todayKey)) $todayBookings++;
}
$todayReservations = array_values(array_filter($reservations, static fn($r) => str_starts_with((string) ($r['start_datetime'] ?? ''), $todayKey)));
usort($todayReservations, static fn($a, $b) => strcmp($a['start_datetime'] ?? '', $b['start_datetime'] ?? ''));
$facilityUsage = [];
foreach ($reservations as $r) {
    if (($r['status'] ?? '') === 'approved') {
        $facilityUsage[$r['facility_name']] = ($facilityUsage[$r['facility_name']] ?? 0) + 1;
    }
}
arsort($facilityUsage);
$upcomingApproved = array_values(array_filter($reservations, static fn($r) => ($r['status'] ?? '') === 'approved' && strtotime($r['start_datetime'] ?? '') >= time()));
usort($upcomingApproved, static fn($a, $b) => strcmp($a['start_datetime'] ?? '', $b['start_datetime'] ?? ''));
$upcomingApproved = array_slice($upcomingApproved, 0, 5);
?>
<div class="animate-fade-in-up space-y-8">
    <?php
    render_page_header([
        'title' => 'Facilities Reservation',
        'subtitle' => $canApprove
            ? 'All reservation requests across the organization.'
            : 'Your reservation requests.',
        'actions' => function () {
            if (!can_perm('reservation.create')) return;
            ?>
            <button onclick="openModal('reservationModal')" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Reservation Request
            </button>
            <?php
        },
    ]);
    ?>

    @include('partials.stat_kpi_grid', ['cards' => [
        ['label' => 'Pending review', 'value' => $pendingCount, 'hint' => 'Awaiting approve / reject', 'tone' => 'peach', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Approved', 'value' => $approvedCount, 'hint' => 'Confirmed bookings', 'tone' => 'navy', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Today', 'value' => $todayBookings, 'hint' => 'Starting today', 'tone' => 'navy', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['label' => 'Rejected', 'value' => $rejectedCount, 'hint' => 'Declined requests', 'tone' => 'terracotta', 'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ]])

    <div class="module-info-grid">
        @include('partials.module_feed_list', [
            'title' => "Today's schedule",
            'subtitle' => 'All bookings starting today across facilities',
            'items' => array_map(static fn($r) => [
                'label' => $r['facility_name'],
                'meta'  => ($r['purpose'] ?? '') . ' · ' . ($r['requester_name'] ?? ''),
                'aside' => date('g:ia', strtotime($r['start_datetime'])) . ' – ' . date('g:ia', strtotime($r['end_datetime'])),
                'badge' => $r['status'] ?? null,
            ], $todayReservations),
            'empty' => 'No reservations scheduled for today.',
        ])
        @include('partials.module_feed_list', [
            'title' => 'Upcoming approved bookings',
            'subtitle' => 'Next confirmed facility reservations',
            'items' => array_map(static fn($r) => [
                'label' => $r['facility_name'],
                'meta'  => ($r['requester_name'] ?? '') . ' · ' . ($r['purpose'] ?? ''),
                'aside' => date('M j, g:ia', strtotime($r['start_datetime'])),
            ], $upcomingApproved),
            'empty' => 'No upcoming approved bookings.',
        ])
    </div>

    <?php if (!empty($facilityUsage)): ?>
    <div class="module-insight-panel">
        <h3 class="module-insight-title">Facility utilization</h3>
        <p class="module-insight-sub">Approved bookings per facility (all time in view)</p>
        <?php foreach (array_slice($facilityUsage, 0, 8, true) as $facility => $count): ?>
            <div class="module-stat-row">
                <span class="text-ink/70"><?= htmlspecialchars($facility) ?></span>
                <strong class="tabular-nums text-ink"><?= $count ?> booking<?= $count === 1 ? '' : 's' ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
    $pendingReservations = array_values(array_filter($reservations, static fn($r) => $r['status'] === 'pending'));
    $facilityOptions = [];
    foreach ($reservations as $r) {
        $facilityOptions[$r['facility_name']] = $r['facility_name'];
    }
    ksort($facilityOptions);
    ?>
    <div class="space-y-6" data-filter-root>
        <?php if (!empty($reservations)): ?>
            @include('partials.module_filters', ['fields' => [
                ['name' => 'q', 'label' => 'Search', 'placeholder' => 'Facility, purpose, requester…'],
                ['name' => 'status', 'label' => 'Status', 'allLabel' => 'All statuses', 'options' => [
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'cancelled' => 'Cancelled',
                ]],
                ['name' => 'facility', 'label' => 'Facility', 'allLabel' => 'All facilities', 'options' => $facilityOptions],
            ]])
        <?php endif; ?>

        <div class="module-empty" data-filter-empty hidden>No reservations match these filters.</div>

        <?php if ($canApprove && !empty($pendingReservations)): ?>
            <section data-filter-section>
                <p class="module-section-label">Awaiting decision · <?= count($pendingReservations) ?></p>
                <div class="module-table-wrap">
                    <div class="overflow-x-auto">
                        <table class="module-table">
                            <thead>
                                <tr>
                                    <th>Facility</th>
                                    <th>When</th>
                                    <th>Purpose</th>
                                    <th>Requested by</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingReservations as $r): ?>
                                    <tr data-filter-item
                                        data-reservation-id="<?= (int) $r['id'] ?>"
                                        data-filter-status="<?= htmlspecialchars($r['status']) ?>"
                                        data-filter-facility="<?= htmlspecialchars($r['facility_name']) ?>"
                                        data-filter-search="<?= htmlspecialchars(strtolower($r['facility_name'] . ' ' . $r['purpose'] . ' ' . ($r['requester_name'] ?? ''))) ?>">
                                        <td class="font-semibold"><?= htmlspecialchars($r['facility_name']) ?></td>
                                        <td class="text-ink/65 whitespace-nowrap">
                                            <?= date('M j, g:ia', strtotime($r['start_datetime'])) ?> – <?= date('g:ia', strtotime($r['end_datetime'])) ?>
                                        </td>
                                        <td class="text-ink/70"><?= htmlspecialchars($r['purpose']) ?></td>
                                        <td class="text-ink/70"><?= htmlspecialchars($r['requester_name']) ?></td>
                                        <td class="text-right whitespace-nowrap">
                                            <form action="<?= url('/reservations/' . $r['id'] . '/decide') ?>" method="POST" class="inline" data-ajax-action="reservation.decide">
                                                @csrf
                                                <input type="hidden" name="decision" value="approved">
                                                <button class="text-xs font-bold text-brand-700 hover:text-brand-900 mr-3">Approve</button>
                                            </form>
                                            <form action="<?= url('/reservations/' . $r['id'] . '/decide') ?>" method="POST" class="inline" data-ajax-action="reservation.decide">
                                                @csrf
                                                <input type="hidden" name="decision" value="rejected">
                                                <button class="text-xs font-bold text-accent-700 hover:text-accent-600">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section>
            <?php if (empty($reservations)): ?>
                <div class="module-empty">No reservations yet.</div>
            <?php else: ?>
                <div class="cal-shell" id="jsCalendar"></div>
                <script>
                (function () {
                    var MIN = '2026-07';
                    var MAX = '2030-12';
                    var TODAY = <?= json_encode($todayKey) ?>;
                    var MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                    var reservations = <?= json_encode(array_values($reservations), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

                    var byDay = {};
                    reservations.forEach(function (r) {
                        var key = r.start_datetime.substring(0, 10);
                        if (!byDay[key]) byDay[key] = [];
                        byDay[key].push(r);
                    });

                    var now = new Date();
                    var currentMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
                    if (currentMonth < MIN) currentMonth = MIN;
                    if (currentMonth > MAX) currentMonth = MAX;

                    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

                    function formatTime(dt) {
                        var d = new Date(dt.replace(' ', 'T'));
                        var h = d.getHours(), m = d.getMinutes();
                        var ampm = h >= 12 ? 'pm' : 'am';
                        h = h % 12 || 12;
                        return h + (m ? ':' + pad2(m) : '') + ampm;
                    }

                    function esc(s) {
                        var el = document.createElement('span');
                        el.textContent = s;
                        return el.innerHTML;
                    }

                    function refreshFilters() {
                        var root = document.querySelector('[data-filter-root]');
                        if (root && typeof root._moduleFilterApply === 'function') {
                            root._moduleFilterApply();
                        }
                    }

                    function render(ym) {
                        var el = document.getElementById('jsCalendar');
                        if (!el) return;

                        var parts = ym.split('-');
                        var year = parseInt(parts[0], 10);
                        var month = parseInt(parts[1], 10) - 1;
                        var first = new Date(year, month, 1);
                        var daysInMonth = new Date(year, month + 1, 0).getDate();
                        var startDow = first.getDay();

                        var prev = month === 0 ? (year - 1) + '-12' : year + '-' + pad2(month);
                        var next = month === 11 ? (year + 1) + '-01' : year + '-' + pad2(month + 2);

                        var html = '<div class="cal-toolbar"><h2>' + MONTHS[month] + ' ' + year + ' schedule</h2><div class="cal-nav">';

                        if (ym > MIN) {
                            html += '<button type="button" data-cal-go="' + prev + '" aria-label="Previous month">&#8249;</button>';
                        } else {
                            html += '<span class="cal-nav-disabled">&#8249;</span>';
                        }

                        var todayMonth = new Date().getFullYear() + '-' + pad2(new Date().getMonth() + 1);
                        if (todayMonth < MIN) todayMonth = MIN;
                        if (todayMonth > MAX) todayMonth = MAX;
                        html += '<button type="button" data-cal-go="' + todayMonth + '">Today</button>';

                        if (ym < MAX) {
                            html += '<button type="button" data-cal-go="' + next + '" aria-label="Next month">&#8250;</button>';
                        } else {
                            html += '<span class="cal-nav-disabled">&#8250;</span>';
                        }

                        html += '</div></div>';
                        html += '<div class="cal-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>';
                        html += '<div class="cal-grid">';

                        for (var i = 0; i < startDow; i++) {
                            html += '<div class="cal-day is-muted"></div>';
                        }

                        for (var d = 1; d <= daysInMonth; d++) {
                            var key = ym + '-' + pad2(d);
                            var isToday = key === TODAY;
                            var events = byDay[key] || [];
                            html += '<div class="cal-day' + (isToday ? ' is-today' : '') + '">';
                            html += '<div class="cal-day-num">' + d + '</div>';
                            html += '<div class="cal-events">';
                            events.forEach(function (r) {
                                var search = (r.facility_name + ' ' + r.purpose + ' ' + (r.requester_name || '')).toLowerCase();
                                html += '<div class="cal-event is-' + esc(r.status) + '"'
                                    + ' data-filter-item'
                                    + ' data-filter-status="' + esc(r.status) + '"'
                                    + ' data-filter-facility="' + esc(r.facility_name) + '"'
                                    + ' data-filter-search="' + esc(search) + '"'
                                    + ' title="' + esc(r.facility_name + ' \u00b7 ' + r.purpose + ' \u00b7 ' + r.status) + '">'
                                    + '<span class="cal-event-time">' + formatTime(r.start_datetime) + '</span>'
                                    + '<span class="cal-event-title">' + esc(r.facility_name) + '</span>'
                                    + '</div>';
                            });
                            html += '</div></div>';
                        }

                        var totalCells = startDow + daysInMonth;
                        while (totalCells % 7 !== 0) {
                            html += '<div class="cal-day is-muted"></div>';
                            totalCells++;
                        }

                        html += '</div>';
                        html += '<div class="cal-legend">'
                            + '<span><i style="background:#CCAD9F"></i>Pending</span>'
                            + '<span><i style="background:#142E4C"></i>Approved</span>'
                            + '<span><i style="background:#A06A56"></i>Rejected</span>'
                            + '<span><i style="background:#94a3b8"></i>Cancelled</span>'
                            + '</div>';

                        el.innerHTML = html;

                        el.querySelectorAll('[data-cal-go]').forEach(function (btn) {
                            btn.addEventListener('click', function (e) {
                                e.preventDefault();
                                e.stopPropagation();
                                currentMonth = btn.getAttribute('data-cal-go');
                                render(currentMonth);
                            });
                        });

                        refreshFilters();
                    }

                    render(currentMonth);
                })();
                </script>
            <?php endif; ?>
        </section>
    </div>
</div>

<!-- Reservation Form Modal (Outside of transform container) -->
<?php if (can_perm('reservation.create')): ?>
<div id="reservationModal" class="modal-overlay hidden">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full overflow-hidden border border-ink/10 animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-8 py-5 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-xl font-bold text-ink">New Reservation Request</h3>
            <button onclick="closeModal('reservationModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/reservations') ?>" method="POST" class="p-8 space-y-6">
    @csrf
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-ink/60">Select Facility</label>
                <select name="facility_id" required class="w-full rounded-xl border border-ink/15 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-sand/10">
                    <option value="">Select a facility…</option>
                    <?php foreach ($facilities as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> (Capacity: <?= $f['capacity'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-ink/60">Purpose of Booking</label>
                <input type="text" name="purpose" required maxlength="255"
                       class="w-full rounded-xl border border-ink/15 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-sand/10"
                       placeholder="e.g. Board Meeting, Maintenance, Client Presentation">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-ink/60">Start Date &amp; Time</label>
                    <input type="datetime-local" name="start_datetime" required
                           class="w-full rounded-xl border border-ink/15 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-sand/10">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-ink/60">End Date &amp; Time</label>
                    <input type="datetime-local" name="end_datetime" required
                           class="w-full rounded-xl border border-ink/15 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-sand/10">
                </div>
            </div>
            <div class="pt-6 border-t border-ink/10 flex justify-end gap-3.5">
                <button type="button" onclick="closeModal('reservationModal')" class="px-5 py-3 rounded-xl border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-3 rounded-xl bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm transition shadow-sm">
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

@endsection
