<x-app-layout>
    <x-pp-page-header title="Firm Settings" description="Manage workspace-level capabilities for {{ $organization->name }}." />

    @if(session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="max-w-3xl space-y-6">
        <div class="pp-card overflow-hidden">
            <div class="pp-auto-card-head">
                <div>
                    <p class="pp-auto-card-head-title">AI Mode</p>
                    <p class="pp-auto-card-head-sub">Controls every feature that generates content with AI for this firm</p>
                </div>
                <span class="pp-status-badge {{ $organization->ai_enabled ? 'pp-status-badge--success' : 'pp-status-badge--muted' }}">
                    {{ $organization->ai_enabled ? 'Enabled' : 'Disabled' }}
                </span>
            </div>

            <div class="px-5 py-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl border border-[var(--pp-border-soft)] p-4">
                        <p class="font-semibold text-[var(--pp-text)] mb-1 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px] text-[#004ac6]">auto_awesome</span>
                            Requires AI Mode
                        </p>
                        <ul class="text-xs text-[var(--pp-text-muted)] leading-relaxed list-disc pl-4 space-y-1">
                            <li>Generate with AI (captions, titles, hashtags)</li>
                            <li>AI content library generation</li>
                            <li>AI Automation campaigns &amp; pipeline</li>
                            <li>Auto Review agent</li>
                        </ul>
                    </div>
                    <div class="rounded-xl border border-[var(--pp-border-soft)] p-4">
                        <p class="font-semibold text-[var(--pp-text)] mb-1 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px] text-emerald-600">check_circle</span>
                            Always available
                        </p>
                        <ul class="text-xs text-[var(--pp-text-muted)] leading-relaxed list-disc pl-4 space-y-1">
                            <li>Semi-AI Assist (copy prompts, paste JSON)</li>
                            <li>Manual posts, scheduling &amp; publishing</li>
                            <li>Media library and team management</li>
                        </ul>
                    </div>
                </div>

                @if($organization->ai_enabled && ! $aiConfigured)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        AI Mode is on, but no AI provider is configured for this installation yet. Contact your platform administrator to finish setup.
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.settings.ai') }}" class="flex items-center justify-between gap-4 rounded-xl border border-[var(--pp-border-soft)] bg-[var(--pp-surface-2,#fafbff)] px-4 py-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <p class="font-semibold text-sm text-[var(--pp-text)]">
                            {{ $organization->ai_enabled ? 'Turn off AI Mode' : 'Turn on AI Mode' }}
                        </p>
                        <p class="text-xs text-[var(--pp-text-muted)] mt-0.5">
                            @if($organization->ai_enabled)
                                AI generation and automation will be paused immediately. Nothing is deleted — turn it back on anytime.
                            @else
                                Unlocks AI generation and automation features for owners, admins, and your team.
                            @endif
                        </p>
                    </div>
                    <input type="hidden" name="ai_enabled" value="{{ $organization->ai_enabled ? 0 : 1 }}">
                    <button type="submit" class="{{ $organization->ai_enabled ? 'pp-btn-secondary' : 'pp-btn-primary' }} shrink-0">
                        <span class="material-symbols-outlined text-[18px]">{{ $organization->ai_enabled ? 'toggle_off' : 'toggle_on' }}</span>
                        {{ $organization->ai_enabled ? 'Turn off' : 'Turn on' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="pp-card overflow-hidden">
            <div class="pp-auto-card-head">
                <div>
                    <p class="pp-auto-card-head-title">AI Automation</p>
                    <p class="pp-auto-card-head-sub">Autopilot content pipeline for this firm</p>
                </div>
                <span class="pp-status-badge {{ ($organization->ai_enabled && $organization->automation_enabled) ? 'pp-status-badge--success' : 'pp-status-badge--muted' }}">
                    {{ ($organization->ai_enabled && $organization->automation_enabled) ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <div class="px-5 py-4 text-sm text-[var(--pp-text-muted)] flex items-center justify-between gap-4">
                <p class="text-xs leading-relaxed">
                    @if(! $organization->ai_enabled)
                        Automation requires AI Mode. Enable AI Mode above first.
                    @elseif($organization->automation_enabled)
                        Automation is set up for this firm. Manage campaigns, prompts, and reviews from the automation dashboard.
                    @else
                        AI Mode is on — you can now set up automation with starter prompts and a sample content category.
                    @endif
                </p>
                <a href="{{ route('admin.automation.index') }}" class="pp-btn-secondary text-xs shrink-0 {{ $organization->ai_enabled ? '' : 'opacity-50 pointer-events-none' }}">
                    <span class="material-symbols-outlined text-[16px]">smart_toy</span>
                    Open automation
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
