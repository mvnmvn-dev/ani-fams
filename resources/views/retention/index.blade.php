@extends('layouts.app')

@section('content')
<?php
/** @var array $policies */
/** @var array $assignments */
/** @var array $holds */
/** @var array $availDocs */
/** @var array $allDocs */
/** @var array $legalCases */
/** @var bool $canManage */

$overdueList = array_values(array_filter($assignments, fn($a) => $a['status'] === 'active' && strtotime($a['calculated_disposal_date']) <= time()));
$lockedHolds = count(array_filter($holds, fn($h) => $h['released_at'] === null));
$dueReview = count(array_filter($assignments, fn($a) => ($a['status'] ?? '') === 'due_for_review'));
$activeAsg = count(array_filter($assignments, fn($a) => ($a['status'] ?? '') === 'active'));
$onHoldAsg = count(array_filter($assignments, fn($a) => ($a['status'] ?? '') === 'on_legal_hold'));

// Templates and Notifications (passed from controller)
$templates = $templates ?? [];
$unreadHoldNotifs = $unreadHoldNotifs ?? [];
$dueReview = count(array_filter($assignments, fn($a) => ($a['status'] ?? '') === 'due_for_review'));
$activeAsg = count(array_filter($assignments, fn($a) => ($a['status'] ?? '') === 'active'));
$onHoldAsg = count(array_filter($assignments, fn($a) => ($a['status'] ?? '') === 'on_legal_hold'));

