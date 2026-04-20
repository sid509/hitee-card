<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" 
    class="layout-wide customizer-hide {{ $theme === 'dark' ? 'dark-style' : ($theme === 'light' ? 'light-style' : '') }}" 
    dir="ltr"
    data-theme="{{ $theme === 'dark' ? 'theme-dark' : ($theme === 'light' ? 'theme-default' : '') }}" 
    data-assets-path="{{ asset('assets') }}/" 
    data-template="hitee-vertical-menu-template">

<head>
    <meta charset="utf-8" />
    <script>
        (function() {
            const theme = "{{ $theme }}";
            if (theme === 'system') {
                const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');
                const applyTheme = (isDark) => {
                    if (isDark) {
                        document.documentElement.classList.add('dark-style');
                        document.documentElement.classList.remove('light-style');
                        document.documentElement.setAttribute('data-theme', 'theme-dark');
                    } else {
                        document.documentElement.classList.add('light-style');
                        document.documentElement.classList.remove('dark-style');
                        document.documentElement.setAttribute('data-theme', 'theme-default');
                    }
                };

                applyTheme(darkQuery.matches);
            }
        })();
    </script>
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>@yield('title') | Hitee</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" />
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('page-css')
    <style>
        /* Logo Switching */
        .logo-dark-version, .logo-light-version { display: none !important; }
        .dark-style .logo-dark-version { display: inline-block !important; }
        .light-style .logo-light-version { display: inline-block !important; }
    </style>
</head>

<body>
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                @yield('content')
            </div>
        </div>
    </div>
    @stack('page-js')
</body>

</html>
