<x-app-layout>
    <div class="pp-automation-page">
        <x-pp-page-header title="Content Library" description="Categories define your niche, safety rules, and topic pool for AI generation.">
            <x-slot name="breadcrumb"><span>Admin</span><span>/</span><span class="text-[#004ac6]">Content</span></x-slot>
        </x-pp-page-header>
        @include('admin.automation._nav')

        <div class="pp-card p-2 mb-6 flex flex-wrap gap-2">
            <a href="{{ route('admin.automation.categories', ['mode' => 'manual']) }}"
               class="pp-automation-platform-tab {{ $mode === 'manual' ? 'is-active pp-platform-tab--facebook' : '' }}">
                <span class="material-symbols-outlined text-[18px]">edit_note</span>
                Manual
            </a>
            <a href="{{ route('admin.automation.categories', ['mode' => 'ai']) }}"
               class="pp-automation-platform-tab {{ $mode === 'ai' ? 'is-active pp-platform-tab--instagram' : '' }}">
                <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
                AI JSON import
            </a>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
            <div class="xl:col-span-4">
                @if($mode === 'ai')
                    <div class="space-y-5 sticky top-6">
                        <div class="pp-card p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-[#4648d4] to-[#004ac6] text-white flex items-center justify-center">
                                    <span class="material-symbols-outlined">psychology</span>
                                </div>
                                <div>
                                    <h3 class="font-display font-bold text-[#131b2e]">1. Describe your niche</h3>
                                    <p class="text-xs text-[#737686]">Cursor generates category + 7–10 topics</p>
                                </div>
                            </div>
                            <form action="{{ route('admin.automation.categories.generate') }}" method="POST" class="space-y-4">
                                @csrf
                                <x-automation-field label="Category brief" hint="e.g. Health & wellness for women over 40, evidence-based tips, Pinterest hooks">
                                    <textarea name="category_brief" id="category-brief" class="pp-input min-h-[100px]" placeholder="Women's hormonal health and skincare — educational pins with empathetic hooks…" required>{{ old('category_brief') }}</textarea>
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

                        <div class="pp-card p-6">
                            <p class="font-display font-bold text-[#131b2e] mb-1">Or use external AI</p>
                            <p class="text-xs text-[#737686] mb-3 leading-relaxed">Copy this meta-prompt into Cursor or ChatGPT. Paste the JSON response into step 2.</p>
                            <textarea id="content-meta-prompt" class="pp-input min-h-[140px] font-mono text-[10px] leading-relaxed mb-3 bg-[#0f172a] text-emerald-300 border-[#1e293b]" readonly spellcheck="false">{{ $contentMetaPrompt }}</textarea>
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('content-meta-prompt').value); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy meta-prompt', 2000)" class="pp-btn-secondary w-full text-sm py-2.5 justify-center">
                                Copy meta-prompt
                            </button>
                        </div>

                        <div class="pp-card p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-11 h-11 rounded-xl bg-[#f2f3ff] text-[#004ac6] flex items-center justify-center">
                                    <span class="material-symbols-outlined">data_object</span>
                                </div>
                                <div>
                                    <h3 class="font-display font-bold text-[#131b2e]">2. Save category & topics</h3>
                                    <p class="text-xs text-[#737686]">Edit JSON, then import</p>
                                </div>
                            </div>
                            <form action="{{ route('admin.automation.categories.import') }}" method="POST" class="space-y-4">
                                @csrf
                                <x-automation-field label="Content bundle (JSON)" hint='Needs "category" + "topics" array (7–10 items with title, type, summary, angle, key_points).'>
                                    <textarea name="content_bundle" id="content-bundle-editor" class="pp-input min-h-[320px] font-mono text-[11px] leading-relaxed bg-[#0f172a] text-emerald-100 border-[#1e293b]" spellcheck="false">{{ $contentBundleJson }}</textarea>
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
                    <div class="pp-card p-6 sticky top-6">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-[#004ac6] to-[#4648d4] text-white flex items-center justify-center">
                                <span class="material-symbols-outlined">add</span>
                            </div>
                            <div>
                                <h3 class="font-display font-bold text-[#131b2e]">New category</h3>
                                <p class="text-xs text-[#737686]">One niche per category</p>
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
                            <label class="flex items-center gap-2.5 text-sm p-3 rounded-lg bg-[#fafbff] border border-[#e8eaf5]">
                                <input type="checkbox" name="is_active" value="1" checked>
                                <span>Active category</span>
                            </label>
                            <button type="submit" class="pp-btn-primary w-full py-2.5">Create category</button>
                        </form>
                        <p class="text-xs text-[#737686] mt-4 text-center">
                            Want AI to draft topics too?
                            <a href="{{ route('admin.automation.categories', ['mode' => 'ai']) }}" class="text-[#004ac6] font-semibold hover:underline">Use AI JSON import →</a>
                        </p>
                    </div>
                @endif
            </div>

            <div class="xl:col-span-8 space-y-5">
                @forelse($categories as $category)
                    <div class="pp-card overflow-hidden">
                        <div class="pp-auto-card-head">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-[#f2f3ff] text-[#004ac6] flex items-center justify-center font-display font-bold">
                                    {{ strtoupper(substr($category->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="pp-auto-card-head-title">{{ $category->name }}</p>
                                    <p class="pp-auto-card-head-sub">{{ $category->topics_count }} topics @if(!$category->is_active) · <span class="text-amber-600">Inactive</span>@endif</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.automation.topics', $category) }}" class="pp-btn-primary text-xs py-2 px-4">
                                Manage topics
                            </a>
                        </div>
                        <form action="{{ route('admin.automation.categories.update', $category) }}" method="POST" class="p-5 space-y-4">
                            @csrf @method('PUT')
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-automation-field label="Category name">
                                    <input type="text" name="name" class="pp-input" value="{{ $category->name }}" required>
                                </x-automation-field>
                                <x-automation-field label="Target audience">
                                    <input type="text" name="audience" class="pp-input" value="{{ $category->audience }}">
                                </x-automation-field>
                            </div>
                            <x-automation-field label="Niche description">
                                <textarea name="niche_prompt" class="pp-input min-h-[70px]">{{ $category->niche_prompt }}</textarea>
                            </x-automation-field>
                            <x-automation-field label="Safety rules">
                                <textarea name="safety_rules" class="pp-input min-h-[60px]">{{ $category->safety_rules }}</textarea>
                            </x-automation-field>
                            <div class="flex flex-wrap gap-4 items-center pt-2">
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Active</label>
                                <button type="submit" class="pp-btn-secondary text-sm py-2">Save changes</button>
                            </div>
                        </form>
                    </div>
                @empty
                    <div class="pp-card">
                        @include('admin.automation._empty', [
                            'icon' => 'folder_open',
                            'title' => 'No categories yet',
                            'description' => $mode === 'ai'
                                ? 'Use AI JSON import on the left to create a category with topics in one step.'
                                : 'Create your first content category using the form on the left.',
                        ])
                    </div>
                @endforelse
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
