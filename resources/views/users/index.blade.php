@extends('layouts.app')

@section('content')
<?php

/** @var array $users */
/** @var array $roles */

$roleColors = [
    'admin'    => 'bg-brand-50 text-brand-700 border-brand-200',
    'staff'    => 'bg-brand-50 text-brand-700 border-brand-300',
    'employee' => 'bg-brand-50 text-brand-700 border-brand-200',
];
$activeUsers = 0;
$suspendedUsers = 0;
$roleCounts = [];
foreach ($users as $u) {
    if (($u['status'] ?? '') === 'active') $activeUsers++;
    else $suspendedUsers++;
    $code = $u['role_code'] ?? 'other';
    $roleCounts[$code] = ($roleCounts[$code] ?? 0) + 1;
}
$recentAccounts = array_slice($users, 0, 5);
$suspendedList = array_values(array_filter($users, static fn($u) => ($u['status'] ?? '') === 'suspended'));
$suspendedList = array_slice($suspendedList, 0, 4);
$deptCounts = [];
foreach ($users as $u) {
    $dept = trim($u['department_name'] ?? '') ?: 'Unassigned';
    $deptCounts[$dept] = ($deptCounts[$dept] ?? 0) + 1;
}
arsort($deptCounts);
?>
<div class="animate-fade-in-up space-y-8">
    <?php
    render_page_header([
        'title' => 'User Management',
        'subtitle' => 'Create accounts and assign Admin, Staff, or Employee roles.',
        'actions' => function () {
            ?>
            <button type="button" onclick="openModal('importUsersModal')" class="btn-secondary mr-2">
                Import CSV
            </button>
            <button type="button" onclick="openModal('bulkRoleModal')" class="btn-secondary mr-2" id="bulkRoleBtn" disabled title="Select users first">
                Bulk Role Reassign
            </button>
            <button type="button" onclick="openModal('createUserModal')" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create Account
            </button>
            <?php
        },
    ]);
    ?>

    @include('partials.stat_kpi_grid', ['cards' => [
        ['label' => 'Total accounts', 'value' => count($users), 'hint' => 'Registered system users', 'tone' => 'navy', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
        ['label' => 'Active', 'value' => $activeUsers, 'hint' => 'Can sign in', 'tone' => 'navy', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Suspended', 'value' => $suspendedUsers, 'hint' => 'Access revoked', 'tone' => 'terracotta', 'icon' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'],
        ['label' => 'Admins', 'value' => $roleCounts['admin'] ?? 0, 'hint' => 'Elevated access', 'tone' => 'brown', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
    ]])

    <div class="module-info-grid">
        @include('partials.module_feed_list', [
            'title' => 'Recently created accounts',
            'subtitle' => 'Newest users added to the system',
            'items' => array_map(static fn($u) => [
                'label' => $u['full_name'],
                'meta'  => $u['role_name'] . ' · ' . $u['email'],
                'aside' => !empty($u['created_at']) ? date('M j, Y', strtotime($u['created_at'])) : '',
                'badge' => ($u['status'] ?? '') !== 'active' ? ($u['status'] ?? null) : null,
            ], $recentAccounts),
            'empty' => 'No accounts yet.',
        ])
        @include('partials.module_feed_list', [
            'title' => 'Suspended accounts',
            'subtitle' => 'Users with access currently revoked',
            'items' => array_map(static fn($u) => [
                'label' => $u['full_name'],
                'meta'  => $u['role_name'] . ' · ' . $u['email'],
            ], $suspendedList),
            'empty' => 'No suspended accounts.',
        ])
    </div>

    <?php if (empty($users)): ?>
        <div class="module-empty">No users yet.</div>
    <?php else: ?>
        <?php
        $roleOptions = [];
        foreach ($users as $u) {
            $roleOptions[$u['role_code']] = $u['role_name'];
        }
        ?>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 space-y-6" data-filter-root>
            @include('partials.module_filters', ['fields' => [
                ['name' => 'q', 'label' => 'Search', 'placeholder' => 'Name or email…'],
                ['name' => 'role', 'label' => 'Role', 'allLabel' => 'All roles', 'options' => $roleOptions],
                ['name' => 'status', 'label' => 'Status', 'allLabel' => 'All statuses', 'options' => [
                    'active' => 'Active',
                    'suspended' => 'Suspended',
                ]],
            ]])
            <div class="module-empty" data-filter-empty hidden>No users match these filters.</div>
            <div class="directory-grid">
                <?php foreach ($users as $u): ?>
                    <?php
                        $nextAction = $u['status'] === 'active' ? 'Suspend' : 'Reactivate';
                        $isSelf = (int) $u['id'] === (int) Auth::id();
                        $initial = strtoupper(substr($u['full_name'], 0, 1));
                    ?>
                    <article class="directory-card <?= $u['status'] !== 'active' ? 'is-inactive' : '' ?>"
                             data-filter-item
                             data-user-id="<?= (int) $u['id'] ?>"
                             data-filter-role="<?= htmlspecialchars($u['role_code']) ?>"
                             data-filter-status="<?= htmlspecialchars($u['status']) ?>"
                             data-filter-search="<?= htmlspecialchars(strtolower($u['full_name'] . ' ' . $u['email'] . ' ' . $u['role_name'])) ?>">
                        <div class="directory-avatar" aria-hidden="true"><?= htmlspecialchars($initial) ?></div>
                        <div>
                            <h3 class="font-bold text-ink">
                                <?php if (!$isSelf): ?>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" class="user-bulk-check rounded border-ink/20" value="<?= (int) $u['id'] ?>" onchange="syncBulkRoleBtn()">
                                        <?= htmlspecialchars($u['full_name']) ?>
                                    </label>
                                <?php else: ?>
                                    <?= htmlspecialchars($u['full_name']) ?>
                                    <span class="ml-1 text-[10px] uppercase tracking-wider text-ink/40 font-bold">You</span>
                                <?php endif; ?>
                            </h3>
                            <p class="text-xs text-ink/50 mt-1 break-all"><?= htmlspecialchars($u['email']) ?></p>
                            <?php if (!empty($u['otp_email'])): ?>
                                <p class="text-[11px] text-ink/45 mt-1 break-all">OTP: <?= htmlspecialchars($u['otp_email']) ?></p>
                            <?php else: ?>
                                <p class="text-[11px] text-accent-700 mt-1 break-all">OTP: account email (fallback)</p>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-wrap justify-center gap-2">
                            <span class="px-2.5 py-1 rounded-md text-xs border font-semibold <?= $roleColors[$u['role_code']] ?? '' ?>">
                                <?= htmlspecialchars($u['role_name']) ?>
                            </span>
                            <span data-status-badge class="px-2.5 py-1 rounded-md text-xs border font-semibold <?= $u['status'] === 'active' ? 'bg-brand-50 text-brand-700 border-brand-300' : 'bg-ink/5 text-ink/50 border-ink/10' ?>">
                                <?= ucfirst($u['status']) ?>
                            </span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <button type="button"
                                    class="text-xs font-bold px-3 py-2 rounded-lg text-brand-700 hover:bg-brand-50 transition"
                                    onclick="openOtpEmailModal(<?= (int) $u['id'] ?>, <?= htmlspecialchars(json_encode($u['full_name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($u['otp_email'] ?? ''), ENT_QUOTES) ?>)">
                                OTP Email
                            </button>
                            <?php if (!$isSelf): ?>
                                <button type="button"
                                        data-user-toggle
                                        class="text-xs font-bold px-3 py-2 rounded-lg <?= $u['status'] === 'active' ? 'text-accent-700 hover:bg-accent-50' : 'text-brand-700 hover:bg-brand-50' ?> transition"
                                        onclick="openStatusModal(<?= (int) $u['id'] ?>, <?= htmlspecialchars(json_encode($u['full_name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($u['status']), ENT_QUOTES) ?>)">
                                    <?= $nextAction ?>
                                </button>
                            <?php else: ?>
                                <span class="text-ink/30 text-xs">Current account</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <aside class="space-y-4">
            <div class="module-insight-panel">
                <h3 class="module-insight-title">Role distribution</h3>
                <p class="module-insight-sub">Who has what access</p>
                <ul class="module-insight-list">
                    <?php foreach ($roleCounts as $code => $count): ?>
                        <li>
                            <span class="module-insight-dot"></span>
                            <span class="flex-1 capitalize"><?= htmlspecialchars($code) ?></span>
                            <strong class="tabular-nums text-ink"><?= $count ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="module-insight-panel">
                <h3 class="module-insight-title">Access hygiene</h3>
                <p class="module-insight-sub">Account management reminders</p>
                <ul class="module-insight-list">
                    <li><span class="module-insight-dot"></span><span><?= $activeUsers ?> active · <?= $suspendedUsers ?> suspended</span></li>
                    <li><span class="module-insight-dot"></span><span><?= $roleCounts['admin'] ?? 0 ?> admin<?= ($roleCounts['admin'] ?? 0) === 1 ? '' : 's' ?> with elevated access</span></li>
                    <li><span class="module-insight-dot"></span><span>Suspend unused accounts promptly.</span></li>
                </ul>
            </div>
            <?php if (!empty($deptCounts)): ?>
            <div class="module-insight-panel">
                <h3 class="module-insight-title">By department</h3>
                <p class="module-insight-sub">Where accounts are assigned</p>
                <?php foreach (array_slice($deptCounts, 0, 6, true) as $dept => $count): ?>
                    <div class="module-stat-row">
                        <span class="text-ink/70"><?= htmlspecialchars($dept) ?></span>
                        <strong class="tabular-nums text-ink"><?= $count ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </aside>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Create Account -->
<div id="createUserModal" class="modal-overlay hidden">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-8 py-5 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-xl font-bold text-ink">Create Account</h3>
            <button type="button" onclick="closeModal('createUserModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= route('users.store') ?>" method="POST" class="p-8 space-y-5">
    @csrf
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-ink/60">Full name</label>
                <input type="text" name="full_name" required
                       class="w-full rounded-xl border border-ink/15 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-sand/10"
                       placeholder="e.g. Jane Doe">
            </div>
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-ink/60">Email</label>
                <input type="email" name="email" required
                       class="w-full rounded-xl border border-ink/15 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-sand/10"
                       placeholder="name@company.local">
            </div>
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-ink/60">Temporary password</label>
                <input type="password" name="password" required minlength="10"
                       class="w-full rounded-xl border border-ink/15 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-sand/10"
                       placeholder="Min. 10 chars, mixed case, number, symbol"
                       pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{10,}"
                       title="At least 10 characters with uppercase, lowercase, a number, and a special character">
                <p class="text-[11px] text-ink/45">Must include uppercase, lowercase, a number, and a special character.</p>
            </div>
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-ink/60">Role</label>
                <select name="role_id" required
                        class="w-full rounded-xl border border-ink/15 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-sand/10">
                    <option value="">Select a role…</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-ink/60">OTP Gmail <span class="normal-case font-normal text-ink/40">(optional)</span></label>
                <input type="email" name="otp_email"
                       class="w-full rounded-xl border border-ink/15 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-sand/10"
                       placeholder="user@gmail.com">
                <p class="text-[11px] text-ink/45">Login codes are sent here (required for all roles, including admin). Leave blank to use the account email.</p>
            </div>
            <div class="pt-6 border-t border-ink/10 flex justify-end gap-3.5">
                <button type="button" onclick="closeModal('createUserModal')"
                        class="px-5 py-3 rounded-xl border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">
                    Cancel
                </button>
                <button type="submit"
                        class="px-6 py-3 rounded-xl bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm transition shadow-sm">
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Import users CSV -->
<div id="importUsersModal" class="modal-overlay hidden">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-8 py-5 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-xl font-bold text-ink">Import users (CSV)</h3>
            <button type="button" onclick="closeModal('importUsersModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="<?= route('users.import') ?>" method="POST" enctype="multipart/form-data" class="p-8 space-y-4">
            @csrf
            <p class="text-xs text-ink/55">Columns: <code>full_name,email,password,role_code,department_id</code> (department_id optional).</p>
            <input type="file" name="csv_file" accept=".csv,text/csv" required class="w-full text-sm">
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('importUsersModal')" class="px-5 py-3 rounded-xl border border-ink/10 text-ink/70 font-semibold text-sm">Cancel</button>
                <button type="submit" class="px-6 py-3 rounded-xl bg-brand-700 text-white font-semibold text-sm">Import</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Confirm Status Change -->
<div id="statusUserModal" class="modal-overlay hidden">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-8 py-5 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 id="statusModalTitle" class="font-sans text-xl font-bold text-ink">Confirm</h3>
            <button type="button" onclick="closeModal('statusUserModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="statusUserForm" method="POST" class="p-8 space-y-5" data-ajax-action="user.toggle">
            @csrf
            <input type="hidden" name="current_status" id="statusCurrentValue" value="">
            <p id="statusModalBody" class="text-sm text-ink/70 leading-relaxed"></p>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3.5">
                <button type="button" onclick="closeModal('statusUserModal')"
                        class="px-5 py-3 rounded-xl border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">
                    Cancel
                </button>
                <button type="submit" id="statusConfirmBtn"
                        class="px-6 py-3 rounded-xl bg-ink hover:bg-ink/90 text-sand font-semibold text-sm transition shadow-sm">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: OTP Gmail -->
<div id="otpEmailModal" class="modal-overlay hidden">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-8 py-5 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-xl font-bold text-ink">OTP Gmail</h3>
            <button type="button" onclick="closeModal('otpEmailModal')" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="otpEmailForm" method="POST" class="p-8 space-y-5">
            @csrf
            <p id="otpEmailModalBody" class="text-sm text-ink/70 leading-relaxed"></p>
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-ink/60">Gmail for login OTP</label>
                <input type="email" name="otp_email" id="otpEmailInput"
                       class="w-full rounded-xl border border-ink/15 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-sand/10"
                       placeholder="user@gmail.com">
                <p class="text-[11px] text-ink/45">Clear the field and save to remove OTP for this account.</p>
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3.5">
                <button type="button" onclick="closeModal('otpEmailModal')"
                        class="px-5 py-3 rounded-xl border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">
                    Cancel
                </button>
                <button type="submit"
                        class="px-6 py-3 rounded-xl bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm transition shadow-sm">
                    Save
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Bulk role reassignment -->
<div id="bulkRoleModal" class="modal-overlay hidden">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-8 py-5 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-xl font-bold text-ink">Bulk role reassignment</h3>
            <button type="button" onclick="closeModal('bulkRoleModal')" class="text-ink/40 hover:text-ink/60 transition">✕</button>
        </div>
        <form action="<?= route('users.bulk-role') ?>" method="POST" class="p-8 space-y-4" id="bulkRoleForm" onsubmit="return prepareBulkRoleForm()">
            @csrf
            <div id="bulkRoleSelectedIds"></div>
            <p class="text-sm text-ink/65"><span id="bulkRoleCount">0</span> user(s) selected. Your own account cannot be reassigned here.</p>
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-ink/60">New role</label>
                <select name="role_id" required class="w-full rounded-xl border border-ink/15 px-4 py-3 text-sm bg-sand/10">
                    <option value="">Select a role…</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeModal('bulkRoleModal')" class="px-5 py-3 rounded-xl border border-ink/10 text-sm font-semibold">Cancel</button>
                <button type="submit" class="px-6 py-3 rounded-xl bg-brand-700 text-white text-sm font-semibold">Reassign</button>
            </div>
        </form>
    </div>
</div>

<script>
function syncBulkRoleBtn() {
    var checks = document.querySelectorAll('.user-bulk-check:checked');
    var btn = document.getElementById('bulkRoleBtn');
    var count = document.getElementById('bulkRoleCount');
    if (btn) {
        btn.disabled = checks.length === 0;
        btn.title = checks.length === 0 ? 'Select users first' : 'Reassign role for ' + checks.length + ' user(s)';
    }
    if (count) count.textContent = String(checks.length);
}
function prepareBulkRoleForm() {
    var wrap = document.getElementById('bulkRoleSelectedIds');
    if (!wrap) return false;
    wrap.innerHTML = '';
    var checks = document.querySelectorAll('.user-bulk-check:checked');
    if (checks.length === 0) {
        alert('Select at least one user.');
        return false;
    }
    checks.forEach(function (el) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'user_ids[]';
        input.value = el.value;
        wrap.appendChild(input);
    });
    return true;
}
function openOtpEmailModal(id, name, otpEmail) {
    var form = document.getElementById('otpEmailForm');
    form.action = (typeof window.appUrl === 'function')
        ? window.appUrl('users/' + id + '/otp-email')
        : (window.location.pathname.replace(/\/+$/, '') + '/' + id + '/otp-email');
    document.getElementById('otpEmailModalBody').textContent =
        'Set the Gmail address where login verification codes are sent for "' + name + '".';
    document.getElementById('otpEmailInput').value = otpEmail || '';
    openModal('otpEmailModal');
}
function openStatusModal(id, name, status) {
    var next = status === 'active' ? 'Suspend' : 'Reactivate';
    var form = document.getElementById('statusUserForm');
    form.action = (typeof window.appUrl === 'function')
        ? window.appUrl('users/' + id + '/toggle-status')
        : (window.location.pathname.replace(/\/+$/, '') + '/' + id + '/toggle-status');
    document.getElementById('statusCurrentValue').value = status;
    document.getElementById('statusModalTitle').textContent = next + ' user';
    document.getElementById('statusModalBody').textContent =
        next === 'Suspend'
            ? 'Suspend "' + name + '"? They will not be able to sign in until reactivated.'
            : 'Reactivate "' + name + '"? They will regain access immediately.';
    var btn = document.getElementById('statusConfirmBtn');
    btn.textContent = next;
    btn.className = next === 'Suspend'
        ? 'px-6 py-3 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-semibold text-sm transition shadow-sm'
        : 'px-6 py-3 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm transition shadow-sm';
    openModal('statusUserModal');
}
</script>

@endsection
