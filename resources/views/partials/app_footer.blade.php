<?php
$systemTitle = config('app.name');
$year = date('Y');
?>
<footer id="app-footer" class="app-footer">
    <p class="app-footer-copy">
        &copy; <?= (int) $year ?> <?= htmlspecialchars($systemTitle) ?>. All rights reserved.
    </p>
</footer>