$asgStatusStyles = [
    'active'         => 'bg-brand-50 text-brand-700 border-brand-200',
    'due_for_review' => 'bg-accent-50 text-accent-700 border-accent-200',
    'on_legal_hold'  => 'bg-accent-50 text-accent-700 border-accent-200',
    'disposed'       => 'bg-gray-100 text-gray-700 border-gray-200',
];
$hasRetentionData = !empty($assignments) || !empty($holds);
$activeHolds = array_values(array_filter($holds, static fn($h) => empty($h['released_at'])));
$dueReviewList = array_values(array_filter($assignments, static fn($a) => ($a['status'] ?? '') === 'due_for_review'));
usort($overdueList, static fn($a, $b) => strtotime($a['calculated_disposal_date'] ?? '0') <=> strtotime($b['calculated_disposal_date'] ?? '0'));
$overduePreview = array_slice($overdueList, 0, 5);
$dueReviewList = array_slice($dueReviewList, 0, 5);
?>
<div class="animate-fade-in-up space-y-8">
    <?php
    render_page_header([
        'title' => 'Records',
        'subtitle' => 'Retention schedules, disposal processing, and legal holds.',
        'actions' => function () use ($canManage) {
            ?>
            <a href="<?= route('retention.export') ?>" download data-export-link class="btn-secondary mr-2 inline-flex items-center gap-1.5" style="text-decoration: none; display: inline-flex; align-items: center; padding: 0.5rem 1rem; border-radius: 0.75rem;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export CSV
            </a>
            <a href="<?= route('retention.export.pdf') ?>" download data-export-link class="btn-secondary mr-2 inline-flex items-center gap-1.5" style="text-decoration: none; display: inline-flex; align-items: center; padding: 0.5rem 1rem; border-radius: 0.75rem;">
                Export PDF
            </a>
            <?php
            if (!$canManage) return;
            ?>
            <button onclick="openModal('assignPolicyModal')" class="btn-secondary">Assign Policy</button>
            <button onclick="openModal('placeHoldModal')" class="btn-secondary">Place Legal Hold</button>
            <button onclick="openComplianceCalendar()" class="btn-secondary">Compliance Calendar</button>
            <button onclick="openModal('definePolicyModal')" class="btn-primary">Define Policy</button>
            <?php
        },
    ]);
    ?>

    <?php $ai = $aiInsights ?? ['summary' => '', 'suggestions' => [], 'archive_candidates' => []]; ?>
    <section class="rounded-2xl border border-brand-200 bg-brand-50/30 p-5 shadow-sm">
        <div class="flex items-center gap-2"><span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-brand-700 text-white text-xs font-bold">AI</span><div><h2 class="section-title">ARNEL retention advisor</h2><p class="type-meta">Suggested categories and records approaching archival review</p></div></div>
        <p class="mt-3 text-sm text-ink/70"><?= htmlspecialchars($ai['summary'] ?? '') ?></p>
        <?php if (!empty($ai['suggestions'])): ?><div class="mt-4 flex flex-wrap gap-2"><?php foreach (array_slice($ai['suggestions'], 0, 8) as $item): ?><span class="rounded-full border border-brand-200 bg-white/75 px-3 py-1.5 text-xs text-ink/70"><strong><?= htmlspecialchars($item['document']) ?></strong> · <?= htmlspecialchars($item['category']) ?></span><?php endforeach; ?></div><?php endif; ?>
        <?php if (!empty($ai['archive_candidates'])): ?><p class="mt-3 text-xs text-ink/55"><strong>Archive candidates:</strong> <?= htmlspecialchars(implode(' · ', $ai['archive_candidates'])) ?></p><?php endif; ?>
    </section>

    @include('partials.stat_kpi_grid', ['cards' => [
        ['label' => 'Active schedules', 'value' => $activeAsg, 'hint' => 'Retention clocks running', 'tone' => 'navy', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        ['label' => 'Due for review', 'value' => $dueReview, 'hint' => 'Ready for assessment', 'tone' => 'peach', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Overdue disposal', 'value' => count($overdueList), 'hint' => 'Past calculated date', 'tone' => 'terracotta', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
        ['label' => 'Legal holds', 'value' => $lockedHolds, 'hint' => 'Active locks in place', 'tone' => 'brown', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
    ]])

    <?php if (!empty($unreadHoldNotifs)): ?>
    <div class="border border-red-300 bg-red-100 rounded-lg px-5 py-4 text-sm text-red-900 flex flex-col gap-3">
        <h3 class="font-bold flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Urgent Hold Notifications
        </h3>
        <ul class="space-y-2">
            <?php foreach ($unreadHoldNotifs as $notif): ?>
                <li class="flex items-center justify-between" id="notif-row-<?= $notif['id'] ?>">
                    <span><?= htmlspecialchars($notif['reason'] ?? 'Hold Notice') ?> — <strong class="capitalize"><?= htmlspecialchars($notif['notification_type'] ?? 'Placed') ?></strong> on <?= date('M d, Y', strtotime($notif['notified_at'])) ?></span>
                    <button onclick="markHoldNotifRead(<?= $notif['id'] ?>)" class="px-3 py-1 bg-red-200 hover:bg-red-300 text-red-900 rounded font-semibold transition text-xs">Dismiss</button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>


    <div class="module-info-grid">
        @include('partials.module_feed_list', [
            'title' => 'Overdue for disposal',
            'subtitle' => 'Records past their calculated disposal date',
            'items' => array_map(static fn($a) => [
                'label' => $a['document_title'] ?? ('Document #' . ($a['document_id'] ?? '')),
                'meta'  => ($a['policy_name'] ?? 'No policy') . ' · Dispose by ' . date('M j, Y', strtotime($a['calculated_disposal_date'])),
                'badge' => $a['status'] ?? null,
            ], $overduePreview),
            'empty' => 'No overdue disposal records.',
        ])
        @include('partials.module_feed_list', [
            'title' => 'Due for review',
            'subtitle' => 'Schedules ready for retention assessment',
            'items' => array_map(static fn($a) => [
                'label' => $a['document_title'] ?? ('Document #' . ($a['document_id'] ?? '')),
                'meta'  => ($a['policy_name'] ?? 'Unassigned policy') . ' · ' . ucfirst(str_replace('_', ' ', $a['disposal_action'] ?? 'review')),
                'aside' => !empty($a['calculated_disposal_date']) ? date('M j, Y', strtotime($a['calculated_disposal_date'])) : '',
            ], $dueReviewList),
            'empty' => 'No schedules due for review.',
        ])
    </div>

    <?php if (!empty($policies)): ?>
    <div class="module-insight-panel">
        <h3 class="module-insight-title">Retention policy library</h3>
        <p class="module-insight-sub"><?= count($policies) ?> defined polic<?= count($policies) === 1 ? 'y' : 'ies' ?> governing record lifecycles</p>
        <?php foreach (array_slice($policies, 0, 6) as $policy): ?>
            <div class="module-stat-row">
                <span class="text-ink/70"><?= htmlspecialchars($policy['name'] ?? 'Policy') ?></span>
                <span class="text-xs text-ink/45"><?= (int) ($policy['retention_years'] ?? 0) ?> yr · <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $policy['disposal_action'] ?? 'review'))) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($activeHolds)): ?>
    <div class="module-insight-panel">
        <h3 class="module-insight-title">Active legal holds</h3>
        <p class="module-insight-sub"><?= count($activeHolds) ?> record<?= count($activeHolds) === 1 ? '' : 's' ?> locked from disposal</p>
        <ul class="module-insight-list">
            <?php foreach (array_slice($activeHolds, 0, 4) as $hold): ?>
                <li>
                    <span class="module-insight-dot"></span>
                    <span><strong><?= htmlspecialchars($hold['document_title'] ?? 'Document') ?></strong> — <?= htmlspecialchars($hold['reason'] ?? 'No reason stated') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($overdueList)): ?>
        <div class="border border-accent-200 bg-accent-50 rounded-lg px-4 py-3 text-sm text-accent-700">
            <strong><?= count($overdueList) ?></strong> record<?= count($overdueList) === 1 ? '' : 's' ?> overdue for disposal and require action.
        </div>
    <?php endif; ?>

    <div class="space-y-8" data-filter-root>
        <?php if ($hasRetentionData): ?>
            @include('partials.module_filters', ['fields' => [
                ['name' => 'q', 'label' => 'Search', 'placeholder' => 'Document, policy, hold reason…'],
                ['name' => 'panel', 'label' => 'Section', 'allLabel' => 'Schedules & holds', 'options' => [
                    'schedule' => 'Retention schedules',
                    'hold' => 'Legal holds',
                ]],
                ['name' => 'status', 'label' => 'Status', 'allLabel' => 'All statuses', 'options' => [
                    'active' => 'Active',
                    'due_for_review' => 'Due for review',
                    'on_legal_hold' => 'On legal hold',
                    'disposed' => 'Disposed',
                    'locked' => 'Hold locked',
                    'released' => 'Hold released',
                    'overdue' => 'Overdue for disposal',
                ]],
            ]])
            <div class="module-empty" data-filter-empty hidden>No records match these filters.</div>
        <?php endif; ?>

        <section data-filter-section>
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-3">
                <div class="xl:col-span-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-ink">Retention Schedules</h2>
                    <span class="text-xs text-ink/45">
                        <?= count($overdueList) > 0 ? count($overdueList) . ' overdue' : 'All current' ?>
                    </span>
                </div>
                <div class="module-insight-panel py-3 px-4 hidden xl:block">
                    <p class="text-xs text-ink/60 leading-relaxed"><strong class="text-ink">Disposal readiness:</strong> assess due-for-review items first, then clear overdue active schedules that are not on legal hold.</p>
                </div>
            </div>
            <?php if (empty($assignments)): ?>
                <div class="module-empty">No retention assignments scheduled.</div>
            <?php else: ?>
                <div class="module-table-wrap">
                    <div class="overflow-x-auto">
                        <table class="module-table">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Policy</th>
                                    <th>Disposal Action</th>
                                    <th>Dispose By</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $asg): ?>
                                    <?php
                                    $overdue = strtotime($asg['calculated_disposal_date']) <= time() && $asg['status'] === 'active';
                                    $disposalTs = strtotime($asg['calculated_disposal_date']);
                                    $daysLeft = max(0, (int) ceil(($disposalTs - time()) / 86400));
                                    ?>
                                    <tr class="<?= $overdue ? 'is-watch' : '' ?>"
                                        data-filter-item
                                        data-assignment-id="<?= (int) $asg['id'] ?>"
                                        data-filter-panel="schedule"
                                        data-filter-status="<?= htmlspecialchars(($overdue ? 'overdue ' : '') . $asg['status']) ?>"
                                        data-filter-search="<?= htmlspecialchars(strtolower($asg['document_title'] . ' ' . $asg['document_number'] . ' ' . $asg['policy_name'] . ' ' . $asg['disposal_action'])) ?>">
                                        <td>
                                            <a href="<?= url('/documents/' . $asg['document_id']) ?>" data-record-modal class="font-semibold text-ink hover:text-brand-700">
                                                <?= htmlspecialchars($asg['document_title']) ?>
                                            </a>
                                            <div class="text-[11px] font-mono text-ink/40 mt-0.5"><?= htmlspecialchars($asg['document_number']) ?></div>
                                        </td>
                                        <td class="text-ink/70"><?= htmlspecialchars($asg['policy_name']) ?></td>
                                        <td class="capitalize text-ink/70"><?= htmlspecialchars($asg['disposal_action']) ?></td>
                                        <td class="text-xs whitespace-nowrap <?= $overdue ? 'text-accent-700 font-semibold' : 'text-ink/60' ?>">
                                            <?= date('M d, Y', $disposalTs) ?>
                                            <?php if (!$overdue && $asg['status'] === 'active'): ?>
                                                <div class="text-[10px] text-ink/40"><?= $daysLeft ?> day<?= $daysLeft !== 1 ? 's' : '' ?> remaining</div>
                                            <?php elseif ($overdue): ?>
                                                <div class="text-[10px] text-accent-700 font-semibold">Overdue</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span data-status-badge class="inline-block px-2 py-0.5 text-[11px] border rounded capitalize <?= $asgStatusStyles[$asg['status']] ?? 'bg-gray-50 border-gray-200' ?>">
                                                <?= str_replace('_', ' ', $asg['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-right whitespace-nowrap" data-row-actions>
                                            <?php if ($canManage && $asg['status'] !== 'disposed'): ?>
                                                <?php if ($asg['status'] === 'on_legal_hold'): ?>
                                                    <span class="text-xs text-accent-700 font-semibold">Hold locked</span>
                                                <?php else: ?>
                                                    <form action="<?= url('/retention/' . $asg['id'] . '/dispose') ?>" method="POST" class="inline" data-ajax-action="retention.dispose">
                                                        @csrf
                                                        <button type="submit" class="text-xs font-semibold <?= $overdue ? 'text-accent-700 hover:text-red-900' : 'text-brand-700 hover:text-brand-900' ?>" onclick="return confirmForm(event, 'Execute disposal action (<?= htmlspecialchars($asg['disposal_action'], ENT_QUOTES) ?>) for this record?', { confirmLabel: 'Process', danger: true })">
                                                            Process <?= ucfirst($asg['disposal_action']) ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-ink/30">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section data-filter-section>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-ink">Legal Holds</h2>
                <?php if ($lockedHolds > 0): ?>
                    <span class="text-xs text-accent-700 font-semibold"><?= $lockedHolds ?> active</span>
                <?php endif; ?>
            </div>
            <?php if (empty($holds)): ?>
                <div class="module-empty">No legal holds active.</div>
            <?php else: ?>
                <div class="module-table-wrap">
                    <div class="overflow-x-auto">
                        <table class="module-table">
                            <thead>
                                <tr>
                                    <th>Reason</th>
                                    <th>Related Case</th>
                                    <th>Placed By</th>
                                    <th>Documents</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($holds as $hold): ?>
                                    <?php $holdStatus = $hold['released_at'] === null ? 'locked' : 'released'; ?>
                                    <tr data-filter-item
                                        data-hold-id="<?= (int) $hold['id'] ?>"
                                        data-filter-panel="hold"
                                        data-filter-status="<?= $holdStatus ?>"
                                        data-filter-search="<?= htmlspecialchars(strtolower($hold['reason'] . ' ' . ($hold['case_title'] ?? '') . ' ' . $hold['placed_by_name'] . ' ' . ($hold['documents_on_hold'] ?? ''))) ?>">
                                        <td class="font-semibold"><?= htmlspecialchars($hold['reason']) ?></td>
                                        <td class="text-ink/70"><?= $hold['case_title'] ? htmlspecialchars($hold['case_title']) : '—' ?></td>
                                        <td class="text-ink/70"><?= htmlspecialchars($hold['placed_by_name']) ?></td>
                                        <td class="text-ink/60 text-xs max-w-[14rem]"><?= htmlspecialchars($hold['documents_on_hold'] ?: '—') ?></td>
                                        <td class="text-xs text-ink/60 whitespace-nowrap">
                                            <?= date('M d, Y', strtotime($hold['placed_at'])) ?>
                                            <?php if ($hold['released_at']): ?>
                                                <div class="text-ink/40">Released <?= date('M d, Y', strtotime($hold['released_at'])) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($holdStatus === 'locked'): ?>
                                                <span data-status-badge class="inline-block px-2 py-0.5 text-[11px] border border-accent-200 bg-accent-50 text-accent-700 rounded">Locked</span>
                                            <?php else: ?>
                                                <span data-status-badge class="inline-block px-2 py-0.5 text-[11px] border border-gray-200 bg-gray-100 text-gray-600 rounded">Released</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right whitespace-nowrap" data-row-actions>
                                            <?php if ($holdStatus === 'locked' && $canManage): ?>
                                                <form action="<?= url('/retention/holds/' . $hold['id'] . '/release') ?>" method="POST" class="inline" data-ajax-action="retention.release">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold text-accent-700 hover:text-red-900" onclick="return confirmForm(event, 'Release this legal hold?', { title: 'Release legal hold', confirmLabel: 'Release Hold', danger: true })">
                                                        Release
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-ink/30">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php if ($canManage): ?>
<div id="assignPolicyModal" class="modal-overlay hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-xl w-full border border-ink/10 overflow-hidden" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="text-lg font-semibold text-ink">Assign Retention Policy</h3>
            <button onclick="closeModal('assignPolicyModal')" class="text-ink/40 hover:text-ink/60"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form action="<?= url('/retention/assign') ?>" method="POST" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Document</label>
                <select name="document_id" required class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 outline-none">
                    <option value="">— Choose Document —</option>
                    <?php foreach ($availDocs as $doc): ?>
                        <option value="<?= $doc['id'] ?>"><?= htmlspecialchars($doc['title']) ?> (<?= htmlspecialchars($doc['document_number']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Policy</label>
                <select name="policy_id" required class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 outline-none">
                    <option value="">— Choose Policy —</option>
                    <?php foreach ($policies as $pol): ?>
                        <option value="<?= $pol['id'] ?>"><?= htmlspecialchars($pol['name']) ?> (<?= $pol['retention_period_months'] ?> months)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Retention Start Date</label>
                <input type="date" name="retention_start_date" value="<?= date('Y-m-d') ?>" required class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 outline-none">
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('assignPolicyModal')" class="px-4 py-2 rounded-md border border-ink/10 text-sm font-semibold text-ink/70">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-md bg-brand-700 hover:bg-brand-600 text-white text-sm font-semibold">Assign Policy</button>
            </div>
        </form>
    </div>
</div>

<div id="placeHoldModal" class="modal-overlay hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-xl w-full border border-ink/10 overflow-hidden" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="text-lg font-semibold text-ink">Place Legal Hold</h3>
            <button onclick="closeModal('placeHoldModal')" class="text-ink/40 hover:text-ink/60"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form action="<?= url('/retention/holds') ?>" method="POST" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Reason for Hold</label>
                <input type="text" name="reason" required class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Related Legal Case (Optional)</label>
                <select name="related_case_id" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 outline-none">
                    <option value="">— None —</option>
                    <?php foreach ($legalCases as $lc): ?>
                        <option value="<?= $lc['id'] ?>"><?= htmlspecialchars($lc['title']) ?> (<?= htmlspecialchars($lc['case_number']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Documents to Lock</label>
                <div class="border border-ink/15 rounded-md p-3 bg-ink/[0.03] max-h-40 overflow-y-auto space-y-2">
                    <?php if (empty($allDocs)): ?>
                        <p class="text-xs text-ink/50 text-center py-2">No documents available.</p>
                    <?php else: ?>
                        <?php foreach ($allDocs as $doc): ?>
                            <label class="flex items-center gap-2 text-xs text-ink font-medium cursor-pointer">
                                <input type="checkbox" name="document_ids[]" value="<?= $doc['id'] ?>" class="rounded border-ink/20 text-brand-600">
                                <?= htmlspecialchars($doc['title']) ?> (<?= htmlspecialchars($doc['document_number']) ?>)
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('placeHoldModal')" class="px-4 py-2 rounded-md border border-ink/10 text-sm font-semibold text-ink/70">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-md bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold">Place Hold</button>
            </div>
        </form>
    </div>
</div>

<div id="definePolicyModal" class="modal-overlay hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-xl w-full border border-ink/10 overflow-hidden" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="text-lg font-semibold text-ink">Define Retention Policy</h3>
            <button onclick="closeModal('definePolicyModal')" class="text-ink/40 hover:text-ink/60"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form action="<?= url('/retention/policies') ?>" method="POST" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Policy Name</label>
                <input type="text" name="name" required class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Record Category</label>
                <input type="text" name="record_category" required class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Duration (Months)</label>
                    <input type="number" name="retention_period_months" required min="1" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 outline-none">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Disposal Action</label>
                    <select name="disposal_action" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 outline-none">
                        <option value="review">Review Required</option>
                        <option value="archive">Archive Record</option>
                        <option value="destroy">Destroy Record</option>
                    </select>
                </div>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Trigger Event</label>
                <select name="trigger_event" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 outline-none">
                    <option value="creation_date">Document Creation Date</option>
                    <option value="last_activity">Last Modified/Activity Date</option>
                    <option value="contract_end">Contract End Date</option>
                    <option value="fiscal_year_end">Fiscal Year End</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Legal Basis / Citation</label>
                <input type="text" name="legal_basis" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 outline-none">
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('definePolicyModal')" class="px-4 py-2 rounded-md border border-ink/10 text-sm font-semibold text-ink/70">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-md bg-brand-700 hover:bg-brand-600 text-white text-sm font-semibold">Save Policy</button>
            </div>
        </form>

        <!-- Template Section -->
        <?php if (!empty($templates)): ?>
        <div class="p-6 bg-gray-50 border-t border-ink/10">
            <h4 class="text-sm font-semibold text-ink mb-3">Or create from a regulatory template:</h4>
            <form action="<?= url('/retention/policies/from-template') ?>" method="POST" class="flex gap-3">
                @csrf
                <select name="template_id" required class="flex-1 border border-ink/15 rounded-md p-2.5 text-sm bg-white outline-none">
                    <option value="">— Select Template —</option>
                    <?php foreach ($templates as $tmpl): ?>
                        <option value="<?= $tmpl['id'] ?>"><?= htmlspecialchars($tmpl['regulation_code']) ?> - <?= htmlspecialchars($tmpl['name']) ?> (<?= $tmpl['retention_period_months'] ?> months)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 rounded-md border border-brand-500 text-brand-700 hover:bg-brand-50 text-sm font-semibold">Use Template</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div id="complianceCalendarModal" class="modal-overlay hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full border border-ink/10 flex flex-col max-h-[90vh]" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50 flex-shrink-0">
            <div>
                <h3 class="text-lg font-semibold text-ink">Compliance Calendar</h3>
                <p class="text-xs text-ink/60">Upcoming disposal deadlines and legal hold events</p>
            </div>
            <button onclick="closeModal('complianceCalendarModal')" class="text-ink/40 hover:text-ink/60"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <div class="p-6 overflow-y-auto flex-1 bg-sand/5">
            <div id="calendar-events-container" class="space-y-4">
                <div class="text-center text-ink/40 py-8 text-sm">Loading events...</div>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-ink/10 bg-gray-50 flex justify-end flex-shrink-0">
            <button type="button" onclick="closeModal('complianceCalendarModal')" class="px-4 py-2 rounded-md border border-ink/10 text-sm font-semibold text-ink/70 bg-white">Close</button>
        </div>
    </div>
</div>

<script>
    function markHoldNotifRead(id) {
        fetch('<?= url('/retention/notifications') ?>/' + id + '/read', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '<?= csrf_token() ?>',
                'Content-Type': 'application/json'
            }
        }).then(res => res.json()).then(data => {
            if (data.success) {
                document.getElementById('notif-row-' + id).remove();
            }
        });
    }

    function openComplianceCalendar() {
        openModal('complianceCalendarModal');
        const container = document.getElementById('calendar-events-container');
        
        // Fetch events for current month (or any range, default uses current month in controller)
        fetch('<?= url('/retention/compliance-calendar') ?>')
            .then(res => res.json())
            .then(data => {
                if (!data.events || data.events.length === 0) {
                    container.innerHTML = '<div class="text-center text-ink/50 py-8 text-sm">No compliance events scheduled for this period.</div>';
                    return;
                }

                let html = '<div class="grid gap-3">';
                data.events.forEach(ev => {
                    let color = 'border-gray-200 bg-white';
                    let icon = '';
                    if (ev.event_type === 'disposal') {
                        color = 'border-brand-200 bg-brand-50/50';
                        icon = '<svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';
                    } else if (ev.event_type === 'legal_hold') {
                        color = 'border-red-200 bg-red-50/50';
                        icon = '<svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>';
                    } else if (ev.event_type === 'legal_hold_release') {
                        color = 'border-green-200 bg-green-50/50';
                        icon = '<svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>';
                    }
                    
                    const eventDate = new Date(ev.event_date).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});

                    html += `
                        <div class="flex items-center gap-4 p-4 rounded-lg border ${color}">
                            <div class="flex-shrink-0 bg-white p-2 rounded-full shadow-sm">
                                ${icon}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-ink">${ev.title}</p>
                                <p class="text-xs text-ink/60 mt-0.5 capitalize">${ev.event_type.replace(/_/g, ' ')}</p>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <span class="text-sm font-bold text-ink/70">${eventDate}</span>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            });
    }
</script>

@endsection
