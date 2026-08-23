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
        {{--
            The shop's own palette, overriding the accent tokens for this page
            only. Rendered raw because it has to be CSS, which is safe here
            for one specific reason: AccentPalette never interpolates the
            stored value. It parses the hex to three integers, derives the
            rest arithmetically, and prints integers back out — so the only
            characters that can reach this element are digits and punctuation
            this application wrote.

            Present on customer pages and absent everywhere else, which is
            what keeps a cashier's till the same colour in every shop they
            work in.

            Placed after @vite deliberately: app.css defines these same
            tokens on :root, and with equal specificity the later rule
            wins. Above the bundle this block loads and is then quietly
            overwritten by the platform default.
        --}}
        @if (! empty($page['props']['store']['brand_css']))
            <style>{!! $page['props']['store']['brand_css'] !!}</style>
        @endif
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
