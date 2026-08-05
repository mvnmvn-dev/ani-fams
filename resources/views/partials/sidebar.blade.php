<?php


$user = auth()->user();
$path = trim(request()->path(), '/'); $currentPath = $path === '' ? '/' : '/'.$path; if ($currentPath === '/') { $currentPath = '/'; }

/** nav definition: label, path, icon (inline svg path), required permission (null = always visible) */
$navItems = [
    ['Dashboard',    '/dashboard',   'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', null],
    ['Reservations', '/reservations','M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'reservation.view'],
    ['Visitors',     '/visitors',    'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 11-8 0 4 4 0 018 0zm6 4a4 4 0 10-8 0', 'visitor.view'],
    ['Documents',    '/documents',   'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'document.view'],
    ['Records',      '/retention',   'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'retention.view'],
    ['Legal Cases',  '/legal',       'M12 3v1m0 16v1m8.66-13.66l-.71.71M4.05 19.95l-.71.71M21 12h-1M4 12H3m16.66 4.66l-.71-.71M4.05 4.05l-.71-.71', 'legal.view'],
    ['Contracts',    '/contracts',   'M9 17v-2a4 4 0 014-4h4m0 0l-3-3m3 3l-3 3M7 7h.01M7 3h5a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z', 'contract.view'],
];
?>
<aside id="app-sidebar" class="app-sidebar h-full shrink-0 text-sand/90 flex flex-col relative z-30 overflow-hidden">
    <div class="sidebar-brand px-3.5 py-5 border-b border-white/10 flex items-center gap-3.5">
        <img src="<?= asset('images/system-logo.png') ?>"
             alt="<?= htmlspecialchars(config('app.name')) ?>"
             class="sidebar-mark shrink-0 w-14 h-14 rounded-2xl object-cover shadow-lg shadow-black/30"
             title="<?= htmlspecialchars(config('app.name')) ?>">
        <div class="sidebar-brand-text sidebar-brand-copy min-w-0">
            <p class="sidebar-brand-title">FAMS</p>
            <p class="sidebar-brand-tagline">Facilities &amp; Admin Management</p>
        </div>
    </div>

    <nav class="flex-1 px-2.5 py-4 space-y-1 overflow-y-auto overflow-x-hidden sidebar-nav">
        <?php foreach ($navItems as [$label, $path, $icon, $permission]): ?>
            <?php if ($permission === null || can_perm($permission)): ?>
                <?php $active = $currentPath === $path || str_starts_with($currentPath, $path . '/'); ?>
                <a href="<?= url($path) ?>"
                   data-nav-link
                   title="<?= htmlspecialchars($label) ?>"
                   class="sidebar-link <?= $active ? 'is-active' : '' ?>">
                    <span class="sidebar-link-icon">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/>
                        </svg>
                    </span>
                    <span class="sidebar-link-label"><?= $label ?></span>
                    <?php if ($active): ?><span class="sidebar-active-dot" aria-hidden="true"></span><?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (can_perm('users.manage') || can_perm('audit.view')): ?>
            <div class="sidebar-section pt-3 mt-3">
                <p class="sidebar-section-label px-3 pb-1.5 text-[10px] uppercase tracking-[0.14em] text-sand/35 font-semibold">Administration</p>
                <?php if (can_perm('users.manage')): ?>
                    <a href="<?= url('/users') ?>"
                       data-nav-link
                       title="User Management"
                       class="sidebar-link <?= $currentPath === '/users' ? 'is-active' : '' ?>">
                        <span class="sidebar-link-icon">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </span>
                        <span class="sidebar-link-label">User Management</span>
                        <?php if ($currentPath === '/users'): ?><span class="sidebar-active-dot" aria-hidden="true"></span><?php endif; ?>
                    </a>
                <?php endif; ?>
                <?php if (can_perm('audit.view') || can_perm('users.manage')): ?>
                    <a href="<?= url('/audit-logs') ?>"
                       data-nav-link
                       title="Audit Log"
                       class="sidebar-link <?= $currentPath === '/audit-logs' ? 'is-active' : '' ?>">
                        <span class="sidebar-link-icon">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                        </span>
                        <span class="sidebar-link-label">Audit Log</span>
                        <?php if ($currentPath === '/audit-logs'): ?><span class="sidebar-active-dot" aria-hidden="true"></span><?php endif; ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </nav>
</aside>
