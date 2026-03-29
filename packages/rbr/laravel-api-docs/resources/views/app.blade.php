<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('api-docs.title', 'API Documentation') }}</title>
    @if(file_exists(public_path('vendor/api-docs/build/manifest.json')))
        <link rel="stylesheet" href="{{ asset('vendor/api-docs/build/' . json_decode(file_get_contents(public_path('vendor/api-docs/build/manifest.json')), true)['resources/css/app.css']['file']) }}">
        <script type="module" src="{{ asset('vendor/api-docs/build/' . json_decode(file_get_contents(public_path('vendor/api-docs/build/manifest.json')), true)['resources/js/app.jsx']['file']) }}"></script>
    @else
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @endif
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
