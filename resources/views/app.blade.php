<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-url" content="{{ rtrim(config('app.url'), '/') }}">
    <title inertia>{{ config('app.name', 'Laravel') }}</title>
    <script>
        (function () {
            var configuredAppUrl = document.querySelector('meta[name="app-url"]')?.content;

            if (!configuredAppUrl) {
                return;
            }

            try {
                var currentUrl = new URL(window.location.href);
                var expectedUrl = new URL(configuredAppUrl);

                if (
                    currentUrl.origin !== expectedUrl.origin &&
                    ['127.0.0.1', 'localhost'].includes(currentUrl.hostname) &&
                    ['127.0.0.1', 'localhost'].includes(expectedUrl.hostname)
                ) {
                    window.location.replace(
                        expectedUrl.origin +
                        currentUrl.pathname +
                        currentUrl.search +
                        currentUrl.hash
                    );
                }
            } catch (_error) {
                // Ignore malformed URL edge cases and continue booting the app.
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
