<!DOCTYPE html>
{{--
    data-density switches the whole type scale. A customer reading a menu on
    a phone gets larger text than a cashier at a terminal, from the same
    components — the roles carry both values and this attribute picks one.
--}}
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-density="{{ $page['props']['density'] ?? 'counter' }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#100d16" media="(prefers-color-scheme: dark)">
        <meta name="color-scheme" content="light dark">

        <title inertia>{{ config('app.name', 'TindaPOS') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
