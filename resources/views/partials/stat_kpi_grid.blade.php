<?php
/** @var array $cards Each: label, value, hint?, icon?, tone?, href?, valueClass? */
$cards = $cards ?? [];
?>
<div class="stat-kpi-grid module-kpi-grid">
    <?php foreach ($cards as $card): ?>
        @include('partials.stat_kpi_card', $card)
    <?php endforeach; ?>
</div>
