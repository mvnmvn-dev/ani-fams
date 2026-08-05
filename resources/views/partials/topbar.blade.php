<?php
/** @var array|null $user from Auth */

use App\Support\TopbarData;

$user = $user ?? auth()->user();
$initial = strtoupper(substr($user->full_name ?? 'U', 0, 1));
$photoUrl = !empty($user->photo_path) ? asset(ltrim((string) $user->photo_path, '/')) : null;
$redirectPath = request()->path() ?: '/dashboard';

$allSearchTargets = [
    ['label' => 'Dashboard', 'path' => '/dashboard', 'keywords' => 'home overview', 'permission' => null],
    ['label' => 'Facilities Reservation', 'path' => '/reservations', 'keywords' => 'facility room booking schedule', 'permission' => 'reservation.view'],
    ['label' => 'Visitor Management', 'path' => '/visitors', 'keywords' => 'guest check-in on site', 'permission' => 'visitor.view'],
    ['label' => 'Document Management', 'path' => '/documents', 'keywords' => 'files upload records', 'permission' => 'document.view'],
    ['label' => 'Records Retention', 'path' => '/retention', 'keywords' => 'disposal policy hold compliance', 'permission' => 'retention.view'],
    ['label' => 'Legal Management', 'path' => '/legal', 'keywords' => 'cases litigation counsel', 'permission' => 'legal.view'],
    ['label' => 'Contract Management', 'path' => '/contracts', 'keywords' => 'agreements renewals', 'permission' => 'contract.view'],
    ['label' => 'User Management', 'path' => '/users', 'keywords' => 'accounts roles admin', 'permission' => 'users.manage'],
    ['label' => 'Audit Log', 'path' => '/audit-logs', 'keywords' => 'history actions trail', 'permission' => 'audit.view'],
];

$searchTargets = array_values(array_filter($allSearchTargets, static function ($item) {
    return $item['permission'] === null
        || can_perm($item['permission'])
        || ($item['permission'] === 'audit.view' && can_perm('users.manage'));
}));

$topbarAlerts = TopbarData::alerts();
$topbarAlertTotal = count(array_filter($topbarAlerts, static fn($n) => !empty($n['unread'])));
if ($topbarAlertTotal === 0) {
    $topbarAlertTotal = count($topbarAlerts);
}

$notifIcons = [
    'amber'  => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    'sky'    => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 11-8 0 4 4 0 018 0zm6 4a4 4 0 10-8 0',
    'red'    => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    'purple' => 'M9 17v-2a4 4 0 014-4h4m0 0l-3-3m3 3l-3 3M7 7h.01M7 3h5a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z',
];
?>
<header id="app-topbar" class="app-topbar">
    <div class="app-topbar-inner">
        <div class="app-topbar-leading">
            <button type="button" id="sidebar-toggle-btn" class="app-topbar-btn" title="Hide sidebar" aria-label="Hide sidebar" aria-pressed="false">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <div class="app-topbar-search relative min-w-0">
                <label class="sr-only" for="global-search">Search the system</label>
                <div class="app-search-field">
                    <svg class="app-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/>
                    </svg>
                    <input id="global-search" type="search" autocomplete="off" placeholder="Search modules…"
                           class="app-search-input">
                    <kbd class="app-search-kbd" title="Press Ctrl+K">Ctrl K</kbd>
                </div>
                <div id="global-search-results" class="app-search-results" hidden></div>
            </div>
        </div>

        <div class="app-topbar-actions">
            <div class="app-clock" aria-live="polite">
                <span id="app-clock-time" class="app-clock-time">--:--:--</span>
                <span id="app-clock-date" class="app-clock-date">—</span>
            </div>

            <div class="app-topbar-menu-wrap">
                <button type="button" id="alerts-btn" class="app-topbar-icon-btn"
                        title="Notifications" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                    <svg class="w-[22px] h-[22px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <?php if ($topbarAlertTotal > 0): ?>
                        <span class="app-topbar-badge"><?= $topbarAlertTotal > 9 ? '9+' : (int) $topbarAlertTotal ?></span>
                    <?php endif; ?>
                </button>
                <div id="alerts-menu" class="app-notif-panel" hidden>
                    <div class="app-notif-header">
                        <div>
                            <p class="app-notif-title">Notifications</p>
                            <p class="app-notif-subtitle">
                                <?= $topbarAlertTotal > 0
                                    ? ($topbarAlertTotal === 1 ? '1 unread message' : $topbarAlertTotal . ' unread messages')
                                    : 'No new messages' ?>
                            </p>
                        </div>
                    </div>
                    <div class="app-notif-list">
                        <?php if (empty($topbarAlerts)): ?>
                            <div class="app-notif-empty">
                                <p class="app-notif-empty-title">You're all caught up</p>
                                <p class="app-notif-empty-text">New system messages will appear here.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($topbarAlerts as $note): ?>
                                <a href="<?= url($note['path']) ?>" class="app-notif-item <?= !empty($note['unread']) ? 'is-unread' : '' ?>" data-nav-link>
                                    <span class="app-notif-icon tone-<?= htmlspecialchars($note['tone']) ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $notifIcons[$note['tone']] ?? $notifIcons['amber'] ?>"/>
                                        </svg>
                                    </span>
                                    <span class="app-notif-body">
                                        <span class="app-notif-item-top">
                                            <span class="app-notif-item-title"><?= htmlspecialchars($note['title']) ?></span>
                                            <?php if (!empty($note['unread'])): ?>
                                                <span class="app-notif-dot" aria-hidden="true"></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="app-notif-message"><?= htmlspecialchars($note['message']) ?></span>
                                        <span class="app-notif-meta">
                                            <span><?= htmlspecialchars($note['module']) ?></span>
                                            <span aria-hidden="true">·</span>
                                            <span><?= htmlspecialchars($note['time']) ?></span>
                                        </span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <a href="<?= url('/dashboard') ?>" class="app-notif-footer" data-nav-link>
                        View all activity
                    </a>
                </div>
            </div>

            <button type="button" id="profile-menu-btn" class="app-profile-btn"
                    title="<?= htmlspecialchars($user->full_name ?? 'Account') ?>"
                    aria-label="Account menu"
                    aria-haspopup="true"
                    aria-expanded="false">
                <span class="app-profile-avatar">
                    <?php if ($photoUrl): ?>
                        <img src="<?= htmlspecialchars($photoUrl) ?>" alt="" class="app-profile-avatar-img">
                    <?php else: ?>
                        <?= htmlspecialchars($initial) ?>
                    <?php endif; ?>
                </span>
            </button>

            <div id="profile-menu" class="app-profile-menu" hidden>
                <button type="button" class="app-profile-menu-item" onclick="closeProfileMenu(); openModal('profileModal')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Customize account
                </button>
                <a href="<?= url('/logout') ?>" class="app-profile-menu-item"
                   onclick="event.preventDefault(); var href = this.href; closeProfileMenu(); confirmAction('Are you sure you want to log out? You will need to sign in again to continue.', { title: 'Log out', confirmLabel: 'Log out', danger: true }).then(function (ok) { if (ok) window.location.href = href; });">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Log out
                </a>
            </div>
        </div>
    </div>
