@extends('layouts.app')

@section('content')
<?php
/** @var array $logs */
/** @var array $filters */
/** @var array $modules */
/** @var array $actions */
/** @var int $total */

$actionStyles = [
    'login'          => 'bg-brand-50 text-brand-700 border-brand-200',
    'logout'         => 'bg-ink/5 text-ink/60 border-ink/10',
    'create'         => 'bg-brand-50 text-brand-700 border-brand-200',
    'update'         => 'bg-accent-50 text-accent-700 border-accent-200',
    'approve'        => 'bg-brand-50 text-brand-700 border-brand-200',
    'reject'         => 'bg-accent-50 text-accent-700 border-accent-200',
    'check_in'       => 'bg-brand-50 text-brand-700 border-brand-200',
    'check_out'      => 'bg-gray-100 text-gray-700 border-gray-200',
    'suspend'        => 'bg-accent-50 text-accent-700 border-accent-200',
    'reactivate'     => 'bg-brand-50 text-brand-700 border-brand-200',
    'upload'         => 'bg-brand-50 text-brand-700 border-brand-200',
    'version'        => 'bg-brand-50 text-brand-700 border-brand-200',
    'access_update'  => 'bg-accent-50 text-accent-700 border-accent-200',
    'watchlist'      => 'bg-accent-50 text-accent-700 border-accent-200',
    'dispose'        => 'bg-accent-50 text-accent-700 border-accent-200',
    'amend'          => 'bg-accent-50 text-accent-700 border-accent-200',
];

