<!DOCTYPE html>
<html lang="en" class="light-style customizer-hide" dir="ltr" data-theme="theme-default" data-assets-path="{{ asset('assets') }}/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>@yield('title') | Hitee</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    @vite(['resources/scss/app.scss'])
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-misc.css') }}" />
</head>
<body>
    <div class="container-xxl container-p-y">
        <div class="misc-wrapper text-center">
            <h1 class="mb-2 mx-2" style="line-height: 6rem; font-size: 6rem">@yield('code')</h1>
            <h4 class="mb-2 mx-2">@yield('headline')</h4>
            <p class="mb-6 mx-2">@yield('message')</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="javascript:history.back()" class="btn btn-outline-secondary">Go Back</a>
                <a href="{{ route('dashboard') }}" class="btn btn-primary">Back to Dashboard</a>
            </div>
            <div class="mt-6">
                <img src="{{ asset('assets/img/illustrations/' . $image) }}" alt="error-illustration" width="500" class="img-fluid" />
            </div>
        </div>
    </div>
</body>
</html>
