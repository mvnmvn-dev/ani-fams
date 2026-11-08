@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-6">
    <div class="flex items-start justify-between gap-4">
        <div><a class="text-sm text-brand-700" href="{{ route('visitors.index') }}">← Visitor Management</a>
            <h1 class="mt-2 text-2xl font-bold">{{ $visitor->full_name }} — visit history</h1>
            <p class="text-sm text-ink/60">{{ ucfirst($visitor->visitor_type ?? 'guest') }} · {{ strtoupper($visitor->source_system ?? 'manual') }} · {{ $visits->count() }} visit(s) · {{ $noShows }} no-show(s)</p>
        </div>
    </div>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="module-table w-full"><thead><tr><th>Scheduled</th><th>Host</th><th>Purpose</th><th>Location</th><th>Check-in / out</th><th>Status</th><th>Badge</th></tr></thead>
        <tbody>
        @forelse($visits as $visit)
            <tr><td>{{ $visit->scheduled_at }}</td><td>{{ $visit->host_name }}</td><td>{{ $visit->purpose }}</td><td>{{ trim(($visit->building_name ?? '') . ' ' . ($visit->floor_designation ?? '')) }}</td><td>{{ $visit->check_in_time ?? '—' }}<br>{{ $visit->check_out_time ?? '—' }}</td><td>{{ str_replace('_', ' ', $visit->status) }}</td><td>{{ $visit->badge_code ?? '—' }}</td></tr>
        @empty
            <tr><td colspan="7" class="p-6 text-center text-ink/50">No visits found.</td></tr>
        @endforelse
        </tbody></table>
    </div>
</div>
@endsection
