<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $school->name }}</title>
    <meta name="theme-color" content="{{ $colour }}">
    <link rel="manifest" href="/parent/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/parent/icon-192.png">
    <link rel="apple-touch-icon" href="/parent/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    @vite(['resources/js/parent/main.js'])
</head>
<body class="bg-zinc-50 text-zinc-900 antialiased" style="--school: {{ $colour }}; --school-text: {{ $textColour }};">
    {{-- Vue (resources/js/parent) mounts here. Only the school's public details are passed in. --}}
    <div id="app"
         data-school-name="{{ $school->name }}"
         data-school-logo="{{ $school->logo_path ? route('school.logo') : '' }}"></div>
    <noscript>
        <p style="padding: 1rem">{{ __('The :school parent app needs JavaScript turned on.', ['school' => $school->name]) }}</p>
    </noscript>
</body>
</html>
