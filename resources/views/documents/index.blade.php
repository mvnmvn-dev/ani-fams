@extends('layouts.app')

@section('content')
<?php
/** @var array $documents */
/** @var array $categories */
/** @var bool $canUpload */
/** @var bool $canManage */

$confStyles = [
    'public'       => 'bg-brand-50 text-brand-700 border-brand-200',
    'internal'     => 'bg-brand-50 text-brand-700 border-brand-200',
    'confidential' => 'bg-accent-50 text-accent-700 border-accent-200',
    'restricted'   => 'bg-accent-50 text-accent-700 border-accent-200',
];
$categoryOptions = [];
$confCounts = ['public' => 0, 'internal' => 0, 'confidential' => 0, 'restricted' => 0];
$versioned = 0;
foreach ($documents as $doc) {
    $cat = $doc['category_name'] ?: 'Uncategorized';
    $categoryOptions[$cat] = $cat;
    $c = $doc['confidentiality'] ?? 'internal';
    if (isset($confCounts[$c])) $confCounts[$c]++;
    if ((int) ($doc['version_no'] ?? 1) > 1) $versioned++;
}
ksort($categoryOptions);
$totalDocs = count($documents);
$sensitive = $confCounts['confidential'] + $confCounts['restricted'];
$recentDocs = $documents;
usort($recentDocs, static fn($a, $b) => strtotime($b['created_at'] ?? '0') <=> strtotime($a['created_at'] ?? '0'));
$recentDocs = array_slice($recentDocs, 0, 5);
$categoryCounts = [];
foreach ($documents as $doc) {
    $cat = $doc['category_name'] ?: 'Uncategorized';
    $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
}
arsort($categoryCounts);
$recentFeed = array_map(static fn($d) => [
    'label' => $d['title'],
    'meta'  => ($d['document_number'] ?? '') . ' · ' . ($d['owner_name'] ?? 'Unknown owner'),
    'href'  => url('/documents/' . $d['id']),
    'modal' => true,
    'aside' => !empty($d['created_at']) ? date('M j, Y', strtotime($d['created_at'])) : '',
    'badge' => $d['confidentiality'] ?? null,
], $recentDocs);
?>
<div class="animate-fade-in-up space-y-8">
    <?php
    render_page_header([
        'title' => 'Documents',
        'subtitle' => 'Corporate document library, including completed and expired contracts retained in the archive.',
        'actions' => function () use ($canUpload, $canManage) {
            ?>
            <a href="<?= route('documents.export') ?>" download data-export-link class="btn-secondary mr-2 inline-flex items-center gap-1.5" style="text-decoration: none; display: inline-flex; align-items: center; padding: 0.5rem 1rem; border-radius: 0.75rem;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export CSV
            </a>
            <a href="<?= route('documents.export.pdf') ?>" download data-export-link class="btn-secondary mr-2 inline-flex items-center gap-1.5" style="text-decoration: none; display: inline-flex; align-items: center; padding: 0.5rem 1rem; border-radius: 0.75rem;">
                Export PDF
            </a>
            <?php if ($canManage): ?>
                <button type="button" onclick="processDocumentsOcr(this)" class="btn-secondary mr-2 inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Process OCR
                </button>
            <?php endif; ?>
            <?php
            if ($canUpload) {
                ?>
                <button onclick="openModal('uploadModal')" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Upload Document
                </button>
                <?php
            }
        },
    ]);
    ?>

    <form method="GET" action="<?= url('/documents') ?>" class="module-insight-panel flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[220px] space-y-1">
            <label class="block text-xs font-bold uppercase tracking-wider text-ink/50">Full-text search</label>
            <input type="search" name="q" value="<?= htmlspecialchars($contentQuery ?? '') ?>"
                   placeholder="Search inside document content, titles…"
                   class="w-full rounded-xl border border-ink/15 px-4 py-2.5 text-sm bg-sand/10">
        </div>
        <button type="submit" class="btn-primary">Search content</button>
        <?php if (!empty($contentQuery)): ?>
            <a href="<?= url('/documents') ?>" class="btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (!empty($contentHits)): ?>
    <div class="module-insight-panel">
        <h3 class="module-insight-title">Content matches for “<?= htmlspecialchars($contentQuery) ?>”</h3>
        <p class="module-insight-sub"><?= count($contentHits) ?> result<?= count($contentHits) === 1 ? '' : 's' ?></p>
        <?php foreach ($contentHits as $hit): ?>
            <a href="<?= url('/documents/' . $hit['id']) ?>" data-modal-link class="module-stat-row block hover:bg-sand/40 rounded-lg px-1">
                <span class="text-ink/80 font-medium"><?= htmlspecialchars($hit['title'] ?? '') ?></span>
                <span class="text-xs text-ink/45"><?= htmlspecialchars(mb_strimwidth((string) ($hit['content_snippet'] ?? $hit['description'] ?? ''), 0, 80, '…')) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php elseif (!empty($contentQuery)): ?>
    <div class="module-empty">No documents matched that content search.</div>
    <?php endif; ?>

    <section class="rounded-2xl border border-brand-200 bg-brand-50/30 p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-brand-700 text-white text-xs font-bold">AI</span>
            <div><h2 class="section-title">ARNEL document intelligence</h2><p class="type-meta">OCR text extraction, classification, metadata, summaries, keyword tags, and duplicate-ready checks run at upload.</p></div>
        </div>
        <?php $aiDocs = array_values(array_filter($documents, static fn ($doc) => !empty($doc['ai_classification']) || !empty($doc['ai_summary']))); ?>
        <?php if (!empty($aiDocs)): ?>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <?php foreach (array_slice($aiDocs, 0, 4) as $aiDoc): ?>
                    <div class="rounded-xl bg-white/75 p-3">
                        <div class="flex items-center justify-between gap-3"><strong class="truncate text-sm text-ink"><?= htmlspecialchars($aiDoc['title'] ?? '') ?></strong><span class="shrink-0 rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-semibold text-brand-700"><?= htmlspecialchars($aiDoc['ai_classification'] ?? 'Analyzed') ?></span></div>
                        <?php $aiKeywords = json_decode((string) ($aiDoc['ai_keywords'] ?? '[]'), true) ?: []; ?>
                        <p class="mt-1 text-xs leading-5 text-ink/60"><?= htmlspecialchars(mb_strimwidth((string) ($aiDoc['ai_summary'] ?? ''), 0, 170, '…')) ?></p>
                        <?php if ($aiKeywords): ?><p class="mt-2 text-[10px] uppercase tracking-wide text-ink/45"><?= htmlspecialchars(implode(' · ', array_slice($aiKeywords, 0, 5))) ?></p><?php endif; ?>
                        <div class="mt-3 flex items-center gap-2 text-[11px]">
                            <button type="button" class="font-semibold text-brand-700 hover:text-brand-900" onclick="arnelReanalyze(<?= (int) $aiDoc['id'] ?>, this)">Re-analyze</button>
                            <span class="text-ink/35">·</span>
                            <?php if ($canManage): ?>
                                <button type="button" class="text-ink/55 hover:text-ink" onclick="arnelClassificationFeedback(<?= (int) $aiDoc['id'] ?>, true)">Classification correct</button>
                                <button type="button" class="text-ink/55 hover:text-ink" onclick="openClassificationCorrectionModal(<?= (int) $aiDoc['id'] ?>)">Not correct</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
    <?php else: ?><p class="mt-4 text-sm text-ink/50">AI analysis will appear here after the first document upload.</p><?php endif; ?>
    </section>

    <?php if ($canManage): ?>
    <div id="classificationCorrectionModal" class="modal-overlay hidden" hidden role="dialog" aria-modal="true" aria-labelledby="classificationCorrectionTitle" onclick="if (event.target === this) closeModal('classificationCorrectionModal')">
        <div class="modal-panel w-full max-w-md mx-4 overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-2xl" onclick="event.stopPropagation()">
            <div class="flex items-start justify-between border-b border-ink/10 bg-gray-50/70 px-6 py-5">
                <div>
                    <div class="mb-1 flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-brand-700 text-xs font-bold text-white">AI</span>
                        <h2 id="classificationCorrectionTitle" class="text-lg font-bold text-ink">Correct classification</h2>
                    </div>
                    <p class="text-sm leading-5 text-ink/60">Tell ARNEL the classification this document should use.</p>
                </div>
                <button type="button" onclick="closeModal('classificationCorrectionModal')" class="text-ink/40 transition hover:text-ink" aria-label="Close correction modal">&times;</button>
            </div>
            <form id="classificationCorrectionForm" class="space-y-5 px-6 py-5" onsubmit="submitClassificationCorrection(event)">
                <input type="hidden" id="classificationCorrectionDocumentId" value="">
                <div class="space-y-2">
                    <label for="correctedClassification" class="block text-xs font-bold uppercase tracking-wider text-ink/60">Correct classification</label>
                    <input id="correctedClassification" name="corrected_classification" type="text" maxlength="150" required autocomplete="off" placeholder="e.g. Contract, Invoice, Memorandum" class="w-full rounded-xl border border-ink/15 bg-sand/10 px-4 py-3 text-sm text-ink outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    <p class="text-xs text-ink/50">This correction will be recorded for audit and applied to the document.</p>
                </div>
                <div id="classificationCorrectionError" class="hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"></div>
                <div class="flex justify-end gap-3 border-t border-ink/10 pt-4">
                    <button type="button" onclick="closeModal('classificationCorrectionModal')" class="rounded-xl border border-ink/10 px-4 py-2.5 text-sm font-semibold text-ink/70 transition hover:bg-ink/5">Cancel</button>
                    <button id="classificationCorrectionSubmit" type="submit" class="btn-primary">Save correction</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        (function () {
            var pendingKey = 'fams_documents_toast';

            function ensureToastHost() {
                var host = document.getElementById('toast-host');
                if (host) return host;
                host = document.createElement('div');
                host.id = 'toast-host';
                host.className = 'toast-host';
                host.setAttribute('aria-live', 'polite');
                document.body.appendChild(host);
                return host;
            }

            function renderToast(type, message, options) {
                options = options || {};
                var kind = type === 'error' ? 'error' : 'success';
                var title = options.title || (kind === 'success' ? 'Success' : 'Something went wrong');
                var duration = typeof options.duration === 'number' ? options.duration : (kind === 'error' ? 5500 : 4200);
                var host = ensureToastHost();
                var toast = document.createElement('div');
                toast.className = 'toast toast-' + kind;
                toast.setAttribute('role', kind === 'error' ? 'alert' : 'status');
                toast.innerHTML =
                    '<span class="toast-icon" aria-hidden="true">' +
                        '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            (kind === 'success'
                                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>'
                                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>') +
                        '</svg>' +
                    '</span>' +
                    '<div class="toast-body">' +
                        '<p class="toast-title"></p>' +
                        '<p class="toast-message"></p>' +
                    '</div>' +
                    '<button type="button" class="toast-close" aria-label="Dismiss">' +
                        '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>' +
                        '</svg>' +
                    '</button>';
                toast.querySelector('.toast-title').textContent = title;
                toast.querySelector('.toast-message').textContent = String(message || '');
                toast.querySelector('.toast-close').addEventListener('click', function () {
                    toast.classList.remove('is-visible');
                    toast.classList.add('is-leaving');
                    window.setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 280);
                });
                host.appendChild(toast);
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () { toast.classList.add('is-visible'); });
                });
                if (duration > 0) {
                    window.setTimeout(function () {
                        toast.classList.remove('is-visible');
                        toast.classList.add('is-leaving');
                        window.setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 280);
                    }, duration);
                }
            }

            window.notifyUser = function (type, message, options) {
                if (typeof window.showToast === 'function') {
                    window.showToast(type, message, options || {});
                    return;
                }
                renderToast(type, message, options);
            };

            try {
                var pending = sessionStorage.getItem(pendingKey);
                if (pending) {
                    sessionStorage.removeItem(pendingKey);
                    var payload = JSON.parse(pending);
                    window.setTimeout(function () {
                        window.notifyUser(payload.type || 'success', payload.message || '');
                    }, 120);
                }
            } catch (e) {}

            window.processDocumentsOcr = async function (button) {
                if (button) button.disabled = true;
                try {
                    const response = await fetch('<?= route('documents.ocr.process') ?>', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '<?= csrf_token() ?>', 'Accept': 'application/json' },
                    });
                    const data = await response.json();
                    window.notifyUser(
                        data.success ? 'success' : 'error',
                        data.success ? ('Processed ' + (data.processed_count ?? 0) + ' documents for OCR.') : (data.message || 'Failed to process OCR.')
                    );
                } catch (error) {
                    window.notifyUser('error', 'Failed to process OCR.');
                } finally {
                    if (button) button.disabled = false;
                }
            };

            window.arnelReanalyze = async function (id, button) {
                button.disabled = true;
                button.textContent = 'Analyzing…';
                try {
                    const response = await fetch('<?= route('api.arnel.documents.analyze') ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '<?= csrf_token() ?>',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ document_id: id }),
                    });
                    const data = await response.json();
                    if (data.success) {
                        try {
                            sessionStorage.setItem(pendingKey, JSON.stringify({
                                type: 'success',
                                message: 'Document re-analyzed successfully.',
                            }));
                        } catch (e) {}
                        window.notifyUser('success', 'Document re-analyzed successfully.');
                        window.setTimeout(function () { window.location.reload(); }, 700);
                        return;
                    }
                    window.notifyUser('error', data.message || 'Unable to analyze document.');
                } catch (error) {
                    window.notifyUser('error', 'Unable to analyze document.');
                }
                button.disabled = false;
                button.textContent = 'Re-analyze';
            };

            window.openClassificationCorrectionModal = function (id) {
                document.getElementById('classificationCorrectionDocumentId').value = id;
                document.getElementById('correctedClassification').value = '';
                document.getElementById('classificationCorrectionError').classList.add('hidden');
                openModal('classificationCorrectionModal');
                setTimeout(function () { document.getElementById('correctedClassification').focus(); }, 50);
            };

            window.arnelClassificationFeedback = async function (id, correct, corrected) {
                corrected = corrected || null;
                const response = await fetch('<?= url('/api/arnel/documents') ?>/' + id + '/classification-feedback', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?= csrf_token() ?>',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ correct: correct, corrected_classification: corrected }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to record feedback.');
                return data;
            };

            window.submitClassificationCorrection = async function (event) {
                event.preventDefault();
                const documentId = document.getElementById('classificationCorrectionDocumentId').value;
                const corrected = document.getElementById('correctedClassification').value.trim();
                const submit = document.getElementById('classificationCorrectionSubmit');
                const error = document.getElementById('classificationCorrectionError');
                submit.disabled = true;
                submit.textContent = 'Saving…';
                error.classList.add('hidden');
                try {
                    await window.arnelClassificationFeedback(documentId, false, corrected);
                    closeModal('classificationCorrectionModal');
                    try {
                        sessionStorage.setItem(pendingKey, JSON.stringify({
                            type: 'success',
                            message: 'Classification correction saved.',
                        }));
                    } catch (e) {}
                    window.notifyUser('success', 'Classification correction saved.');
                    window.setTimeout(function () { window.location.reload(); }, 700);
                } catch (exception) {
                    error.textContent = exception.message;
                    error.classList.remove('hidden');
                    window.notifyUser('error', exception.message || 'Unable to save correction.');
                } finally {
                    submit.disabled = false;
                    submit.textContent = 'Save correction';
                }
            };
        })();
    </script>

    @include('partials.stat_kpi_grid', ['cards' => [
        ['label' => 'Total archive', 'value' => $totalDocs, 'hint' => 'Active records in library', 'tone' => 'navy', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['label' => 'Sensitive files', 'value' => $sensitive, 'hint' => 'Confidential + restricted', 'tone' => 'terracotta', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
        ['label' => 'Versioned', 'value' => $versioned, 'hint' => 'Documents past v1', 'tone' => 'peach', 'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
        ['label' => 'Categories', 'value' => count($categoryOptions), 'hint' => 'Classification groups', 'tone' => 'slate', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
    ]])

    <div class="module-info-grid">
        @include('partials.module_feed_list', [
            'title' => 'Recently uploaded',
            'subtitle' => 'Latest files added to the corporate archive',
            'items' => $recentFeed,
            'empty' => 'No documents uploaded yet.',
        ])
        <div class="module-insight-panel">
            <h3 class="module-insight-title">Archive by category</h3>
            <p class="module-insight-sub">How records are grouped across the library</p>
            <?php if (empty($categoryCounts)): ?>
                <p class="text-sm text-ink/45 py-1">No categories assigned yet.</p>
            <?php else: ?>
                <?php foreach (array_slice($categoryCounts, 0, 8, true) as $cat => $count): ?>
                    <div class="module-stat-row">
                        <span class="text-ink/70"><?= htmlspecialchars($cat) ?></span>
                        <strong class="tabular-nums text-ink"><?= $count ?></strong>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($documents)): ?>
        <div class="module-empty">No documents found.</div>
    <?php else: ?>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <div class="xl:col-span-2 space-y-6" data-filter-root>
                @include('partials.module_filters', ['fields' => [
                    ['name' => 'q', 'label' => 'Search', 'placeholder' => 'Title, number, owner…'],
                    ['name' => 'category', 'label' => 'Category', 'allLabel' => 'All categories', 'options' => $categoryOptions],
                    ['name' => 'confidentiality', 'label' => 'Confidentiality', 'allLabel' => 'All levels', 'options' => [
                        'public' => 'Public',
                        'internal' => 'Internal',
                        'confidential' => 'Confidential',
                        'restricted' => 'Restricted',
                    ]],
                    ['name' => 'status', 'label' => 'Archive status', 'allLabel' => 'All records', 'options' => [
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ]],
                ]])
                <div class="module-empty" data-filter-empty hidden>No documents match these filters.</div>

                <div class="module-table-wrap">
                    <div class="overflow-x-auto">
                        <table class="module-table">
                            <thead>
                                <tr>
                                    <th>Document No.</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Owner</th>
                                    <th>Version</th>
                                    <th>Status</th>
                                    <th>Confidentiality</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documents as $doc): ?>
                                    <?php $conf = $doc['confidentiality'] ?? 'internal'; ?>
                                    <tr data-filter-item
                                        data-filter-category="<?= htmlspecialchars($doc['category_name'] ?: 'Uncategorized') ?>"
                                        data-filter-confidentiality="<?= htmlspecialchars($conf) ?>"
                                        data-filter-status="<?= htmlspecialchars($doc['status'] ?? 'active') ?>"
                                        data-filter-search="<?= htmlspecialchars(strtolower($doc['title'] . ' ' . $doc['document_number'] . ' ' . $doc['owner_name'] . ' ' . ($doc['category_name'] ?? ''))) ?>">
                                        <td class="font-mono text-xs text-ink/60 whitespace-nowrap"><?= htmlspecialchars($doc['document_number']) ?></td>
                                        <td>
                                            <a href="<?= url('/documents/' . $doc['id']) ?>" data-record-modal class="font-semibold text-ink hover:text-brand-700 transition">
                                                <?= htmlspecialchars($doc['title']) ?>
                                            </a>
                                        </td>
                                        <td class="text-ink/70"><?= htmlspecialchars($doc['category_name'] ?: 'Uncategorized') ?></td>
                                        <td class="text-ink/70"><?= htmlspecialchars($doc['owner_name']) ?></td>
                                        <td class="text-ink/60">v<?= htmlspecialchars((string) ($doc['version_no'] ?: '1')) ?></td>
                                        <td>
                                            <span class="inline-block px-2 py-0.5 text-[11px] border rounded capitalize <?= ($doc['status'] ?? 'active') === 'archived' ? 'bg-gray-100 text-gray-700 border-gray-200' : 'bg-brand-50 text-brand-700 border-brand-200' ?>"><?= htmlspecialchars($doc['status'] ?? 'active') ?></span>
                                            <?php if (!empty($doc['archive_reason'])): ?><p class="mt-1 max-w-40 text-[10px] text-ink/50"><?= htmlspecialchars($doc['archive_reason']) ?></p><?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="inline-block px-2 py-0.5 text-[11px] border rounded capitalize <?= $confStyles[$conf] ?? 'bg-gray-50 border-gray-200' ?>">
                                                <?= htmlspecialchars($conf) ?>
                                            </span>
                                        </td>
                                        <td class="text-right whitespace-nowrap">
                                            <a href="<?= url('/documents/' . $doc['id']) ?>" data-record-modal class="text-xs font-semibold text-brand-700 hover:text-brand-900 mr-2">View</a>
                                            <?php if (!empty($doc['file_path'])): ?>
                                                <a href="<?= htmlspecialchars($doc['file_path']) ?>" download target="_blank" class="text-xs font-semibold text-ink/60 hover:text-ink">Download</a>
                                            <?php endif; ?>
                                            <?php if ($canManage && ($doc['status'] ?? '') === 'archived'): ?>
                                                <form action="<?= url('/documents/' . $doc['id'] . '/restore-archive') ?>" method="POST" class="inline-block ml-2">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold text-brand-700 hover:text-brand-900">Restore</button>
                                                </form>
                                            <?php elseif ($canManage && ($doc['status'] ?? '') !== 'disposed'): ?>
                                                <form action="<?= url('/documents/' . $doc['id'] . '/archive') ?>" method="POST" class="mt-2 flex items-center justify-end gap-1">
                                                    @csrf
                                                    <select name="archive_reason" aria-label="Archive reason" class="max-w-36 rounded border border-ink/15 px-1 py-1 text-[10px]">
                                                        <option value="Archived">Other archive</option>
                                                        <option value="Completed contract">Completed contract</option>
                                                        <option value="Expired contract">Expired contract</option>
                                                        <option value="Superseded">Superseded</option>
                                                    </select>
                                                    <button type="submit" class="text-xs font-semibold text-ink/65 hover:text-ink">Archive</button>
                                                </form>
                                            <?php endif; ?>
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
                    <h3 class="module-insight-title">Confidentiality mix</h3>
                    <p class="module-insight-sub">Distribution across the archive</p>
                    <ul class="module-insight-list">
                        <?php foreach ($confCounts as $level => $count): ?>
                            <li>
                                <span class="module-insight-dot"></span>
                                <span class="flex-1 capitalize"><?= htmlspecialchars($level) ?></span>
                                <strong class="tabular-nums text-ink"><?= $count ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="module-insight-panel">
                    <h3 class="module-insight-title">Compliance tips</h3>
                    <p class="module-insight-sub">Keep the archive audit-ready</p>
                    <ul class="module-insight-list">
                        <li><span class="module-insight-dot"></span><span>Review restricted documents quarterly for access rights.</span></li>
                        <li><span class="module-insight-dot"></span><span>Upload a new version instead of replacing files outside the system.</span></li>
                        <li><span class="module-insight-dot"></span><span>Link retention policies on records due for disposal.</span></li>
                    </ul>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php if ($canUpload): ?>
<div id="uploadModal" class="modal-overlay hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-xl w-full border border-ink/10 overflow-hidden" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="text-lg font-semibold text-ink">Upload Document</h3>
            <button onclick="closeModal('uploadModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/documents') ?>" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Document Title</label>
                <input type="text" name="title" required placeholder="e.g. Q3 Audited Financials" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Category</label>
                    <select name="category_id" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                        <option value="">— Uncategorized —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-ink/65">Confidentiality Level</label>
                    <select name="confidentiality" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                        <option value="public">Public</option>
                        <option value="internal" selected>Internal</option>
                        <option value="confidential">Confidential</option>
                        <option value="restricted">Restricted</option>
                    </select>
                </div>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Tags (comma-separated)</label>
                <input type="text" name="tags" placeholder="e.g. tax, invoice, audit" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">File Attachment</label>
                <input type="file" name="document_file" required class="w-full text-sm file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-ink/65">Description</label>
                <textarea name="description" rows="3" class="w-full border border-ink/15 rounded-md p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none"></textarea>
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('uploadModal')" class="px-4 py-2 rounded-md border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-md bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm">Save Document</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

@if(!empty($openRecordId))
<div id="open-record-bootstrap" hidden data-url="<?= url('/documents/' . $openRecordId) ?>?modal=1"></div>
@endif

@endsection
