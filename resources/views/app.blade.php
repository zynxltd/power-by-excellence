<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="PowerByExcellence - real-time lead distribution, ping-tree routing, and buyer management platform.">
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
        @php
            $userTheme = auth()->user()?->theme;
            $userAccent = auth()->user()?->accent_color ?? 'indigo';
            $accentVars = [
                'violet' => ['from' => '#7c3aed', 'to' => '#4f46e5', 'ring' => '#8b5cf6', 'bg' => '#7c3aed'],
                'indigo' => ['from' => '#6366f1', 'to' => '#4f46e5', 'ring' => '#6366f1', 'bg' => '#4f46e5'],
                'emerald' => ['from' => '#10b981', 'to' => '#059669', 'ring' => '#34d399', 'bg' => '#059669'],
                'rose' => ['from' => '#f43f5e', 'to' => '#e11d48', 'ring' => '#fb7185', 'bg' => '#e11d48'],
                'amber' => ['from' => '#f59e0b', 'to' => '#d97706', 'ring' => '#fbbf24', 'bg' => '#d97706'],
                'cyan' => ['from' => '#06b6d4', 'to' => '#0891b2', 'ring' => '#22d3ee', 'bg' => '#0891b2'],
            ];
            $accent = $accentVars[$userAccent] ?? $accentVars['indigo'];
        @endphp
        <script>
            (function () {
                const t = localStorage.getItem('pbe-theme');
                const server = @json($userTheme);
                let dark;
                if (t === 'dark' || t === 'light') {
                    dark = t === 'dark';
                } else if (server === 'dark' || server === 'light') {
                    dark = server === 'dark';
                } else {
                    dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                }
                document.documentElement.classList.toggle('dark', dark);

                const accentKey = localStorage.getItem('pbe-accent') || @json($userAccent);
                const accents = @json($accentVars);
                const colors = accents[accentKey] || accents.indigo;
                document.documentElement.dataset.accent = accentKey in accents ? accentKey : 'indigo';
                document.documentElement.style.setProperty('--accent-from', colors.from);
                document.documentElement.style.setProperty('--accent-to', colors.to);
                document.documentElement.style.setProperty('--accent-ring', colors.ring);
                document.documentElement.style.setProperty('--accent-bg', colors.bg);
            })();
        </script>
        @inertia
    </body>
</html>
