{{--
  Shared module filter bar.
  @var array $fields  Each: ['name' => 'status', 'label' => 'Status', 'type' => 'select'|'search', 'options' => [value => label], 'placeholder' => '...']
--}}
<?php
$fields = $fields ?? [];
?>
<div class="module-filters">
    <?php foreach ($fields as $field): ?>
        <?php
            $name = $field['name'] ?? 'q';
            $label = $field['label'] ?? ucfirst($name);
            $type = $field['type'] ?? ($name === 'q' ? 'search' : 'select');
            $grow = !empty($field['grow']) || $type === 'search';
        ?>
        <div class="module-filters-field <?= $grow ? 'is-grow' : '' ?>">
            <label for="filter-<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($label) ?></label>
            <?php if ($type === 'search'): ?>
                <input id="filter-<?= htmlspecialchars($name) ?>" type="search" data-module-filter="<?= htmlspecialchars($name) ?>"
                       placeholder="<?= htmlspecialchars($field['placeholder'] ?? 'Search…') ?>" autocomplete="off">
            <?php else: ?>
                <select id="filter-<?= htmlspecialchars($name) ?>" data-module-filter="<?= htmlspecialchars($name) ?>">
                    <option value=""><?= htmlspecialchars($field['allLabel'] ?? 'All') ?></option>
                    <?php foreach (($field['options'] ?? []) as $value => $optLabel): ?>
                        <option value="<?= htmlspecialchars((string) $value) ?>"><?= htmlspecialchars($optLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <div class="module-filters-actions">
        <button type="button" class="module-filters-clear" data-filter-clear hidden>Clear</button>
        <span class="module-filters-count" data-filter-count></span>
    </div>
</div>
