@extends('layouts.app')

@section('content')
<?php
/** @var array $contracts */
/** @var array $types */
/** @var array $users */
/** @var array $cases */
/** @var bool $canCreate */
/** @var bool $canManage */

$statusStyles = [
    'draft'             => 'bg-gray-100 text-gray-700 border-gray-200',
    'in_review'         => 'bg-accent-50 text-accent-700 border-accent-200',
    'pending_signature' => 'bg-accent-50 text-accent-700 border-accent-200',
    'active'            => 'bg-brand-50 text-brand-700 border-brand-200',
    'expired'           => 'bg-accent-50 text-accent-700 border-accent-200',
    'terminated'        => 'bg-accent-50 text-accent-700 border-accent-200',
    'renewed'           => 'bg-brand-50 text-brand-700 border-brand-200',
];
$typeOptions = [];
$statusCounts = ['draft' => 0, 'in_review' => 0, 'pending_signature' => 0, 'active' => 0, 'expired' => 0, 'terminated' => 0, 'renewed' => 0];
$expiringSoon = 0;
$now = time();
$in90 = strtotime('+90 days');
$totalValue = 0;
foreach ($contracts as $con) {
    $type = $con['type_name'] ?: 'General';
    $typeOptions[$type] = $type;
    $st = $con['status'] ?? 'draft';
    if (isset($statusCounts[$st])) $statusCounts[$st]++;
    if (!empty($con['end_date']) && in_array($st, ['active', 'renewed'], true)) {
        $end = strtotime($con['end_date']);
        if ($end && $end >= $now && $end <= $in90) $expiringSoon++;
    }
    if (!empty($con['value_amount'])) $totalValue += (float) $con['value_amount'];
}
ksort($typeOptions);
$needsDecision = $statusCounts['in_review'] + $statusCounts['pending_signature'];
$renewalSoon = [];
$awaitingDecision = [];
$typeCounts = [];
foreach ($contracts as $con) {
    $type = $con['type_name'] ?: 'General';
    $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
    if (in_array($con['status'] ?? '', ['in_review', 'pending_signature'], true)) {
        $awaitingDecision[] = $con;
    }
    if (!empty($con['end_date']) && in_array($con['status'] ?? '', ['active', 'renewed'], true)) {
        $end = strtotime($con['end_date']);
        if ($end && $end >= $now && $end <= $in90) {
            $renewalSoon[] = $con;
        }
    }
}
usort($renewalSoon, static fn($a, $b) => strtotime($a['end_date']) <=> strtotime($b['end_date']));
usort($awaitingDecision, static fn($a, $b) => strcmp($a['status'], $b['status']));
$renewalSoon = array_slice($renewalSoon, 0, 5);
$awaitingDecision = array_slice($awaitingDecision, 0, 5);
arsort($typeCounts);
?>

<div class="animate-fade-in-up space-y-8">
<?php
render_page_header([
    'title'    => 'Contracts',
    'subtitle' => 'Draft agreements, manage approval routing, and track renewals.',
    'actions'  => function () use ($canCreate, $canManage) {
        ?>
        <a href="<?= route('contracts.export') ?>" class="btn-secondary mr-2 inline-flex items-center gap-1.5" style="text-decoration: none; display: inline-flex; align-items: center; padding: 0.5rem 1rem; border-radius: 0.75rem;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Export CSV
        </a>
        <a href="<?= route('contracts.export.pdf') ?>" class="btn-secondary mr-2 inline-flex items-center gap-1.5" style="text-decoration: none; display: inline-flex; align-items: center; padding: 0.5rem 1rem; border-radius: 0.75rem;">
            Export PDF
        </a>
        <?php
        if ($canManage) {
            ?>
            <button onclick="openModal('templateModal')" class="btn-secondary mr-2">Template</button>
            <button onclick="openModal('chainModal')" class="btn-secondary mr-2">Approval Chain</button>
            <?php
        }
        if ($canCreate) {
            ?>
            <button onclick="openModal('contractModal')" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Draft Contract
            </button>
            <?php
        }
    },
]);
?>

<?php if (!empty($spendRollup)): ?>
<div class="module-insight-panel">
    <h3 class="module-insight-title">Spend rollup by type</h3>
    <p class="module-insight-sub">Total contract value grouped by type</p>
    <?php foreach ($spendRollup as $row): ?>
        <div class="module-stat-row">
            <span class="text-ink/70"><?= htmlspecialchars($row['type_name'] ?? 'General') ?>
                <span class="text-ink/40 text-xs">(<?= (int) ($row['contract_count'] ?? 0) ?>)</span>
            </span>
            <strong class="tabular-nums text-ink">₱<?= number_format((float) ($row['total_value'] ?? 0), 0) ?></strong>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

