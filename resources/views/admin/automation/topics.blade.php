<x-app-layout>
    <div class="pp-automation-page">
        @include('admin.automation._nav')

        @include('admin.automation._mobile-bar', [
            'default' => 'list',
            'back' => [
                'url' => route('admin.automation.categories'),
                'label' => 'Categories',
            ],
            'tabs' => [
                ['id' => 'list', 'label' => 'List', 'icon' => 'topic'],
                ['id' => 'add', 'label' => 'Add', 'icon' => 'add'],
                ['id' => 'import', 'label' => 'Import', 'icon' => 'upload'],
            ],
            'radioName' => 'pp-auto-topics',
        ])

        <div class="pp-auto-split">
            <div class="pp-auto-split-forms space-y-5">
                <div class="pp-auto-panel" data-pp-auto-panel-id="add">
                    <div class="pp-auto-panel-head xl:hidden">
                        <button type="button" class="pp-auto-panel-back" data-pp-auto-panel="list">
                            <span class="material-symbols-outlined">arrow_back</span>
                            Topics
                        </button>
                        <h3 class="font-display font-bold text-[var(--pp-text)]">Add topic</h3>
                    </div>
                    <div class="pp-card p-5 sm:p-6">
                        <div class="hidden xl:flex items-center gap-2 mb-4">
                            <span class="w-9 h-9 rounded-lg bg-[var(--pp-primary-muted)] text-[var(--pp-primary)] flex items-center justify-center">
                                <span class="material-symbols-outlined text-[20px]">add</span>
                            </span>
                            <h3 class="font-display font-bold text-[var(--pp-text)]">Add topic</h3>
                        </div>
                        <form action="{{ route('admin.automation.topics.store', $category) }}" method="POST" class="space-y-4">
                            @csrf
                            <x-automation-field label="Topic headline" hint="Core idea AI expands into a full post.">
                                <textarea name="title" class="pp-input min-h-[90px]" placeholder="5 morning stretches that ease desk-job back pain" required></textarea>
                            </x-automation-field>
                            <x-automation-field label="Topic type">
                                <select name="topic_type" class="pp-input">
                                    <option value="educational">Educational</option>
                                    <option value="listicle">Listicle</option>
                                    <option value="tip">Tip</option>
                                    <option value="promotional">Promotional</option>
                                </select>
                            </x-automation-field>
                            <x-automation-field label="Promo link URL" hint="For promotional topics only.">
                                <input type="url" name="link_url" class="pp-input" placeholder="https://yoursite.com/offer">
                            </x-automation-field>
                            <button type="submit" class="pp-btn-primary w-full py-2.5">Add topic</button>
                        </form>
                    </div>
                </div>

                <div class="pp-auto-panel" data-pp-auto-panel-id="import">
                    <div class="pp-auto-panel-head xl:hidden">
                        <button type="button" class="pp-auto-panel-back" data-pp-auto-panel="list">
                            <span class="material-symbols-outlined">arrow_back</span>
                            Topics
                        </button>
                        <h3 class="font-display font-bold text-[var(--pp-text)]">Bulk import</h3>
                    </div>
                    <div class="pp-card p-5 sm:p-6">
                        <div class="hidden xl:flex items-center gap-2 mb-4">
                            <span class="w-9 h-9 rounded-lg bg-[var(--pp-primary-muted)] text-[var(--pp-primary)] flex items-center justify-center">
                                <span class="material-symbols-outlined text-[20px]">upload</span>
                            </span>
                            <h3 class="font-display font-bold text-[var(--pp-text)]">Bulk import</h3>
                        </div>
                        <form action="{{ route('admin.automation.topics.bulk', $category) }}" method="POST" class="space-y-4">
                            @csrf
                            <x-automation-field label="Headlines" hint="One headline per line.">
                                <textarea name="titles" class="pp-input min-h-[130px]" placeholder="10 foods that support better sleep&#10;Why hydration matters…"></textarea>
                            </x-automation-field>
                            <x-automation-field label="Default type">
                                <select name="topic_type" class="pp-input">
                                    <option value="educational">Educational</option>
                                    <option value="listicle">Listicle</option>
                                    <option value="tip">Tip</option>
                                    <option value="promotional">Promotional</option>
                                </select>
                            </x-automation-field>
                            <button type="submit" class="pp-btn-secondary w-full py-2.5 justify-center">Import topics</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="pp-auto-panel pp-auto-split-list is-open" data-pp-auto-panel-id="list">
                <div class="pp-card overflow-hidden">
                    <div class="pp-auto-card-head">
                        <div>
                            <p class="pp-auto-card-head-title">Topic pool</p>
                            <p class="pp-auto-card-head-sub">{{ $topics->total() }} headlines · tap to edit</p>
                        </div>
                    </div>
                    <div class="divide-y divide-[var(--pp-border-soft)]" id="pp-topics-list" data-pp-paginated-list>
                        @if($topics->total() === 0)
                            @include('admin.automation._empty', [
                                'icon' => 'topic',
                                'title' => 'No topics yet',
                                'description' => 'Tap Add or Import above. Aim for at least 10 headlines for good variety.',
                            ])
                        @else
                            @foreach($topics as $topic)
                            <details class="pp-auto-topic-row group">
                                <summary class="pp-topic-summary px-4 sm:px-5 py-3.5 sm:py-4 cursor-pointer flex justify-between gap-3 items-start transition-colors">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-[var(--pp-text)] leading-snug">{{ $topic->title }}</p>
                                        <div class="flex flex-wrap gap-2 items-center mt-2">
                                            <span class="pp-automation-badge capitalize">{{ $topic->topic_type }}</span>
                                            <span class="text-xs text-[var(--pp-text-muted)]">Used {{ $topic->times_used }}×</span>
                                            @if(!$topic->is_active)<span class="text-xs text-amber-600 font-semibold">Inactive</span>@endif
                                            @if($topic->link_url)
                                                <a href="{{ $topic->link_url }}" class="text-xs text-[var(--pp-primary)] font-medium" target="_blank" rel="noopener" onclick="event.stopPropagation()">↗ link</a>
                                            @endif
                                            @if(!empty($topic->metadata['summary']))
                                                <span class="text-xs text-[var(--pp-text-muted)] line-clamp-1">{{ $topic->metadata['summary'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <span class="material-symbols-outlined text-[var(--pp-text-muted)] group-open:rotate-180 transition-transform shrink-0">expand_more</span>
                                </summary>
                                <div class="pp-topic-edit-panel px-4 sm:px-5 pb-5 border-t border-[var(--pp-border-soft)]">
                                    <form action="{{ route('admin.automation.topics.update', $topic) }}" method="POST" class="space-y-4 max-w-2xl pt-4">
                                        @csrf @method('PUT')
                                        <x-automation-field label="Headline">
                                            <textarea name="title" class="pp-input min-h-[70px]" required>{{ $topic->title }}</textarea>
                                        </x-automation-field>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <x-automation-field label="Type">
                                                <select name="topic_type" class="pp-input">
                                                    @foreach(['educational','listicle','tip','promotional'] as $type)
                                                        <option value="{{ $type }}" @selected($topic->topic_type === $type)>{{ ucfirst($type) }}</option>
                                                    @endforeach
                                                </select>
                                            </x-automation-field>
                                            <x-automation-field label="Promo link">
                                                <input type="url" name="link_url" class="pp-input" value="{{ $topic->link_url }}">
                                            </x-automation-field>
                                        </div>
                                        <label class="flex items-center gap-2 text-sm text-[var(--pp-text)]"><input type="checkbox" name="is_active" value="1" @checked($topic->is_active)> Active</label>
                                        @if(!empty($topic->metadata['summary']) || !empty($topic->metadata['angle']) || !empty($topic->metadata['key_points']))
                                            <div class="pp-topic-meta-box rounded-lg border border-[var(--pp-border-soft)] p-4 text-sm space-y-2">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--pp-text-muted)]">AI import details</p>
                                                @if(!empty($topic->metadata['summary']))
                                                    <p><span class="font-medium text-[var(--pp-text)]">Summary:</span> <span class="text-[var(--pp-text-secondary)]">{{ $topic->metadata['summary'] }}</span></p>
                                                @endif
                                                @if(!empty($topic->metadata['angle']))
                                                    <p><span class="font-medium text-[var(--pp-text)]">Angle:</span> <span class="text-[var(--pp-text-secondary)]">{{ $topic->metadata['angle'] }}</span></p>
                                                @endif
                                                @if(!empty($topic->metadata['key_points']))
                                                    <ul class="list-disc pl-5 text-[var(--pp-text-secondary)]">
                                                        @foreach($topic->metadata['key_points'] as $point)
                                                            <li>{{ $point }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </div>
                                        @endif
                                        <button type="submit" class="pp-btn-primary text-sm py-2 w-full sm:w-auto">Save changes</button>
                                    </form>
                                    <form action="{{ route('admin.automation.topics.delete', $topic) }}" method="POST" class="mt-3" data-pp-confirm="Delete this topic?" data-pp-confirm-variant="danger">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 text-sm font-medium hover:underline">Delete topic</button>
                                    </form>
                                </div>
                            </details>
                            @endforeach
                        @endif
                    </div>
                    @if($topics->total() > 0)
                        <div class="px-4 sm:px-5 pb-4">
                            <x-pp-pagination :paginator="$topics" :per-page="$perPage" list-target="#pp-topics-list" />
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
