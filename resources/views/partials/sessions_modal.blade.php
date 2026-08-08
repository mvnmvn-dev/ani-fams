<?php
/** Active sessions modal — included from the topbar on every authenticated page. */

use App\Http\Controllers\SessionController;

$sessionState = SessionController::deviceState();
$sessions = $sessionState['sessions'];
$databaseSessions = $sessionState['databaseSessions'];
$sessionDriver = $sessionState['sessionDriver'];
?>
<div id="sessionsModal" class="modal-overlay hidden" hidden>
    <div class="modal-panel max-w-2xl w-full mx-4 bg-white rounded-2xl shadow-xl border border-ink/10 overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="sessionsModalTitle" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex items-start justify-between gap-4 bg-gray-50/50">
            <div class="min-w-0">
                <h2 id="sessionsModalTitle" class="text-lg font-semibold text-ink">Active sessions</h2>
                <p class="text-sm text-ink/50 mt-1">Review and revoke devices signed in to your account.</p>
            </div>
            <button type="button" onclick="closeModal('sessionsModal')" class="text-ink/40 hover:text-ink transition shrink-0" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
            <?php if (! $databaseSessions || $sessionDriver !== 'database'): ?>
                <div class="rounded-xl border border-ink/10 bg-sand/30 p-4 text-sm text-ink/70 leading-relaxed">
                    <p class="font-semibold text-ink mb-1">Database sessions required</p>
                    <p>
                        Session driver is currently <strong><?= htmlspecialchars($sessionDriver) ?></strong>.
                        Set <code class="text-xs bg-ink/5 px-1.5 py-0.5 rounded">SESSION_DRIVER=database</code>
                        in <code class="text-xs bg-ink/5 px-1.5 py-0.5 rounded">.env</code>, then sign in again to manage devices here.
                    </p>
                </div>
            <?php elseif (empty($sessions)): ?>
                <div class="rounded-xl border border-dashed border-ink/15 px-4 py-10 text-center text-sm text-ink/45">
                    No database sessions found for your account yet. Sign out and back in after enabling the database driver.
                </div>
            <?php else: ?>
                <div class="flex justify-end">
                    <form action="<?= route('sessions.revokeOthers') ?>" method="POST">
                        @csrf
                        <button type="submit" class="btn-secondary btn-sm">Revoke other sessions</button>
                    </form>
                </div>
                <div class="module-table-wrap border border-ink/10 rounded-xl overflow-hidden">
                    <table class="module-table">
                        <thead>
                            <tr>
                                <th>Device</th>
                                <th>IP</th>
                                <th>Last activity</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td>
                                        <div class="font-semibold text-ink"><?= htmlspecialchars($session['device']) ?></div>
                                        <?php if (!empty($session['is_current'])): ?>
                                            <span class="text-[11px] font-bold text-brand-700">This device</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-ink/60"><?= htmlspecialchars($session['ip_address'] ?: '—') ?></td>
                                    <td class="text-ink/60 text-xs whitespace-nowrap"><?= htmlspecialchars($session['last_activity'] ?: '—') ?></td>
                                    <td class="text-right">
                                        <?php if (empty($session['is_current'])): ?>
                                            <form action="<?= route('sessions.revoke', $session['id']) ?>" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs font-bold text-accent-700 hover:text-accent-600">Revoke</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-ink/40">Current</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="px-6 py-4 border-t border-ink/10 flex justify-end bg-gray-50/30">
            <button type="button" onclick="closeModal('sessionsModal')" class="px-4 py-2.5 rounded-xl border border-ink/15 text-sm font-semibold text-ink/70 hover:bg-ink/5 transition">
                Close
            </button>
        </div>
    </div>
</div>
