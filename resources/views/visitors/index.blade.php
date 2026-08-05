@extends('layouts.app')

@section('content')
<?php
/** @var array $visits */
/** @var array $visitors */
/** @var array $employees */
/** @var array $areas */
/** @var bool $canRegister */
/** @var bool $canCheckIn */
/** @var bool $canManage */

$statusStyles = [
    'scheduled'   => 'bg-brand-50 text-brand-700 border-brand-200',
    'checked_in'  => 'bg-brand-50 text-brand-700 border-brand-300 font-semibold',
    'checked_out' => 'bg-gray-100 text-gray-700 border-gray-200',
    'no_show'     => 'bg-accent-50 text-accent-700 border-accent-200',
    'denied'      => 'bg-accent-50 text-accent-700 border-accent-200 font-semibold',
];

$onSite      = array_values(array_filter($visits, fn($v) => $v['status'] === 'checked_in'));
$scheduled   = array_values(array_filter($visits, fn($v) => $v['status'] === 'scheduled'));
$completed   = array_values(array_filter($visits, fn($v) => in_array($v['status'], ['checked_out', 'no_show', 'denied'])));
$watchlisted = array_values(array_filter($visits, fn($v) => $v['is_watchlisted']));
$todayKey = date('Y-m-d');
$scheduledToday = count(array_filter($scheduled, fn($v) => str_starts_with((string) ($v['expected_arrival'] ?? $v['visit_date'] ?? ''), $todayKey)
    || str_starts_with((string) ($v['scheduled_at'] ?? ''), $todayKey)
    || str_starts_with((string) ($v['expected_arrival_at'] ?? ''), $todayKey)));
$checkedOutToday = count(array_filter($visits, fn($v) => $v['status'] === 'checked_out' && (
    str_starts_with((string) ($v['checked_out_at'] ?? ''), $todayKey)
)));
$expectedToday = array_values(array_filter($scheduled, static fn($v) =>
    str_starts_with((string) ($v['expected_arrival'] ?? $v['visit_date'] ?? $v['scheduled_at'] ?? $v['expected_arrival_at'] ?? ''), $todayKey)
    || str_starts_with((string) ($v['start_datetime'] ?? ''), $todayKey)
));
$recentCheckIns = array_values(array_filter($visits, static fn($v) => ($v['status'] ?? '') === 'checked_in' || !empty($v['check_in_time'])));
usort($recentCheckIns, static fn($a, $b) => strtotime($b['check_in_time'] ?? $b['created_at'] ?? '0') <=> strtotime($a['check_in_time'] ?? $a['created_at'] ?? '0'));
$recentCheckIns = array_slice($recentCheckIns, 0, 5);

