@extends('layouts.app')

@section('content')
<?php

/** @var array $stats */
/** @var array $charts */
/** @var array $recentReservations */
/** @var array $recentVisits */
/** @var array $recentContracts */
/** @var array $attentionItems */

$user = auth()->user();

$statusColors = [
    'pending'           => 'bg-accent-50 text-accent-700 border-accent-200',
    'approved'          => 'bg-brand-50 text-brand-700 border-brand-300',
    'rejected'          => 'bg-accent-50 text-accent-700 border-accent-200',
    'cancelled'         => 'bg-gray-100 text-gray-500 border-gray-200',
    'scheduled'         => 'bg-brand-50 text-brand-700 border-brand-200',
    'checked_in'        => 'bg-brand-50 text-brand-800 border-brand-300',
    'checked_out'       => 'bg-gray-100 text-gray-600 border-gray-200',
    'draft'             => 'bg-gray-100 text-gray-600 border-gray-200',
    'in_review'         => 'bg-accent-50 text-accent-700 border-accent-200',
    'pending_signature' => 'bg-brand-50 text-brand-700 border-brand-200',
    'pending_approval'  => 'bg-accent-50 text-accent-700 border-accent-200',
    'active'            => 'bg-brand-50 text-brand-800 border-brand-300',
    'expired'           => 'bg-ink/5 text-ink/50 border-ink/10',
    'terminated'        => 'bg-accent-50 text-accent-700 border-accent-200',
];

$toneMap = [
    'amber'  => 'bg-accent-50 text-accent-700 border-accent-200',
    'blue'   => 'bg-brand-50 text-brand-700 border-brand-200',
    'yellow' => 'bg-accent-50 text-accent-700 border-accent-200',
    'red'    => 'bg-accent-50 text-accent-700 border-accent-200',
    'purple' => 'bg-brand-50 text-brand-700 border-brand-200',
];

