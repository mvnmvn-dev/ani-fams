@extends('layouts.app')

@section('content')
<?php
/** @var array $cases */
/** @var array $counsels */
/** @var bool $canManage */

$statusStyles = [
    'open'        => 'bg-brand-50 text-brand-700 border-brand-200',
    'in_progress' => 'bg-brand-50 text-brand-700 border-brand-200',
    'on_hold'     => 'bg-accent-50 text-accent-700 border-accent-200',
    'closed'      => 'bg-gray-100 text-gray-700 border-gray-200',
    'won'         => 'bg-brand-50 text-brand-700 border-brand-200',
    'lost'        => 'bg-accent-50 text-accent-700 border-accent-200',
    'settled'     => 'bg-brand-100 text-brand-700 border-brand-300',
];
$riskStyles = [
    'low'      => 'bg-brand-50 text-brand-700 border-brand-200',
    'medium'   => 'bg-brand-50 text-brand-700 border-brand-200',
    'high'     => 'bg-accent-50 text-accent-700 border-accent-200',
    'critical' => 'bg-accent-50 text-accent-700 border-accent-200',
];
$openCount = 0;
$highRisk = 0;
$onHold = 0;
$activeMatter = 0;
foreach ($cases as $c) {
    $st = $c['status'] ?? 'open';
    $risk = $c['risk_level'] ?? 'medium';
    if (in_array($st, ['open', 'in_progress'], true)) $activeMatter++;
    if ($st === 'on_hold') $onHold++;
    if ($st === 'open') $openCount++;
    if (in_array($risk, ['high', 'critical'], true) && !in_array($st, ['closed', 'won', 'lost', 'settled'], true)) $highRisk++;
}
$highRiskCases = array_values(array_filter($cases, static fn($c) => in_array($c['risk_level'] ?? '', ['high', 'critical'], true) && !in_array($c['status'] ?? '', ['closed', 'won', 'lost', 'settled'], true)));
$onHoldCases = array_values(array_filter($cases, static fn($c) => ($c['status'] ?? '') === 'on_hold'));
$typeCounts = [];
foreach ($cases as $c) {
    $type = $c['case_type'] ?? 'other';
    $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
}
arsort($typeCounts);
$highRiskCases = array_slice($highRiskCases, 0, 5);
$onHoldCases = array_slice($onHoldCases, 0, 5);
?>
<div class="animate-fade-in-up space-y-8">
    <?php
    render_page_header([
        'title'    => 'Legal Cases',
        'subtitle' => 'Track legal matters, filings, counsel assignments, and case status.',
        'actions'  => function () use ($canManage) {
            ?>
            <a href="<?= route('legal.export') ?>" download data-export-link class="btn-secondary mr-2 inline-flex items-center gap-1.5" style="text-decoration: none; display: inline-flex; align-items: center; padding: 0.5rem 1rem; border-radius: 0.75rem;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export CSV
            </a>
            <a href="<?= route('legal.export.pdf') ?>" download data-export-link class="btn-secondary mr-2 inline-flex items-center gap-1.5" style="text-decoration: none; display: inline-flex; align-items: center; padding: 0.5rem 1rem; border-radius: 0.75rem;">
                Export PDF
            </a>
            <?php
            if ($canManage) {
                ?>
                <button onclick="openModal('vendorModal')" class="btn-secondary mr-2">+ Vendor / Counsel</button>
                <button onclick="openModal('caseModal')" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    File Legal Case
                </button>
                <?php
            }
        },
    ]);
    ?>

    <?php $ai = $aiInsights ?? ['summary' => '', 'deadlines' => [], 'related_documents' => []]; ?>
    <section class="rounded-2xl border border-brand-200 bg-brand-50/30 p-5 shadow-sm">
        <div class="flex items-center gap-2"><span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-brand-700 text-white text-xs font-bold">AI</span><div><h2 class="section-title">ARNEL case brief</h2><p class="type-meta">Operational summary, deadline detection, and document linkage suggestions</p></div></div>
        <p class="mt-3 text-sm text-ink/70"><?= htmlspecialchars($ai['summary'] ?? '') ?></p>
        <?php if (!empty($ai['deadlines'])): ?><div class="mt-4 grid gap-2 md:grid-cols-3"><?php foreach (array_slice($ai['deadlines'], 0, 6) as $deadline): ?><div class="rounded-xl bg-white/75 p-3"><div class="flex justify-between gap-2"><strong class="truncate text-sm"><?= htmlspecialchars($deadline['case']) ?></strong><span class="text-[10px] font-bold uppercase <?= ($deadline['priority'] ?? '') === 'high' ? 'text-accent-700' : 'text-brand-700' ?>"><?= htmlspecialchars($deadline['priority']) ?></span></div><p class="mt-1 text-xs text-ink/55"><?= htmlspecialchars($deadline['type']) ?> · <?= htmlspecialchars($deadline['date']) ?></p></div><?php endforeach; ?></div><?php endif; ?>
    </section>

    <?php $budgetRollup = $budgetRollup ?? []; ?>
    <?php if (!empty($budgetRollup)): ?>
    <div class="module-insight-panel">
        <h3 class="module-insight-title">Budget vs actual</h3>
        <p class="module-insight-sub">Case budgets compared to logged expenses</p>
        <?php foreach (array_slice($budgetRollup, 0, 8) as $row): ?>
            <?php
                $budget = $row['budget_amount'] !== null ? (float) $row['budget_amount'] : null;
                $cost = (float) ($row['total_cost'] ?? 0);
                $variance = $row['variance'] ?? ($budget !== null ? $budget - $cost : null);
            ?>
            <div class="module-stat-row">
                <span class="text-ink/70"><?= htmlspecialchars($row['case_number'] ?? '') ?> · <?= htmlspecialchars($row['title'] ?? '') ?></span>
                <strong class="tabular-nums text-ink">
                    <?= $budget !== null ? number_format($budget, 0) : '—' ?> /
                    <?= number_format($cost, 0) ?>
                    <?php if ($variance !== null): ?>
                        <span class="<?= $variance < 0 ? 'text-accent-700' : 'text-brand-700' ?>">(<?= number_format($variance, 0) ?>)</span>
                    <?php endif; ?>
                </strong>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    @include('partials.stat_kpi_grid', ['cards' => [
        ['label' => 'Active matters', 'value' => $activeMatter, 'hint' => 'Open or in progress', 'tone' => 'navy', 'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3'],
        ['label' => 'High / critical risk', 'value' => $highRisk, 'hint' => 'Needs counsel attention', 'tone' => 'terracotta', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
        ['label' => 'On hold', 'value' => $onHold, 'hint' => 'Paused proceedings', 'tone' => 'peach', 'icon' => 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Total docket', 'value' => count($cases), 'hint' => 'All registered cases', 'tone' => 'slate', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ]])

    <div class="module-info-grid">
        @include('partials.module_feed_list', [
            'title' => 'High-risk matters',
            'subtitle' => 'Cases rated high or critical that remain open',
            'items' => array_map(static fn($c) => [
                'label' => $c['title'],
                'meta'  => ($c['case_number'] ?? '') . ' · ' . ($c['court_or_agency'] ?: 'Venue TBD'),
                'href'  => url('/legal/' . $c['id']),
                'modal' => true,
                'badge' => $c['risk_level'] ?? null,
            ], $highRiskCases),
            'empty' => 'No high-risk matters on the active docket.',
        ])
        @include('partials.module_feed_list', [
            'title' => 'On hold',
            'subtitle' => 'Paused proceedings awaiting direction',
            'items' => array_map(static fn($c) => [
                'label' => $c['title'],
                'meta'  => ($c['opposing_party'] ?: 'Opposing party TBD') . ' · ' . ucfirst(str_replace('_', ' ', $c['case_type'] ?? '')),
                'href'  => url('/legal/' . $c['id']),
                'modal' => true,
                'aside' => !empty($c['filed_date']) ? date('M j, Y', strtotime($c['filed_date'])) : '',
            ], $onHoldCases),
            'empty' => 'No cases are currently on hold.',
        ])
    </div>

    <?php if (!empty($typeCounts)): ?>
    <div class="module-insight-panel">
        <h3 class="module-insight-title">Docket by case type</h3>
        <p class="module-insight-sub">Breakdown of registered legal matters</p>
        <?php foreach ($typeCounts as $type => $count): ?>
            <div class="module-stat-row">
                <span class="text-ink/70 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $type)) ?></span>
                <strong class="tabular-nums text-ink"><?= $count ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($cases)): ?>
        <div class="module-empty">No legal cases found.</div>
    <?php else: ?>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 space-y-6" data-filter-root>
            @include('partials.module_filters', ['fields' => [
                ['name' => 'q', 'label' => 'Search', 'placeholder' => 'Title, number, venue, counsel…'],
                ['name' => 'status', 'label' => 'Status', 'allLabel' => 'All statuses', 'options' => [
                    'open' => 'Open',
                    'in_progress' => 'In progress',
                    'on_hold' => 'On hold',
                    'closed' => 'Closed',
                    'won' => 'Won',
                    'lost' => 'Lost',
                    'settled' => 'Settled',
                ]],
                ['name' => 'risk', 'label' => 'Risk', 'allLabel' => 'All risk levels', 'options' => [
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                    'critical' => 'Critical',
                ]],
                ['name' => 'type', 'label' => 'Type', 'allLabel' => 'All types', 'options' => [
                    'litigation' => 'Litigation',
                    'regulatory' => 'Regulatory',
                    'labor' => 'Labor / HR',
                    'ip' => 'Intellectual Property',
                    'corporate' => 'Corporate',
                    'other' => 'Other',
                ]],
            ]])
            <div class="module-empty" data-filter-empty hidden>No cases match these filters.</div>

            <div class="module-table-wrap">
                <div class="overflow-x-auto">
                    <table class="module-table">
                        <thead>
                            <tr>
                                <th>Case No.</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Venue / Forum</th>
                                <th>Opposing Party</th>
                                <th>Counsel</th>
                                <th>Filed</th>
                                <th>Risk</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cases as $c): ?>
                                <?php
                                $counsel = $c['counsel_name']
                                    ?: ($c['external_counsel_name'] ? $c['external_counsel_name'] . ' (External)' : '—');
                                $risk = $c['risk_level'] ?? 'medium';
                                $status = $c['status'] ?? 'open';
                                ?>
                                <tr data-filter-item
                                    data-filter-status="<?= htmlspecialchars($status) ?>"
                                    data-filter-risk="<?= htmlspecialchars($risk) ?>"
                                    data-filter-type="<?= htmlspecialchars($c['case_type']) ?>"
                                    data-filter-search="<?= htmlspecialchars(strtolower($c['title'] . ' ' . $c['case_number'] . ' ' . ($c['court_or_agency'] ?? '') . ' ' . ($c['opposing_party'] ?? '') . ' ' . $counsel)) ?>">
                                    <td class="font-mono text-xs text-ink/60 whitespace-nowrap"><?= htmlspecialchars($c['case_number']) ?></td>
                                    <td>
                                        <a href="<?= url('/legal/' . $c['id']) ?>" data-record-modal class="font-semibold text-ink hover:text-brand-700 transition">
                                            <?= htmlspecialchars($c['title']) ?>
                                        </a>
                                    </td>
                                    <td class="capitalize text-ink/70"><?= htmlspecialchars($c['case_type']) ?></td>
                                    <td class="text-ink/70"><?= htmlspecialchars($c['court_or_agency'] ?: '—') ?></td>
                                    <td class="text-ink/70"><?= htmlspecialchars($c['opposing_party'] ?: '—') ?></td>
                                    <td class="text-ink/70"><?= htmlspecialchars($counsel) ?></td>
                                    <td class="text-xs text-ink/60 whitespace-nowrap">
                                        <?= !empty($c['filed_date']) ? date('M d, Y', strtotime($c['filed_date'])) : '—' ?>
                                    </td>
                                    <td>
                                        <span class="inline-block px-2 py-0.5 text-[11px] border rounded capitalize <?= $riskStyles[$risk] ?? 'bg-gray-50 border-gray-200' ?>">
                                            <?= htmlspecialchars($risk) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="inline-block px-2 py-0.5 text-[11px] border rounded capitalize <?= $statusStyles[$status] ?? 'bg-gray-50 border-gray-200' ?>">
                                            <?= str_replace('_', ' ', $status) ?>
                                        </span>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <a href="<?= url('/legal/' . $c['id']) ?>" data-record-modal class="text-xs font-semibold text-brand-700 hover:text-brand-900">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <aside class="space-y-4">
            <div class="module-insight-panel">
                <h3 class="module-insight-title">Risk focus</h3>
                <p class="module-insight-sub">Prioritize counsel capacity</p>
                <ul class="module-insight-list">
                    <li><span class="module-insight-dot"></span><span><strong><?= $highRisk ?></strong> matters currently rated high or critical.</span></li>
                    <li><span class="module-insight-dot"></span><span><strong><?= $openCount ?></strong> newly open cases awaiting first action.</span></li>
                    <li><span class="module-insight-dot"></span><span>Update milestones after each hearing or filing.</span></li>
                </ul>
            </div>
            <div class="module-insight-panel">
                <h3 class="module-insight-title">Case management notes</h3>
                <p class="module-insight-sub">What to verify on each case</p>
                <ul class="module-insight-list">
                    <li><span class="module-insight-dot"></span><span>Confirm counsel assignment and opposing party.</span></li>
                    <li><span class="module-insight-dot"></span><span>Check upcoming milestones and cost exposure.</span></li>
                    <li><span class="module-insight-dot"></span><span>Link related contracts and supporting documents.</span></li>
                </ul>
            </div>
        </aside>
        </div>
    <?php endif; ?>
</div>

<?php if ($canManage): ?>
<div id="caseModal" class="modal-overlay hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-xl w-full border border-ink/10 overflow-hidden" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="text-lg font-semibold text-ink">File Legal Case</h3>
            <button onclick="closeModal('caseModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/legal') ?>" method="POST" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Case Title</label>
                <input type="text" name="title" required placeholder="e.g. Contract non-performance - Supplier X" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Case Type</label>
                    <select name="case_type" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                        <option value="litigation">Litigation</option>
                        <option value="regulatory">Regulatory</option>
                        <option value="labor">Labor / HR</option>
                        <option value="ip">Intellectual Property</option>
                        <option value="corporate">Corporate</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Initial Status</label>
                    <select name="status" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="on_hold">On Hold</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Risk Level</label>
                    <select name="risk_level" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Date Filed</label>
                    <input type="date" name="filed_date" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                </div>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Court, Tribunal, or Agency</label>
                <input type="text" name="court_or_agency" placeholder="e.g. Regional Trial Court Branch 41" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Opposing Party</label>
                <input type="text" name="opposing_party" placeholder="e.g. Alpha Solutions Corp" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Assigned Internal Counsel</label>
                <select name="assigned_counsel_id" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                    <option value="">— None —</option>
                    <?php foreach ($counsels as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['position'] ?: 'Counsel') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">External Counsel (Optional)</label>
                <input type="text" name="external_counsel_name" placeholder="e.g. SyCip Salazar Law Firm" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Budget Amount (Optional)</label>
                <input type="number" name="budget_amount" step="0.01" min="0" placeholder="e.g. 250000"
                       class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Brief Summary</label>
                <textarea name="description" rows="3" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none"></textarea>
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('caseModal')" class="px-4 py-2 rounded-md border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-md bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm">Register Case</button>
            </div>
        </form>
    </div>
</div>

<div id="vendorModal" class="modal-overlay hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full border border-ink/10 overflow-hidden" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="text-lg font-semibold text-ink">Add Vendor / Counsel Contact</h3>
            <button onclick="closeModal('vendorModal')" class="text-ink/40 hover:text-ink/60">✕</button>
        </div>
        <form action="<?= url('/legal/vendors') ?>" method="POST" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Name</label>
                <input type="text" name="name" required class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Type</label>
                    <select name="vendor_type" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10">
                        <option value="counsel">Counsel</option>
                        <option value="vendor">Vendor</option>
                        <option value="expert">Expert</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Firm</label>
                    <input type="text" name="firm" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Contact</label>
                    <input type="text" name="contact_name" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Email</label>
                    <input type="email" name="email" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10">
                </div>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Phone</label>
                <input type="text" name="phone" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10">
            </div>
            <div class="pt-3 flex justify-end gap-2">
                <button type="button" onclick="closeModal('vendorModal')" class="px-4 py-2 rounded-md border border-ink/10 text-sm">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-md bg-brand-700 text-white text-sm font-semibold">Save Contact</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

@if(!empty($openRecordId))
<div id="open-record-bootstrap" hidden data-url="<?= url('/legal/' . $openRecordId) ?>?modal=1"></div>
@endif

@endsection
