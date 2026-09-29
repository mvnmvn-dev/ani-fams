<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Visitor Badge</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; background: #f4f1ec; margin: 0; padding: 2rem; color: #1a2332; }
        .badge {
            max-width: 340px; margin: 0 auto; background: #fff; border: 1px solid #d8cfc4;
            border-radius: 1rem; padding: 1.5rem; text-align: center; box-shadow: 0 10px 30px rgba(20,46,76,.08);
        }
        .brand { font-size: .65rem; letter-spacing: .16em; text-transform: uppercase; color: #6b7280; margin: 0 0 .75rem; }
        .photo { width: 120px; height: 120px; object-fit: cover; border-radius: 999px; border: 3px solid #142E4C; }
        .photo-placeholder {
            width: 120px; height: 120px; border-radius: 999px; margin: 0 auto;
            background: #e5e7eb; display: grid; place-items: center; color: #6b7280; font-size: 12px;
            font-family: system-ui, sans-serif;
        }
        h1 { font-size: 1.25rem; margin: 1rem 0 0.25rem; color: #142E4C; }
        .company { color: #6b7280; font-size: 0.875rem; margin: 0; }
        .meta { margin-top: .85rem; font-size: .8rem; color: #4b5563; line-height: 1.5; font-family: system-ui, sans-serif; }
        .code { margin-top: 1rem; font-family: ui-monospace, monospace; font-weight: 700; letter-spacing: 0.06em; color: #142E4C; }
        .qr { margin-top: 1rem; }
        .actions { margin-top: 1.25rem; font-family: system-ui, sans-serif; }
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
    $host = $visit['host_name'] ?? '';
    $expires = $visit['badge_expires_at'] ?? null;
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($badge);
@endphp
<div class="badge">
    <p class="brand">Archon Nell · Visitor Pass</p>
    @if ($photo)
        <img src="{{ $photo }}" alt="Visitor photo" class="photo">
    @else
        <div class="photo-placeholder">No photo</div>
    @endif
    <h1>{{ $name }}</h1>
    <p class="company">{{ $company ?: 'Guest' }}</p>
    <div class="meta">
        @if ($host) Host: {{ $host }}<br>@endif
        @if ($expires) Expires: {{ \Illuminate\Support\Carbon::parse($expires)->format('M j, Y g:ia') }}@endif
    </div>
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
