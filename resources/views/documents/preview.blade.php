@extends('layouts.app')

@section('content')
<div class="animate-fade-in-up space-y-6 max-w-4xl">
    <?php
    render_page_header([
        'title' => $pageTitle ?? 'Document Preview',
        'subtitle' => $doc['document_number'] ?? '',
        'actions' => function () use ($doc) {
            ?>
            <a href="<?= route('documents.index', ['open' => $doc['id'] ?? null]) ?>" class="btn-secondary btn-sm">Back</a>
            <?php
        },
    ]);
    ?>

    @if (($mode ?? '') === 'text')
        <div class="bg-white border border-ink/10 rounded-xl p-6 shadow-sm">
            <pre class="text-sm whitespace-pre-wrap font-mono text-ink/80 overflow-x-auto">{{ $content }}</pre>
        </div>
    @elseif (($mode ?? '') === 'download')
        <div class="module-insight-panel">
            <h3 class="module-insight-title">Preview not available</h3>
            <p class="module-insight-sub">This file type cannot be shown inline. Download it instead.</p>
            @if (!empty($downloadUrl))
                <p class="mt-4">
                    <a href="{{ $downloadUrl }}" class="btn-primary btn-sm" target="_blank">Download file</a>
                </p>
            @endif
        </div>
    @endif
</div>
@endsection
