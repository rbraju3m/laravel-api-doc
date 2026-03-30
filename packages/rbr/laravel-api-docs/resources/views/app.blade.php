<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('api-docs.title', 'API Documentation') }}</title>
    @php
        $packageManifestPath = public_path('vendor/api-docs/build/manifest.json');
        $hostManifestPath = public_path('build/manifest.json');
        $hasPackageManifest = file_exists($packageManifestPath);
        $hasHostManifest = file_exists($hostManifestPath);
        $isHot = file_exists(public_path('hot'));

        // Debug info for the developer
        $debugInfo = [
            'package_manifest' => $packageManifestPath,
            'package_exists' => $hasPackageManifest,
            'host_manifest' => $hostManifestPath,
            'host_exists' => $hasHostManifest,
            'is_hot' => $isHot,
        ];
    @endphp

    <!-- Asset Detection Debug: {{ json_encode($debugInfo) }} -->

    @if($hasPackageManifest)
        @php
            $manifest = json_decode(file_get_contents($packageManifestPath), true);
        @endphp
        @if(isset($manifest['resources/css/app.css']['file']))
            <link rel="stylesheet" href="{{ asset('vendor/api-docs/build/' . $manifest['resources/css/app.css']['file']) }}">
        @endif
        @if(isset($manifest['resources/js/app.jsx']['css']))
            @foreach($manifest['resources/js/app.jsx']['css'] as $cssFile)
                <link rel="stylesheet" href="{{ asset('vendor/api-docs/build/' . $cssFile) }}">
            @endforeach
        @endif
        @if(isset($manifest['resources/js/app.jsx']['file']))
            <script type="module" src="{{ asset('vendor/api-docs/build/' . $manifest['resources/js/app.jsx']['file']) }}"></script>
        @endif
        @inertiaHead
    @elseif($isHot || $hasHostManifest)
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    @else
        <div id="api-docs-no-assets" style="padding: 2rem; font-family: sans-serif; text-align: center;">
            <h1 style="color: #e03131;">Assets Not Found</h1>
            <p>The API documentation assets (JS/CSS) could not be loaded.</p>
            <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px; display: inline-block; text-align: left; margin-top: 1rem;">
                <strong>To fix this, please run one of the following:</strong>
                <pre style="margin-top: 0.5rem; background: #eee; padding: 0.5rem;"># Option 1: Build host assets
npm install && npm run build

# Option 2: Publish pre-built package assets (if available)
php artisan vendor:publish --tag=api-docs-assets-build</pre>
            </div>
            <p style="font-size: 0.8rem; color: #868e96; margin-top: 1rem;">
                (Searched for: <code>{{ $packageManifestPath }}</code> [{{ $hasPackageManifest ? 'FOUND' : 'NOT FOUND' }}]
                and <code>{{ $hostManifestPath }}</code> [{{ $hasHostManifest ? 'FOUND' : 'NOT FOUND' }}])
                <br>
                isHot: [{{ $isHot ? 'TRUE' : 'FALSE' }}]
            </p>
        </div>
    @endif
</head>
<body>
    @if($hasPackageManifest || $isHot || $hasHostManifest)
        @inertia
    @endif
</body>
</html>
