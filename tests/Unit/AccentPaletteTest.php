<?php

declare(strict_types=1);

use App\Services\AccentPalette;

/*
|--------------------------------------------------------------------------
| A brand colour that cannot be unreadable
|--------------------------------------------------------------------------
| A shop picks one hex on a settings form and the whole customer menu is
| built from it. The failure this class exists to prevent is quiet: a pale
| brand colour producing white-on-yellow buttons that the owner never sees,
| because the owner is looking at their own menu on their own good screen.
*/

const AA = 4.5;

/** @return array{0:int,1:int,2:int} */
function rgbOf(string $tokens): array
{
    [$r, $g, $b] = array_map('intval', explode(' ', $tokens));

    return [$r, $g, $b];
}

it('reads back the colour the shop actually chose as the fill', function () {
    $light = AccentPalette::forGround(AccentPalette::parse('#2F5D50'), [255, 255, 255], darken: true);

    // The fill is untouched — that is the shop's brand, exactly as picked.
    expect($light['accent'])->toBe('47 93 80');
});

it('puts readable text on the accent whatever the accent is', function (string $hex) {
    $light = AccentPalette::forGround(AccentPalette::parse($hex), [255, 255, 255], darken: true);

    $contrast = AccentPalette::contrast(rgbOf($light['on-accent']), rgbOf($light['accent']));

    expect($contrast)->toBeGreaterThanOrEqual(AA);
})->with([
    '#2F5D50',  // deep forest
    '#F5C518',  // lemon — the case that breaks naive white-on-accent
    '#FFFFFF',  // pathological: white
    '#000000',  // pathological: black
    '#B5851F',
    '#D42B1C',
]);

it('darkens the text variant only until it is readable', function (string $hex) {
    $light = AccentPalette::forGround(AccentPalette::parse($hex), [255, 255, 255], darken: true);

    $contrast = AccentPalette::contrast(rgbOf($light['accent-ink']), [255, 255, 255]);

    expect($contrast)->toBeGreaterThanOrEqual(AA);
})->with(['#2F5D50', '#F5C518', '#9BE8B0', '#FFFFFF', '#5B3FD9']);

it('leaves a colour that is already readable alone', function () {
    // Deep forest clears AA on white by itself, so the ink role should be
    // the same colour — nudging it would change a brand for no reason.
    $light = AccentPalette::forGround(AccentPalette::parse('#2F5D50'), [255, 255, 255], darken: true);

    expect($light['accent-ink'])->toBe($light['accent']);
});

it('keeps the tint close to the page it sits on', function (string $hex) {
    // A tint is a wash, not a fill: it backs a chip with dark text on it, so
    // it has to stay near the ground. Getting the mix the wrong way round
    // produces a solid block of brand colour and unreadable text on top.
    $light = AccentPalette::forGround(AccentPalette::parse($hex), [255, 255, 255], darken: true);

    $tint = rgbOf($light['accent-tint']);

    expect(AccentPalette::contrast($tint, [255, 255, 255]))->toBeLessThan(1.6)
        ->and(AccentPalette::contrast(rgbOf($light['accent-ink']), $tint))->toBeGreaterThanOrEqual(AA);
})->with(['#2F5D50', '#D42B1C', '#5B3FD9', '#F5C518']);

it('lightens rather than darkens against a dark page', function () {
    $dark = AccentPalette::forGround(AccentPalette::parse('#2F5D50'), [26, 22, 34], darken: false);

    $contrast = AccentPalette::contrast(rgbOf($dark['accent-ink']), [26, 22, 34]);

    expect($contrast)->toBeGreaterThanOrEqual(AA);
});

it('emits nothing at all when a shop has set no colour', function () {
    expect(AccentPalette::css(null))->toBe('')
        ->and(AccentPalette::css(''))->toBe('');
});

it('refuses anything that is not a plain six-digit hex', function (string $hex) {
    // The output of this class lands inside a <style> element, so the parser
    // is the boundary that keeps it there.
    expect(AccentPalette::parse($hex))->toBeNull()
        ->and(AccentPalette::css($hex))->toBe('');
})->with([
    'red',
    '#fff',
    '#5B3FD',
    '#5B3FD99',
    'rgb(0,0,0)',
    '#5B3FD9;}</style><script>alert(1)</script>',
    '5B3FD9',
]);

it('writes only digits and punctuation into the stylesheet', function () {
    $css = AccentPalette::css('#F5C518');

    expect($css)->toContain('--accent:')
        ->and($css)->toContain('prefers-color-scheme: dark')
        // No stored value survives into the output; every number was computed.
        // The permitted set is exactly what this class can emit: token names,
        // digits, the selectors, and the @ of @media.
        ->and(preg_match('/[^0-9a-zA-Z@\-:;{}()\s,\[\]="\.]/', $css))->toBe(0);
});
