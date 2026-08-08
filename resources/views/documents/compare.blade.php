@extends('layouts.app')

@section('content')
<div class="animate-fade-in-up space-y-6">
    <?php
    render_page_header([
        'title' => 'Compare versions — ' . ($doc['title'] ?? 'Document'),
        'subtitle' => 'v' . ($left['version_no'] ?? '') . ' vs v' . ($right['version_no'] ?? ''),
        'actions' => function () use ($doc) {
            ?>
            <a href="<?= route('documents.index', ['open' => $doc['id'] ?? null]) ?>" class="btn-secondary btn-sm">Back</a>
            <?php
        },
    ]);
    ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white border border-ink/10 rounded-xl p-5 shadow-sm">
            <h3 class="font-bold text-ink mb-3">Version {{ $left['version_no'] ?? '' }}</h3>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between gap-2"><dt class="text-ink/50">Checksum</dt><dd class="font-mono text-xs break-all">{{ $metaDiff['checksum']['left'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-ink/50">Size</dt><dd>{{ isset($metaDiff['size']['left']) ? number_format($metaDiff['size']['left']) . ' bytes' : '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-ink/50">Path</dt><dd class="font-mono text-xs break-all">{{ $metaDiff['path']['left'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-ink/50">Uploaded</dt><dd>{{ $metaDiff['uploaded_at']['left'] ?? '—' }}</dd></div>
                <div><dt class="text-ink/50 mb-1">Notes</dt><dd>{{ $metaDiff['change_notes']['left'] ?? '—' }}</dd></div>
            </dl>
        </div>
        <div class="bg-white border border-ink/10 rounded-xl p-5 shadow-sm">
            <h3 class="font-bold text-ink mb-3">Version {{ $right['version_no'] ?? '' }}</h3>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between gap-2"><dt class="text-ink/50">Checksum</dt><dd class="font-mono text-xs break-all">{{ $metaDiff['checksum']['right'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-ink/50">Size</dt><dd>{{ isset($metaDiff['size']['right']) ? number_format($metaDiff['size']['right']) . ' bytes' : '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-ink/50">Path</dt><dd class="font-mono text-xs break-all">{{ $metaDiff['path']['right'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-ink/50">Uploaded</dt><dd>{{ $metaDiff['uploaded_at']['right'] ?? '—' }}</dd></div>
                <div><dt class="text-ink/50 mb-1">Notes</dt><dd>{{ $metaDiff['change_notes']['right'] ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="module-insight-panel">
        <p class="text-sm">Checksums {{ !empty($metaDiff['checksum']['same']) ? 'match' : 'differ' }}.</p>
    </div>

    @if (!empty($textComparable) && is_array($diff))
        <div class="bg-white border border-ink/10 rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-3 border-b border-ink/10 bg-gray-50/50 font-bold text-ink">Text diff</div>
            <div class="p-4 font-mono text-xs space-y-1 max-h-[28rem] overflow-y-auto">
                @foreach ($diff as $line)
                    @if (($line['type'] ?? '') === 'same')
                        <div class="text-ink/50">{{ $line['text'] ?? '' }}</div>
                    @elseif (($line['type'] ?? '') === 'add')
                        <div class="bg-brand-50 text-brand-800 px-1">+ {{ $line['right'] ?? '' }}</div>
                    @elseif (($line['type'] ?? '') === 'del')
                        <div class="bg-accent-50 text-accent-800 px-1">− {{ $line['left'] ?? '' }}</div>
                    @else
                        <div class="bg-accent-50 text-accent-800 px-1">− {{ $line['left'] ?? '' }}</div>
                        <div class="bg-brand-50 text-brand-800 px-1">+ {{ $line['right'] ?? '' }}</div>
                    @endif
                @endforeach
            </div>
        </div>
    @else
        <p class="text-sm text-ink/50">Text line diff is only available when both versions are plain text / md / csv / json.</p>
    @endif
</div>
@endsection
