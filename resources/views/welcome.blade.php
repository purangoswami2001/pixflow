<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50 dark:bg-zinc-950">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>PixFlow - Social Media Scheduling Platform</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
            }
        </style>
    </head>
    <body class="h-full antialiased text-gray-900 dark:text-zinc-100 bg-gray-50 dark:bg-zinc-950">
        <!-- Navigation Header -->
        <header class="sticky top-0 z-40 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border-b border-gray-100 dark:border-zinc-800">
            <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
                <a href="#" class="flex items-center gap-2 font-bold text-xl tracking-tight text-indigo-600 dark:text-indigo-400">
                    <svg class="h-8 w-8 text-indigo-600 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                    <span>PixFlow</span>
                </a>
                
                <div class="flex items-center gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-gray-700 dark:text-zinc-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">Go to Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-700 dark:text-zinc-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">Sign in</a>
                    @endauth
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="py-24 px-6 max-w-7xl mx-auto text-center relative overflow-hidden">
            <!-- Background glow -->
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-indigo-500/10 rounded-full blur-[120px] pointer-events-none"></div>

            <div class="relative z-10 max-w-3xl mx-auto space-y-8">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/30 rounded-full border border-indigo-100 dark:border-indigo-900">
                    🚀 Introducing PixFlow SaaS Platform
                </span>
                <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-gray-900 dark:text-zinc-550 leading-none">
                    Schedule and Publish to <span class="bg-gradient-to-r from-indigo-500 via-pink-500 to-red-500 bg-clip-text text-transparent">Social Channels</span> Autonomously
                </h1>
                <p class="text-lg text-gray-500 dark:text-zinc-400">
                    Connect Facebook Pages, Instagram Creators, and Pinterest Boards. Upload assets, auto-crop to platform dimensions, generate AI captions, and orchestrate weekly recurring posting queues.
                </p>

                <div class="flex items-center justify-center gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-6 py-3.5 text-base font-semibold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl shadow-lg transition">Go to Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-6 py-3.5 text-base font-semibold text-gray-700 dark:text-zinc-300 bg-white dark:bg-zinc-900 border border-gray-250 dark:border-zinc-800 hover:bg-gray-50 rounded-xl transition">Sign In</a>
                    @endauth
                </div>
            </div>
        </section>

        <!-- Features Grid -->
        <section class="py-20 bg-white dark:bg-zinc-900 border-t border-b border-gray-100 dark:border-zinc-800">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center max-w-xl mx-auto mb-16 space-y-4">
                    <h2 class="text-3xl font-extrabold text-gray-900 dark:text-zinc-50">Intelligent Scheduling Operations</h2>
                    <p class="text-sm text-gray-500">Everything you need to orchestrate multinetwork campaigns from a single dashboard.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div class="p-8 bg-gray-50 dark:bg-zinc-950 rounded-2xl border dark:border-zinc-850 space-y-4">
                        <div class="h-10 w-10 bg-indigo-600 text-white rounded-xl flex items-center justify-center font-bold">1</div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-50">Unified Media Library</h3>
                        <p class="text-sm text-gray-500 dark:text-zinc-400 leading-relaxed">
                            Upload photos/videos and crop them center-fill or inside-fit using dimensions tuned for Instagram Stories, Pinterest Pins, and Facebook Cards.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="p-8 bg-gray-50 dark:bg-zinc-950 rounded-2xl border dark:border-zinc-850 space-y-4">
                        <div class="h-10 w-10 bg-pink-600 text-white rounded-xl flex items-center justify-center font-bold">2</div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-50">7-Step Wizard</h3>
                        <p class="text-sm text-gray-500 dark:text-zinc-400 leading-relaxed">
                            A multi-step compiler supporting platform selecting, profile checking, AI templates drafting, timezone adjustments, and live mock rendering preview.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="p-8 bg-gray-50 dark:bg-zinc-950 rounded-2xl border dark:border-zinc-850 space-y-4">
                        <div class="h-10 w-10 bg-red-600 text-white rounded-xl flex items-center justify-center font-bold">3</div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-50">Queued Publication Engine</h3>
                        <p class="text-sm text-gray-500 dark:text-zinc-400 leading-relaxed">
                            Leverages Laravel queue workers, automatic retries, cache locking keys, and daily token refreshes to guarantee delivery metrics.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-12 text-center text-xs text-gray-400 dark:text-zinc-600 border-t border-gray-100 dark:border-zinc-900">
            <p>&copy; {{ date('Y') }} PixFlow Scheduler. Powered by Laravel 11 & Livewire. All rights reserved.</p>
        </footer>
    </body>
</html>
