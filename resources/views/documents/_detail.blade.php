<?php
/** @var array $doc */
/** @var array $roles */
/** @var bool $canManage */
/** @var bool $canUpload */
/** @var bool $asModal */

$confStyles = [
    'public'       => 'bg-brand-50 text-brand-700 border-brand-200',
    'internal'     => 'bg-brand-50 text-brand-700 border-brand-200',
    'confidential' => 'bg-accent-50 text-accent-700 border-accent-200 font-semibold',
    'restricted'   => 'bg-accent-50 text-accent-700 border-accent-200 font-semibold',
];
$confClass = $confStyles[$doc['confidentiality']] ?? 'bg-gray-50 border-gray-200 text-ink/70';
$asModal = !empty($asModal);
$versionCount = count($doc['versions'] ?? []);
$latestVersion = !empty($doc['versions']) ? $doc['versions'][0] : null;
$accessRoleCount = count($doc['access_list'] ?? []);
$isSensitive = in_array($doc['confidentiality'] ?? '', ['confidential', 'restricted'], true);
?>

<div class="space-y-6" data-record-title="<?= htmlspecialchars($doc['title'], ENT_QUOTES) ?>">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-sans text-xl font-bold text-ink"><?= htmlspecialchars($doc['title']) ?></h2>
                <span class="inline-block px-2.5 py-0.5 text-[11px] font-bold border rounded-full capitalize <?= $confClass ?>">
                    <?= htmlspecialchars($doc['confidentiality']) ?>
                </span>
            </div>
            <p class="text-xs text-ink/50 font-mono mt-1"><?= htmlspecialchars($doc['document_number']) ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="window.print()" class="btn-secondary btn-sm inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h8z"/>
                </svg>
                Print / Save PDF
            </button>
            <?php if ($canManage): ?>
                <button type="button" onclick="openModal('accessModal')" class="btn-secondary btn-sm">
                    Permissions
                </button>
                <button type="button" onclick="openModal('signatureModal')" class="btn-secondary btn-sm">
                    Request Signature
                </button>
            <?php endif; ?>
            <?php if ($canUpload): ?>
                <button type="button" onclick="openModal('versionModal')" class="btn-primary btn-sm">
                    + Upload New Version
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="module-info-grid">
        <div class="module-insight-panel">
            <h3 class="module-insight-title">Record summary</h3>
            <p class="module-insight-sub">Key facts about this document</p>
            <div class="module-stat-row">
                <span class="text-ink/70">Versions on file</span>
                <strong class="tabular-nums text-ink"><?= $versionCount ?></strong>
            </div>
            <div class="module-stat-row">
                <span class="text-ink/70">Roles with access</span>
                <strong class="tabular-nums text-ink"><?= $accessRoleCount ?></strong>
            </div>
            <div class="module-stat-row">
                <span class="text-ink/70">Classification</span>
                <strong class="capitalize text-ink"><?= htmlspecialchars($doc['confidentiality']) ?></strong>
            </div>
            <?php if ($latestVersion): ?>
                <p class="text-xs text-ink/45 mt-3 pt-3 border-t border-ink/5">
                    Latest v<?= htmlspecialchars($latestVersion['version_no']) ?> uploaded <?= date('M j, Y', strtotime($latestVersion['uploaded_at'])) ?>
                    by <?= htmlspecialchars($latestVersion['uploaded_by_name']) ?>
                </p>
            <?php endif; ?>
        </div>
        <?php if ($isSensitive): ?>
        <div class="module-insight-panel">
            <h3 class="module-insight-title">Handling notice</h3>
            <p class="module-insight-sub">This record requires restricted handling</p>
            <ul class="module-insight-list">
                <li><span class="module-insight-dot"></span><span>Marked <?= htmlspecialchars($doc['confidentiality']) ?> — limit sharing outside approved roles.</span></li>
                <li><span class="module-insight-dot"></span><span>Review access permissions before distributing copies.</span></li>
                <li><span class="module-insight-dot"></span><span>Check retention schedule before archival or disposal.</span></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white border border-ink/10 rounded-2xl p-6 shadow-sm space-y-4">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-ink/40">Record details</p>
                <p class="text-sm text-ink/60 mt-1">Status: <span class="capitalize font-semibold text-ink/80"><?= htmlspecialchars($doc['status']) ?></span></p>
            </div>
        </div>

        <hr class="border-ink/5">

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Category</p>
                <p class="font-semibold text-ink mt-1"><?= htmlspecialchars($doc['category_name'] ?: 'None') ?></p>
            </div>
            <div>
                <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Uploaded By</p>
                <p class="font-semibold text-ink mt-1"><?= htmlspecialchars($doc['owner_name']) ?></p>
            </div>
            <div>
                <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Current Version</p>
                <p class="font-semibold text-ink mt-1">v<?= htmlspecialchars($doc['version_no'] ?: '1') ?></p>
            </div>
            <div>
                <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Uploaded At</p>
                <p class="font-semibold text-ink mt-1"><?= date('M d, Y', strtotime($doc['created_at'])) ?></p>
            </div>
        </div>

        <hr class="border-ink/5">

        <div>
            <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider mb-1">Description</p>
            <p class="text-ink/70 text-sm leading-relaxed whitespace-pre-line"><?= htmlspecialchars($doc['description'] ?: 'No description provided.') ?></p>
        </div>

        <?php if (!empty($doc['file_path'])): ?>
            <div class="pt-2 flex flex-wrap gap-2">
                <a href="<?= route('documents.preview', $doc['id']) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-brand-600 text-white font-semibold text-sm hover:bg-brand-700 transition">
                    Preview
                </a>
                <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-ink/15 text-ink font-semibold text-sm hover:bg-gray-50 transition">
                    Open Current File
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (count($doc['versions'] ?? []) >= 2): ?>
    <div class="bg-white border border-ink/10 rounded-xl p-5 shadow-sm">
        <h3 class="font-sans text-lg font-bold text-ink mb-3">Compare versions</h3>
        <form action="<?= route('documents.compare', $doc['id']) ?>" method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-ink/60 mb-1">Version A</label>
                <select name="v1" required class="border border-ink/15 rounded-lg p-2 text-sm bg-sand/10">
                    <?php foreach ($doc['versions'] as $ver): ?>
                        <option value="<?= (int) $ver['version_no'] ?>">v<?= (int) $ver['version_no'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink/60 mb-1">Version B</label>
                <select name="v2" required class="border border-ink/15 rounded-lg p-2 text-sm bg-sand/10">
                    <?php foreach ($doc['versions'] as $i => $ver): ?>
                        <option value="<?= (int) $ver['version_no'] ?>" <?= $i === 1 ? 'selected' : '' ?>>v<?= (int) $ver['version_no'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-secondary btn-sm">Compare</button>
        </form>
    </div>
    <?php endif; ?>

    <div id="versions" class="bg-white border border-ink/10 rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-4 border-b border-ink/10 bg-gray-50/50">
            <h3 class="font-sans text-lg font-bold text-ink">Version History</h3>
        </div>
        <div class="divide-y divide-ink/10">
            <?php foreach ($doc['versions'] as $ver): ?>
                <div class="p-5 flex justify-between items-start hover:bg-gray-50/30 transition-colors gap-4">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-ink text-sm">Version <?= $ver['version_no'] ?></span>
                            <span class="text-xs text-ink/40">Uploaded by <?= htmlspecialchars($ver['uploaded_by_name']) ?></span>
                        </div>
                        <p class="text-xs text-ink/50 mt-1 font-mono break-all">SHA-256: <?= htmlspecialchars($ver['checksum'] ?: 'Unknown') ?></p>
                        <p class="text-sm text-ink/70 mt-2 font-medium">Notes: <?= htmlspecialchars($ver['change_notes'] ?: 'No notes.') ?></p>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-xs text-ink/40 block font-semibold"><?= date('M d, Y h:i A', strtotime($ver['uploaded_at'])) ?></span>
                        <?php if (!empty($ver['file_path'])): ?>
                            <a href="<?= htmlspecialchars($ver['file_path']) ?>" download class="inline-block text-xs font-bold text-brand-650 hover:text-brand-850 underline mt-2.5" target="_blank">
                                Download File
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<div id="accessModal" class="modal-overlay hidden" hidden>
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-lg font-bold text-ink">Access Permissions</h3>
            <button type="button" onclick="closeModal('accessModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/documents/' . $doc['id'] . '/access') ?>" method="POST" class="p-6 space-y-4">
            @csrf
            <p class="text-xs text-ink/50">Configure access level by role. Inherits confidentiality level by default.</p>
            <div class="space-y-3">
                <?php foreach ($roles as $role): ?>
                    <?php
                    $currentAccess = '';
                    foreach ($doc['access_list'] as $access) {
                        if ((int) ($access['role_id'] ?? 0) === (int) $role['id']) {
                            $currentAccess = $access['access_level'];
                        }
                    }
                    ?>
                    <div class="flex items-center justify-between text-sm gap-3">
                        <span class="font-medium text-ink/80"><?= htmlspecialchars($role['name']) ?></span>
                        <select name="role_access[<?= $role['id'] ?>]" class="border border-ink/15 rounded-lg p-2 text-xs bg-sand/10 focus:border-brand-500 outline-none">
                            <option value="" <?= $currentAccess === '' ? 'selected' : '' ?>>Inherit (Default)</option>
                            <option value="view" <?= $currentAccess === 'view' ? 'selected' : '' ?>>View Only</option>
                            <option value="edit" <?= $currentAccess === 'edit' ? 'selected' : '' ?>>View &amp; Edit</option>
                            <option value="manage" <?= $currentAccess === 'manage' ? 'selected' : '' ?>>View, Edit &amp; Manage</option>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('accessModal')" class="px-4 py-2.5 rounded-lg border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm transition">Update Access Control</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canUpload): ?>
<div id="versionModal" class="modal-overlay hidden" hidden>
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-lg font-bold text-ink">Upload New Version</h3>
            <button type="button" onclick="closeModal('versionModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/documents/' . $doc['id'] . '/version') ?>" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">File Upload</label>
                <input type="file" name="document_file" required class="w-full text-xs file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Change Notes</label>
                <textarea name="change_notes" rows="3" placeholder="Describe the updates in this version..." class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none"></textarea>
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('versionModal')" class="px-4 py-2.5 rounded-lg border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm transition">Upload Version</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<div id="signatureModal" class="modal-overlay hidden" hidden>
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-lg font-bold text-ink">Request E-Signature</h3>
            <button type="button" onclick="closeModal('signatureModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/documents/' . $doc['id'] . '/request-signature') ?>" method="POST" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Signer Name</label>
                <input type="text" name="signer_name" required class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Signer Email</label>
                <input type="email" name="signer_email" required class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Message (Optional)</label>
                <textarea name="message" rows="3" placeholder="Please sign this document..." class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none"></textarea>
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('signatureModal')" class="px-4 py-2.5 rounded-lg border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm transition">Send Request</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
