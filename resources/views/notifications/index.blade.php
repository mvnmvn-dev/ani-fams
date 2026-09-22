@extends('layouts.app')

@section('content')
<div class="px-6 py-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-ink">{{ $pageTitle }}</h1>
        <form method="POST" action="{{ route('notifications.readAll') }}">
            @csrf
            <button type="submit" class="btn btn-secondary">Mark all as read</button>
        </form>
    </div>

    <?php $priorityGroups = $priorityGroups ?? ['high' => [], 'medium' => [], 'informational' => []]; ?>
    <section class="mb-6 rounded-2xl border border-brand-200 bg-brand-50/30 p-5 shadow-sm">
        <div class="flex items-center gap-2"><span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-brand-700 text-white text-xs font-bold">AI</span><div><h2 class="text-base font-bold text-ink">ARNEL priority grouping</h2><p class="text-xs text-ink/50">Notifications grouped by operational urgency</p></div></div>
        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <?php foreach (['high' => 'High Priority', 'medium' => 'Medium Priority', 'informational' => 'Informational'] as $key => $label): ?><div class="rounded-xl bg-white/75 p-3"><div class="flex justify-between"><strong class="text-sm <?= $key === 'high' ? 'text-accent-700' : 'text-ink' ?>"><?= $label ?></strong><span class="text-xs text-ink/45"><?= count($priorityGroups[$key] ?? []) ?></span></div><p class="mt-1 text-xs text-ink/55"><?= $key === 'high' ? 'Deadlines, expirations, and required actions.' : ($key === 'medium' ? 'Reviews, approvals, and reminders.' : 'General system updates.') ?></p></div><?php endforeach; ?>
        </div>
    </section>

    <div class="bg-white rounded-xl shadow-sm border border-ink/10 overflow-hidden">
        @if($notifications->isEmpty())
            <div class="p-8 text-center text-ink/50">
                You have no notifications.
            </div>
        @else
            <ul class="divide-y divide-ink/10">
                @foreach($notifications as $notif)
                    <li class="p-4 hover:bg-sand/10 transition flex gap-4 {{ $notif->is_read ? 'opacity-70' : '' }}">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                @if(!$notif->is_read)
                                    <span class="w-2 h-2 rounded-full bg-brand-500"></span>
                                @endif
                                <h3 class="font-semibold text-ink">{{ $notif->title }}</h3>
                                <span class="text-xs text-ink/50 ml-auto">{{ $notif->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm text-ink/70 mb-2">{{ $notif->body }}</p>
                            @if($notif->link)
                                <a href="{{ url($notif->link) }}" class="text-sm text-brand-600 hover:underline">View details</a>
                            @endif
                        </div>
                        @if(!$notif->is_read)
                        <div class="self-center">
                            <form method="POST" action="{{ route('notifications.read', $notif->id) }}">
                                @csrf
                                <button type="submit" class="text-xs text-ink/50 hover:text-ink">Mark read</button>
                            </form>
                        </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="p-4 border-t border-ink/10 flex justify-center">
                {{ $notifications->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
