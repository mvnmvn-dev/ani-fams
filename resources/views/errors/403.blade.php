<?php
$pageTitle = 'Access denied';
$showSidebar = auth()->check();
require __DIR__ . '/../partials/header.php';
if ($showSidebar) {
    echo '<div class="flex">';
    require __DIR__ . '/../partials/sidebar.php';
    echo '<main class="flex-1 p-8 max-w-6xl">';
}
?>
<div class="max-w-lg <?= $showSidebar ? '' : 'mx-auto mt-24' ?> text-center">
    <p class="font-display text-3xl text-ink mb-2">403 — Access denied</p>
    <p class="text-ink/60 text-sm">Your role doesn't include the permission needed for this page.
        If you believe this is a mistake, contact your administrator.</p>
    <a href="<?= url('/dashboard') ?>" class="inline-block mt-6 text-sm text-brand-700 underline underline-offset-2">Back to dashboard</a>
</div>
<?php
if ($showSidebar) {
    echo '</main></div>';
}
require __DIR__ . '/../partials/footer.php';
