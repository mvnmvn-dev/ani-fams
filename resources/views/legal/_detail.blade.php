<?php
/** @var array $case */
/** @var array $allDocs */
/** @var bool $canManage */
/** @var bool $asModal */

$asModal = !empty($asModal);
$upcomingMilestones = array_values(array_filter($case['milestones'] ?? [], static fn($m) => empty($m['is_completed'])));
usort($upcomingMilestones, static fn($a, $b) => strtotime($a['milestone_date'] ?? '0') <=> strtotime($b['milestone_date'] ?? '0'));
$upcomingMilestones = array_slice($upcomingMilestones, 0, 3);
$totalCosts = (float) ($case['total_cost'] ?? array_sum(array_column($case['costs'] ?? [], 'amount')));
$partyCount = count($case['parties'] ?? []);
$linkedDocCount = count($case['documents'] ?? []);
?>

<div class="space-y-6" data-record-title="<?= htmlspecialchars($case['title'], ENT_QUOTES) ?>">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-sans text-xl font-bold text-ink"><?= htmlspecialchars($case['title']) ?></h2>
                <span class="inline-block px-2.5 py-0.5 rounded-md bg-brand-100 text-brand-800 text-[11px] font-bold uppercase tracking-wider">
                    <?= htmlspecialchars($case['status']) ?>
                </span>
            </div>
            <p class="text-xs text-ink/50 font-mono mt-1">Case Number: <?= htmlspecialchars($case['case_number']) ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($canManage): ?>
                <button type="button" onclick="openModal('partyModal')" class="btn-secondary btn-sm">+ Party</button>
                <button type="button" onclick="openModal('documentModal')" class="btn-secondary btn-sm">+ Link Document</button>
                <button type="button" onclick="openModal('milestoneModal')" class="btn-secondary btn-sm">+ Milestone</button>
                <button type="button" onclick="openModal('costModal')" class="btn-primary btn-sm">Log Expense</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="module-info-grid">
        <div class="module-insight-panel">
            <h3 class="module-insight-title">Case exposure</h3>
            <p class="module-insight-sub">Financial and procedural snapshot</p>
            <div class="module-stat-row">
                <span class="text-ink/70">Logged expenses</span>
                <strong class="tabular-nums text-ink"><?= $totalCosts > 0 ? number_format($totalCosts, 2) : '0.00' ?></strong>
            </div>
            <div class="module-stat-row">
                <span class="text-ink/70">Parties involved</span>
                <strong class="tabular-nums text-ink"><?= $partyCount ?></strong>
            </div>
            <div class="module-stat-row">
                <span class="text-ink/70">Linked documents</span>
                <strong class="tabular-nums text-ink"><?= $linkedDocCount ?></strong>
            </div>
            <div class="module-stat-row">
                <span class="text-ink/70">Risk level</span>
                <strong class="capitalize <?= in_array($case['risk_level'] ?? '', ['high', 'critical'], true) ? 'text-accent-700' : 'text-ink' ?>"><?= htmlspecialchars($case['risk_level'] ?? 'medium') ?></strong>
            </div>
        </div>
        @include('partials.module_feed_list', [
            'title' => 'Upcoming milestones',
            'subtitle' => 'Deadlines and hearings not yet completed',
            'items' => array_map(static fn($m) => [
                'label' => ucwords(str_replace('_', ' ', $m['milestone_type'] ?? 'Milestone')),
                'meta'  => $m['description'] ?? '',
                'aside' => !empty($m['milestone_date']) ? date('M j, Y', strtotime($m['milestone_date'])) : '',
            ], $upcomingMilestones),
            'empty' => 'No pending milestones scheduled.',
        ])
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-ink/10 rounded-2xl p-6 shadow-sm space-y-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-ink/40">Case overview</p>
                    <p class="text-sm text-ink/60 mt-1">Risk level: <span class="capitalize font-bold text-accent-700"><?= htmlspecialchars($case['risk_level']) ?></span></p>
                </div>

                <hr class="border-ink/5">

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Case Type</p>
                        <p class="font-semibold text-ink mt-1 capitalize"><?= htmlspecialchars($case['case_type']) ?></p>
                    </div>
                    <div>
                        <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Court/Agency</p>
                        <p class="font-semibold text-ink mt-1"><?= htmlspecialchars($case['court_or_agency'] ?: 'N/A') ?></p>
                    </div>
                    <div>
                        <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Opposing Party</p>
                        <p class="font-semibold text-ink mt-1"><?= htmlspecialchars($case['opposing_party'] ?: 'N/A') ?></p>
                    </div>
                    <div>
                        <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider">Filed Date</p>
                        <p class="font-semibold text-ink mt-1"><?= $case['filed_date'] ? date('M d, Y', strtotime($case['filed_date'])) : 'Pending' ?></p>
                    </div>
                </div>

                <hr class="border-ink/5">

                <div>
                    <p class="text-ink/40 text-xs font-semibold uppercase tracking-wider mb-1">Matter Summary</p>
                    <p class="text-ink/70 text-sm leading-relaxed whitespace-pre-line"><?= htmlspecialchars($case['description'] ?: 'No details provided.') ?></p>
                </div>
            </div>

            <div id="milestones" class="bg-white border border-ink/10 rounded-xl overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-ink/10 bg-gray-50/50 flex justify-between items-center">
                    <h3 class="font-sans text-lg font-bold text-ink">Milestones &amp; Deadlines</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($case['milestones'])): ?>
                        <p class="text-sm text-ink/40 text-center py-4">No milestones scheduled yet.</p>
                    <?php else: ?>
                        <div class="relative border-l-2 border-brand-100 pl-6 space-y-6">
                            <?php foreach ($case['milestones'] as $m): ?>
                                <div class="relative">
                                    <span class="absolute -left-[31px] top-1.5 flex h-4 w-4 items-center justify-center rounded-full border-2 border-white
                                                 <?= $m['is_completed'] ? 'bg-brand-700' : 'bg-accent-500 animate-pulse' ?>"></span>
                                    <div class="flex justify-between items-start gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-ink capitalize"><?= htmlspecialchars($m['milestone_type']) ?></p>
                                            <p class="text-xs text-ink/50 mt-0.5"><?= date('M d, Y h:i A', strtotime($m['milestone_date'])) ?></p>
                                            <p class="text-sm text-ink/75 mt-1 font-medium"><?= htmlspecialchars($m['description']) ?></p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <?php if ($canManage): ?>
                                                <form action="<?= url('/legal/' . $case['id'] . '/milestones/' . $m['id'] . '/toggle') ?>" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="completed" value="<?= $m['is_completed'] ? '0' : '1' ?>">
                                                    <button type="submit" class="text-xs font-semibold text-brand-600 hover:text-brand-800 underline">
                                                        Mark <?= $m['is_completed'] ? 'Pending' : 'Completed' ?>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-xs font-semibold <?= $m['is_completed'] ? 'text-brand-700' : 'text-accent-600' ?>">
                                                    <?= $m['is_completed'] ? 'Completed' : 'Pending' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white border border-ink/10 rounded-xl p-5 shadow-sm space-y-3">
                <h3 class="font-sans text-base font-bold text-ink">Assigned Counsel</h3>
                <div class="text-sm">
                    <?php if ($case['counsel_name']): ?>
                        <p class="font-bold text-ink"><?= htmlspecialchars($case['counsel_name']) ?></p>
                        <p class="text-xs text-ink/50">Lead Counsel (Internal Legal Dept)</p>
                    <?php endif; ?>
                    <?php if ($case['external_counsel_name']): ?>
                        <p class="font-bold text-ink mt-2"><?= htmlspecialchars($case['external_counsel_name']) ?></p>
                        <p class="text-xs text-ink/50">External Counsel Representative</p>
                    <?php endif; ?>
                    <?php if (!$case['counsel_name'] && !$case['external_counsel_name']): ?>
                        <p class="text-ink/40 text-center py-2">No counsel assigned yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white border border-ink/10 rounded-xl p-5 shadow-sm space-y-4">
                <h3 class="font-sans text-base font-bold text-ink">Involved Parties</h3>
                <div class="space-y-3">
                    <?php if (empty($case['parties'])): ?>
                        <p class="text-xs text-ink/40 text-center py-2">No parties cataloged.</p>
                    <?php else: ?>
                        <?php foreach ($case['parties'] as $p): ?>
                            <div class="text-xs p-3 rounded bg-gray-50 border border-ink/5 flex justify-between items-start gap-2">
                                <div>
                                    <span class="font-semibold text-ink"><?= htmlspecialchars($p['party_name']) ?></span>
                                    <p class="text-xs text-ink/50 mt-1"><?= htmlspecialchars($p['contact_info'] ?: 'No contact info') ?></p>
                                </div>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-brand-50 text-brand-700 capitalize font-bold shrink-0"><?= htmlspecialchars($p['role']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white border border-ink/10 rounded-xl p-5 shadow-sm space-y-4">
                <h3 class="font-sans text-base font-bold text-ink">Linked Case Documents</h3>
                <div class="space-y-2">
                    <?php if (empty($case['documents'])): ?>
                        <p class="text-xs text-ink/40 text-center py-2">No documents attached.</p>
                    <?php else: ?>
                        <?php foreach ($case['documents'] as $doc): ?>
                            <div class="flex justify-between items-center text-xs p-3 rounded bg-gray-50 border border-ink/5 gap-2">
                                <div class="min-w-0">
                                    <a href="<?= url('/documents/' . $doc['id']) ?>" data-record-modal class="font-semibold text-ink hover:underline"><?= htmlspecialchars($doc['title']) ?></a>
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

            <div id="costs" class="bg-white border border-ink/10 rounded-xl p-5 shadow-sm space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="font-sans text-base font-bold text-ink">Incurred Costs</h3>
                    <span class="text-sm font-bold text-brand-750">PHP <?= number_format($case['total_cost'], 2) ?></span>
                </div>
                <div class="space-y-2">
                    <?php if (empty($case['costs'])): ?>
                        <p class="text-xs text-ink/40 text-center py-2">No expenses logged.</p>
                    <?php else: ?>
                        <?php foreach ($case['costs'] as $cost): ?>
                            <div class="flex justify-between items-center text-xs p-2 rounded bg-gray-55/40 border border-ink/5 gap-2">
                                <div>
                                    <div class="font-semibold text-ink"><?= htmlspecialchars($cost['description']) ?></div>
                                    <div class="text-[10px] text-ink/40"><?= date('M d, Y', strtotime($cost['incurred_date'])) ?></div>
                                </div>
                                <span class="font-bold text-ink shrink-0">PHP <?= number_format($cost['amount'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<div id="partyModal" class="modal-overlay hidden" hidden>
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-lg font-bold text-ink">Add Involved Party</h3>
            <button type="button" onclick="closeModal('partyModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/legal/' . $case['id'] . '/parties') ?>" method="POST" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Party Full Name</label>
                <input type="text" name="party_name" required placeholder="e.g. Jane Smith" class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Role in Case</label>
                <select name="role" class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                    <option value="plaintiff">Plaintiff</option>
                    <option value="defendant">Defendant</option>
                    <option value="witness">Witness</option>
                    <option value="third_party">Third Party</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Contact Info</label>
                <input type="text" name="contact_info" placeholder="e.g. jane@email.com / 555-012" class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('partyModal')" class="px-4 py-2.5 rounded-lg border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm transition">Add Party</button>
            </div>
        </form>
    </div>
</div>

<div id="documentModal" class="modal-overlay hidden" hidden>
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-lg font-bold text-ink">Link Document from Archive</h3>
            <button type="button" onclick="closeModal('documentModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/legal/' . $case['id'] . '/documents') ?>" method="POST" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Choose Document</label>
                <select name="document_id" required class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                    <option value="">-- Select Document --</option>
                    <?php foreach ($allDocs as $doc): ?>
                        <option value="<?= $doc['id'] ?>"><?= htmlspecialchars($doc['title']) ?> (<?= htmlspecialchars($doc['document_number']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('documentModal')" class="px-4 py-2.5 rounded-lg border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm transition">Link Document</button>
            </div>
        </form>
    </div>
</div>

<div id="milestoneModal" class="modal-overlay hidden" hidden>
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-lg font-bold text-ink">Schedule Milestone / Deadline</h3>
            <button type="button" onclick="closeModal('milestoneModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/legal/' . $case['id'] . '/milestones') ?>" method="POST" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Event Type</label>
                <select name="milestone_type" class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                    <option value="hearing">Hearing / Trial</option>
                    <option value="filing_deadline">Filing Deadline</option>
                    <option value="discovery">Discovery Event</option>
                    <option value="settlement_talk">Settlement discussion</option>
                    <option value="other">Other Event</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Scheduled Date &amp; Time</label>
                <input type="datetime-local" name="milestone_date" required class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Brief Description</label>
                <input type="text" name="description" required placeholder="e.g. Submit preliminary briefs" class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('milestoneModal')" class="px-4 py-2.5 rounded-lg border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm transition">Save Milestone</button>
            </div>
        </form>
    </div>
</div>

<div id="costModal" class="modal-overlay hidden" hidden>
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-lg font-bold text-ink">Log Case Expense</h3>
            <button type="button" onclick="closeModal('costModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= url('/legal/' . $case['id'] . '/costs') ?>" method="POST" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Expense Description</label>
                <input type="text" name="description" required placeholder="e.g. Filing fee, Travel expenses" class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Amount (PHP)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="5000.00" class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Date Incurred</label>
                    <input type="date" name="incurred_date" value="<?= date('Y-m-d') ?>" required class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                </div>
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('costModal')" class="px-4 py-2.5 rounded-lg border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm transition">Log Expense</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
