<?php

declare(strict_types=1);

namespace App\Services;

/**
 * A shop's brand colour, turned into a palette that cannot be unreadable.
 *
 * A cafe picks one hex. Everything else — the colour of text sitting on it,
 * the tint behind a chip, the hairline of a selected pill, the lighter
 * variant that survives a dark background — is derived here, because asking
 * a shop owner for five related colours is asking them to do colour theory
 * on a settings form.
 *
 * Two guarantees, and they are the whole reason this class exists rather
 * than a `--accent: {$store->accent}` in a template:
 *
 *   1. Text on the accent (`--on-accent`) is whichever of near-black or white
 *      actually contrasts with it. A shop picking lemon yellow gets dark text
 *      on its buttons, not white text nobody can read.
 *   2. The accent used AS text (`--accent-ink`) is darkened — only as far as
 *      needed — until it reaches AA against the page. Most brand colours are
 *      already fine and come through untouched; a pale one is adjusted rather
 *      than rejected, because refusing someone's brand colour is a worse
 *      answer than nudging it.
 *
 * The fill keeps the colour the shop actually chose. Only the text role
 * moves, which is why they are separate tokens.
 */
class AccentPalette
{
    /** WCAG AA for body text. */
    private const MIN_CONTRAST = 4.5;

    /** @var array{0:int,1:int,2:int} */
    private const NEAR_BLACK = [21, 18, 28];

    /** @var array{0:int,1:int,2:int} */
    private const WHITE = [255, 255, 255];

    /** The page behind the text, light theme and dark. */
    private const LIGHT_GROUND = [255, 255, 255];

    private const DARK_GROUND = [26, 22, 34];

    /**
     * The CSS that overrides the default tokens for this shop, ready to be
     * dropped in a <style> element. Built entirely from integers this class
     * computed, so nothing a user typed is ever interpolated into a rule.
     */
    public static function css(?string $hex): string
    {
        $rgb = self::parse($hex);

        if ($rgb === null) {
            return '';
        }

        $light = self::forGround($rgb, self::LIGHT_GROUND, darken: true);
        $dark = self::forGround($rgb, self::DARK_GROUND, darken: false);

        // `:root:root` rather than `:root`, and the same doubling in each dark
        // selector. app.css declares these tokens at one specificity lower, so
        // this wins no matter which stylesheet the browser applied last —
        // which matters because it genuinely varies: a production build links
        // the bundle ahead of this block, while Vite's dev server injects it
        // from JavaScript afterwards. Relying on order would mean a shop's
        // colour appearing in production and silently not in development.
        return ':root:root{'.self::declarations($light).'}'
            .'@media (prefers-color-scheme: dark){:root:root:not([data-theme="light"]){'.self::declarations($dark).'}}'
            .':root:root[data-theme="dark"]{'.self::declarations($dark).'}';
    }

    /**
     * The resolved palette for one ground, as `token => "r g b"`.
     *
     * @param  array{0:int,1:int,2:int}  $rgb
     * @param  array{0:int,1:int,2:int}  $ground
     * @return array<string, string>
     */
    public static function forGround(array $rgb, array $ground, bool $darken): array
    {
        // Mostly ground with a wash of accent through it — the order of these
        // arguments is the difference between a pale tint and a solid block
        // of colour behind small text.
        $tint = self::mix($ground, $rgb, 0.90);

        // Derived against the tint rather than the page, because the tint is
        // the harder of the two: accent text appears on it inside chips and
        // callouts, and it is always a step further from the ink than the
        // bare page is. Clearing the tint therefore clears the page as well.
        //
        // On a dark page a mid-tone brand colour is the thing that struggles,
        // so the text variant is lightened there and darkened on a light one.
        $ink = self::readableOn($rgb, $tint, $darken);

        return [
            'accent' => self::channels($rgb),
            'accent-ink' => self::channels($ink),
            'accent-hover' => self::channels(self::shift($rgb, $darken ? -0.10 : 0.12)),
            'accent-tint' => self::channels($tint),
            'accent-line' => self::channels(self::mix($ground, $rgb, 0.62)),
            'on-accent' => self::channels(self::bestOn($rgb)),
        ];
    }