if (!function_exists('audit_pretty_json')) {
    function audit_pretty_json(?string $json): string
    {
        if ($json === null || $json === '') {
            return '—';
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return htmlspecialchars($json);
        }
        if ($decoded === []) {
            return '—';
        }
        $lines = [];
        foreach ($decoded as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $value = 'null';
            }
            $lines[] = htmlspecialchars((string) $key) . ': ' . htmlspecialchars((string) $value);
        }
        return implode("\n", $lines);
    }
}
?>
<div class="animate-fade-in-up space-y-8">
    <?php
    render_page_header([
        'title' => 'Audit Log',
        'subtitle' => 'History of actions taken across the system.',
        'badge' => '<span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-ink/5 border border-ink/10 text-ink/60 text-[11px] font-bold">'
            . number_format($total) . ' entries</span>',
    ]);
    ?>

    <?php
    $pageApproves = 0;
    $pageRejects = 0;
    $pageModules = [];
    $actionCounts = [];
    $moduleCounts = [];
    foreach ($logs as $log) {
        $act = $log['action'] ?? '';
        if ($act === 'approve') $pageApproves++;
        if ($act === 'reject') $pageRejects++;
        $mod = $log['module'] ?? '';
        if ($mod !== '') $pageModules[$mod] = true;
        $actionCounts[$act] = ($actionCounts[$act] ?? 0) + 1;
        if ($mod !== '') $moduleCounts[$mod] = ($moduleCounts[$mod] ?? 0) + 1;
    }
    arsort($actionCounts);
    arsort($moduleCounts);
    $criticalActions = ['suspend', 'dispose', 'reject', 'watchlist', 'access_update'];
    $recentCritical = array_values(array_filter($logs, static fn($log) => in_array($log['action'] ?? '', $criticalActions, true)));
    $recentCritical = array_slice($recentCritical, 0, 5);
    ?>
    @include('partials.stat_kpi_grid', ['cards' => [
        ['label' => 'Total entries', 'value' => number_format($total), 'hint' => 'Matching current filters', 'tone' => 'navy', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['label' => 'This page', 'value' => count($logs), 'hint' => 'Rows shown below', 'tone' => 'navy', 'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16'],
        ['label' => 'Approvals (page)', 'value' => $pageApproves, 'hint' => 'Approve actions visible', 'tone' => 'peach', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Modules (page)', 'value' => count($pageModules), 'hint' => 'Distinct sources', 'tone' => 'slate', 'icon' => 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z'],
    ]])

    <div class="module-info-grid">
        @include('partials.module_feed_list', [
            'title' => 'Actions on this page',
            'subtitle' => 'Breakdown of logged activity in current results',
            'items' => array_map(static fn($act, $count) => [
                'label' => ucwords(str_replace('_', ' ', $act)),
                'aside' => (string) $count,
            ], array_keys(array_slice($actionCounts, 0, 6, true)), array_values(array_slice($actionCounts, 0, 6, true))),
            'empty' => 'No actions logged yet.',
        ])
        @include('partials.module_feed_list', [
            'title' => 'Recent sensitive actions',
            'subtitle' => 'Suspensions, disposals, rejections, and access changes',
            'items' => array_map(static fn($log) => [
                'label' => ucwords(str_replace('_', ' ', $log['action'] ?? '')),
                'meta'  => ($log['actor_name'] ?? 'System') . ' · ' . ucwords(str_replace('_', ' ', $log['module'] ?? '')),
                'aside' => !empty($log['created_at']) ? date('M j, g:ia', strtotime($log['created_at'])) : '',
            ], $recentCritical),
            'empty' => 'No sensitive actions on this page.',
        ])
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
    <div class="xl:col-span-2">
    <form method="get" action="<?= url('/audit-logs') ?>" class="module-filters">
        <div class="module-filters-field is-grow">
            <label for="filter-q">Search</label>
            <input id="filter-q" type="search" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>"
                   placeholder="Actor, module, entity…">
        </div>
        <div class="module-filters-field">
            <label for="filter-module">Module</label>
            <select id="filter-module" name="module">
                <option value="">All modules</option>
                <?php foreach ($modules as $module): ?>
                    <option value="<?= htmlspecialchars($module) ?>" <?= ($filters['module'] ?? '') === $module ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $module))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="module-filters-field">
            <label for="filter-action">Action</label>
            <select id="filter-action" name="action">
                <option value="">All actions</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= htmlspecialchars($action) ?>" <?= ($filters['action'] ?? '') === $action ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $action))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="module-filters-actions">
            <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition">
                Filter
            </button>
            <?php if (($filters['q'] ?? '') || ($filters['module'] ?? '') || ($filters['action'] ?? '')): ?>
                <a href="<?= url('/audit-logs') ?>" class="module-filters-clear">Clear</a>
            <?php endif; ?>
            <span class="module-filters-count"><?= number_format($total) ?> entries</span>
        </div>
    </form>

    <?php if (empty($logs)): ?>
        <div class="module-empty">No audit entries yet. Actions across the system will appear here.</div>
    <?php else: ?>
        <div class="audit-timeline">
            <?php foreach ($logs as $log): ?>
                <?php
                    $style = $actionStyles[$log['action']] ?? 'bg-ink/5 text-ink/60 border-ink/10';
                    $detailId = 'audit-detail-' . (int) $log['id'];
                    $hasDetails = !empty($log['old_values']) || !empty($log['new_values']);
                ?>
                <article class="audit-event">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="px-2.5 py-1 rounded-md text-xs border font-semibold <?= $style ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?>
                                </span>
                                <span class="text-xs text-ink/45 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $log['module'])) ?></span>
                            </div>
                            <p class="text-sm font-semibold text-ink">
                                <?php if (!empty($log['actor_name'])): ?>
                                    <?= htmlspecialchars($log['actor_name']) ?>
                                <?php else: ?>
                                    System / unknown
                                <?php endif; ?>
                                <span class="font-normal text-ink/50">on</span>
                                <?= htmlspecialchars($log['entity_type']) ?>
                                <span class="text-ink/40 font-mono text-xs">#<?= htmlspecialchars($log['entity_id']) ?></span>
                            </p>
                            <p class="text-xs text-ink/45 mt-1.5">
                                <?= htmlspecialchars(date('M j, Y · g:i A', strtotime($log['created_at']))) ?>
                                <?php if (!empty($log['actor_email'])): ?>
                                    <span class="mx-1 text-ink/25">·</span>
                                    <?= htmlspecialchars($log['actor_email']) ?>
                                <?php endif; ?>
                                <span class="mx-1 text-ink/25">·</span>
                                <span class="font-mono"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></span>
                            </p>
                        </div>
                        <?php if ($hasDetails): ?>
                            <button type="button"
                                    onclick="openModal('<?= $detailId ?>')"
                                    class="text-xs font-bold px-3 py-2 rounded-lg bg-brand-50 text-brand-700 hover:bg-brand-100 transition shrink-0">
                                View details
                            </button>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>
    <aside class="space-y-4">
        <div class="module-insight-panel">
            <h3 class="module-insight-title">Module activity</h3>
            <p class="module-insight-sub">Sources represented on this page</p>
            <?php if (empty($moduleCounts)): ?>
                <p class="text-sm text-ink/45 py-1">No module data on this page.</p>
            <?php else: ?>
                <?php foreach (array_slice($moduleCounts, 0, 8, true) as $mod => $count): ?>
                    <div class="module-stat-row">
                        <span class="text-ink/70 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $mod)) ?></span>
                        <strong class="tabular-nums text-ink"><?= $count ?></strong>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="module-insight-panel">
            <h3 class="module-insight-title">Reading the trail</h3>
            <p class="module-insight-sub">What each entry tells you</p>
            <ul class="module-insight-list">
                <li><span class="module-insight-dot"></span><span><?= $pageApproves ?> approval<?= $pageApproves === 1 ? '' : 's' ?> · <?= $pageRejects ?> rejection<?= $pageRejects === 1 ? '' : 's' ?> on this page</span></li>
                <li><span class="module-insight-dot"></span><span>Open “View details” to compare before/after values.</span></li>
                <li><span class="module-insight-dot"></span><span>IP and actor email help trace who changed what.</span></li>
            </ul>
        </div>
    </aside>
    </div>