@include('partials.stat_kpi_grid', ['cards' => [
    ['label' => 'Active contracts', 'value' => $statusCounts['active'] + $statusCounts['renewed'], 'hint' => 'Live agreements in force', 'tone' => 'navy', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ['label' => 'Awaiting action', 'value' => $needsDecision, 'hint' => 'In review or pending signature', 'tone' => 'peach', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['label' => 'Expiring in 90 days', 'value' => $expiringSoon, 'hint' => 'Renewal pipeline', 'tone' => 'terracotta', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ['label' => 'Portfolio value', 'value' => $totalValue > 0 ? '₱' . number_format($totalValue, 0) : '—', 'hint' => 'Sum of registered values', 'tone' => 'brown', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'valueClass' => 'is-compact'],
]])

<div class="module-info-grid">
    @include('partials.module_feed_list', [
        'title' => 'Renewals approaching',
        'subtitle' => 'Active agreements ending within 90 days',
        'items' => array_map(static fn($c) => [
            'label' => $c['title'],
            'meta'  => ($c['counterparty_name'] ?? '') . ' · ' . ($c['contract_number'] ?? ''),
            'href'  => url('/contracts/' . $c['id']),
            'modal' => true,
            'aside' => !empty($c['end_date']) ? date('M j, Y', strtotime($c['end_date'])) : '',
        ], $renewalSoon),
        'empty' => 'No renewals due in the next 90 days.',
    ])
    @include('partials.module_feed_list', [
        'title' => 'Awaiting decision',
        'subtitle' => 'Contracts in review or pending signature',
        'items' => array_map(static fn($c) => [
            'label' => $c['title'],
            'meta'  => ($c['counterparty_name'] ?? '') . ' · ' . str_replace('_', ' ', $c['status'] ?? ''),
            'href'  => url('/contracts/' . $c['id']),
            'modal' => true,
            'badge' => $c['status'] ?? null,
        ], $awaitingDecision),
        'empty' => 'No contracts waiting on approval or signature.',
    ])
</div>

<?php if (!empty($typeCounts)): ?>
<div class="module-insight-panel">
    <h3 class="module-insight-title">Contract types in registry</h3>
    <p class="module-insight-sub">Distribution of agreements by classification</p>
    <?php foreach (array_slice($typeCounts, 0, 6, true) as $type => $count): ?>
        <div class="module-stat-row">
            <span class="text-ink/70"><?= htmlspecialchars($type) ?></span>
            <strong class="tabular-nums text-ink"><?= $count ?></strong>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($spendRollup)): ?>
<div class="module-insight-panel">
    <h3 class="module-insight-title">Spend rollup by type</h3>
    <p class="module-insight-sub">Total contract value grouped by type</p>
    <?php foreach ($spendRollup as $row): ?>
        <div class="module-stat-row">
            <span class="text-ink/70"><?= htmlspecialchars($row['type_name'] ?? 'General') ?>
                <span class="text-ink/40 text-xs">(<?= (int) ($row['contract_count'] ?? 0) ?>)</span>
            </span>
            <strong class="tabular-nums text-ink">₱<?= number_format((float) ($row['total_value'] ?? 0), 0) ?></strong>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($contracts)): ?>
    <div class="module-empty">No contracts registered.</div>
<?php else: ?>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
    <div class="xl:col-span-2 space-y-6" data-filter-root>
        @include('partials.module_filters', ['fields' => [
            ['name' => 'q', 'label' => 'Search', 'placeholder' => 'Title, number, counterparty…'],
            ['name' => 'status', 'label' => 'Status', 'allLabel' => 'All statuses', 'options' => [
                'draft' => 'Draft',
                'in_review' => 'In review',
                'pending_signature' => 'Pending signature',
                'active' => 'Active',
                'expired' => 'Expired',
                'terminated' => 'Terminated',
                'renewed' => 'Renewed',
            ]],
            ['name' => 'type', 'label' => 'Type', 'allLabel' => 'All types', 'options' => $typeOptions],
        ]])
        <div class="module-empty" data-filter-empty hidden>No contracts match these filters.</div>

        <div class="module-table-wrap">
            <div class="overflow-x-auto">
                <table class="module-table">
                    <thead>
                        <tr>
                            <th>Contract No.</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Counterparty</th>
                            <th>Term</th>
                            <th>Value</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $con): ?>
                            <tr data-filter-item
                                data-contract-id="<?= (int) $con['id'] ?>"
                                data-filter-status="<?= htmlspecialchars($con['status']) ?>"
                                data-filter-type="<?= htmlspecialchars($con['type_name'] ?: 'General') ?>"
                                data-filter-search="<?= htmlspecialchars(strtolower($con['title'] . ' ' . $con['contract_number'] . ' ' . $con['counterparty_name'] . ' ' . ($con['type_name'] ?? ''))) ?>">
                                <td class="font-mono text-xs text-ink/60 whitespace-nowrap"><?= htmlspecialchars($con['contract_number']) ?></td>
                                <td>
                                    <a href="<?= url('/contracts/' . $con['id']) ?>" data-record-modal class="font-semibold text-ink hover:text-brand-700 transition">
                                        <?= htmlspecialchars($con['title']) ?>
                                    </a>
                                </td>
                                <td class="text-ink/70"><?= htmlspecialchars($con['type_name'] ?: 'General') ?></td>
                                <td class="text-ink/70"><?= htmlspecialchars($con['counterparty_name']) ?></td>
                                <td class="text-ink/60 text-xs whitespace-nowrap">
                                    <?= date('M d, Y', strtotime($con['start_date'])) ?>
                                    –
                                    <?= $con['end_date'] ? date('M d, Y', strtotime($con['end_date'])) : 'Ongoing' ?>
                                </td>
                                <td class="text-ink/70 whitespace-nowrap">
                                    <?= $con['value_amount'] ? htmlspecialchars($con['currency']) . ' ' . number_format($con['value_amount'], 2) : '—' ?>
                                </td>
                                <td>
                                    <span data-status-badge class="inline-block px-2 py-0.5 text-[11px] border rounded capitalize <?= $statusStyles[$con['status']] ?? 'bg-gray-50 border-gray-200' ?>">
                                        <?= str_replace('_', ' ', $con['status']) ?>
                                    </span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="<?= url('/contracts/' . $con['id']) ?>" data-record-modal class="text-xs font-semibold text-brand-700 hover:text-brand-900">View</a>
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
            <h3 class="module-insight-title">Pipeline by status</h3>
            <p class="module-insight-sub">Where agreements currently sit</p>
            <ul class="module-insight-list">
                <?php foreach (['in_review' => 'In review', 'pending_signature' => 'Pending signature', 'active' => 'Active', 'expired' => 'Expired'] as $key => $label): ?>
                    <li>
                        <span class="module-insight-dot"></span>
                        <span class="flex-1"><?= $label ?></span>
                        <strong class="tabular-nums text-ink"><?= $statusCounts[$key] ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="module-insight-panel">
            <h3 class="module-insight-title">Renewal & approval notes</h3>
            <p class="module-insight-sub">Before approving or renewing</p>
            <ul class="module-insight-list">
                <li><span class="module-insight-dot"></span><span>Confirm counterparty details and commercial value.</span></li>
                <li><span class="module-insight-dot"></span><span>Verify approval sequence is complete.</span></li>
                <li><span class="module-insight-dot"></span><span>Flag contracts ending within 90 days for renewal.</span></li>
            </ul>
        </div>
        <?php if (!empty($vendorScoreRollup)): ?>
        <div class="module-insight-panel">
            <h3 class="module-insight-title">Vendor scorecards</h3>
            <p class="module-insight-sub">Average performance by counterparty</p>
            <?php foreach (array_slice($vendorScoreRollup, 0, 6) as $vs): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70 truncate" title="<?= htmlspecialchars($vs['counterparty_name']) ?>"><?= htmlspecialchars($vs['counterparty_name']) ?></span>
                    <strong class="tabular-nums text-ink"><?= number_format((float) $vs['avg_overall'], 2) ?> <span class="text-ink/40 text-xs font-normal">(<?= (int) $vs['score_count'] ?>)</span></strong>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </aside>
    </div>
