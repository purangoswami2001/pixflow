<x-app-layout>
    <div class="pp-automation-page">
        <x-pp-page-header title="Prompt Studio" description="AI instructions for each pipeline step — with JSON schemas and a meta-prompt to generate all templates.">
            <x-slot name="breadcrumb"><span>Admin</span><span>/</span><span class="text-[#004ac6]">Prompt Studio</span></x-slot>
        </x-pp-page-header>
        @include('admin.automation._nav')

        @php $activePlatform = request('platform', $templates->first()?->platform ?? 'pinterest'); @endphp
        @php $promptView = request('view', 'individual'); @endphp

        <div class="pp-card p-2 mb-6 flex flex-wrap gap-2">
            <a href="{{ route('admin.automation.prompts', array_filter(['platform' => $activePlatform, 'view' => 'individual'])) }}"
               class="pp-automation-platform-tab {{ $promptView === 'individual' ? 'is-active pp-platform-tab--facebook' : '' }}">
                <span class="material-symbols-outlined text-[18px]">edit_note</span>
                Per template
            </a>
            <a href="{{ route('admin.automation.prompts', ['view' => 'bulk']) }}"
               class="pp-automation-platform-tab {{ $promptView === 'bulk' ? 'is-active pp-platform-tab--instagram' : '' }}">
                <span class="material-symbols-outlined text-[18px]">data_object</span>
                Bulk JSON editor
            </a>
        </div>

        @if($promptView === 'bulk')
            <div class="pp-card overflow-hidden">
                <div class="pp-auto-card-head">
                    <div>
                        <p class="pp-auto-card-head-title">All prompts & schemas</p>
                        <p class="pp-auto-card-head-sub">Edit every template at once. Includes schemas, placeholders, image presets, image styles, and templates — same format as meta-prompt output.</p>
                    </div>
                    <button type="button" id="reload-bundle-btn" class="pp-btn-secondary text-sm py-2">Reload from server</button>
                </div>
                <form action="{{ route('admin.automation.prompts.bulk') }}" method="POST" class="p-5 space-y-4">
                    @csrf @method('PUT')
                    <x-automation-field label="Prompt bundle (JSON)" hint='Full bundle: version, schemas, placeholders, image_presets, default_image_presets, image_styles, default_image_styles, templates[]. Paste meta-prompt output here.'>
                        <textarea name="prompt_bundle" id="prompt-bundle-editor" class="pp-input min-h-[480px] font-mono text-[11px] leading-relaxed bg-[#0f172a] text-emerald-100 border-[#1e293b]" spellcheck="false">{{ old('prompt_bundle', $promptBundleJson) }}</textarea>
                    </x-automation-field>
                    @error('prompt_bundle')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="pp-btn-primary py-2.5 px-6">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            Save all templates
                        </button>
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('prompt-bundle-editor').value)" class="pp-btn-secondary py-2.5 px-6">Copy JSON</button>
                    </div>
                </form>
            </div>
            @push('scripts')
            <script>
                document.getElementById('reload-bundle-btn')?.addEventListener('click', () => {
                    document.getElementById('prompt-bundle-editor').value = @json($promptBundleJson);
                });
            </script>
            @endpush
        @else
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 space-y-5">
                <div class="pp-card pp-auto-platform-tabs">
                    @foreach($templates->pluck('platform')->unique() as $platform)
                        @php
                            $icons = ['pinterest' => 'push_pin', 'facebook' => 'groups', 'instagram' => 'photo_camera'];
                        @endphp
                        <a href="{{ route('admin.automation.prompts', ['platform' => $platform]) }}"
                           class="pp-automation-platform-tab pp-platform-tab--{{ $platform }} {{ $activePlatform === $platform ? 'is-active' : '' }}">
                            <span class="material-symbols-outlined text-[18px]">{{ $icons[$platform] ?? 'share' }}</span>
                            <span class="capitalize">{{ $platform }}</span>
                        </a>
                    @endforeach
                </div>

                @foreach($templates->where('platform', $activePlatform)->groupBy('step') as $step => $group)
                    @php $schema = $schemas[$step] ?? null; @endphp
                    @foreach($group as $template)
                        <div class="pp-card overflow-hidden" id="template-{{ $template->id }}">
                            <div class="pp-auto-card-head">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#004ac6] to-[#4648d4] text-white flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[20px]">
                                            {{ $step === 'idea' ? 'lightbulb' : ($step === 'copy' ? 'edit_note' : 'palette') }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="pp-automation-step-badge">{{ $step }}</span>
                                            <p class="font-semibold text-[#131b2e]">{{ $template->name }}</p>
                                        </div>
                                        @if($schema)
                                            <p class="text-xs text-[#737686] mt-0.5">{{ $schema['description'] }}</p>
                                        @endif
                                    </div>
                                </div>
                                @if($schema)
                                    <details>
                                        <summary class="text-xs font-semibold text-[#004ac6] cursor-pointer">JSON schema</summary>
                                        <pre class="pp-automation-log-pre mt-2 text-[10px]">{{ json_encode($schema['keys'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @endif
                            </div>
                            <form action="{{ route('admin.automation.prompts.update', $template) }}" method="POST" class="p-5 space-y-4">
                                @csrf @method('PUT')
                                <label class="flex items-center gap-2.5 text-sm p-3 rounded-lg bg-[#fafbff] border border-[#e8eaf5] cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" @checked($template->is_active) class="rounded border-[#c3c6d7] text-[#004ac6]">
                                    <span><strong>Active</strong> — used for {{ $activePlatform }} generation</span>
                                </label>
                                <x-automation-field label="Prompt template" hint="Instruct AI to return valid JSON with the schema keys above.">
                                    <textarea name="template" class="pp-input min-h-[200px] font-mono text-xs leading-relaxed" spellcheck="false">{{ $template->template }}</textarea>
                                </x-automation-field>
                                <button type="submit" class="pp-btn-primary text-sm py-2">Save template</button>
                            </form>
                        </div>
                    @endforeach
                @endforeach
            </div>

            <div class="space-y-5 xl:sticky xl:top-6 xl:self-start">
                <div class="pp-card p-5">
                    <p class="font-display font-bold text-[#131b2e] mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#004ac6]">data_object</span>
                        Placeholders
                    </p>
                    <dl class="space-y-2.5 max-h-64 overflow-y-auto pr-1">
                        @foreach($placeholders as $key => $desc)
                            <div class="flex gap-2 text-xs p-2 rounded-lg hover:bg-[#fafbff]">
                                <dt class="font-mono text-[#004ac6] shrink-0 font-bold">{{ '{' }}{{ '{' }}{{ $key }}{{ '}' }}{{ '}' }}</dt>
                                <dd class="text-[#737686]">{{ $desc }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div class="pp-card p-5 border-[#004ac6]/20 bg-gradient-to-b from-[#faf8ff] to-white">
                    <p class="font-display font-bold text-[#131b2e] mb-1">AI meta-prompt</p>
                    <p class="text-xs text-[#737686] mb-3 leading-relaxed">Paste into Cursor or ChatGPT. Returns the full bulk JSON (schemas + image presets + 9 templates) ready to paste into Bulk JSON editor.</p>
                    <textarea id="meta-prompt" class="pp-input min-h-[180px] font-mono text-[10px] leading-relaxed mb-3 bg-[#0f172a] text-emerald-300 border-[#1e293b]" readonly spellcheck="false">{{ $metaPrompt }}</textarea>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('meta-prompt').value); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy meta-prompt', 2000)" class="pp-btn-primary w-full text-sm py-2.5 justify-center">
                        <span class="material-symbols-outlined text-[18px]">content_copy</span>
                        Copy meta-prompt
                    </button>
                </div>
            </div>
        </div>
        @endif
    </div>
</x-app-layout>
