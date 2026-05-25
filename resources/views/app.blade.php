<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php
            $pageProps = is_array($page ?? null) ? ($page['props'] ?? []) : [];
            $resolvedAppName = trim((string) data_get($pageProps, 'appName', config('app.name', 'Laravel')));
            $resolvedAppName = $resolvedAppName !== '' ? $resolvedAppName : 'Laravel';

            $resolvedLogoBackgroundEnabled = (bool) data_get($pageProps, 'logoBackgroundEnabled', true);
            $resolvedLogoBackgroundColor = strtoupper(trim((string) data_get($pageProps, 'logoBackgroundColor', '#10B981')));
            if (preg_match('/^#[A-F0-9]{6}$/', $resolvedLogoBackgroundColor) !== 1) {
                $resolvedLogoBackgroundColor = '#10B981';
            }

            $resolvedFavicon = trim((string) data_get($pageProps, 'appLogoUrl', ''));
            if ($resolvedFavicon === '') {
                $tokens = preg_split('/\s+/', $resolvedAppName) ?: [];
                $tokens = array_values(array_filter($tokens, fn (string $token): bool => trim($token) !== ''));
                $faviconInitials = 'APP';

                if (count($tokens) >= 2) {
                    $faviconInitials = strtoupper(substr($tokens[0], 0, 1).substr($tokens[1], 0, 1));
                } elseif (count($tokens) === 1) {
                    $faviconInitials = strtoupper(substr($tokens[0], 0, 2));
                }

                $faviconBackground = $resolvedLogoBackgroundEnabled ? $resolvedLogoBackgroundColor : '#FFFFFF';
                $faviconForeground = $resolvedLogoBackgroundEnabled ? '#FFFFFF' : '#0F172A';
                $safeInitials = htmlspecialchars($faviconInitials, ENT_QUOTES, 'UTF-8');

                $faviconSvg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
    <rect x="0" y="0" width="64" height="64" rx="14" fill="{$faviconBackground}" />
    <text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" font-family="Outfit, Arial, sans-serif" font-size="24" font-weight="700" fill="{$faviconForeground}">
        {$safeInitials}
    </text>
</svg>
SVG;

                $resolvedFavicon = 'data:image/svg+xml,'.rawurlencode($faviconSvg);
            }
        @endphp

        <meta name="platform-app-name" content="{{ $resolvedAppName }}">
        <title inertia>{{ $resolvedAppName }}</title>
        <link rel="icon" href="{{ $resolvedFavicon }}" data-platform-favicon>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Sora:wght@600;700&display=swap"
            rel="stylesheet"
        >
        <script>
            (function () {
                try {
                    if (localStorage.getItem('theme_mode') === 'dark') {
                        document.documentElement.classList.add('dark');
                        return;
                    }
                } catch (error) {}

                document.documentElement.classList.remove('dark');
            })();
        </script>

        @vite('resources/js/app.js')
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
