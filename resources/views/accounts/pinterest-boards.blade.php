<x-app-layout>
    <x-pp-page-header
        title="Connect Pinterest Boards"
        description="Select boards under {{ $account->name }} for publishing pins from PixFlow."
    >
        <x-slot name="actions">
            <a href="{{ route('accounts.index') }}" class="pp-btn-secondary">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                <span class="pp-action-label">Back</span>
            </a>
            <a href="{{ route('accounts.pinterest.boards', $account->id) }}" class="pp-btn-secondary">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
                <span class="pp-action-label">Refresh</span>
            </a>
        </x-slot>
    </x-pp-page-header>

    @if($pinterestSandbox)
        <div class="pp-callout pp-callout--warning mb-4 sm:mb-5">
            <span class="material-symbols-outlined pp-callout-icon shrink-0">info</span>
            <div>
                <p class="font-semibold mb-0.5">Pinterest Trial / Sandbox mode</p>
                <p class="text-xs leading-relaxed opacity-90">Only boards visible to the <strong>API sandbox</strong> appear here. Trial apps must use Sandbox to publish.</p>
            </div>
        </div>
    @else
        <div class="pp-callout mb-4 sm:mb-5" style="border-color: color-mix(in srgb, #10b981 28%, transparent); background: color-mix(in srgb, #10b981 10%, var(--pp-surface));">
            <span class="material-symbols-outlined pp-callout-icon shrink-0" style="color:#059669">verified</span>
            <div>
                <p class="font-semibold mb-0.5">Pinterest Live mode</p>
                <p class="text-xs leading-relaxed opacity-90">Publishing uses <code>api.pinterest.com</code>. Your app needs <strong>Standard access</strong>.</p>
            </div>
        </div>
    @endif

    @if($loadError)
        <div class="pp-alert-error mb-4 sm:mb-5">{{ $loadError }}</div>
    @endif

    <div class="pp-boards-panel pp-card overflow-hidden max-w-3xl">
        <div class="pp-boards-panel-head">
            <div>
                <h3>Available Boards</h3>
                <p>{{ $account->name }}</p>
            </div>
        </div>

        @if(empty($boards) && !$loadError)
            <div class="pp-accounts-empty">
                <span class="material-symbols-outlined">dashboard</span>
                <h3>No boards found</h3>
                <p>
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
                <div class="pp-boards-list">
                    @foreach($boards as $board)
                        @php
                            $boardId = (string) ($board['id'] ?? '');
                            $cover = $board['media']['image_cover_url'] ?? $account->avatar_url;
                            $privacy = $board['privacy'] ?? 'PUBLIC';
                            $isSelected = in_array($boardId, $selectedBoardIds, true);
                        @endphp
                        <label class="pp-board-row">
                            <input
                                type="checkbox"
                                name="board_ids[]"
                                value="{{ $boardId }}"
                                class="pp-board-check"
                                @checked($isSelected)
                            >
                            <img src="{{ $cover }}" alt="" class="pp-board-cover">
                            <div class="pp-board-meta min-w-0 flex-1">
                                <p class="pp-board-name">{{ $board['name'] ?? 'Untitled board' }}</p>
                                <p class="pp-board-desc">{{ $account->name }} · {{ $board['description'] ?? 'No description' }}</p>
                                <span class="pp-status-chip bg-[#BD081C]/10 text-[#BD081C] mt-1 inline-flex">{{ ucfirst(strtolower($privacy)) }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="pp-boards-footer">
                    <p>Select boards available when creating posts. Existing posts are kept when you change this list.</p>
                    <button type="submit" class="pp-btn-primary" style="background:#BD081C;border-color:#BD081C">
                        <span class="material-symbols-outlined text-[18px]">check</span>
                        Save boards
                    </button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
