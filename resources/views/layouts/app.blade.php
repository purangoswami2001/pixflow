<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'PixFlow') }} - Social Scheduler</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Hanken+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="{{ asset('css/pixflow.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="min-h-screen">
    @include('layouts.partials.sidebar')

    <div class="pp-main ml-0 lg:ml-[260px] min-h-screen flex flex-col">
        @include('layouts.partials.topbar')

        <main class="flex-1 p-6 max-w-[1280px] mx-auto w-full pp-page-content">
            {{ $slot }}
        </main>
    </div>

    <div id="ppSidebarBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden" onclick="document.getElementById('ppSidebar').classList.remove('open'); this.classList.add('hidden');"></div>

    <x-pp-ui-shell />
    <script src="{{ asset('js/pixflow-ui.js') }}"></script>

    <script>
        document.getElementById('ppSidebar')?.querySelector('[onclick]')?.addEventListener?.('click', function() {
            document.getElementById('ppSidebarBackdrop')?.classList.remove('hidden');
        });
        document.querySelector('header button.lg\\:hidden')?.addEventListener('click', function() {
            document.getElementById('ppSidebarBackdrop')?.classList.toggle('hidden');
        });
    </script>
    @stack('scripts')
</body>
</html>
