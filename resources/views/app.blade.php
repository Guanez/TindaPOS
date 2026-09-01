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
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#f6f5f9">

        {{--
            Stamps the saved theme before the first paint.

            This has to be inline and it has to be up here. Anything loaded
            from the bundle runs after the browser has already painted a
            frame, and that frame is a white flash on the way into a dark
            till — which is exactly the thing somebody working a late shift
            notices and nobody can explain away.

            The key and the two accepted values are shared with
            Composables/theme.js. A missing entry means "follow the device",
            which is why that case writes nothing and stamps nothing.
        --}}
        <script>
            (function () {
                try {
                    var choice = localStorage.getItem('tindapos-theme');
                    var explicit = choice === 'light' || choice === 'dark';

                    if (explicit) {
                        document.documentElement.setAttribute('data-theme', choice);
                    }

                    var dark = choice === 'dark' || (! explicit
                        && window.matchMedia('(prefers-color-scheme: dark)').matches);

                    if (dark) {
                        document.querySelector('meta[name="theme-color"]')
                            .setAttribute('content', '#100d16');
                    }
                } catch (e) {
                    // Unreadable storage just means the device decides.
                }
            })();
        </script>

        <title inertia>{{ config('app.name', 'TindaPOS') }}</title>

        {{--
            The link preview for a shared menu.

            Rendered here, in Blade, rather than through Inertia's <Head> —
            and that is the whole point of it being here. Every crawler that
            builds a preview card (Messenger, Viber, Facebook, Twitter) reads
            the HTML it is served and does not run JavaScript, so a title set
            from a Vue component is a title those crawlers never see. A cafe
            pasting its own menu link into a Facebook post is the single most
            likely way this application ever gets shared, and without these
            it renders as a bare URL.

            Only present on pages that ask for it, via withViewData('og').
        --}}
        @isset($og)
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="{{ config('app.name', 'TindaPOS') }}">
            <meta property="og:title" content="{{ $og['title'] }}">
            <meta property="og:description" content="{{ $og['description'] }}">
            <meta property="og:url" content="{{ $og['url'] }}">
            <meta name="twitter:title" content="{{ $og['title'] }}">
            <meta name="twitter:description" content="{{ $og['description'] }}">

            @if (! empty($og['image']))
                {{-- summary_large_image only earns its size when there is an
                     image to fill it; without one it renders as a wide empty
                     box with the title beneath. --}}
                <meta property="og:image" content="{{ $og['image'] }}">
                <meta name="twitter:card" content="summary_large_image">
                <meta name="twitter:image" content="{{ $og['image'] }}">
            @else
                <meta name="twitter:card" content="summary">
            @endif
        @endisset

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
