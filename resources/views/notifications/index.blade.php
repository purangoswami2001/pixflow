<x-app-layout>
    <x-pp-page-header title="Notifications" description="Publishing, approvals, AI pipeline, and agent review updates.">
        <x-slot name="breadcrumb">
            <span>Account</span><span>/</span><span class="text-[#004ac6]">Notifications</span>
        </x-slot>
        <x-slot name="actions">
            @if($unreadCount > 0)
                <form action="{{ route('notifications.read-all.form') }}" method="POST">
                    @csrf
                    <button type="submit" class="pp-btn-secondary text-sm py-2">
                        <span class="material-symbols-outlined text-[18px]">done_all</span>
                        Mark all read
                    </button>
                </form>
            @endif
        </x-slot>
    </x-pp-page-header>

    <div class="pp-card overflow-hidden">
        <div class="pp-card-header flex items-center justify-between gap-3">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-[#131b2e]">All activity</h3>
            <span class="pp-status-chip bg-[#f2f3ff] text-[#434655]">{{ $unreadCount }} unread</span>
        </div>

        @if($notifications->isEmpty())
            <div class="p-10 text-center">
                <span class="material-symbols-outlined text-[40px] text-[#c3c6d7]">notifications</span>
                <p class="mt-3 text-sm font-semibold text-[#131b2e]">No notifications yet</p>
                <p class="mt-1 text-xs text-[#737686]">You’ll see publish, approval, and AI review updates here.</p>
            </div>
        @else
            <div class="divide-y divide-[#e8eaf5]">
                @foreach($notifications as $n)
                    @php
                        $levelClass = match ($n->level) {
                            'success' => 'pp-notif-level--success',
                            'danger' => 'pp-notif-level--danger',
                            'warning' => 'pp-notif-level--warning',
                            default => 'pp-notif-level--info',
                        };
                    @endphp
                    <a href="{{ $n->link ?: '#' }}"
                       class="pp-notif-item {{ $n->isUnread() ? 'is-unread' : '' }}"
                       @if($n->isUnread())
                           onclick="fetch('{{ route('notifications.read', $n->id) }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'})"
                       @endif>
                        <span class="pp-notif-icon {{ $levelClass }}">
                            <span class="material-symbols-outlined">{{ $service->iconFor($n->type) }}</span>
                        </span>
                        <span class="pp-notif-body">
                            <span class="pp-notif-title">{{ $n->title }}</span>
                            @if($n->body)
                                <span class="pp-notif-text" style="-webkit-line-clamp:3">{{ $n->body }}</span>
                            @endif
                            <span class="pp-notif-time">{{ $n->created_at?->diffForHumans() }} · {{ $n->created_at?->toDayDateTimeString() }}</span>
                        </span>
                        @if($n->isUnread())
                            <span class="pp-notif-dot" title="Unread"></span>
                        @endif
                    </a>
                @endforeach
            </div>

            @if($notifications->hasPages())
                <div class="p-4 border-t border-[#e8eaf5]">
                    {{ $notifications->links() }}
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
