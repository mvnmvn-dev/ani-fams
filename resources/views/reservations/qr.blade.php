<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reservation Check-In QR</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; background: #f4f1ec; margin: 0; padding: 2rem; color: #1a2332; }
        .card {
            max-width: 360px; margin: 0 auto; background: #fff; border: 1px solid #d8cfc4;
            border-radius: 1rem; padding: 1.75rem; text-align: center; box-shadow: 0 12px 32px rgba(20,46,76,.08);
        }
        .brand { font-size: .7rem; letter-spacing: .14em; text-transform: uppercase; color: #6b7280; margin: 0 0 .5rem; }
        h1 { font-size: 1.2rem; margin: 0 0 .35rem; color: #142E4C; }
        .meta { color: #6b7280; font-size: .85rem; margin: 0 0 1rem; line-height: 1.45; }
        .code { margin: 1rem 0; font-family: ui-monospace, Consolas, monospace; font-weight: 700; letter-spacing: .08em; color: #142E4C; font-size: 1.05rem; }
        .actions { margin-top: 1.25rem; font-family: system-ui, sans-serif; font-size: .85rem; }
        .actions a { color: #142E4C; }
        @media print { .actions { display: none; } body { background: #fff; } }
    </style>
</head>
<body>
@php
    $code = $reservation['check_in_code'] ?? '';
    $facility = $reservation['facility_name'] ?? ('Facility #'.($reservation['facility_id'] ?? ''));
    $purpose = $reservation['purpose'] ?? 'Reservation';
    $when = trim(($reservation['start_datetime'] ?? '').' – '.($reservation['end_datetime'] ?? ''));
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($code);
@endphp
<div class="card">
    <p class="brand">Archon Nell · FAMS</p>
    <h1>{{ $facility }}</h1>
    <p class="meta">{{ $purpose }}<br>{{ $when }}</p>
    @if ($code)
        <img src="{{ $qrUrl }}" width="200" height="200" alt="Check-in QR">
        <div class="code">{{ $code }}</div>
        <p class="meta">Present this QR at arrival for automatic check-in.</p>
    @else
        <p class="meta">No check-in code yet. Approve the reservation first.</p>
    @endif
    <div class="actions">
        <a href="{{ route('reservations.index') }}">Back</a>
        ·
        <a href="#" onclick="window.print(); return false;">Print</a>
    </div>
</div>
</body>
</html>
