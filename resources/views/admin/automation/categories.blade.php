<x-app-layout>
    <div class="pp-automation-page">
        @include('admin.automation._nav')

        <div class="pp-auto-mode-toggle" role="tablist" aria-label="Content mode">
            <a href="{{ route('admin.automation.categories', ['mode' => 'manual']) }}"
               class="pp-auto-mode-toggle-btn {{ $mode === 'manual' ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">edit_note</span>
                Manual
            </a>
            <a href="{{ route('admin.automation.categories', ['mode' => 'ai']) }}"
               class="pp-auto-mode-toggle-btn {{ $mode === 'ai' ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">auto_awesome</span>
                AI import
            </a>
        </div>

        @if($mode === 'ai')
            @include('admin.automation._mobile-bar', [
                'default' => (session('generated_bundle') || old('content_bundle'))
                    ? 'import'
                    : ($errors->has('category_brief') ? 'generate' : 'list'),
                'tabs' => [
                    ['id' => 'list', 'label' => 'List', 'icon' => 'folder_open'],
                    ['id' => 'generate', 'label' => 'Generate', 'icon' => 'bolt'],
                    ['id' => 'import', 'label' => 'Import', 'icon' => 'data_object'],
                ],
                'radioName' => 'pp-auto-cat-ai',
            ])
        @else
            @include('admin.automation._mobile-bar', [
                'default' => $errors->any() ? 'create' : 'list',
                'tabs' => [
                    ['id' => 'list', 'label' => 'List', 'icon' => 'folder_open'],
                    ['id' => 'create', 'label' => 'Add', 'icon' => 'add'],
                ],
                'radioName' => 'pp-auto-cat-manual',
            ])
        @endif

        <div class="pp-auto-split">
            <div class="pp-auto-split-forms">
                @if($mode === 'ai')
                    <div class="pp-auto-panel space-y-5" data-pp-auto-panel-id="generate">
                        <div class="pp-auto-panel-head xl:hidden">
                            <button type="button" class="pp-auto-panel-back" data-pp-auto-panel="list">
                                <span class="material-symbols-outlined">arrow_back</span>
                                List
                            </button>
                            <h3 class="font-display font-bold text-[var(--pp-text)]">Generate with AI</h3>
                        </div>
                        <div class="pp-card p-5 sm:p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-[#4648d4] to-[#004ac6] text-white flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined">psychology</span>
                                </div>
                                <div>
                                    <h3 class="font-display font-bold text-[var(--pp-text)]">1. Describe your niche</h3>
                                    <p class="text-xs text-[var(--pp-text-muted)]">AI generates category + 7–10 topics</p>
                                </div>
                            </div>
                            <form action="{{ route('admin.automation.categories.generate') }}" method="POST" class="space-y-4">
                                @csrf
                                <x-automation-field label="Category brief" hint="e.g. Health & wellness for women over 40, evidence-based tips, Pinterest hooks">
                                    <textarea name="category_brief" id="category-brief" class="pp-input min-h-[100px]" placeholder="Women's hormonal health and skincare…" required>{{ old('category_brief') }}</textarea>
                                </x-automation-field>
                                @error('category_brief')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <button type="submit" class="pp-btn-primary w-full py-2.5 justify-center">
                                    <span class="material-symbols-outlined text-[18px]">bolt</span>
                                    Generate JSON with AI
                                </button>
                            </form>
                        </div>
                        <div class="pp-card p-5 sm:p-6">
                            <p class="font-display font-bold text-[var(--pp-text)] mb-1">Or use external AI</p>
                            <p class="text-xs text-[var(--pp-text-muted)] mb-3 leading-relaxed">Copy this meta-prompt into Cursor or ChatGPT, then paste JSON into Import.</p>
                            <textarea id="content-meta-prompt" class="pp-input min-h-[120px] font-mono text-[10px] leading-relaxed mb-3 bg-[#0f172a] text-emerald-300 border-[#1e293b]" readonly spellcheck="false">{{ $contentMetaPrompt }}</textarea>
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('content-meta-prompt').value); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy meta-prompt', 2000)" class="pp-btn-secondary w-full text-sm py-2.5 justify-center">
                                Copy meta-prompt
                            </button>
                        </div>
                    </div>

                    <div class="pp-auto-panel space-y-5" data-pp-auto-panel-id="import">
                        <div class="pp-auto-panel-head xl:hidden">
                            <button type="button" class="pp-auto-panel-back" data-pp-auto-panel="list">
                                <span class="material-symbols-outlined">arrow_back</span>
                                List
                            </button>
                            <h3 class="font-display font-bold text-[var(--pp-text)]">Import JSON</h3>
                        </div>
                        <div class="pp-card p-5 sm:p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-11 h-11 rounded-xl bg-[var(--pp-surface-2)] text-[var(--pp-primary)] flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined">data_object</span>
                                </div>
                                <div>
                                    <h3 class="font-display font-bold text-[var(--pp-text)]">2. Save category & topics</h3>
                                    <p class="text-xs text-[var(--pp-text-muted)]">Paste or edit JSON, then import</p>
                                </div>
                            </div>
                            <form action="{{ route('admin.automation.categories.import') }}" method="POST" class="space-y-4">
                                @csrf
                                <x-automation-field label="Content bundle (JSON)" hint='Needs "category" + "topics" array.'>
                                    <textarea name="content_bundle" id="content-bundle-editor" class="pp-input min-h-[240px] sm:min-h-[320px] font-mono text-[11px] leading-relaxed bg-[#0f172a] text-emerald-100 border-[#1e293b]" spellcheck="false">{{ $contentBundleJson }}</textarea>
                                </x-automation-field>
                                @error('content_bundle')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <div class="flex flex-col gap-2">
                                    <button type="submit" class="pp-btn-primary w-full py-2.5 justify-center">
                                        <span class="material-symbols-outlined text-[18px]">save</span>
                                        Save category & topics
                                    </button>
                                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('content-bundle-editor').value)" class="pp-btn-secondary w-full text-sm py-2.5">Copy JSON</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="pp-auto-panel" data-pp-auto-panel-id="create">
                        <div class="pp-auto-panel-head xl:hidden">
                            <button type="button" class="pp-auto-panel-back" data-pp-auto-panel="list">
                                <span class="material-symbols-outlined">arrow_back</span>
                                List
                            </button>
                            <h3 class="font-display font-bold text-[var(--pp-text)]">New category</h3>
                        </div>
                        <div class="pp-card p-5 sm:p-6 xl:sticky xl:top-6">
                            <div class="hidden xl:flex items-center gap-3 mb-5">
                                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-[#004ac6] to-[#4648d4] text-white flex items-center justify-center">
                                    <span class="material-symbols-outlined">add</span>
                                </div>
                                <div>
                                    <h3 class="font-display font-bold text-[var(--pp-text)]">New category</h3>
                                    <p class="text-xs text-[var(--pp-text-muted)]">One niche per category</p>
                                </div>
                            </div>
                            <form action="{{ route('admin.automation.categories.store') }}" method="POST" class="space-y-4">
                                @csrf
                                <x-automation-field label="Category name" hint="Shown in campaign dropdowns.">
                                    <input type="text" name="name" class="pp-input" placeholder="Health & Wellness" required>
                                </x-automation-field>
                                <x-automation-field label="Niche description" hint="Injected into every AI prompt.">
                                    <textarea name="niche_prompt" class="pp-input min-h-[90px]" placeholder="Evidence-based wellness tips…"></textarea>
                                </x-automation-field>
                                <x-automation-field label="Safety rules" hint="Hard limits for AI output.">
                                    <textarea name="safety_rules" class="pp-input min-h-[70px]" placeholder="No medical diagnoses…"></textarea>
                                </x-automation-field>
                                <x-automation-field label="Target audience">
                                    <input type="text" name="audience" class="pp-input" placeholder="Women 25–45 interested in wellness">
                                </x-automation-field>
                                <label class="flex items-center gap-2.5 text-sm p-3 rounded-lg bg-[var(--pp-surface-soft)] border border-[var(--pp-border-soft)] text-[var(--pp-text)]">
                                    <input type="checkbox" name="is_active" value="1" checked>
                                    <span>Active category</span>
                                </label>
                                <button type="submit" class="pp-btn-primary w-full py-2.5">Create category</button>
                            </form>
                            <p class="text-xs text-[var(--pp-text-muted)] mt-4 text-center">
                                Want AI to draft topics too?
                                <a href="{{ route('admin.automation.categories', ['mode' => 'ai']) }}" class="text-[var(--pp-primary)] font-semibold hover:underline">Use AI JSON import →</a>
                            </p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="pp-auto-panel pp-auto-split-list is-open" data-pp-auto-panel-id="list">
                <div class="pp-card pp-auto-dense-list overflow-hidden">
                    <div class="pp-auto-dense-list-head">
                        <div>
                            <p class="pp-auto-card-head-title">Categories</p>
                            <p class="pp-auto-card-head-sub">{{ $categories->total() }} total · tap to open topics</p>
                        </div>
                    </div>
                    <div class="pp-auto-dense-list-body" id="pp-categories-list" data-pp-paginated-list>
                        @if($categories->total() === 0)
                            @include('admin.automation._empty', [
                                'icon' => 'folder_open',
                                'title' => 'No categories yet',
                                'description' => $mode === 'ai'
                                    ? 'Tap Generate or Import above to create a category with topics.'
                                    : 'Tap Add above to create your first content category.',
                            ])
                        @else
                            @foreach($categories as $category)
                            <div class="pp-auto-dense-row">
                                <a href="{{ route('admin.automation.topics', $category) }}" class="pp-auto-dense-main">
                                    <span class="pp-auto-dense-avatar">{{ strtoupper(substr($category->name, 0, 1)) }}</span>
                                    <span class="pp-auto-dense-text min-w-0">
                                        <span class="pp-auto-dense-title truncate">{{ $category->name }}</span>
                                        <span class="pp-auto-dense-meta">
                                            {{ $category->topics_count }} topics
                                            @if(!$category->is_active)<span class="text-amber-600"> · Inactive</span>@endif
                                        </span>
                                    </span>
                                    <span class="pp-auto-dense-go">
                                        Topics
                                        <span class="material-symbols-outlined">chevron_right</span>
                                    </span>
                                </a>
                                <details class="pp-category-edit pp-auto-dense-edit">
                                    <summary class="pp-auto-dense-edit-sum">
                                        <span class="material-symbols-outlined">tune</span>
                                        Edit
                                    </summary>
                                    <form action="{{ route('admin.automation.categories.update', $category) }}" method="POST" class="pp-auto-dense-edit-form space-y-4">
                                        @csrf @method('PUT')
                                        <x-automation-field label="Category name">
                                            <input type="text" name="name" class="pp-input" value="{{ $category->name }}" required>
                                        </x-automation-field>
                                        <x-automation-field label="Target audience">
                                            <input type="text" name="audience" class="pp-input" value="{{ $category->audience }}">
                                        </x-automation-field>
                                        <x-automation-field label="Niche description">
                                            <textarea name="niche_prompt" class="pp-input min-h-[70px]">{{ $category->niche_prompt }}</textarea>
                                        </x-automation-field>
                                        <x-automation-field label="Safety rules">
                                            <textarea name="safety_rules" class="pp-input min-h-[60px]">{{ $category->safety_rules }}</textarea>
                                        </x-automation-field>
                                        <div class="flex flex-wrap gap-3 items-center">
                                            <label class="flex items-center gap-2 text-sm text-[var(--pp-text)]"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Active</label>
                                            <button type="submit" class="pp-btn-secondary text-sm py-2">Save</button>
                                        </div>
                                    </form>
                                </details>
                            </div>
                            @endforeach
                        @endif
                    </div>
                    @if($categories->total() > 0)
                        <div class="px-4 sm:px-5 pb-4">
                            <x-pp-pagination :paginator="$categories" :per-page="$perPage" list-target="#pp-categories-list" />
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @if($mode === 'ai')
        @push('scripts')
            <script>
                (function () {
                    const briefEl = document.getElementById('category-brief');
                    const metaEl = document.getElementById('content-meta-prompt');
                    if (!briefEl || !metaEl) return;
                    const base = metaEl.value;
                    briefEl.addEventListener('input', function () {
                        const brief = this.value.trim() || '(describe your niche — audience, tone, platform focus)';
                        metaEl.value = base.replace(
                            /## User category brief[\s\S]*?(?=\n## )/,
                            '## User category brief\n' + brief + '\n'
                        );
                    });
                })();
            </script>
        @endpush
    @endif
</x-app-layout>
