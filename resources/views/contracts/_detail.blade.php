<?php
/** @var array $c */
/** @var array|null $myStep */
/** @var bool $canApprove */
/** @var bool $canManage */
/** @var bool $asModal */

$statusStyles = [
    'draft'             => 'bg-gray-100 text-gray-700 border-gray-200',
    'in_review'         => 'bg-accent-50 text-accent-700 border-accent-200 font-medium',
    'pending_signature' => 'bg-accent-50 text-accent-700 border-accent-200 font-medium',
    'active'            => 'bg-brand-50 text-brand-700 border-brand-300 font-bold',
    'expired'           => 'bg-accent-50 text-accent-700 border-accent-200',
    'terminated'        => 'bg-accent-100 text-accent-700 border-accent-200 font-semibold',
    'renewed'           => 'bg-brand-50 text-brand-700 border-brand-200',
];
$statusClass = $statusStyles[$c['status']] ?? 'bg-gray-50 border-gray-200 text-ink/70';
$asModal = !empty($asModal);
$pendingApprovals = count(array_filter($c['approvals'] ?? [], static fn($a) => ($a['status'] ?? '') === 'pending'));
$approvedCount = count(array_filter($c['approvals'] ?? [], static fn($a) => ($a['status'] ?? '') === 'approved'));
$amendmentCount = count($c['amendments'] ?? []);
$daysToEnd = !empty($c['end_date']) ? (int) floor((strtotime($c['end_date']) - time()) / 86400) : null;
?>

