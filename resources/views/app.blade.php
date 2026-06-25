<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="PowerByExcellence — real-time lead distribution, ping-tree routing, and buyer management platform.">
        <meta name="theme-color" content="#0f172a">
        <link rel="canonical" href="{{ url()->current() }}">

        <title inertia>{{ config('app.name', 'PowerByExcellence') }}</title>

        @php
            $faviconHref = asset('favicon.svg');
            $hostAccount = request()->attributes->get('host_account');
            if ($hostAccount?->favicon_path) {
                $faviconHref = \Illuminate\Support\Facades\Storage::disk('public')->url($hostAccount->favicon_path);
            }
        @endphp
        <link rel="icon" href="{{ $faviconHref }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ $faviconHref }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <script>
            (function () {
                const t = localStorage.getItem('pbe-theme');
                const dark = t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>
        @inertia
    </body>
</html>