</header>

<div id="profileModal" class="modal-overlay hidden">
    <div class="modal-panel max-w-lg w-full mx-4 bg-white rounded-2xl shadow-xl border border-ink/10 p-6" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle">
        <div class="flex items-start justify-between gap-4 mb-5">
            <div>
                <h2 id="profileModalTitle" class="text-lg font-semibold text-ink">Customize account</h2>
                <p class="text-sm text-ink/50 mt-1">Update your photo, name, email, or password.</p>
            </div>
            <button type="button" onclick="closeModal('profileModal')" class="text-ink/40 hover:text-ink transition" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form method="post" action="<?= url('/profile') ?>" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="hidden" name="redirect" value="<?= htmlspecialchars('/' . ltrim($redirectPath, '/')) ?>">
            <div class="flex items-center gap-4">
                <div id="profile-photo-preview" class="app-profile-photo-preview">
                    <?php if ($photoUrl): ?>
                        <img src="<?= htmlspecialchars($photoUrl) ?>" alt="" class="app-profile-avatar-img">
                    <?php else: ?>
                        <span><?= htmlspecialchars($initial) ?></span>
                    <?php endif; ?>
                </div>
                <div class="min-w-0 flex-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-ink/45 mb-2">Profile picture</label>
                    <input type="file" name="photo" id="profile-photo-input" accept="image/jpeg,image/png,image/webp,image/gif"
                           class="block w-full text-sm text-ink/70 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-700">
                    <p class="text-xs text-ink/40 mt-1.5">JPG, PNG, WEBP, or GIF. Max 2 MB.</p>
                    <?php if ($photoUrl): ?>
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-ink/55">
                            <input type="checkbox" name="remove_photo" value="1" class="rounded border-ink/20 text-brand-600 focus:ring-brand-500">
                            Remove current photo
                        </label>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-ink/45 mb-2">Full name</label>
                <input type="text" name="full_name" required value="<?= htmlspecialchars($user->full_name ?? '') ?>"
                       class="w-full border border-ink/15 rounded-xl px-3 py-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-ink/45 mb-2">Email</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($user->email ?? '') ?>"
                       class="w-full border border-ink/15 rounded-xl px-3 py-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
            </div>
            <div class="border-t border-ink/10 pt-4">
                <p class="text-xs font-bold uppercase tracking-wider text-ink/45 mb-3">Change password <span class="font-medium normal-case tracking-normal text-ink/35">(optional)</span></p>
                <div class="space-y-3">
                    <input type="password" name="current_password" placeholder="Current password"
                           class="w-full border border-ink/15 rounded-xl px-3 py-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none" autocomplete="current-password">
                    <input type="password" name="new_password" placeholder="New password (min 8 characters)"
                           class="w-full border border-ink/15 rounded-xl px-3 py-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none" autocomplete="new-password">
                    <input type="password" name="confirm_password" placeholder="Confirm new password"
                           class="w-full border border-ink/15 rounded-xl px-3 py-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none" autocomplete="new-password">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('profileModal')"
                        class="px-4 py-2.5 rounded-xl border border-ink/15 text-sm font-semibold text-ink/70 hover:bg-ink/5 transition">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition">
                    Save changes
                </button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var input = document.getElementById('profile-photo-input');
    var preview = document.getElementById('profile-photo-preview');
    if (!input || !preview) return;
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file || !file.type.startsWith('image/')) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            preview.innerHTML = '<img src="' + e.target.result + '" alt="" class="app-profile-avatar-img">';
        };
        reader.readAsDataURL(file);
    });
})();
</script>

<script type="application/json" id="global-search-data"><?= json_encode(array_map(static function ($item) {
    return [
        'label' => $item['label'],
        'path' => url($item['path']),
        'keywords' => $item['keywords'],
    ];
}, $searchTargets), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
