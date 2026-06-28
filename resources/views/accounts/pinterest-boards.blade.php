<x-app-layout>
    <x-pp-page-header
        title="Connect Pinterest Boards"
        description="Select one or more boards under {{ $account->name }} to publish pins from PixFlow."
    >
        <x-slot name="actions">
            <a href="{{ route('accounts.index') }}" class="pp-btn-secondary text-sm py-2">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Back to Accounts
            </a>
        </x-slot>
    </x-pp-page-header>

    @if($pinterestSandbox)
        <div class="pp-card p-4 mb-6 border border-amber-200 bg-amber-50/80 text-sm text-amber-950">
            <p class="font-semibold mb-1">Pinterest Trial / Sandbox mode</p>
            <p class="text-xs leading-relaxed">Only boards visible to the <strong>API sandbox</strong> appear here. Trial apps must use Sandbox to publish. For live pinterest.com boards with public pins, apply for <strong>Standard access</strong> at Pinterest Developers and turn Sandbox off.</p>
        </div>
    @else
        <div class="pp-card p-4 mb-6 border border-emerald-200 bg-emerald-50/80 text-sm text-emerald-950">
            <p class="font-semibold mb-1">Pinterest Live mode</p>
            <p class="text-xs leading-relaxed">Publishing uses <code>api.pinterest.com</code>. Your Pinterest app must have <strong>Standard access</strong> — Trial apps cannot create production pins (error code 29).</p>
        </div>
    @endif

    @if($loadError)
        <div class="pp-alert-error mb-6">{{ $loadError }}</div>
    @endif

    <div class="pp-card overflow-hidden max-w-3xl">
        <div class="pp-card-header flex items-center justify-between gap-4">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-[#131b2e]">Available Boards</h3>
            <a href="{{ route('accounts.pinterest.boards', $account->id) }}" class="text-xs font-semibold text-[#004ac6] flex items-center gap-1 hover:underline">
                <span class="material-symbols-outlined text-[16px]">refresh</span>
                Refresh
            </a>
        </div>

        @if(empty($boards) && !$loadError)
            <div class="p-10 text-center text-[#737686]">
                <span class="material-symbols-outlined text-5xl mb-3 block opacity-40">dashboard</span>
                <p class="font-semibold text-[#131b2e] mb-1">No boards found</p>
                <p class="text-sm max-w-md mx-auto leading-relaxed">
                    @if($pinterestSandbox)
                        Create a board in the Pinterest sandbox environment, then click Refresh.
                    @else
                        Create a board on Pinterest, then click Refresh.
                    @endif
                </p>
            </div>
        @elseif(!empty($boards))
            <form action="{{ route('accounts.pinterest.boards.save', $account->id) }}" method="POST">
                @csrf
                <div class="divide-y divide-[#c3c6d7]">
                    @foreach($boards as $board)
                        @php
                            $boardId = (string) ($board['id'] ?? '');
                            $cover = $board['media']['image_cover_url'] ?? $account->avatar_url;
                            $privacy = $board['privacy'] ?? 'PUBLIC';
                            $isSelected = in_array($boardId, $selectedBoardIds, true);
                        @endphp
                        <label class="flex items-center gap-4 p-4 cursor-pointer hover:bg-[#faf8ff] transition-colors has-[:checked]:bg-[#f2f3ff]">
                            <input
                                type="checkbox"
                                name="board_ids[]"
                                value="{{ $boardId }}"
                                class="rounded text-[#BD081C] focus:ring-[#BD081C]"
                                @checked($isSelected)
                            >
                            <img src="{{ $cover }}" alt="" class="w-14 h-14 rounded-lg border border-[#c3c6d7] object-cover shrink-0">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm text-[#131b2e] truncate">{{ $board['name'] ?? 'Untitled board' }}</p>
                                <p class="text-xs text-[#737686] truncate">{{ $account->name }} · {{ $board['description'] ?? 'No description' }}</p>
                                <span class="pp-status-chip bg-[#BD081C]/10 text-[#BD081C] mt-1 inline-flex">{{ ucfirst(strtolower($privacy)) }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="p-4 border-t border-[#c3c6d7] bg-[#faf8ff] flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-[#737686]">Select all boards you want available when creating posts. Existing posts are kept when you change this list.</p>
                    <button type="submit" class="pp-btn-primary bg-[#BD081C] hover:bg-[#a00718]">
                        <span class="material-symbols-outlined text-[18px]">check</span>
                        Save boards
                    </button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
