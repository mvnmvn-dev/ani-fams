<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Visitor Badge</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; margin: 0; padding: 2rem; }
        .badge {
            max-width: 320px; margin: 0 auto; background: #fff; border: 1px solid #d1d5db;
            border-radius: 1rem; padding: 1.5rem; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,.08);
        }
        .photo { width: 120px; height: 120px; object-fit: cover; border-radius: 999px; border: 3px solid #142E4C; }
        .photo-placeholder {
            width: 120px; height: 120px; border-radius: 999px; margin: 0 auto;
            background: #e5e7eb; display: grid; place-items: center; color: #6b7280; font-size: 12px;
        }
        h1 { font-size: 1.25rem; margin: 1rem 0 0.25rem; color: #111827; }
        .company { color: #6b7280; font-size: 0.875rem; margin: 0; }
        .code { margin-top: 1rem; font-family: ui-monospace, monospace; font-weight: 700; letter-spacing: 0.06em; color: #142E4C; }
        .qr { margin-top: 1rem; }
        .actions { margin-top: 1.25rem; }
        .actions a { color: #142E4C; font-size: 0.875rem; }
        @media print { .actions { display: none; } body { background: #fff; } }
    </style>
</head>
<body>
@php
    $name = $visit['visitor_name'] ?? 'Visitor';
    $company = $visit['visitor_company'] ?? '';
    $badge = $visit['badge_code'] ?? '';
    $photo = $visit['visitor_photo'] ?? null;
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($badge);
@endphp
<div class="badge">
    @if ($photo)
        <img src="{{ $photo }}" alt="Visitor photo" class="photo">
    @else
        <div class="photo-placeholder">No photo</div>
    @endif
    <h1>{{ $name }}</h1>
    <p class="company">{{ $company ?: 'Guest' }}</p>
    <div class="code">{{ $badge ?: 'NO BADGE' }}</div>
    @if ($badge)
        <div class="qr">
            <img src="{{ $qrUrl }}" width="180" height="180" alt="QR {{ $badge }}">
        </div>
    @endif
    <div class="actions">
        <a href="{{ route('visitors.index') }}">Back to visitors</a>
        ·
        <a href="#" onclick="window.print(); return false;">Print</a>
    </div>
</div>
</body>
</html>
