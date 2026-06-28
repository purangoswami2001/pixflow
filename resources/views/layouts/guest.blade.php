<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'PixFlow') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Hanken+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="{{ asset('css/pixflow.css') }}" rel="stylesheet">
</head>
<body class="pp-auth-page">
    <div class="pp-auth-card">
        <a href="{{ url('/') }}" class="pp-auth-logo">
            <div class="pp-auth-logo-icon">
                <span class="material-symbols-outlined text-[28px]">send</span>
            </div>
            <h1>PixFlow</h1>
            <p>Social scheduling made simple</p>
        </a>

        {{ $slot }}
    </div>

    <x-pp-ui-shell />
    <script src="{{ asset('js/pixflow-ui.js') }}"></script>
</body>
</html>