?>
<div class="animate-fade-in-up space-y-8">
    <?php
    render_page_header([
        'title' => 'Visitors',
        'subtitle' => 'Pre-register visitors, check-in/out, and track who is on the premises.',
        'actions' => function () use ($canRegister) {
            if (!$canRegister) return;
            ?>
            <button onclick="openModal('registerModal')" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Pre-Register Visitor
            </button>
            <?php
        },
    ]);
    ?>

    @include('partials.stat_kpi_grid', ['cards' => [
        ['label' => 'On site now', 'value' => count($onSite), 'hint' => 'Checked in and present', 'tone' => 'navy', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Scheduled', 'value' => count($scheduled), 'hint' => 'Awaiting arrival', 'tone' => 'navy', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['label' => 'Watchlist hits', 'value' => count($watchlisted), 'hint' => 'Flagged visitors', 'tone' => 'peach', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
        ['label' => 'Completed', 'value' => count($completed), 'hint' => 'Checked out / closed', 'tone' => 'terracotta', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ]])

    <div class="module-info-grid">
        @include('partials.module_feed_list', [
            'title' => 'Expected arrivals today',
            'subtitle' => 'Pre-registered visitors scheduled for today',
            'items' => array_map(static fn($v) => [
                'label' => $v['visitor_name'],
                'meta'  => 'Host: ' . ($v['host_name'] ?? 'TBD') . ' · ' . ($v['purpose'] ?? 'No purpose stated'),
                'aside' => !empty($v['expected_arrival']) ? date('g:ia', strtotime($v['expected_arrival'])) : 'TBD',
                'badge' => $v['is_watchlisted'] ? 'watchlist' : null,
            ], $expectedToday),
            'empty' => 'No visitors expected today.',
        ])
        @include('partials.module_feed_list', [
            'title' => 'Recent check-ins',
            'subtitle' => 'Latest visitors who entered the premises',
            'items' => array_map(static fn($v) => [
                'label' => $v['visitor_name'],
                'meta'  => ($v['visitor_company'] ?: 'Individual') . ' · ' . ($v['allowed_areas'] ?: 'General access'),
                'aside' => !empty($v['check_in_time']) ? date('M j, g:ia', strtotime($v['check_in_time'])) : '',
            ], $recentCheckIns),
            'empty' => 'No check-ins recorded yet.',
        ])
    </div>

    <?php if (!empty($watchlisted)): ?>
    <div class="module-insight-panel">
        <h3 class="module-insight-title">Security watchlist</h3>
        <p class="module-insight-sub"><?= count($watchlisted) ?> flagged visitor record<?= count($watchlisted) === 1 ? '' : 's' ?> require extra screening before entry</p>
        <ul class="module-insight-list">
            <?php foreach (array_slice($watchlisted, 0, 4) as $w): ?>
                <li>
                    <span class="module-insight-dot"></span>
                    <span><strong><?= htmlspecialchars($w['visitor_name']) ?></strong> — <?= htmlspecialchars($w['visitor_company'] ?: 'No company') ?> · Host: <?= htmlspecialchars($w['host_name'] ?? 'TBD') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- On-site visitors — prominent cards -->
    <?php if (!empty($onSite)): ?>
        <section id="onsite">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-2.5 h-2.5 rounded-full bg-brand-500 animate-pulse"></span>
                <h2 class="text-sm font-bold uppercase tracking-wider text-ink/50">Currently on site · <?= count($onSite) ?></h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($onSite as $visit): ?>
                    <div class="visitor-onsite-card <?= $visit['is_watchlisted'] ? 'is-watch' : '' ?>">
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="font-bold text-ink text-sm"><?= htmlspecialchars($visit['visitor_name']) ?></p>
                                    <?php if ($visit['is_watchlisted']): ?>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-accent-100 text-accent-700 uppercase tracking-wide border border-accent-200">Watchlist</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-ink/50 mt-0.5"><?= htmlspecialchars($visit['visitor_company'] ?: 'Individual') ?></p>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-brand-50 text-brand-700 border border-brand-200 shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-brand-500"></span>
                                Checked In
                            </span>
                        </div>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center gap-2 text-ink/60">
                                <svg class="w-3.5 h-3.5 shrink-0 text-ink/35" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <span>Host: <strong class="text-ink/80"><?= htmlspecialchars($visit['host_name']) ?></strong></span>
                            </div>
                            <div class="flex items-center gap-2 text-ink/60">
                                <svg class="w-3.5 h-3.5 shrink-0 text-ink/35" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/></svg>
                                <span><?= htmlspecialchars($visit['purpose'] ?: 'No purpose stated') ?></span>
                            </div>
                            <?php if ($visit['check_in_time']): ?>
                                <div class="flex items-center gap-2 text-ink/60">
                                    <svg class="w-3.5 h-3.5 shrink-0 text-ink/35" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>In since <strong class="text-ink/80"><?= date('M j, Y · g:i A', strtotime($visit['check_in_time'])) ?></strong></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($visit['allowed_areas']): ?>
                                <div class="flex items-center gap-2 text-brand-700">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span class="font-semibold"><?= htmlspecialchars($visit['allowed_areas']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($canCheckIn): ?>
                            <div class="mt-3 pt-3 border-t border-ink/8 flex gap-2">
                                <form action="<?= url('/visitors/' . $visit['id'] . '/check-out') ?>" method="POST" class="flex-1">
                                    @csrf
                                    <button type="submit" class="w-full text-xs font-bold px-3 py-2 rounded-lg bg-ink text-sand hover:bg-ink/80 transition">Check Out</button>
                                </form>
                                <?php if ($canManage): ?>
                                    <form action="<?= url('/visitors/' . $visit['visitor_id'] . '/toggle-watchlist') ?>" method="POST">
                                        @csrf
                                        <input type="hidden" name="watchlist" value="<?= $visit['is_watchlisted'] ? '0' : '1' ?>">
                                        <button type="submit" class="text-xs font-bold px-3 py-2 rounded-lg border border-accent-200 text-accent-700 hover:bg-accent-50 transition" title="<?= $visit['is_watchlisted'] ? 'Remove from watchlist' : 'Add to watchlist' ?>">
                                            <?= $visit['is_watchlisted'] ? '✕ Watch' : '⚑ Watch' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- All visits table -->
    <div class="space-y-6" data-filter-root>
        <?php if (!empty($visits)): ?>
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wider text-ink/50">All visits</h2>
            </div>
            @include('partials.module_filters', ['fields' => [
                ['name' => 'q', 'label' => 'Search', 'placeholder' => 'Visitor, host, purpose, company…'],
                ['name' => 'status', 'label' => 'Status', 'allLabel' => 'All statuses', 'options' => [
                    'scheduled' => 'Scheduled',
                    'checked_in' => 'Checked in',
                    'checked_out' => 'Checked out',
                    'no_show' => 'No show',
                    'denied' => 'Denied',
                ]],
                ['name' => 'watch', 'label' => 'Watchlist', 'allLabel' => 'All visitors', 'options' => [
                    'yes' => 'On watchlist',
                    'no' => 'Not watchlisted',
                ]],
            ]])
        <?php endif; ?>

        <div class="module-empty" data-filter-empty hidden>No visits match these filters.</div>

        <?php if (empty($visits)): ?>
            <div class="module-empty">No visits logged.</div>
        <?php else: ?>
            <div class="module-table-wrap">
                <div class="overflow-x-auto">
                    <table class="module-table">
                        <thead>
                            <tr>
                                <th>Visitor</th>
                                <th>Host</th>
                                <th>Purpose</th>
                                <th>Scheduled</th>
                                <th>Check-in / out</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visits as $visit): ?>
                                <tr class="<?= $visit['is_watchlisted'] ? 'is-watch' : '' ?>"
                                    data-filter-item
                                    data-filter-status="<?= htmlspecialchars($visit['status']) ?>"
                                    data-filter-watch="<?= $visit['is_watchlisted'] ? 'yes' : 'no' ?>"
                                    data-filter-search="<?= htmlspecialchars(strtolower($visit['visitor_name'] . ' ' . $visit['host_name'] . ' ' . ($visit['visitor_company'] ?? '') . ' ' . ($visit['purpose'] ?? '') . ' ' . ($visit['allowed_areas'] ?? ''))) ?>">
                                    <td>
                                        <div class="font-semibold flex flex-wrap items-center gap-1.5">
                                            <?= htmlspecialchars($visit['visitor_name']) ?>
                                            <?php if ($visit['is_watchlisted']): ?>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-accent-100 text-accent-700 uppercase tracking-wide border border-accent-200">Watch</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-[11px] text-ink/45 mt-0.5"><?= htmlspecialchars($visit['visitor_company'] ?: 'Individual') ?></div>
                                    </td>
                                    <td class="font-medium text-ink/75"><?= htmlspecialchars($visit['host_name']) ?></td>
                                    <td class="text-ink/70 max-w-[14rem] truncate"><?= htmlspecialchars($visit['purpose'] ?: '—') ?></td>
                                    <td class="text-ink/60 whitespace-nowrap text-xs">
                                        <?= date('M d, Y', strtotime($visit['scheduled_at'])) ?>
                                        <div class="text-[10px] text-ink/40"><?= date('g:i A', strtotime($visit['scheduled_at'])) ?></div>
                                    </td>
                                    <td class="whitespace-nowrap text-xs">
                                        <?php if ($visit['check_in_time']): ?>
                                            <div class="text-brand-700 font-medium">In: <?= date('M j, Y', strtotime($visit['check_in_time'])) ?></div>
                                            <div class="text-brand-700/80 text-[10px]"><?= date('g:i A', strtotime($visit['check_in_time'])) ?></div>
                                        <?php else: ?>
                                            <div class="text-ink/30">—</div>
                                        <?php endif; ?>
                                        <?php if ($visit['check_out_time']): ?>
                                            <div class="text-ink/50 mt-1">Out: <?= date('M j, Y', strtotime($visit['check_out_time'])) ?></div>
                                            <div class="text-ink/40 text-[10px]"><?= date('g:i A', strtotime($visit['check_out_time'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="inline-block px-2.5 py-1 text-[11px] border rounded-md capitalize <?= $statusStyles[$visit['status']] ?? 'bg-gray-50 border-gray-200' ?>">
                                            <?= str_replace('_', ' ', $visit['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <?php if ($canCheckIn): ?>
                                            <?php if ($visit['status'] === 'scheduled'): ?>
                                                <form action="<?= url('/visitors/' . $visit['id'] . '/check-in') ?>" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-bold text-brand-700 hover:text-brand-900 mr-2">Check In</button>
                                                </form>
                                            <?php elseif ($visit['status'] === 'checked_in'): ?>
                                                <form action="<?= url('/visitors/' . $visit['id'] . '/check-out') ?>" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-bold text-ink hover:text-ink/80 mr-2">Check Out</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($canManage): ?>
                                            <form action="<?= url('/visitors/' . $visit['visitor_id'] . '/toggle-watchlist') ?>" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="watchlist" value="<?= $visit['is_watchlisted'] ? '0' : '1' ?>">
                                                <button type="submit" class="text-xs font-bold text-accent-700 hover:text-accent-600">
                                                    <?= $visit['is_watchlisted'] ? 'Unwatch' : 'Watch' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Pre-registration Form (Outside of transform container) -->
<?php if ($canRegister): ?>
<div id="registerModal" class="modal-overlay hidden">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-8 py-5 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-xl font-bold text-ink">Pre-register Visitor</h3>
            <button onclick="closeModal('registerModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <form action="<?= url('/visitors') ?>" method="POST" class="p-8 space-y-5 max-h-[80vh] overflow-y-auto">
    @csrf
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-ink/60">Visitor Profile</label>
                <select name="visitor_id" id="visitor_select" onchange="toggleVisitorFields()" class="w-full border border-ink/15 rounded-xl p-3 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                    <option value="">-- Create New Visitor Profile --</option>
                    <?php foreach ($visitors as $v): ?>
                        <option value="<?= $v['id'] ?>" data-company="<?= htmlspecialchars($v['company']) ?>" data-watchlist="<?= $v['is_watchlisted'] ?>">
                            <?= htmlspecialchars($v['full_name']) ?> <?= $v['company'] ? '('.htmlspecialchars($v['company']).')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="new_visitor_fields" class="space-y-4 pt-2">
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-ink/75">Full Name</label>
                    <input type="text" name="full_name" class="w-full border border-ink/15 rounded-xl p-3 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-ink/75">Company / Organization</label>
                    <input type="text" name="company" class="w-full border border-ink/15 rounded-xl p-3 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-ink/75">Email Address</label>
                        <input type="email" name="email" class="w-full border border-ink/15 rounded-xl p-3 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-ink/75">Phone Number</label>
                        <input type="text" name="phone" class="w-full border border-ink/15 rounded-xl p-3 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-ink/75">Govt ID Number (e.g. Passport, License)</label>
                    <input type="text" name="id_number" class="w-full border border-ink/15 rounded-xl p-3 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                </div>
                <div class="flex items-center gap-2.5 pt-1">
                    <input type="checkbox" name="is_watchlisted" id="is_watchlisted" value="1" class="rounded border-ink/10 text-brand-600 focus:ring-brand-500">
                    <label for="is_watchlisted" class="text-xs text-accent-700 font-bold">Flag this profile on Security Watchlist</label>
                </div>
            </div>

            <hr class="border-ink/10">

            <div class="space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-ink/75">Host Employee</label>
                    <select name="host_employee_id" required class="w-full border border-ink/15 rounded-xl p-3 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                        <option value="">-- Select Corporate Host --</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['position'] ?: 'Host') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-ink/75">Purpose of Visit</label>
                    <input type="text" name="purpose" placeholder="e.g. Service Audit, Vendor negotiation" required class="w-full border border-ink/15 rounded-xl p-3 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-ink/75">Scheduled Arrival Date &amp; Time</label>
                    <input type="datetime-local" name="scheduled_at" required class="w-full border border-ink/15 rounded-xl p-3 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-ink/65 mb-1">Permitted Office Areas</label>
                    <div class="grid grid-cols-2 gap-3 p-4 bg-ink/[0.03] rounded-xl border border-ink/10">
                        <?php foreach ($areas as $area): ?>
                            <label class="flex items-center gap-2.5 text-xs text-ink font-medium cursor-pointer">
                                <input type="checkbox" name="areas[]" value="<?= $area['id'] ?>" class="rounded border-ink/20 text-brand-600 focus:ring-brand-500">
                                <?= htmlspecialchars($area['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-ink/75">Special Instructions / Reception Notes</label>
                    <textarea name="notes" rows="3" class="w-full border border-ink/15 rounded-xl p-3 text-sm bg-sand/10 focus:border-brand-500 outline-none"></textarea>
                </div>
            </div>

            <div class="pt-6 border-t border-ink/10 flex justify-end gap-3.5">
                <button type="button" onclick="closeModal('registerModal')" class="px-5 py-3 rounded-xl border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-3 rounded-xl bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm transition shadow-sm">
                    Submit Registration
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function toggleVisitorFields() {
    const select = document.getElementById('visitor_select');
    const fieldsDiv = document.getElementById('new_visitor_fields');
    const inputs = fieldsDiv.querySelectorAll('input');
    
    if (select.value !== "") {
        fieldsDiv.style.display = 'none';
        inputs.forEach(input => {
            if (input.type !== 'checkbox') {
                input.required = false;
            }
        });
    } else {
        fieldsDiv.style.display = 'block';
        inputs.forEach(input => {
            if (input.name === 'full_name') {
                input.required = true;
            }
        });
    }
}
document.addEventListener('DOMContentLoaded', toggleVisitorFields);
</script>

@endsection
