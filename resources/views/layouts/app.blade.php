<!DOCTYPE html>
@php
    $themePref = auth()->user()->themePreference();
    $resolvedTheme = $themePref === 'system' ? 'light' : $themePref;
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="{{ $resolvedTheme }}"
      data-theme-pref="{{ $themePref }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base" content="{{ rtrim(url('/'), '/') }}">
    <meta name="pp-theme-endpoint" content="{{ route('profile.theme') }}">
    <title>{{ config('app.name', 'PixFlow') }} - Social Scheduler</title>
    {{-- Apply theme before paint to avoid light/dark flash --}}
    <script>
        (function () {
            try {
                var root = document.documentElement;
                var pref = root.getAttribute('data-theme-pref') || 'light';
                var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var mode = pref === 'system' ? (systemDark ? 'dark' : 'light') : pref;
                if (mode !== 'dark' && mode !== 'light') mode = 'light';
                root.classList.remove('light', 'dark');
                root.classList.add(mode);
                root.style.colorScheme = mode;
                root.setAttribute('data-theme-resolved', mode);
            } catch (e) {}
        })();
    </script>
    {{-- Critical: hide off-canvas panels before stylesheet loads (prevents open→close flash) --}}
    <style>
        @media (max-width: 1023px) {
            #ppSidebar { transform: translateX(-100%); }
            #ppSidebar.open { transform: translateX(0); }
        }
        .pp-team-drawer { transform: translateX(100%) !important; }
        .pp-team-drawer.is-open { transform: translateX(0) !important; }
        .pp-team-drawer-backdrop:not(.is-open) {
            opacity: 0 !important;
            visibility: hidden !important;
            pointer-events: none !important;
        }
        html.dark body { background-color: #0a0f1a; color: #eef1f8; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Hanken+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="{{ asset('css/pixflow.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="min-h-screen">
    @include('layouts.partials.sidebar')

    <div class="pp-main">
        @include('layouts.partials.topbar')

        <main class="flex-1 p-3 sm:p-6 lg:p-8 max-w-[1280px] mx-auto w-full min-w-0 pp-page-content">
            {{ $slot }}
        </main>
    </div>

    <div id="ppSidebarBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden" onclick="document.getElementById('ppSidebar').classList.remove('open'); this.classList.add('hidden'); document.body.classList.remove('pp-sidebar-open');"></div>

    <x-pp-ui-shell />
    <script src="{{ asset('js/pixflow-ui.js') }}?v={{ @filemtime(public_path('js/pixflow-ui.js')) ?: time() }}"></script>

    <script>
        (function () {
            const sidebar = document.getElementById('ppSidebar');
            const backdrop = document.getElementById('ppSidebarBackdrop');
            const menuBtn = document.querySelector('header button.lg\\:hidden');

            function closeSidebar() {
                sidebar?.classList.remove('open');
                backdrop?.classList.add('hidden');
                document.body.classList.remove('pp-sidebar-open');
            }

            function toggleSidebar() {
                const opening = !sidebar?.classList.contains('open');
                sidebar?.classList.toggle('open', opening);
                backdrop?.classList.toggle('hidden', !opening);
                document.body.classList.toggle('pp-sidebar-open', opening);
            }

            menuBtn?.addEventListener('click', function (e) {
                e.preventDefault();
                toggleSidebar();
            });

            document.addEventListener('click', function (e) {
                const openBtn = e.target.closest('[data-pp-open-sidebar]');
                if (!openBtn) return;
                e.preventDefault();
                if (!sidebar?.classList.contains('open')) {
                    toggleSidebar();
                }
                // Scroll automation subnav into view inside the sidebar
                requestAnimationFrame(function () {
                    sidebar?.querySelector('.pp-sidebar-subnav')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                });
            });

            sidebar?.querySelectorAll('a[href]').forEach(function (link) {
                link.addEventListener('click', closeSidebar);
            });

            window.addEventListener('resize', function () {
                if (window.matchMedia('(min-width: 1024px)').matches) {
                    closeSidebar();
                }
            });

            // Enable slide animations only after first paint (no load-time close animation)
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    document.documentElement.classList.add('pp-motion-ready');
                });
            });
        })();

        (function () {
            var endpoint = document.querySelector('meta[name="pp-theme-endpoint"]')?.content || '';
            var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

            function systemIsDark() {
                return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            }

            function resolveMode(pref) {
                if (pref === 'system') return systemIsDark() ? 'dark' : 'light';
                return pref === 'dark' ? 'dark' : 'light';
            }

            function applyTheme(pref, persist) {
                var mode = resolveMode(pref);
                var root = document.documentElement;
                root.setAttribute('data-theme-pref', pref);
                root.setAttribute('data-theme-resolved', mode);
                root.classList.remove('light', 'dark');
                root.classList.add(mode);
                root.style.colorScheme = mode;

                document.querySelectorAll('[data-theme-option]').forEach(function (el) {
                    el.classList.toggle('is-active', el.getAttribute('data-theme-option') === pref);
                });

                var toggle = document.getElementById('ppThemeToggle');
                if (toggle) {
                    toggle.setAttribute('data-mode', mode);
                    toggle.setAttribute('aria-label', mode === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
                    toggle.title = mode === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
                }

                if (persist && endpoint) {
                    fetch(endpoint, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            theme: pref,
                            system_dark: systemIsDark(),
                        }),
                    }).catch(function () {});
                }
            }

            window.ppSetTheme = function (pref) {
                if (['light', 'dark', 'system'].indexOf(pref) === -1) return;
                applyTheme(pref, true);
            };

            window.ppToggleTheme = function (event) {
                if (event) event.stopPropagation();
                var current = document.documentElement.getAttribute('data-theme-resolved') || 'light';
                window.ppSetTheme(current === 'dark' ? 'light' : 'dark');
            };

            var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
            if (mq) {
                var onChange = function () {
                    var pref = document.documentElement.getAttribute('data-theme-pref') || 'light';
                    if (pref === 'system') applyTheme('system', false);
                };
                if (mq.addEventListener) mq.addEventListener('change', onChange);
                else if (mq.addListener) mq.addListener(onChange);
            }

            applyTheme(document.documentElement.getAttribute('data-theme-pref') || 'light', false);
        })();
    </script>
    @stack('scripts')
</body>
</html>