<?php endif; ?>
</div>

<?php if ($canCreate): ?>
<div id="contractModal" class="modal-overlay hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-xl w-full border border-ink/10 overflow-hidden" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="text-lg font-semibold text-ink">Draft Contract</h3>
            <button onclick="closeModal('contractModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/contracts') ?>" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Template (optional)</label>
                <select name="template_id" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10">
                    <option value="">— None —</option>
                    <?php foreach (($templates ?? []) as $tpl): ?>
                        <option value="<?= (int) $tpl['id'] ?>"><?= htmlspecialchars($tpl['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Contract Title</label>
                <input type="text" name="title" required placeholder="e.g. Office Cleaning Services SLA" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Contract Type</label>
                    <select name="contract_type_id" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                        <option value="">— Select Type —</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Value Amount</label>
                    <input type="number" name="value_amount" step="0.01" min="0" placeholder="150000.00" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Start Date</label>
                    <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">End Date (Optional)</label>
                    <input type="date" name="end_date" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                </div>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Counterparty Organization</label>
                <input type="text" name="counterparty_name" required placeholder="e.g. Acme Services Inc." class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Counterparty Contact</label>
                <input type="text" name="counterparty_contact" placeholder="e.g. rep@acme.com / 555-010" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">File Attachment (PDF / DOC)</label>
                <input type="file" name="contract_file" class="w-full text-sm file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Saved approval chain (optional)</label>
                <select name="approval_chain_id" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10">
                    <option value="">— Manual selection below —</option>
                    <?php foreach (($chains ?? []) as $chain): ?>
                        <option value="<?= (int) $chain['id'] ?>"><?= htmlspecialchars($chain['name']) ?><?= !empty($chain['is_default']) ? ' (default)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Approval Sequence</label>
                <div class="border border-ink/15 rounded-md p-3 bg-gray-50 max-h-36 overflow-y-auto space-y-2">
                    <?php foreach ($users as $u): ?>
                        <label class="flex items-center gap-2 text-xs text-ink/80 font-medium cursor-pointer">
                            <input type="checkbox" name="approvers[]" value="<?= $u['id'] ?>" class="rounded border-ink/10 text-brand-600 focus:ring-brand-500">
                            <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="text-[11px] text-ink/40 mt-1">Select approvers in the required sign-off order, or use a saved chain.</p>
            </div>
            <label class="flex items-center gap-2 text-xs text-ink/70">
                <input type="checkbox" name="skip_signature" value="1" class="rounded border-ink/10">
                Skip e-signature (activate immediately when no approvers)
            </label>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Related Legal Case (Optional)</label>
                <select name="related_case_id" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                    <option value="">— None —</option>
                    <?php foreach ($cases as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?> (<?= htmlspecialchars($c['case_number']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" name="auto_renew" id="auto_renew" value="1" class="rounded border-ink/10 text-brand-600 focus:ring-brand-500">
                <label for="auto_renew" class="text-xs text-ink/75 font-medium">Enable automatic renewal</label>
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('contractModal')" class="px-4 py-2 rounded-md border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-md bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm">Submit Draft</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($canManage)): ?>
<div id="templateModal" class="modal-overlay hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full border border-ink/10" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center">
            <h3 class="text-lg font-semibold">New Contract Template</h3>
            <button onclick="closeModal('templateModal')">✕</button>
        </div>
        <form action="<?= url('/contracts/templates') ?>" method="POST" class="p-6 space-y-3">
            @csrf
            <input type="text" name="name" required placeholder="Template name" class="w-full border border-ink/15 rounded-md p-2.5 text-sm">
            <select name="contract_type_id" class="w-full border border-ink/15 rounded-md p-2.5 text-sm">
                <option value="">Type (optional)</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" step="0.01" name="default_value" placeholder="Default value" class="w-full border border-ink/15 rounded-md p-2.5 text-sm">
            <textarea name="body_text" required rows="5" placeholder="Template body / clauses" class="w-full border border-ink/15 rounded-md p-2.5 text-sm"></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal('templateModal')" class="btn-secondary">Cancel</button>
                <button class="btn-primary">Save Template</button>
            </div>
        </form>
    </div>
</div>
<div id="chainModal" class="modal-overlay hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full border border-ink/10" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center">
            <h3 class="text-lg font-semibold">Approval Chain</h3>
            <button onclick="closeModal('chainModal')">✕</button>
        </div>
        <form action="<?= url('/contracts/chains') ?>" method="POST" class="p-6 space-y-3">
            @csrf
            <input type="text" name="name" required placeholder="Chain name" class="w-full border border-ink/15 rounded-md p-2.5 text-sm">
            <select name="contract_type_id" class="w-full border border-ink/15 rounded-md p-2.5 text-sm">
                <option value="">Type (optional)</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="is_default" value="1"> Default for selected type</label>
            <div class="border border-ink/15 rounded-md p-3 max-h-40 overflow-y-auto space-y-2">
                <?php foreach ($users as $u): ?>
                    <label class="flex items-center gap-2 text-xs">
                        <input type="checkbox" name="approvers[]" value="<?= $u['id'] ?>">
                        <?= htmlspecialchars($u['full_name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal('chainModal')" class="btn-secondary">Cancel</button>
                <button class="btn-primary">Save Chain</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

@if(!empty($openRecordId))
<div id="open-record-bootstrap"
     hidden
     data-url="<?= url('/contracts/' . $openRecordId) ?>?modal=1"
     <?php if (!empty($openEsign)): ?>data-esign="1"<?php endif; ?>></div>
@endif

@endsection
