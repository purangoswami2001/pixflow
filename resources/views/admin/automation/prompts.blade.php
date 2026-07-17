<x-app-layout>
    <div class="pp-automation-page">
        @include('admin.automation._nav')

        @php $activePlatform = request('platform', $templates->first()?->platform ?? 'pinterest'); @endphp
        @php $promptView = request('view', 'individual'); @endphp

        <div class="pp-auto-mode-toggle" role="tablist" aria-label="Prompt editor mode">
            <a href="{{ route('admin.automation.prompts', array_filter(['platform' => $activePlatform, 'view' => 'individual'])) }}"
               class="pp-auto-mode-toggle-btn {{ $promptView === 'individual' ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">edit_note</span>
                Per template
            </a>
            <a href="{{ route('admin.automation.prompts', ['view' => 'bulk']) }}"
               class="pp-auto-mode-toggle-btn {{ $promptView === 'bulk' ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">data_object</span>
                Bulk JSON
            </a>
        </div>

        @if($promptView === 'bulk')
            <div class="pp-card overflow-hidden">
                <div class="pp-auto-card-head flex-col sm:flex-row items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="pp-auto-card-head-title">All prompts & schemas</p>
                        <p class="pp-auto-card-head-sub">Edit every template at once. Paste meta-prompt output here.</p>
                    </div>
                    <button type="button" id="reload-bundle-btn" class="pp-btn-secondary text-sm py-2 w-full sm:w-auto shrink-0">Reload from server</button>
                </div>
                <form action="{{ route('admin.automation.prompts.bulk') }}" method="POST" class="p-4 sm:p-5 space-y-4">
                    @csrf @method('PUT')
                    <x-automation-field label="Prompt bundle (JSON)" hint='Full bundle: version, schemas, placeholders, image_presets, templates[].'>
                        <textarea name="prompt_bundle" id="prompt-bundle-editor" class="pp-input min-h-[280px] sm:min-h-[480px] font-mono text-[11px] leading-relaxed bg-[#0f172a] text-emerald-100 border-[#1e293b]" spellcheck="false">{{ old('prompt_bundle', $promptBundleJson) }}</textarea>
                    </x-automation-field>
                    @error('prompt_bundle')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="flex flex-col sm:flex-row flex-wrap gap-2 sm:gap-3">
                        <button type="submit" class="pp-btn-primary py-2.5 px-6 w-full sm:w-auto justify-center">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            Save all templates
                        </button>
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('prompt-bundle-editor').value)" class="pp-btn-secondary py-2.5 px-6 w-full sm:w-auto justify-center">Copy JSON</button>
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
            @include('admin.automation._mobile-bar', [
                'default' => 'templates',
                'tabs' => [
                    ['id' => 'templates', 'label' => 'Templates', 'icon' => 'edit_note'],
                    ['id' => 'tools', 'label' => 'Tools', 'icon' => 'construction'],
                ],
                'radioName' => 'pp-auto-prompts',
            ])

            <div class="pp-auto-split pp-auto-split--prompts">
                <div class="pp-auto-panel pp-auto-split-list is-open space-y-5" data-pp-auto-panel-id="templates">
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
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#004ac6] to-[#4648d4] text-white flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-[20px]">
                                                {{ $step === 'idea' ? 'lightbulb' : ($step === 'copy' ? 'edit_note' : 'palette') }}
                                            </span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="pp-automation-step-badge">{{ $step }}</span>
                                                <p class="font-semibold text-[var(--pp-text)] truncate">{{ $template->name }}</p>
                                            </div>
                                            @if($schema)
                                                <p class="text-xs text-[var(--pp-text-muted)] mt-0.5 line-clamp-2">{{ $schema['description'] }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    @if($schema)
                                        <details class="shrink-0">
                                            <summary class="text-xs font-semibold text-[var(--pp-primary)] cursor-pointer">Schema</summary>
                                            <pre class="pp-automation-log-pre mt-2 text-[10px]">{{ json_encode($schema['keys'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </details>
                                    @endif
                                </div>
                                <form action="{{ route('admin.automation.prompts.update', $template) }}" method="POST" class="p-4 sm:p-5 space-y-4">
                                    @csrf @method('PUT')
                                    <label class="flex items-center gap-2.5 text-sm p-3 rounded-lg bg-[var(--pp-surface-soft)] border border-[var(--pp-border-soft)] cursor-pointer text-[var(--pp-text)]">
                                        <input type="checkbox" name="is_active" value="1" @checked($template->is_active) class="rounded border-[var(--pp-border)] text-[var(--pp-primary)]">
                                        <span><strong>Active</strong> — used for {{ $activePlatform }} generation</span>
                                    </label>
                                    <x-automation-field label="Prompt template" hint="Instruct AI to return valid JSON with the schema keys above.">
                                        <textarea name="template" class="pp-input min-h-[160px] sm:min-h-[200px] font-mono text-xs leading-relaxed" spellcheck="false">{{ $template->template }}</textarea>
                                    </x-automation-field>
                                    <button type="submit" class="pp-btn-primary text-sm py-2 w-full sm:w-auto">Save template</button>
                                </form>
                            </div>
                        @endforeach
                    @endforeach
                </div>

                <div class="pp-auto-panel pp-auto-split-forms space-y-5" data-pp-auto-panel-id="tools">
                    <div class="pp-auto-panel-head xl:hidden">
                        <button type="button" class="pp-auto-panel-back" data-pp-auto-panel="templates">
                            <span class="material-symbols-outlined">arrow_back</span>
                            Templates
                        </button>
                        <h3 class="font-display font-bold text-[var(--pp-text)]">Tools</h3>
                    </div>
                    <div class="pp-card p-5">
                        <p class="font-display font-bold text-[var(--pp-text)] mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[var(--pp-primary)]">data_object</span>
                            Placeholders
                        </p>
                        <dl class="space-y-2.5 max-h-64 overflow-y-auto pr-1">
                            @foreach($placeholders as $key => $desc)
                                <div class="flex gap-2 text-xs p-2 rounded-lg hover:bg-[var(--pp-hover)]">
                                    <dt class="font-mono text-[var(--pp-primary)] shrink-0 font-bold">{{ '{' }}{{ '{' }}{{ $key }}{{ '}' }}{{ '}' }}</dt>
                                    <dd class="text-[var(--pp-text-muted)]">{{ $desc }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    <div class="pp-card p-5 border-[var(--pp-primary)]/20 bg-[var(--pp-surface-soft)]">
                        <p class="font-display font-bold text-[var(--pp-text)] mb-1">AI meta-prompt</p>
                        <p class="text-xs text-[var(--pp-text-muted)] mb-3 leading-relaxed">Paste into Cursor or ChatGPT. Returns the full bulk JSON ready for Bulk JSON editor.</p>
                        <textarea id="meta-prompt" class="pp-input min-h-[160px] sm:min-h-[180px] font-mono text-[10px] leading-relaxed mb-3 bg-[#0f172a] text-emerald-300 border-[#1e293b]" readonly spellcheck="false">{{ $metaPrompt }}</textarea>
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
