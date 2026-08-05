<?php
$pageTitle = 'Not found';
require __DIR__ . '/../partials/header.php';
?>
<div class="max-w-lg mx-auto mt-24 text-center">
    <p class="font-display text-3xl text-ink mb-2">404 — Page not found</p>
    <p class="text-ink/60 text-sm">The page you're looking for doesn't exist.</p>
    <a href="<?= url('/dashboard') ?>" class="inline-block mt-6 text-sm text-brand-700 underline underline-offset-2">Back to dashboard</a>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
