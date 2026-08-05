<?php
/**
 * Shared page title container.
 *
 * @var string      $title
 * @var string      $subtitle
 * @var string|null $eyebrow
 * @var string|null $meta
 * @var string|null $badgeHtml
 * @var array|null  $back   ['url' => string, 'label' => string]
 * @var string      $actionsHtml
 */
$title       = $title ?? '';
$subtitle    = $subtitle ?? '';
$eyebrow     = $eyebrow ?? null;
$meta        = $meta ?? null;
$badgeHtml   = $badgeHtml ?? null;
$back        = $back ?? null;
$actionsHtml = $actionsHtml ?? '';
?>
<header class="page-header">
    <?php if (!empty($back['url'])): ?>
        <a href="<?= htmlspecialchars($back['url']) ?>" class="page-header-back">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <?= htmlspecialchars($back['label'] ?? 'Back') ?>
        </a>
    <?php endif; ?>

    <div class="page-header-body">
        <div class="page-header-copy min-w-0">
            <?php if ($eyebrow): ?>
                <div class="page-header-topline">
                    <span class="page-header-eyebrow"><?= htmlspecialchars($eyebrow) ?></span>
                </div>
            <?php endif; ?>

            <div class="page-header-title-row">
                <h1 class="page-header-title"><?= $title ?></h1>
                <?php if ($badgeHtml): ?>
                    <div class="page-header-badge"><?= $badgeHtml ?></div>
                <?php endif; ?>
            </div>

            <?php if ($subtitle !== ''): ?>
                <p class="page-header-subtitle"><?= $subtitle ?></p>
            <?php endif; ?>
        </div>

        <?php if ($actionsHtml !== '' || $meta): ?>
            <div class="page-header-aside">
                <?php if ($meta): ?>
                    <p class="page-header-meta"><?= $meta ?></p>
                <?php endif; ?>
                <?php if ($actionsHtml !== ''): ?>
                    <div class="page-header-actions">
                        <?= $actionsHtml ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</header>
