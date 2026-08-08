@extends('layouts.app')

@section('content')
<div class="animate-fade-in-up space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="font-sans text-2xl font-bold text-ink">Amendment Diff</h1>
            <p class="text-sm text-ink/55">
                <?= htmlspecialchars($result['meta']['left_label'] ?? 'Original') ?>
                →
                <?= htmlspecialchars($result['meta']['right_label'] ?? 'Amendment') ?>
            </p>
        </div>
        <a href="<?= url('/contracts?open=' . $contractId) ?>" class="btn-secondary">Back</a>
    </div>

    <?php if (empty($result['text_comparable'])): ?>
        <div class="module-empty">Attached files are not text-comparable (PDF/binary). Download both documents to compare manually.</div>
    <?php else: ?>
        <div class="bg-white border border-ink/10 rounded-xl overflow-hidden text-sm font-mono">
            <?php foreach (($result['diff'] ?? []) as $line): ?>
                <?php
                    $type = $line['type'] ?? 'same';
                    $cls = match ($type) {
                        'add' => 'bg-brand-50 text-brand-800',
                        'del' => 'bg-accent-50 text-accent-800',
                        'change' => 'bg-amber-50 text-ink',
                        default => 'text-ink/70',
                    };
                ?>
                <div class="px-4 py-1 border-b border-ink/5 <?= $cls ?>">
                    <?php if ($type === 'same'): ?>
                        <?= htmlspecialchars($line['text'] ?? '') ?>
                    <?php elseif ($type === 'add'): ?>
                        + <?= htmlspecialchars($line['right'] ?? '') ?>
                    <?php elseif ($type === 'del'): ?>
                        − <?= htmlspecialchars($line['left'] ?? '') ?>
                    <?php else: ?>
                        − <?= htmlspecialchars($line['left'] ?? '') ?><br>+ <?= htmlspecialchars($line['right'] ?? '') ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
@endsection
