<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PixFlow — Plan, create, and publish across social</title>
    <meta name="description" content="PixFlow helps teams schedule Facebook, Instagram, and Pinterest posts with AI automation, media tools, approvals, and reliable publishing.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Hanken+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="{{ asset('css/pixflow.css') }}" rel="stylesheet">
</head>
<body class="pp-landing antialiased">
    <header class="pp-landing-nav">
        <a href="/" class="pp-landing-brand" aria-label="PixFlow home">
            <span class="pp-landing-brand-mark">
                <span class="material-symbols-outlined filled text-[22px]">bolt</span>
            </span>
            <span class="pp-landing-brand-name">PixFlow</span>
        </a>
        <div class="flex items-center gap-2 sm:gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="pp-btn-primary text-sm py-2 px-4">Open app</a>
            @else
                <a href="{{ route('login') }}" class="hidden sm:inline text-sm font-semibold text-[#434655] hover:text-[#004ac6] transition">Sign in</a>
                <a href="{{ route('register') }}" class="pp-btn-primary text-sm py-2 px-4">Get started</a>
            @endauth
        </div>
    </header>

    <section class="pp-landing-hero">
        <div class="pp-landing-hero-grid" aria-hidden="true"></div>
        <div class="pp-landing-hero-inner">
            <div>
                <h1 class="pp-landing-kicker">PixFlow</h1>
                <p class="pp-landing-lede">
                    One workspace to plan, approve, and publish social content — with AI automation that keeps your channels moving.
                </p>
                <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="pp-landing-cta">
                    {{ auth()->check() ? 'Go to dashboard' : 'Register your firm' }}
                    <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                </a>
            </div>

            <div class="pp-landing-stage" aria-hidden="true">
                <div class="pp-landing-stage-bar">
                    <span class="pp-landing-stage-dot"></span>
                    <span class="pp-landing-stage-dot"></span>
                    <span class="pp-landing-stage-dot"></span>
                </div>
                <div class="pp-landing-stage-body">
                    <div class="pp-landing-mock-row">
                        <div class="pp-landing-mock-card">
                            <div class="pp-landing-mock-line"></div>
                            <div class="pp-landing-mock-line short"></div>
                        </div>
                        <div class="pp-landing-mock-card">
                            <div class="pp-landing-mock-line"></div>
                            <div class="pp-landing-mock-line short"></div>
                        </div>
                        <div class="pp-landing-mock-card">
                            <div class="pp-landing-mock-line"></div>
                            <div class="pp-landing-mock-line short"></div>
                        </div>
                    </div>
                    <div class="pp-landing-mock-calendar">
                        @foreach(range(1, 28) as $d)
                            <div class="pp-landing-mock-day {{ in_array($d, [3, 8, 12, 17, 21, 25], true) ? 'is-on' : '' }}"></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pp-landing-section">
        <h2 class="pp-landing-section-title pp-reveal">Built for modern social ops</h2>
        <p class="pp-landing-section-copy pp-reveal pp-reveal-delay-1">
            From media prep to multi-channel publishing, PixFlow covers the full workflow — including AI generation, team approvals, and agent review.
        </p>
        <div class="pp-landing-features">
            @foreach([
                ['icon' => 'hub', 'title' => 'Connected profiles', 'text' => 'Link Facebook Pages, Instagram business accounts, and Pinterest boards in one admin workspace.'],
                ['icon' => 'perm_media', 'title' => 'Media library', 'text' => 'Upload images and video, organize assets, and reuse them across posts with platform-ready crops.'],
                ['icon' => 'edit_note', 'title' => 'Post composer', 'text' => 'Guided create flow with captions, hashtags, channel settings, and Semi-AI assist when you want help.'],
                ['icon' => 'calendar_month', 'title' => 'Calendar & queues', 'text' => 'See what goes out when. Schedule once, recur weekly, or pin posts into your publishing queue.'],
                ['icon' => 'smart_toy', 'title' => 'AI automation', 'text' => 'Campaigns generate ideas, copy, and images — then hand posts to Auto Review before anything publishes.'],
                ['icon' => 'verified_user', 'title' => 'Team approvals', 'text' => 'Creators submit work. Admins approve, request revision, or reject — with clear notifications either way.'],
                ['icon' => 'rocket_launch', 'title' => 'Reliable publishing', 'text' => 'Queue workers publish with retries, logs, and failure alerts so you know what succeeded and what needs attention.'],
                ['icon' => 'notifications', 'title' => 'Live notifications', 'text' => 'Stay on top of publish results, pipeline status, agent decisions, and approval outcomes with sound alerts.'],
                ['icon' => 'devices', 'title' => 'Works everywhere', 'text' => 'Responsive layouts for phone, tablet, and desktop so your team can review and ship from any device.'],
            ] as $i => $feature)
                <article class="pp-landing-feature pp-reveal pp-reveal-delay-{{ ($i % 4) + 1 }}">
                    <div class="pp-landing-feature-icon">
                        <span class="material-symbols-outlined">{{ $feature['icon'] }}</span>
                    </div>
                    <h3>{{ $feature['title'] }}</h3>
                    <p>{{ $feature['text'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="pp-landing-section" style="padding-top:0">
        <div class="pp-landing-split">
            <div class="pp-reveal">
                <h2 class="pp-landing-section-title">How teams ship with PixFlow</h2>
                <p class="pp-landing-section-copy" style="margin-bottom:0">
                    A simple loop: connect channels, create or generate content, approve, and publish — without hopping between tools.
                </p>
            </div>
            <div class="pp-landing-steps pp-reveal pp-reveal-delay-2">
                @foreach([
                    ['Connect', 'Admin links social profiles once. The team creates against those destinations.'],
                    ['Create or automate', 'Build posts manually, with Semi-AI, or let campaigns generate a full pipeline batch.'],
                    ['Review & publish', 'Approve pending work, let the agent score automation posts, then schedule or publish now.'],
                ] as $i => $step)
                    <div class="pp-landing-step">
                        <span class="pp-landing-step-num">{{ $i + 1 }}</span>
                        <div>
                            <p class="font-semibold text-[#131b2e] mb-1">{{ $step[0] }}</p>
                            <p class="text-sm text-[#5b6478] leading-relaxed m-0">{{ $step[1] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="pp-landing-band">
        <div class="pp-landing-band-inner">
            <div>
                <h2 class="pp-landing-section-title" style="color:#fff;margin-bottom:.5rem">Ready when your content is</h2>
                <p style="margin:0;color:rgba(255,255,255,.72);max-width:32rem;line-height:1.55">
                    Sign in to connect channels, invite your team, and start scheduling with AI-assisted workflows.
                </p>
            </div>
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="pp-landing-cta">
                {{ auth()->check() ? 'Open PixFlow' : 'Sign in to PixFlow' }}
                <span class="material-symbols-outlined text-[20px]">login</span>
            </a>
        </div>
    </section>

    <footer class="pp-landing-footer">
        © {{ date('Y') }} PixFlow · Social scheduling for modern teams
    </footer>

    <script src="{{ asset('js/pixflow-ui.js') }}"></script>
</body>
</html>
