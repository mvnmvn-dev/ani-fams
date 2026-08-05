<?php
/** @var array $quickActions Each: label, path, icon, tone? */
$quickActions = $quickActions ?? [];
$toneCycle = ['navy', 'navy', 'peach', 'terracotta', 'brown', 'slate'];
?>
<?php if (!empty($quickActions)): ?>
<div class="dash-quick-access">
    <div class="dash-quick-head">
        <h2 class="section-title">Quick access</h2>
        <p class="type-meta">Common tasks</p>
    </div>
    <div class="dash-quick-list">
        <?php foreach ($quickActions as $i => $action): ?>
            <?php $tone = $action['tone'] ?? $toneCycle[$i % count($toneCycle)]; ?>
            <a href="<?= url($action['path']) ?>" class="dash-quick-item" data-nav-link>
                <span class="stat-kpi-icon stat-kpi-icon--<?= htmlspecialchars($tone) ?>" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $action['icon'] ?>"/>
                    </svg>
                </span>
                <span class="dash-quick-label"><?= htmlspecialchars($action['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