$statCards = [
    ['reservation.view', '/reservations', 'Facilities', $stats['pending_reservations'], 'Pending requests', 'navy', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ['visitor.view', '/visitors', 'Visitors', $stats['visitors_on_site'], 'On site now', 'navy', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['document.view', '/documents', 'Documents', $stats['active_documents'], 'Active records', 'peach', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ['retention.view', '/retention', 'Retention', $stats['pending_disposals'], 'Pending reviews', 'terracotta', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['legal.view', '/legal', 'Legal', $stats['active_legal_cases'], 'Open cases', 'brown', 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3'],
    ['contract.view', '/contracts', 'Contracts', $stats['active_contracts'], 'Active registry', 'slate', 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
];

$attentionCount = count($attentionItems);
$contractsInPipeline = count(array_filter($recentContracts, static fn($c) => in_array($c['status'] ?? '', ['draft', 'in_review', 'pending_signature'], true)));

?>
<div class="animate-fade-in-up space-y-8">
    <?php
    render_page_header([
        'title'    => 'Welcome back, ' . explode(' ', $user->full_name)[0],
        'subtitle' => 'Signed in as ' . ($user->role->name ?? $user->role_name ?? '') . ' — ANI facilities, plant, and admin snapshot.',
        'badge'    => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-md bg-brand-50 text-brand-700 text-[11px] font-semibold capitalize border border-brand-200">'
            . htmlspecialchars($user->role->name ?? $user->role_name ?? '') . '</span>',
    ]);
    ?>

    <div class="dash-overview">
        <div class="stat-kpi-grid stat-kpi-grid--dashboard">
            <?php foreach ($statCards as [$perm, $href, $label, $value, $sub, $tone, $icon]): ?>
                <?php if (!can_perm($perm)) continue; ?>
                @include('partials.stat_kpi_card', [
                    'label' => $label,
                    'value' => (int) $value,
                    'hint'  => $sub,
                    'tone'  => $tone,
                    'icon'  => $icon,
                    'href'  => url($href),
                ])
            <?php endforeach; ?>
        </div>
        @include('partials.dashboard_quick_access', ['quickActions' => $quickActions ?? []])
    </div>

    <div class="module-info-grid lg:!grid-cols-3">
        <div class="module-insight-panel">
            <h3 class="module-insight-title">Operational snapshot</h3>
            <p class="module-insight-sub">Live counts across your accessible modules</p>
            <?php if (can_perm('reservation.view')): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Pending reservations</span>
                    <strong class="tabular-nums text-ink"><?= (int) $stats['pending_reservations'] ?></strong>
                </div>
            <?php endif; ?>
            <?php if (can_perm('visitor.view')): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Visitors on site</span>
                    <strong class="tabular-nums text-ink"><?= (int) $stats['visitors_on_site'] ?></strong>
                </div>
            <?php endif; ?>
            <?php if (can_perm('document.view')): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Active documents</span>
                    <strong class="tabular-nums text-ink"><?= (int) $stats['active_documents'] ?></strong>
                </div>
            <?php endif; ?>
            <?php if (can_perm('retention.view')): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Retention reviews due</span>
                    <strong class="tabular-nums text-ink"><?= (int) $stats['pending_disposals'] ?></strong>
                </div>
            <?php endif; ?>
            <?php if (can_perm('legal.view')): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Open legal cases</span>
                    <strong class="tabular-nums text-ink"><?= (int) $stats['active_legal_cases'] ?></strong>
                </div>
            <?php endif; ?>
            <?php if (can_perm('contract.view')): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Active contracts</span>
                    <strong class="tabular-nums text-ink"><?= (int) $stats['active_contracts'] ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <div class="module-insight-panel">
            <h3 class="module-insight-title">Executive Trends</h3>
            <p class="module-insight-sub">Completion & success rates</p>
            <?php if (can_perm('reservation.view')): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Reservation Approval Rate</span>
                    <strong class="tabular-nums text-ink"><?= $trends['reservation_rate'] ?? 0 ?>%</strong>
                </div>
            <?php endif; ?>
            <?php if (can_perm('visitor.view')): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Visitor Check-in Rate</span>
                    <strong class="tabular-nums text-ink"><?= $trends['visitor_rate'] ?? 0 ?>%</strong>
                </div>
            <?php endif; ?>
            <?php if (can_perm('contract.view')): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Contract Execution Rate</span>
                    <strong class="tabular-nums text-ink"><?= $trends['contract_rate'] ?? 0 ?>%</strong>
                </div>
            <?php endif; ?>
            <?php if (can_perm('legal.view')): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Legal Case Resolution Rate</span>
                    <strong class="tabular-nums text-ink"><?= $trends['legal_rate'] ?? 0 ?>%</strong>
                </div>
            <?php endif; ?>
            <?php if (can_perm('document.view')): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Document Active Rate</span>
                    <strong class="tabular-nums text-ink"><?= $trends['document_rate'] ?? 0 ?>%</strong>
                </div>
            <?php endif; ?>
            <?php if (can_perm('retention.view')): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Retention Compliance Rate</span>
                    <strong class="tabular-nums text-ink"><?= $trends['retention_rate'] ?? 0 ?>%</strong>
                </div>
            <?php endif; ?>
        </div>

        <div class="module-insight-panel">
            <h3 class="module-insight-title">Needs attention</h3>
            <p class="module-insight-sub"><?= $attentionCount ?> item<?= $attentionCount === 1 ? '' : 's' ?> waiting on action</p>
            <?php if (empty($attentionItems)): ?>
                <p class="text-sm text-ink/45 py-1">You're all caught up — nothing urgent right now.</p>
            <?php else: ?>
                <ul class="module-insight-list">
                    <?php foreach (array_slice($attentionItems, 0, 4) as $item): ?>
                        <li>
                            <span class="module-insight-dot"></span>
                            <span class="min-w-0">
                                <strong class="text-ink"><?= htmlspecialchars($item['module']) ?></strong>
                                — <?= htmlspecialchars($item['title']) ?>
                                <span class="block text-xs text-ink/45 truncate"><?= htmlspecialchars($item['meta']) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (can_perm('contract.view') && $contractsInPipeline > 0): ?>
                <p class="text-xs text-ink/50 mt-3 pt-3 border-t border-ink/5"><?= $contractsInPipeline ?> contract<?= $contractsInPipeline === 1 ? '' : 's' ?> in draft or approval pipeline</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Charts -->
    <div>
        <h2 class="section-title mb-1">Analytics overview</h2>
        <p class="type-meta mb-5">Cross-module activity and workload distribution</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 chart-card is-reveal">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="section-title">Activity (7 days)</h2>
                    <p class="type-meta">Records created across all accessible modules</p>
                </div>
            </div>
            <div class="h-72 relative">
                <canvas id="activityChart"></canvas>
            </div>
        </div>
        <div class="chart-card is-reveal">
            <div class="mb-3">
                <h2 class="section-title">Workload mix</h2>
                <p class="type-meta">Open items by module</p>
            </div>
            <div class="chart-donut-layout">
                <div class="chart-donut-canvas">
                    <canvas id="workloadChart"></canvas>
                </div>
                <div class="chart-donut-legend" id="workloadLegend"></div>
            </div>
        </div>
    </div>

    <div>
        <h2 class="section-title mb-1">Module analytics</h2>
        <p class="type-meta mb-5">Status breakdowns and distributions from your operational data</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <?php if (can_perm('reservation.view')): ?>
        <div class="chart-card is-reveal">
            <div class="mb-3">
                <h2 class="section-title">Reservation status</h2>
                <p class="type-meta">Distribution of your visible bookings</p>
            </div>
            <div class="chart-donut-layout">
                <div class="chart-donut-canvas">
                    <canvas id="reservationChart"></canvas>
                </div>
                <div class="chart-donut-legend" id="reservationLegend"></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (can_perm('visitor.view')): ?>
        <div class="chart-card is-reveal">
            <div class="mb-3">
                <h2 class="section-title">Visitor status</h2>
                <p class="type-meta">Scheduled through completed visits</p>
            </div>
            <div class="chart-donut-layout">
                <div class="chart-donut-canvas">
                    <canvas id="visitChart"></canvas>
                </div>
                <div class="chart-donut-legend" id="visitLegend"></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (can_perm('document.view')): ?>
        <div class="chart-card is-reveal">
            <div class="mb-3">
                <h2 class="section-title">Document status</h2>
                <p class="type-meta">Draft, active, and archived records</p>
            </div>
            <div class="chart-donut-layout">
                <div class="chart-donut-canvas">
                    <canvas id="documentChart"></canvas>
                </div>
                <div class="chart-donut-legend" id="documentLegend"></div>
            </div>
        </div>
        <div class="chart-card is-reveal">
            <div class="mb-3">
                <h2 class="section-title">Document confidentiality</h2>
                <p class="type-meta">Access classification across records</p>
            </div>
            <div class="h-56 relative">
                <canvas id="documentConfChart"></canvas>
            </div>
        </div>
        <div class="chart-card is-reveal">
            <div class="mb-3">
                <h2 class="section-title">Documents by category</h2>
                <p class="type-meta">Top categories in the registry</p>
            </div>
            <div class="h-56 relative">
                <canvas id="documentCategoryChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <?php if (can_perm('retention.view')): ?>
        <div class="chart-card is-reveal">
            <div class="mb-3">
                <h2 class="section-title">Retention assignments</h2>
                <p class="type-meta">Lifecycle status of assigned records</p>
            </div>
            <div class="chart-donut-layout">
                <div class="chart-donut-canvas">
                    <canvas id="retentionChart"></canvas>
                </div>
                <div class="chart-donut-legend" id="retentionLegend"></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (can_perm('legal.view')): ?>
        <div class="chart-card is-reveal">
            <div class="mb-3">
                <h2 class="section-title">Legal case status</h2>
                <p class="type-meta">Open through resolved matters</p>
            </div>
            <div class="chart-donut-layout">
                <div class="chart-donut-canvas">
                    <canvas id="legalChart"></canvas>
                </div>
                <div class="chart-donut-legend" id="legalLegend"></div>
            </div>
        </div>
        <div class="chart-card is-reveal">
            <div class="mb-3">
                <h2 class="section-title">Legal risk levels</h2>
                <p class="type-meta">Exposure across active caseload</p>
            </div>
            <div class="h-56 relative">
                <canvas id="legalRiskChart"></canvas>
            </div>
        </div>
        <div class="chart-card is-reveal">
            <div class="mb-3">
                <h2 class="section-title">Legal case types</h2>
                <p class="type-meta">Matters grouped by practice area</p>
            </div>
            <div class="h-56 relative">
                <canvas id="legalTypeChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <?php if (can_perm('contract.view')): ?>
        <div class="chart-card is-reveal">
            <div class="mb-3">
                <h2 class="section-title">Contract pipeline</h2>
                <p class="type-meta">Draft through active agreements</p>
            </div>
            <div class="chart-donut-layout">
                <div class="chart-donut-canvas">
                    <canvas id="contractChart"></canvas>
                </div>
                <div class="chart-donut-legend" id="contractLegend"></div>
            </div>
        </div>
        <div class="chart-card is-reveal">
            <div class="mb-3">
                <h2 class="section-title">Contracts by type</h2>
                <p class="type-meta">Agreement categories in the registry</p>
            </div>
            <div class="h-56 relative">
                <canvas id="contractTypeChart"></canvas>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (can_perm('audit.view')): ?>
    <div class="chart-card is-reveal">
        <div class="mb-3">
            <h2 class="section-title">Audit activity by module</h2>
            <p class="type-meta">Recent system actions grouped by module</p>
        </div>
        <div class="h-64 relative">
            <canvas id="auditModuleChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tables -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 items-stretch">
        <div class="dash-panel is-reveal">
            <div class="dash-panel-head">
                <div>
                    <h2 class="section-title">Recent reservations</h2>
                    <p class="type-meta">Latest booking activity</p>
                </div>
                <?php if (can_perm('reservation.view')): ?>
                    <a href="<?= url('/reservations') ?>" class="text-xs font-semibold text-brand-700 hover:text-brand-900">View all</a>
                <?php endif; ?>
            </div>
            <?php if (!empty($recentReservations)): ?>
            <div class="dash-panel-filters">
                <span class="text-[11px] text-ink/40 font-semibold uppercase tracking-wider mr-1">Filter</span>
                <button type="button" class="dash-filter-btn is-active" data-table="reservations" data-status="all">All</button>
                <?php foreach (['pending', 'approved', 'rejected'] as $st): ?>
                    <button type="button" class="dash-filter-btn" data-table="reservations" data-status="<?= $st ?>"><?= ucfirst($st) ?></button>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="dash-panel-filters dash-panel-filters-spacer" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="dash-panel-body">
                <table class="w-full text-sm">
                    <thead class="bg-ink/[0.02] text-ink/45 text-[11px] font-bold uppercase tracking-wider sticky top-0 z-[1]">
                        <tr>
                            <th class="text-left px-5 py-3 bg-ink/[0.02]">Facility</th>
                            <th class="text-left px-5 py-3 bg-ink/[0.02]">When</th>
                            <th class="text-left px-5 py-3 bg-ink/[0.02]">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/5" id="reservationsTableBody">
                        <?php if (empty($recentReservations)): ?>
                            <tr><td colspan="3" class="px-5 py-10 text-center text-ink/40">No reservations yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentReservations as $i => $r): ?>
                                <tr class="hover:bg-gray-50/70 transition-colors dash-row" data-filter-status="<?= htmlspecialchars($r['status']) ?>" style="animation-delay: <?= min($i * 0.05, 0.35) ?>s">
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-ink"><?= htmlspecialchars($r['facility_name']) ?></p>
                                        <p class="text-[11px] text-ink/45 mt-0.5"><?= htmlspecialchars($r['purpose'] ?? '') ?></p>
                                    </td>
                                    <td class="px-5 py-3.5 text-ink/60 whitespace-nowrap text-xs">
                                        <?= date('M j, g:ia', strtotime($r['start_datetime'])) ?>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="px-2 py-0.5 rounded-md text-[11px] border font-semibold capitalize <?= $statusColors[$r['status']] ?? '' ?>">
                                            <?= htmlspecialchars($r['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="dash-filter-empty hidden"><td colspan="3" class="px-5 py-10 text-center text-ink/40">No reservations match this filter.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dash-panel is-reveal">
            <div class="dash-panel-head">
                <div>
                    <h2 class="section-title">Recent visits</h2>
                    <p class="type-meta">Visitor traffic snapshot</p>
                </div>
                <?php if (can_perm('visitor.view')): ?>
                    <a href="<?= url('/visitors') ?>" class="text-xs font-semibold text-brand-700 hover:text-brand-900">View all</a>
                <?php endif; ?>
            </div>
            <?php if (!empty($recentVisits)): ?>
            <div class="dash-panel-filters">
                <span class="text-[11px] text-ink/40 font-semibold uppercase tracking-wider mr-1">Filter</span>
                <button type="button" class="dash-filter-btn is-active" data-table="visits" data-status="all">All</button>
                <?php foreach (['scheduled', 'checked_in', 'checked_out'] as $st): ?>
                    <button type="button" class="dash-filter-btn" data-table="visits" data-status="<?= $st ?>"><?= ucfirst(str_replace('_', ' ', $st)) ?></button>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="dash-panel-filters dash-panel-filters-spacer" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="dash-panel-body">
                <table class="w-full text-sm">
                    <thead class="bg-ink/[0.02] text-ink/45 text-[11px] font-bold uppercase tracking-wider sticky top-0 z-[1]">
                        <tr>
                            <th class="text-left px-5 py-3 bg-ink/[0.02]">Visitor</th>
                            <th class="text-left px-5 py-3 bg-ink/[0.02]">Host</th>
                            <th class="text-left px-5 py-3 bg-ink/[0.02]">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/5" id="visitsTableBody">
                        <?php if (empty($recentVisits)): ?>
                            <tr><td colspan="3" class="px-5 py-10 text-center text-ink/40">No visits logged yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentVisits as $i => $v): ?>
                                <tr class="hover:bg-gray-50/70 transition-colors dash-row" data-filter-status="<?= htmlspecialchars($v['status']) ?>" style="animation-delay: <?= min($i * 0.05, 0.35) ?>s">
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-ink"><?= htmlspecialchars($v['visitor_name']) ?></p>
                                        <p class="text-[11px] text-ink/45 mt-0.5"><?= htmlspecialchars($v['purpose'] ?? '') ?></p>
                                    </td>
                                    <td class="px-5 py-3.5 text-ink/60 text-xs"><?= htmlspecialchars($v['host_name'] ?? '—') ?></td>
                                    <td class="px-5 py-3.5">
                                        <span class="px-2 py-0.5 rounded-md text-[11px] border font-semibold capitalize <?= $statusColors[$v['status']] ?? '' ?>">
                                            <?= htmlspecialchars(str_replace('_', ' ', $v['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="dash-filter-empty hidden"><td colspan="3" class="px-5 py-10 text-center text-ink/40">No visits match this filter.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Attention + contracts -->
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5 items-stretch">
        <div class="xl:col-span-3 dash-panel dash-panel-tall is-reveal">
            <div class="dash-panel-head">
                <div>
                    <h2 class="section-title">Needs attention</h2>
                    <p class="type-meta">Items waiting on action across modules</p>
                </div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-ink/35"><?= count($attentionItems) ?> items</span>
            </div>
            <div class="dash-panel-body">
                <?php if (empty($attentionItems)): ?>
                    <p class="px-5 py-12 text-center text-ink/40 text-sm">You're all caught up — nothing needs attention right now.</p>
                <?php else: ?>
                    <ul class="divide-y divide-ink/5">
                        <?php foreach ($attentionItems as $i => $item): ?>
                            <li class="dash-attn-item" style="animation-delay: <?= min($i * 0.05, 0.35) ?>s">
                                <a href="<?= url($item['href']) ?>" class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50/70 transition-colors">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-ink/40 w-20 shrink-0"><?= htmlspecialchars($item['module']) ?></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-ink truncate"><?= htmlspecialchars($item['title']) ?></span>
                                        <span class="block text-[11px] text-ink/45 truncate"><?= htmlspecialchars($item['meta']) ?></span>
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md text-[11px] border font-semibold shrink-0 <?= $toneMap[$item['tone']] ?? '' ?>">
                                        <?= htmlspecialchars($item['status']) ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="xl:col-span-2 dash-panel dash-panel-tall is-reveal">
            <div class="dash-panel-head">
                <div>
                    <h2 class="section-title">Contracts</h2>
                    <p class="type-meta">Latest drafts and live deals</p>
                </div>
                <?php if (can_perm('contract.view')): ?>
                    <a href="<?= url('/contracts') ?>" class="text-xs font-semibold text-brand-700 hover:text-brand-900">View all</a>
                <?php endif; ?>
            </div>
            <div class="dash-panel-body">
                <table class="w-full text-sm">
                    <thead class="bg-ink/[0.02] text-ink/45 text-[11px] font-bold uppercase tracking-wider sticky top-0 z-[1]">
                        <tr>
                            <th class="text-left px-5 py-3 bg-ink/[0.02]">Title</th>
                            <th class="text-left px-5 py-3 bg-ink/[0.02]">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/5">
                        <?php if (empty($recentContracts)): ?>
                            <tr><td colspan="2" class="px-5 py-10 text-center text-ink/40">No contracts yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentContracts as $i => $c): ?>
                                <tr class="hover:bg-gray-50/70 transition-colors dash-animate-row" style="animation-delay: <?= min($i * 0.05, 0.35) ?>s">
                                    <td class="px-5 py-3.5">
                                        <a href="<?= url('/contracts/' . $c['id']) ?>" data-record-modal class="font-semibold text-ink hover:text-brand-700 transition">
                                            <?= htmlspecialchars($c['title']) ?>
                                        </a>
                                        <p class="text-[11px] text-ink/45 mt-0.5"><?= htmlspecialchars($c['counterparty_name'] ?? '') ?></p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="px-2 py-0.5 rounded-md text-[11px] border font-semibold capitalize <?= $statusColors[$c['status']] ?? '' ?>">
                                            <?= htmlspecialchars(str_replace('_', ' ', $c['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.dash-panel {
    display: flex;
    flex-direction: column;
    height: 22rem;
    background: #fff;
    border: 1px solid rgba(78, 84, 92, 0.1);
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(20, 46, 76, 0.06);
}
.dash-panel-tall { height: 20rem; }
.dash-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(28, 43, 36, 0.1);
    flex-shrink: 0;
}
.dash-panel-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
    padding: 0.65rem 1.25rem;
    border-bottom: 1px solid rgba(28, 43, 36, 0.08);
    background: rgba(28, 43, 36, 0.015);
    flex-shrink: 0;
    min-height: 2.75rem;
    box-sizing: border-box;
}
.dash-panel-filters-spacer { visibility: hidden; pointer-events: none; }
.dash-panel-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
}
.dash-filter-btn {
    font-size: 11px;
    font-weight: 600;
    padding: 0.3rem 0.65rem;
    border-radius: 0.5rem;
    border: 1px solid rgba(28,43,36,0.1);
    color: rgba(28,43,36,0.55);
    background: #fff;
    transition: all 0.15s ease;
}
.dash-filter-btn:hover { border-color: rgba(20,46,76,0.35); color: #142E4C; }
.dash-filter-btn.is-active {
    background: #142E4C;
    border-color: #142E4C;
    color: #fff;
}
.dash-filter-empty.hidden { display: none; }

html.dark .dash-panel {
    background: #122033;
    border-color: rgba(123, 168, 204, 0.14);
    box-shadow: none;
}
html.dark .dash-panel-head,
html.dark .dash-panel-filters {
    border-bottom-color: rgba(123, 168, 204, 0.12);
}
html.dark .dash-panel-filters {
    background: rgba(123, 168, 204, 0.05);
}
html.dark .dash-filter-btn {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(123, 168, 204, 0.18);
    color: rgba(232, 238, 246, 0.68);
}
html.dark .dash-filter-btn:hover {
    border-color: rgba(123, 168, 204, 0.4);
    color: #5E7288;
    background: rgba(123, 168, 204, 0.1);
}
html.dark .dash-filter-btn.is-active {
    background: #334963;
    border-color: #334963;
    color: #fff;
}
html.dark .dash-panel thead,
html.dark .dash-panel thead th {
    background-color: rgba(123, 168, 204, 0.08) !important;
    color: rgba(232, 238, 246, 0.58);
}
html.dark .dash-panel .hover\:bg-gray-50\/70:hover,
html.dark .dash-panel a.hover\:bg-gray-50\/70:hover {
    background-color: rgba(123, 168, 204, 0.08) !important;
}
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var charts = <?= json_encode($charts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var palette = {
        navy: '#142E4C',
        navyMid: '#334963',
        slate: '#5E7288',
        gray: '#BBC3C8',
        peach: '#CCAD9F',
        brown: '#A06A56',
        muted: '#9E9EA0'
    };
    var muted = 'rgba(78,84,92,0.45)';

    Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui, sans-serif';
    Chart.defaults.color = muted;
    Chart.defaults.animation = {
        duration: 1100,
        easing: 'easeOutQuart'
    };

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var centerTextPlugin = {
        id: 'centerText',
        afterDraw: function (chart) {
            var meta = chart.getDatasetMeta(0);
            if (!meta || !meta.data.length) return;
            var total = chart.config._centerTotal;
            if (total === undefined) {
                total = chart.data.datasets[0].data.reduce(function (a, b) { return a + b; }, 0);
            }
            var ctx = chart.ctx;
            var centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
            var centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#1E293B';
            ctx.font = '700 1.875rem Inter, sans-serif';
            ctx.fillText(String(total), centerX, centerY - 8);
            ctx.font = '500 0.75rem Inter, sans-serif';
            ctx.fillStyle = '#64748B';
            ctx.fillText('Total', centerX, centerY + 12);
            ctx.restore();
        }
    };

    function buildLegend(container, labels, values, colors) {
        if (!container) return;
        var total = values.reduce(function (a, b) { return a + b; }, 0);
        if (total === 0) {
            container.innerHTML = '<p class="text-sm text-ink/40">No data yet</p>';
            return;
        }
        container.innerHTML = labels.map(function (label, i) {
            var count = values[i];
            var pct = total ? Math.round((count / total) * 100) : 0;
            return '<div class="chart-legend-row" style="animation-delay:' + (0.15 + i * 0.06) + 's">' +
                '<span class="chart-legend-swatch" style="background:' + colors[i] + '"></span>' +
                '<span class="chart-legend-label">' + label + '</span>' +
                '<span class="chart-legend-pct">' + pct + '%</span>' +
                '<span class="chart-legend-count">' + count + '</span>' +
                '</div>';
        }).join('');
    }

    function doughnut(el, legendEl, dataMap, colors, emptyMsg) {
        if (!el) return;
        var keys = Object.keys(dataMap);
        var labels = keys.map(function (k) { return k.replace(/_/g, ' '); });
        var values = keys.map(function (k) { return dataMap[k]; });
        var wrap = el.closest('.chart-donut-canvas') || el.parentElement;
        if (values.every(function (v) { return v === 0; })) {
            if (wrap && wrap.parentElement) {
                wrap.parentElement.innerHTML = '<p class="text-sm text-ink/40 py-8 text-center">' + (emptyMsg || 'No data yet') + '</p>';
            }
            return;
        }
        buildLegend(legendEl, labels, values, colors);
        new Chart(el, {
            type: 'doughnut',
            plugins: [centerTextPlugin],
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '72%',
                animation: reduceMotion ? false : {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1200,
                    easing: 'easeOutQuart',
                    delay: function (ctx) {
                        return ctx.type === 'data' ? ctx.dataIndex * 80 : 0;
                    }
                },
                plugins: { legend: { display: false }, tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                            var pct = total ? Math.round((ctx.raw / total) * 100) : 0;
                            return ' ' + ctx.label + ': ' + ctx.raw + ' (' + pct + '%)';
                        }
                    }
                }}
            }
        });
    }

    function paletteColors(count) {
        var base = [palette.navy, palette.navyMid, palette.slate, palette.peach, palette.brown, palette.gray, palette.muted, '#7B9CB8', '#D4A574', '#8FA68E'];
        var colors = [];
        for (var i = 0; i < count; i++) {
            colors.push(base[i % base.length]);
        }
        return colors;
    }

    function barChart(el, dataMap, horizontal) {
        if (!el) return;
        var keys = Object.keys(dataMap || {});
        var labels = keys.map(function (k) { return k.replace(/_/g, ' '); });
        var values = keys.map(function (k) { return dataMap[k]; });
        var wrap = el.parentElement;
        if (!keys.length || values.every(function (v) { return v === 0; })) {
            if (wrap) {
                wrap.innerHTML = '<p class="text-sm text-ink/40 py-8 text-center">No data yet</p>';
            }
            return;
        }
        var colors = paletteColors(values.length);
        new Chart(el, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: horizontal ? 28 : 42
                }]
            },
            options: {
                indexAxis: horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                animation: reduceMotion ? false : undefined,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.label + ': ' + ctx.raw;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: muted },
                        grid: { color: 'rgba(20,46,76,0.06)' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: muted },
                        grid: { color: 'rgba(20,46,76,0.06)' }
                    }
                }
            }
        });
    }

    var chartAccess = {
        reservations: <?= can_perm('reservation.view') ? 'true' : 'false' ?>,
        visits: <?= can_perm('visitor.view') ? 'true' : 'false' ?>,
        contracts: <?= can_perm('contract.view') ? 'true' : 'false' ?>,
        documents: <?= can_perm('document.view') ? 'true' : 'false' ?>,
        legal: <?= can_perm('legal.view') ? 'true' : 'false' ?>,
        audit: <?= can_perm('audit.view') ? 'true' : 'false' ?>
    };

    function lineDataset(label, data, color, fillAlpha) {
        return {
            label: label,
            data: data,
            borderColor: color,
            backgroundColor: fillAlpha,
            fill: false,
            tension: 0.35,
            pointRadius: 3,
            pointBackgroundColor: color,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointHoverRadius: 5
        };
    }

    var activityEl = document.getElementById('activityChart');
    if (activityEl) {
        var activityDatasets = [];
        if (chartAccess.reservations) {
            activityDatasets.push(lineDataset('Reservations', charts.activity.reservations, palette.navy, 'rgba(20,46,76,0.08)'));
        }
        if (chartAccess.visits) {
            activityDatasets.push(lineDataset('Visits', charts.activity.visits, palette.peach, 'rgba(204,173,159,0.12)'));
        }
        if (chartAccess.contracts) {
            activityDatasets.push(lineDataset('Contracts', charts.activity.contracts, palette.brown, 'rgba(160,106,86,0.1)'));
        }
        if (chartAccess.documents) {
            activityDatasets.push(lineDataset('Documents', charts.activity.documents, palette.slate, 'rgba(94,114,136,0.1)'));
        }
        if (chartAccess.legal) {
            activityDatasets.push(lineDataset('Legal', charts.activity.legal, '#8B5E4B', 'rgba(139,94,75,0.1)'));
        }
        if (chartAccess.audit) {
            activityDatasets.push(lineDataset('Audit', charts.activity.audit, palette.muted, 'rgba(158,158,160,0.1)'));
        }

        new Chart(activityEl, {
            type: 'line',
            data: {
                labels: charts.activity.labels,
                datasets: activityDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                animation: reduceMotion ? false : {
                    duration: 1300,
                    easing: 'easeOutQuart',
                    delay: function (ctx) {
                        return ctx.type === 'data' ? ctx.dataIndex * 40 + (ctx.datasetIndex || 0) * 90 : 0;
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'circle', padding: 14, color: muted }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: muted } },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: muted },
                        grid: { color: 'rgba(20,46,76,0.06)' }
                    }
                }
            }
        });
    }

    var workloadData = {
        Reservations: <?= (int) $stats['pending_reservations'] ?>,
        Visitors: <?= (int) $stats['visitors_on_site'] ?>,
        Documents: <?= (int) $stats['active_documents'] ?>,
        Retention: <?= (int) $stats['pending_disposals'] ?>,
        Legal: <?= (int) $stats['active_legal_cases'] ?>,
        Contracts: <?= (int) $stats['active_contracts'] ?>
    };
    doughnut(
        document.getElementById('workloadChart'),
        document.getElementById('workloadLegend'),
        workloadData,
        [palette.navy, palette.navyMid, palette.slate, palette.peach, palette.brown, palette.gray]
    );

    doughnut(
        document.getElementById('reservationChart'),
        document.getElementById('reservationLegend'),
        charts.reservations,
        [palette.peach, palette.navy, palette.brown, palette.muted]
    );

    doughnut(
        document.getElementById('visitChart'),
        document.getElementById('visitLegend'),
        charts.visits,
        [palette.peach, palette.navy, palette.slate, palette.brown, palette.muted]
    );

    doughnut(
        document.getElementById('documentChart'),
        document.getElementById('documentLegend'),
        charts.documents,
        [palette.gray, palette.navy, palette.slate]
    );

    barChart(document.getElementById('documentConfChart'), charts.documents_confidentiality, false);
    barChart(document.getElementById('documentCategoryChart'), charts.document_categories, true);

    doughnut(
        document.getElementById('retentionChart'),
        document.getElementById('retentionLegend'),
        charts.retention,
        [palette.navy, palette.peach, palette.brown, palette.muted]
    );

    doughnut(
        document.getElementById('legalChart'),
        document.getElementById('legalLegend'),
        charts.legal,
        paletteColors(Object.keys(charts.legal || {}).length)
    );

    barChart(document.getElementById('legalRiskChart'), charts.legal_risk, false);
    barChart(document.getElementById('legalTypeChart'), charts.legal_types, true);

    doughnut(
        document.getElementById('contractChart'),
        document.getElementById('contractLegend'),
        charts.contracts,
        paletteColors(Object.keys(charts.contracts || {}).length)
    );

    barChart(document.getElementById('contractTypeChart'), charts.contract_types, true);
    barChart(document.getElementById('auditModuleChart'), charts.audit_modules, true);

    document.querySelectorAll('.dash-filter-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var table = btn.getAttribute('data-table');
            var status = btn.getAttribute('data-status');
            var group = btn.parentElement;
            group.querySelectorAll('.dash-filter-btn').forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            var body = document.getElementById(table === 'reservations' ? 'reservationsTableBody' : 'visitsTableBody');
            if (!body) return;
            var visible = 0;
            body.querySelectorAll('.dash-row').forEach(function (row) {
                var match = status === 'all' || row.getAttribute('data-filter-status') === status;
                row.style.display = match ? '' : 'none';
                if (match) {
                    if (!reduceMotion) {
                        row.style.animation = 'none';
                        void row.offsetWidth;
                        row.style.animation = '';
                        row.style.animationDelay = (visible * 0.04) + 's';
                    }
                    visible++;
                }
            });
            var empty = body.querySelector('.dash-filter-empty');
            if (empty) empty.classList.toggle('hidden', visible > 0);
        });
    });
})();
</script>

@endsection