</div>

<?php foreach ($logs as $log): ?>
    <?php
        $detailId = 'audit-detail-' . (int) $log['id'];
        $hasDetails = !empty($log['old_values']) || !empty($log['new_values']);
        if (!$hasDetails) {
            continue;
        }
    ?>
    <div id="<?= $detailId ?>" class="modal-overlay hidden">
        <div class="modal-panel max-w-2xl w-full mx-4" role="dialog" aria-modal="true" aria-labelledby="<?= $detailId ?>-title">
            <div class="flex items-start justify-between gap-4 mb-5">
                <div>
                    <h2 id="<?= $detailId ?>-title" class="text-lg font-semibold text-ink">Audit entry #<?= (int) $log['id'] ?></h2>
                    <p class="text-sm text-ink/50 mt-1">
                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['module']))) ?>
                        · <?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?>
                        · <?= htmlspecialchars($log['entity_type']) ?> #<?= htmlspecialchars($log['entity_id']) ?>
                    </p>
                </div>
                <button type="button" onclick="closeModal('<?= $detailId ?>')" class="text-ink/40 hover:text-ink transition" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-xl border border-ink/10 bg-ink/[0.02] p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-ink/45 mb-2">Before</p>
                    <pre class="text-xs text-ink/70 whitespace-pre-wrap font-mono leading-relaxed"><?= audit_pretty_json($log['old_values'] ?? null) ?></pre>
                </div>
                <div class="rounded-xl border border-ink/10 bg-ink/[0.02] p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-ink/45 mb-2">After</p>
                    <pre class="text-xs text-ink/70 whitespace-pre-wrap font-mono leading-relaxed"><?= audit_pretty_json($log['new_values'] ?? null) ?></pre>
                </div>
            </div>
            <div class="mt-5 flex justify-end">
                <button type="button" onclick="closeModal('<?= $detailId ?>')"
                        class="px-4 py-2.5 rounded-xl border border-ink/15 text-sm font-semibold text-ink/70 hover:bg-ink/5 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
<?php endforeach; ?>

@endsection
