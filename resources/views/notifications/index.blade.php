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