<div class="space-y-6" data-record-title="<?= htmlspecialchars($c['title'], ENT_QUOTES) ?>">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-sans text-xl font-bold text-ink"><?= htmlspecialchars($c['title']) ?></h2>
                <span class="inline-block px-2.5 py-0.5 text-[11px] font-bold border rounded-full capitalize <?= $statusClass ?>">
                    <?= htmlspecialchars(str_replace('_', ' ', $c['status'])) ?>
                </span>
            </div>
            <p class="text-xs text-ink/50 font-mono mt-1">Contract Number: <?= htmlspecialchars($c['contract_number']) ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if (($c['status'] ?? '') === 'pending_signature' && !empty($c['signature_token'])): ?>
                <button type="button" onclick="openModal('signModal_<?= $c['id'] ?>')" class="btn-primary btn-sm">Sign Contract</button>
            <?php endif; ?>
            <button type="button" onclick="window.print()" class="btn-secondary btn-sm inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h8z"/>
                </svg>
                Print / Save PDF
            </button>
            <?php if ($canManage && $c['status'] === 'active'): ?>
                <button type="button" onclick="openModal('amendModal')" class="btn-primary btn-sm">
                    + File Amendment
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="module-info-grid">
        <div class="module-insight-panel">
            <h3 class="module-insight-title">Contract lifecycle</h3>
            <p class="module-insight-sub">Where this agreement stands</p>
            <div class="module-stat-row">
                <span class="text-ink/70">Approval progress</span>
                <strong class="tabular-nums text-ink"><?= $approvedCount ?>/<?= count($c['approvals'] ?? []) ?> signed off</strong>
            </div>
            <?php if (!empty($c['signed_by_name'])): ?>
            <div class="module-stat-row">
                <span class="text-ink/70">Signed by</span>
                <strong class="text-ink"><?= htmlspecialchars($c['signed_by_name']) ?></strong>
            </div>
            <?php endif; ?>
            <?php if (!empty($c['signed_at'])): ?>
            <div class="module-stat-row">
                <span class="text-ink/70">Signed at</span>
                <strong class="tabular-nums text-ink"><?= date('M j, Y g:ia', strtotime($c['signed_at'])) ?></strong>
            </div>
            <?php endif; ?>
            <?php if (!empty($c['signature_path'])): ?>
            <?php
                $sigUrl = asset(ltrim((string) $c['signature_path'], '/'));
                $canSeeClearSignature = auth()->user()?->hasRole('admin', 'staff') ?? false;
            ?>
            <div class="mt-3 pt-3 border-t border-ink/10">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-ink/40">Captured signature</p>
                    <?php if (! $canSeeClearSignature): ?>
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-ink/35">Restricted view</span>
                    <?php endif; ?>
                </div>
                <div class="relative inline-block max-w-full">
                    <img src="<?= htmlspecialchars($sigUrl) ?>"
                         alt="Signature"
                         class="max-h-24 max-w-full bg-white border border-ink/10 rounded-lg p-2 block<?= $canSeeClearSignature ? '' : ' select-none pointer-events-none' ?>"
                         <?php if (! $canSeeClearSignature): ?>style="filter: blur(7px);"<?php endif; ?>>
                    <?php if (! $canSeeClearSignature): ?>
                        <div class="absolute inset-0 flex items-center justify-center rounded-lg bg-white/35">
                            <span class="text-[11px] font-semibold text-ink/55 px-2 text-center">Admin / Staff only</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($pendingApprovals > 0): ?>
                <div class="module-stat-row">
                    <span class="text-ink/70">Awaiting decision</span>
                    <strong class="tabular-nums text-accent-700"><?= $pendingApprovals ?> approver<?= $pendingApprovals === 1 ? '' : 's' ?></strong>
                </div>
            <?php endif; ?>
            <div class="module-stat-row">
                <span class="text-ink/70">Amendments filed</span>
                <strong class="tabular-nums text-ink"><?= $amendmentCount ?></strong>
            </div>
            <?php if ($daysToEnd !== null): ?>
                <p class="text-xs text-ink/45 mt-3 pt-3 border-t border-ink/5">
                    <?php if ($daysToEnd < 0): ?>
                        Ended <?= abs($daysToEnd) ?> day<?= abs($daysToEnd) === 1 ? '' : 's' ?> ago
                    <?php elseif ($daysToEnd <= 90): ?>
                        Renewal window — <?= $daysToEnd ?> day<?= $daysToEnd === 1 ? '' : 's' ?> until end date
                    <?php else: ?>
                        <?= $daysToEnd ?> days remaining on term
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <?php if (!empty($c['case_title'])): ?>
        <div class="module-insight-panel">
            <h3 class="module-insight-title">Linked legal matter</h3>
            <p class="module-insight-sub">Dispute or case tied to this contract</p>
            <p class="text-sm font-semibold text-ink">
                <a href="<?= url('/legal/' . $c['related_case_id']) ?>" data-record-modal class="hover:text-brand-700 transition">
                    <?= htmlspecialchars($c['case_title']) ?>
                </a>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-ink/10 rounded-2xl p-6 shadow-sm space-y-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-ink/40">Contract overview</p>
                    <p class="text-sm text-ink/60 mt-1">Owner: <span class="font-semibold text-ink/80"><?= htmlspecialchars($c['owner_name']) ?></span></p>
                </div>

                <hr class="border-ink/5">

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Contract Type</p>
                        <p class="font-semibold text-ink mt-1"><?= htmlspecialchars($c['type_name'] ?: 'General Contract') ?></p>
                    </div>
                    <div>
                        <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Counterparty</p>
                        <p class="font-semibold text-ink mt-1"><?= htmlspecialchars($c['counterparty_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Value Amount</p>
                        <p class="font-bold text-ink mt-1"><?= $c['value_amount'] ? htmlspecialchars($c['currency']) . ' ' . number_format($c['value_amount'], 2) : 'N/A' ?></p>
                    </div>
                    <div>
                        <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Auto Renew</p>
                        <p class="font-semibold text-ink mt-1"><?= $c['auto_renew'] ? 'Yes (' . $c['renewal_notice_days'] . 'd notice)' : 'No' ?></p>
                    </div>
                </div>

                <hr class="border-ink/5">

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Start Date</p>
                        <p class="font-semibold text-ink mt-1"><?= date('M d, Y', strtotime($c['start_date'])) ?></p>
                    </div>
                    <div>
                        <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">End Date</p>
                        <p class="font-semibold text-ink mt-1"><?= $c['end_date'] ? date('M d, Y', strtotime($c['end_date'])) : 'Ongoing' ?></p>
                    </div>
                    <?php if ($c['case_title']): ?>
                        <div class="col-span-2">
                            <p class="text-accent-700 text-xs font-bold uppercase tracking-wider">Disputed Legal Case</p>
                            <p class="font-semibold text-ink mt-1 underline"><a href="<?= url('/legal/' . $c['related_case_id']) ?>" data-record-modal><?= htmlspecialchars($c['case_title']) ?></a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($c['approvals'])): ?>
                <div id="approvals" class="bg-white border border-ink/10 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-ink/10 bg-gray-50/50">
                        <h3 class="font-sans text-lg font-bold text-ink">Approval Sequence</h3>
                    </div>
                    <div class="p-6 divide-y divide-ink/5">
                        <?php foreach ($c['approvals'] as $app): ?>
                            <div class="py-4 first:pt-0 last:pb-0 flex justify-between items-center text-sm gap-3">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-ink"><?= $app['sequence_order'] ?>. <?= htmlspecialchars($app['approver_name']) ?></span>
                                        <span class="text-xs text-ink/50">(<?= htmlspecialchars($app['approver_email']) ?>)</span>
                                    </div>
                                    <?php if ($app['comments']): ?>
                                        <p class="text-xs text-ink/75 mt-1.5 italic">"<?= htmlspecialchars($app['comments']) ?>"</p>
                                    <?php endif; ?>
                                </div>
                                <div class="shrink-0 text-right">
                                    <?php
                                    $appStyles = [
                                        'pending'  => 'bg-accent-50 text-accent-700 border-accent-200',
                                        'approved' => 'bg-brand-50 text-brand-700 border-brand-300 font-semibold',
                                        'rejected' => 'bg-accent-50 text-accent-700 border-accent-200 font-semibold',
                                    ];
                                    $style = $appStyles[$app['status']] ?? 'bg-gray-50 border-gray-200';
                                    ?>
                                    <span class="inline-block px-2.5 py-0.5 border rounded capitalize text-xs <?= $style ?>">
                                        <?= htmlspecialchars($app['status']) ?>
                                    </span>
                                    <?php if ($app['decided_at']): ?>
                                        <div class="text-[9px] text-ink/40 mt-1"><?= date('M d, h:i A', strtotime($app['decided_at'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div id="amendments" class="bg-white border border-ink/10 rounded-xl overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-ink/10 bg-gray-50/50">
                    <h3 class="font-sans text-lg font-bold text-ink">Amendments &amp; Addendums</h3>
                </div>
                <div class="divide-y divide-ink/10">
                    <?php if (empty($c['amendments'])): ?>
                        <p class="text-sm text-ink/40 text-center py-6">No amendments filed.</p>
                    <?php else: ?>
                        <?php foreach ($c['amendments'] as $amd): ?>
                            <div class="p-5 flex justify-between items-start hover:bg-gray-50/30 transition-colors gap-4">
                                <div>
                                    <span class="font-bold text-ink text-sm">Amendment #<?= $amd['amendment_number'] ?></span>
                                    <span class="text-xs text-ink/40">Filed by <?= htmlspecialchars($amd['creator_name']) ?></span>
                                    <p class="text-sm text-ink/75 mt-2 font-medium">Description: <?= htmlspecialchars($amd['description']) ?></p>
                                </div>
                                <div class="text-right text-xs shrink-0">
                                    <p class="text-ink/45 font-semibold">Effective: <?= date('M d, Y', strtotime($amd['effective_date'])) ?></p>
                                    <?php if (!empty($amd['file_path'])): ?>
                                        <a href="<?= htmlspecialchars($amd['file_path']) ?>" download class="inline-block font-bold text-brand-650 hover:text-brand-850 underline mt-2.5" target="_blank">
                                            Download Amendment
                                        </a>
                                        <a href="<?= url('/contracts/' . $c['id'] . '/amendments/' . $amd['id'] . '/diff') ?>" class="inline-block font-bold text-ink/60 hover:text-ink underline mt-1.5 ml-0 sm:ml-3" target="_blank">
                                            View Diff
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <?php if ($canApprove && $myStep !== null): ?>
                <div class="bg-white border border-accent-200 rounded-xl p-5 shadow-sm space-y-4 bg-accent-50/40">
                    <h3 class="font-sans text-base font-bold text-brand-850">Approval Decision Required</h3>
                    <p class="text-xs text-ink/70">You are next in the approval sequence for this contract agreement.</p>
                    <form action="<?= url('/contracts/' . $c['id'] . '/approve') ?>" method="POST" class="space-y-3" data-ajax-action="contract.approve" data-ajax-reload-modal="<?= url('/contracts/' . $c['id'] . '?modal=1') ?>">
                        @csrf
                        <input type="hidden" name="approval_id" value="<?= $myStep['id'] ?>">
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-ink/50">Comments (Optional)</label>
                            <textarea name="comments" rows="2" placeholder="Provide context or instructions..." class="w-full border border-ink/10 rounded p-2 text-xs bg-white focus:ring-2 focus:ring-brand-500 outline-none"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="submit" name="decision" value="rejected" class="py-2 rounded bg-brand-600 text-white text-xs font-bold hover:bg-brand-600 transition shadow-sm">Reject</button>
                            <button type="submit" name="decision" value="approved" class="py-2 rounded bg-brand-700 text-white text-xs font-bold hover:bg-brand-600 transition shadow-sm">Approve</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="bg-white border border-ink/10 rounded-xl p-5 shadow-sm space-y-4">
                <h3 class="font-sans text-base font-bold text-ink">Contract Attachments</h3>
                <div class="space-y-2">
                    <?php if (empty($c['documents'])): ?>
                        <p class="text-xs text-ink/40 text-center py-2">No documents attached.</p>
                    <?php else: ?>
                        <?php foreach ($c['documents'] as $doc): ?>
                            <div class="flex justify-between items-center text-xs p-3 rounded bg-gray-50 border border-ink/5 gap-2">
                                <div class="min-w-0">
                                    <a href="<?= url('/documents/' . $doc['id']) ?>" data-record-modal class="font-bold text-ink hover:underline"><?= htmlspecialchars($doc['title']) ?></a>
                                    <div class="text-[10px] text-ink/40 font-mono mt-0.5"><?= htmlspecialchars($doc['document_number']) ?></div>
                                </div>
                                <?php if (!empty($doc['file_path'])): ?>
                                    <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="text-brand-700 hover:text-brand-900 underline font-semibold shrink-0">View</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canManage && $c['status'] === 'active'): ?>
<div id="amendModal" class="modal-overlay hidden" hidden>
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-lg font-bold text-ink">File Contract Amendment</h3>
            <button type="button" onclick="closeModal('amendModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/contracts/' . $c['id'] . '/amend') ?>" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Amendment Description</label>
                <input type="text" name="description" required placeholder="e.g. Price adjustments or service extensions" class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Effective Date</label>
                <input type="date" name="effective_date" required class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Upload Amendment File</label>
                <input type="file" name="amendment_file" class="w-full text-xs file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('amendModal')" class="px-4 py-2.5 rounded-lg border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm transition">Save Amendment</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (($c['status'] ?? '') === 'pending_signature' && !empty($c['signature_token'])): ?>
<div id="signModal_<?= $c['id'] ?>" class="modal-overlay hidden" hidden data-esign-modal="1">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-ink/40">E-signature</p>
                <h3 class="font-sans text-lg font-bold text-ink">Draw your signature</h3>
            </div>
            <button type="button" onclick="closeModal('signModal_<?= $c['id'] ?>')" class="text-ink/40 hover:text-ink/60 transition" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/contracts/sign/' . $c['signature_token']) ?>" method="POST" class="p-6 space-y-4"
              onsubmit="return window.submitSignaturePad(this, 'signatureCanvas_<?= $c['id'] ?>', 'signatureData_<?= $c['id'] ?>')">
            @csrf
            <div>
                <p class="font-sans text-base font-bold text-ink"><?= htmlspecialchars($c['title'] ?? 'Contract') ?></p>
                <p class="text-sm text-ink/55 mt-0.5 font-mono"><?= htmlspecialchars($c['contract_number'] ?? '') ?></p>
                <p class="text-sm text-ink/65 mt-2">Counterparty: <?= htmlspecialchars($c['counterparty_name'] ?? '') ?></p>
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Full legal name</label>
                <input type="text" name="signer_name" required
                       class="w-full border border-ink/15 rounded-xl p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none"
                       value="<?= htmlspecialchars(auth()->user()->full_name ?? '') ?>">
            </div>

            <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Signature pad</label>
                    <button type="button" onclick="window.clearSignaturePad('signatureCanvas_<?= $c['id'] ?>')"
                            class="text-xs font-semibold text-brand-700 hover:text-brand-900">Clear</button>
                </div>
                <div class="rounded-xl border border-dashed border-ink/20 bg-[#fcfcfb] overflow-hidden relative">
                    <p class="pointer-events-none absolute inset-x-0 top-2 text-center text-[11px] text-ink/30 select-none">Sign here with mouse or finger</p>
                    <canvas id="signatureCanvas_<?= $c['id'] ?>"
                            data-signature-pad
                            width="520"
                            height="180"
                            class="block w-full h-[180px] cursor-crosshair touch-none bg-transparent"></canvas>
                </div>
                <input type="hidden" name="signature_data" id="signatureData_<?= $c['id'] ?>">
            </div>

            <p class="text-xs text-ink/50">By signing, you confirm you are authorized to execute this agreement electronically.</p>

            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('signModal_<?= $c['id'] ?>')"
                        class="px-4 py-2.5 rounded-xl border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm shadow-sm">
                    Sign &amp; Activate
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