    /**
     * Whether a colour can be used as-is, which is what the settings screen
     * tells the owner before they save.
     */
    public static function isReadableAsText(?string $hex): bool
    {
        $rgb = self::parse($hex);

        return $rgb !== null && self::contrast($rgb, self::LIGHT_GROUND) >= self::MIN_CONTRAST;
    }

    /**
     * @param  array{0:int,1:int,2:int}  $rgb
     * @return array{0:int,1:int,2:int}
     */
    private static function bestOn(array $rgb): array
    {
        return self::contrast($rgb, self::WHITE) >= self::contrast($rgb, self::NEAR_BLACK)
            ? self::WHITE
            : self::NEAR_BLACK;
    }

    /**
     * Walk the colour toward the far end until it clears AA on this ground.
     *
     * Stepwise rather than a formula because the answer is "the closest
     * readable version of what they chose", and stopping at the first step
     * that passes is exactly that.
     *
     * @param  array{0:int,1:int,2:int}  $rgb
     * @param  array{0:int,1:int,2:int}  $ground
     * @return array{0:int,1:int,2:int}
     */
    private static function readableOn(array $rgb, array $ground, bool $darken): array
    {
        $target = $darken ? [0, 0, 0] : self::WHITE;
        $candidate = $rgb;

        for ($step = 0; $step < 20; $step++) {
            if (self::contrast($candidate, $ground) >= self::MIN_CONTRAST) {
                return $candidate;
            }

            $candidate = self::mix($target, $candidate, 0.08);
        }

        // Nothing readable in 20 steps means the ground itself is the problem;
        // fall back to something that certainly works.
        return $darken ? self::NEAR_BLACK : self::WHITE;
    }

    /**
     * @param  array{0:int,1:int,2:int}  $rgb
     * @return array{0:int,1:int,2:int}
     */
    private static function shift(array $rgb, float $amount): array
    {
        $target = $amount < 0 ? [0, 0, 0] : self::WHITE;

        return self::mix($target, $rgb, abs($amount));
    }

    /**
     * `$weight` of $a over $b.
     *
     * @param  array{0:int,1:int,2:int}  $a
     * @param  array{0:int,1:int,2:int}  $b
     * @return array{0:int,1:int,2:int}
     */
    private static function mix(array $a, array $b, float $weight): array
    {
        return [
            (int) round($a[0] * $weight + $b[0] * (1 - $weight)),
            (int) round($a[1] * $weight + $b[1] * (1 - $weight)),
            (int) round($a[2] * $weight + $b[2] * (1 - $weight)),
        ];
    }

    /**
     * @param  array{0:int,1:int,2:int}  $a
     * @param  array{0:int,1:int,2:int}  $b
     */
    public static function contrast(array $a, array $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * @param  array{0:int,1:int,2:int}  $rgb
     */
    private static function luminance(array $rgb): float
    {
        $channels = array_map(static function (int $value): float {
            $v = $value / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * @return array{0:int,1:int,2:int}|null
     */
    public static function parse(?string $hex): ?array
    {
        if ($hex === null || preg_match('/^#[0-9a-f]{6}$/i', $hex) !== 1) {
            return null;
        }

        return [
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ];
    }

    /**
     * @param  array{0:int,1:int,2:int}  $rgb
     */
    private static function channels(array $rgb): string
    {
        return "{$rgb[0]} {$rgb[1]} {$rgb[2]}";
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private static function declarations(array $tokens): string
    {
        $out = '';

        foreach ($tokens as $name => $value) {
            $out .= "--{$name}:{$value};";
        }

        return $out;
    }
}
