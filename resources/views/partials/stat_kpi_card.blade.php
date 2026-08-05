<?php
/**
 * HR1-style KPI stat card with solid circular icon.
 *
 * @var string      $label
 * @var string|int  $value
 * @var string|null $hint
 * @var string|null $icon   SVG path `d` attribute
 * @var string|null $tone   navy|peach|terracotta|brown|slate
 * @var string|null $href   Optional link wrapper
 * @var string|null $valueClass Extra classes on value element
 */
$label = $label ?? '';
$value = $value ?? '0';
$hint = $hint ?? null;
$icon = $icon ?? 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
$tone = $tone ?? 'navy';
$valueClass = trim($valueClass ?? '');
$href = $href ?? null;
$tag = $href ? 'a' : 'div';
$linkClass = $href ? ' stat-kpi-card--link' : '';
?>
<<?= $tag ?>
    class="stat-kpi-card<?= $linkClass ?>"
    <?php if ($href): ?>href="<?= htmlspecialchars($href) ?>"<?php endif; ?>>
    <div class="stat-kpi-card-inner">
        <div class="stat-kpi-icon stat-kpi-icon--<?= htmlspecialchars($tone) ?>" aria-hidden="true">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/>
            </svg>
        </div>
        <div class="stat-kpi-body">
            <p class="stat-kpi-label"><?= htmlspecialchars($label) ?></p>
            <p class="stat-kpi-value<?= $valueClass !== '' ? ' ' . htmlspecialchars($valueClass) : '' ?>"><?= $value ?></p>
            <?php if ($hint): ?>
                <p class="stat-kpi-hint"><?= htmlspecialchars($hint) ?></p>
            <?php endif; ?>
        </div>
    </div>
</<?= $tag ?>>
