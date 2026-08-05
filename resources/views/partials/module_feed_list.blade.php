<?php
/**
 * Reusable module feed list panel.
 *
 * @var string $title
 * @var string|null $subtitle
 * @var array $items  Each: ['label' => string, 'meta' => string|null, 'href' => string|null, 'aside' => string|null, 'badge' => string|null]
 * @var string|null $empty
 */
$title = $title ?? '';
$subtitle = $subtitle ?? null;
$items = $items ?? [];
$empty = $empty ?? 'Nothing to show yet.';
?>
<div class="module-insight-panel">
    <h3 class="module-insight-title"><?= htmlspecialchars($title) ?></h3>
    <?php if ($subtitle): ?>
        <p class="module-insight-sub"><?= htmlspecialchars($subtitle) ?></p>
    <?php endif; ?>
    <?php if (empty($items)): ?>
        <p class="text-sm text-ink/45 py-1"><?= htmlspecialchars($empty) ?></p>
    <?php else: ?>
        <ul class="module-feed-list">
            <?php foreach ($items as $item): ?>
                <li class="module-feed-item">
                    <div class="min-w-0 flex-1">
                        <?php if (!empty($item['href'])): ?>
                            <a href="<?= htmlspecialchars($item['href']) ?>" <?= !empty($item['modal']) ? 'data-record-modal' : '' ?>>
                                <?= htmlspecialchars($item['label']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-sm font-semibold text-ink"><?= htmlspecialchars($item['label']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['meta'])): ?>
                            <p class="module-feed-meta"><?= htmlspecialchars($item['meta']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($item['badge'])): ?>
                            <span class="inline-block mt-1 px-2 py-0.5 text-[10px] font-bold border rounded capitalize bg-brand-50 text-brand-700 border-brand-200"><?= htmlspecialchars($item['badge']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($item['aside'])): ?>
                        <span class="module-feed-aside"><?= htmlspecialchars($item['aside']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
