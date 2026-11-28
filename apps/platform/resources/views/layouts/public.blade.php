{{--
    Public pages rendered on the server (B14.1, ADR 0064): plain HTML that
    people, search engines and answer engines read without JavaScript.
    Light theme, semantic tokens, 16px+ text.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    @if ($noindex ?? false)
        <meta name="robots" content="noindex">
    @endif
    <meta property="og:type" content="profile">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta name="twitter:card" content="summary">
    <meta name="theme-color" content="#F6F7F9">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @foreach ($schema ?? [] as $data)
        <script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
    @vite(['resources/css/app.css'])
</head>
<body class="bg-background font-sans text-foreground antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:rounded-reos-md focus:bg-card focus:p-3">{{ __('cards.skip') }}</a>
    <main id="main">
        {{ $slot ?? '' }}
        @yield('content')
    </main>
</body>
</html>
