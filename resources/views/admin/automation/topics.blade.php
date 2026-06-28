<x-app-layout>
    <div class="pp-automation-page">
        <x-pp-page-header :title="$category->name" description="Topic headlines the AI picks from when generating posts.">
            <x-slot name="breadcrumb">
                <a href="{{ route('admin.automation.categories') }}" class="hover:text-[#004ac6]">Content</a>
                <span>/</span><span class="text-[#004ac6]">Topics</span>
            </x-slot>
            <x-slot name="actions">
                <a href="{{ route('admin.automation.categories') }}" class="pp-btn-secondary text-sm py-2">← Categories</a>
            </x-slot>
        </x-pp-page-header>
        @include('admin.automation._nav')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="pp-card p-6">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-9 h-9 rounded-lg bg-[#004ac6]/10 text-[#004ac6] flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">add</span>
                    </span>
                    <h3 class="font-display font-bold text-[#131b2e]">Add topic</h3>
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

            <div class="pp-card p-6 lg:col-span-2">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-9 h-9 rounded-lg bg-[#4648d4]/10 text-[#4648d4] flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">upload</span>
                    </span>
                    <h3 class="font-display font-bold text-[#131b2e]">Bulk import</h3>
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
                    <button type="submit" class="pp-btn-secondary py-2.5">Import topics</button>
                </form>
            </div>
        </div>

        <div class="pp-card overflow-hidden">
            <div class="pp-auto-card-head">
                <div>
                    <p class="pp-auto-card-head-title">Topic pool</p>
                    <p class="pp-auto-card-head-sub">{{ $topics->count() }} headlines · click to edit</p>
                </div>
            </div>
            <div class="divide-y divide-[#e8eaf5]">
                @forelse($topics as $topic)
                    <details class="pp-auto-topic-row group">
                        <summary class="px-5 py-4 cursor-pointer flex justify-between gap-3 items-start hover:bg-[#fafbff] transition-colors">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-[#131b2e] leading-snug">{{ $topic->title }}</p>
                                <div class="flex flex-wrap gap-2 items-center mt-2">
                                    <span class="pp-automation-badge capitalize">{{ $topic->topic_type }}</span>
                                    <span class="text-xs text-[#737686]">Used {{ $topic->times_used }}×</span>
                                    @if(!$topic->is_active)<span class="text-xs text-amber-600 font-semibold">Inactive</span>@endif
                                    @if($topic->link_url)
                                        <a href="{{ $topic->link_url }}" class="text-xs text-[#004ac6] font-medium" target="_blank" rel="noopener" onclick="event.stopPropagation()">↗ link</a>
                                    @endif
                                    @if(!empty($topic->metadata['summary']))
                                        <span class="text-xs text-[#737686] line-clamp-1">{{ $topic->metadata['summary'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <span class="material-symbols-outlined text-[#c3c6d7] group-open:rotate-180 transition-transform shrink-0">expand_more</span>
                        </summary>
                        <div class="px-5 pb-5 bg-gradient-to-b from-[#fafbff] to-white border-t border-[#e8eaf5]">
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
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($topic->is_active)> Active</label>
                                @if(!empty($topic->metadata['summary']) || !empty($topic->metadata['angle']) || !empty($topic->metadata['key_points']))
                                    <div class="rounded-lg border border-[#e8eaf5] bg-white p-4 text-sm space-y-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-[#737686]">AI import details</p>
                                        @if(!empty($topic->metadata['summary']))
                                            <p><span class="font-medium text-[#131b2e]">Summary:</span> {{ $topic->metadata['summary'] }}</p>
                                        @endif
                                        @if(!empty($topic->metadata['angle']))
                                            <p><span class="font-medium text-[#131b2e]">Angle:</span> {{ $topic->metadata['angle'] }}</p>
                                        @endif
                                        @if(!empty($topic->metadata['key_points']))
                                            <ul class="list-disc pl-5 text-[#434655]">
                                                @foreach($topic->metadata['key_points'] as $point)
                                                    <li>{{ $point }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @endif
                                <button type="submit" class="pp-btn-primary text-sm py-2">Save changes</button>
                            </form>
                            <form action="{{ route('admin.automation.topics.delete', $topic) }}" method="POST" class="mt-3" data-pp-confirm="Delete this topic?" data-pp-confirm-variant="danger">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 text-sm font-medium">Delete topic</button>
                            </form>
                        </div>
                    </details>
                @empty
                    @include('admin.automation._empty', [
                        'icon' => 'topic',
                        'title' => 'No topics yet',
                        'description' => 'Add at least 10 topic headlines for good variety in generated posts.',
                    ])
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
