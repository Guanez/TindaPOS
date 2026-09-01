import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            /*
             * Type roles. Size and leading travel together — a role that only
             * set a size would leave the next person picking a line-height by
             * eye, which is how the codebase ended up with six sizes inside a
             * five-pixel band.
             *
             * Tracking is deliberately NOT baked in: `label` is usually
             * uppercase and wants it, but callers already reach for
             * `tracking-widest`, and two sources for one property is a fight.
             */
            fontSize: {
                label:   ['var(--fs-label)',   { lineHeight: 'var(--lh-label)' }],
                meta:    ['var(--fs-meta)',    { lineHeight: 'var(--lh-meta)' }],
                ui:      ['var(--fs-ui)',      { lineHeight: 'var(--lh-ui)' }],
                body:    ['var(--fs-body)',    { lineHeight: 'var(--lh-body)' }],
                title:   ['var(--fs-title)',   { lineHeight: 'var(--lh-title)' }],
                heading: ['var(--fs-heading)', { lineHeight: 'var(--lh-heading)' }],
                figure:  ['var(--fs-figure)',  { lineHeight: 'var(--lh-figure)' }],
                hero:    ['var(--fs-hero)',    { lineHeight: 'var(--lh-hero)' }],
            },
            colors: {
                /*
                 * Semantic first. Screens should reach for `bg-surface-1` and
                 * `text-ink-2`, never a raw ramp step — that is what makes a
                 * palette change one file rather than an audit.
                 */
                surface: {
                    1: 'rgb(var(--surface-1) / <alpha-value>)',
                    2: 'rgb(var(--surface-2) / <alpha-value>)',
                    3: 'rgb(var(--surface-3) / <alpha-value>)',
                },
                ink: {
                    1: 'rgb(var(--ink-1) / <alpha-value>)',
                    2: 'rgb(var(--ink-2) / <alpha-value>)',
                    3: 'rgb(var(--ink-3) / <alpha-value>)',
                },
                line: {
                    DEFAULT: 'rgb(var(--line) / <alpha-value>)',
                    strong: 'rgb(var(--line-strong) / <alpha-value>)',
                },
                action: {
                    DEFAULT: 'rgb(var(--action-bg) / <alpha-value>)',
                    hover: 'rgb(var(--action-bg-hover) / <alpha-value>)',
                    fg: 'rgb(var(--action-fg) / <alpha-value>)',
                },
                accent: {
                    DEFAULT: 'rgb(var(--accent) / <alpha-value>)',
                    ink: 'rgb(var(--accent-ink) / <alpha-value>)',
                    hover: 'rgb(var(--accent-hover) / <alpha-value>)',
                    tint: 'rgb(var(--accent-tint) / <alpha-value>)',
                    line: 'rgb(var(--accent-line) / <alpha-value>)',
                    fg: 'rgb(var(--on-accent) / <alpha-value>)',
                },
                identity: {
                    DEFAULT: 'rgb(var(--identity) / <alpha-value>)',
                    tint: 'rgb(var(--identity-tint) / <alpha-value>)',
                    solid: 'rgb(var(--identity-solid) / <alpha-value>)',
                },
                /*
                 * tint = the background, ink = text on it, mark = the signal
                 * itself, solid = a filled control carrying `on-solid` text.
                 */
                wait: {
                    ink: 'rgb(var(--wait-ink) / <alpha-value>)',
                    mark: 'rgb(var(--wait-mark) / <alpha-value>)',
                    tint: 'rgb(var(--wait-tint) / <alpha-value>)',
                    solid: 'rgb(var(--wait-solid) / <alpha-value>)',
                },
                ready: {
                    ink: 'rgb(var(--ready-ink) / <alpha-value>)',
                    mark: 'rgb(var(--ready-mark) / <alpha-value>)',
                    tint: 'rgb(var(--ready-tint) / <alpha-value>)',
                    solid: 'rgb(var(--ready-solid) / <alpha-value>)',
                },
                stop: {
                    ink: 'rgb(var(--stop-ink) / <alpha-value>)',
                    mark: 'rgb(var(--stop-mark) / <alpha-value>)',
                    tint: 'rgb(var(--stop-tint) / <alpha-value>)',
                    solid: 'rgb(var(--stop-solid) / <alpha-value>)',
                },
                'on-solid': 'rgb(var(--on-solid) / <alpha-value>)',

                /*
                 * The wash behind a modal. Deliberately not an ink step: a
                 * scrim darkens in both themes, where every ink role inverts.
                 */
                scrim: 'rgb(var(--scrim) / <alpha-value>)',

                /*
                 * The raw ramps the semantic tokens are cut from. Screens
                 * should never reach for a step directly — these are kept as
                 * the palette's source of truth, so changing a hue starts in
                 * one place rather than in a grep.
                 */
                ash: {
                    0:   '#ffffff',
                    50:  '#f6f5f9',
                    100: '#ebe8f1',
                    200: '#d8d4e2',
                    300: '#b4aec4',
                    400: '#8e87a3',
                    500: '#6b6480',
                    600: '#4a4459',
                    800: '#2a2536',
                    950: '#15121c',
                },
                ube: {
                    50:  '#f3effe',
                    100: '#e6defd',
                    200: '#cfc0fb',
                    300: '#b09af6',
                    400: '#8d6fef',
                    500: '#7350e6',
                    600: '#5b3fd9',
                    700: '#4a2fb8',
                    800: '#3c2795',
                    900: '#2f2073',
                },
                kape: {
                    50:  '#f7f1ec',
                    200: '#d9c4b4',
                    500: '#7a5540',
                    700: '#4a3226',
                    900: '#2a1c17',
                },
            },
            boxShadow: {
                /*
                 * Named for the job, not the blur radius. The old set had
                 * `shadow-sm` and `shadow-card` used interchangeably for the
                 * same surface, which meant neither one said anything; three
                 * roles say what a thing is doing instead.
                 */
                'rest': 'var(--e-rest)',
                'raised': 'var(--e-raised)',
                'overlay': 'var(--e-overlay)',
            },
            borderRadius: {
                /* What it wraps, not how round it is. */
                'control': 'var(--r-control)',
                'card': 'var(--r-card)',
                'sheet': 'var(--r-sheet)',
            },
            transitionDuration: {
                'fast': 'var(--t-fast)',
                'base': 'var(--t-base)',
                'slow': 'var(--t-slow)',
            },
            transitionTimingFunction: {
                'out-soft': 'var(--ease-out)',
            },
            animation: {
                'fade-in': 'fadeIn 0.3s ease-out',
                'fade-in-up': 'fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) both',
                'scale-in': 'scaleIn 0.2s cubic-bezier(0.16, 1, 0.3, 1)',
                'sheet-up': 'sheetUp 0.26s cubic-bezier(0.16, 1, 0.3, 1)',
                'pulse-soft': 'pulseSoft 2s ease-in-out infinite',
            },
            keyframes: {
                fadeIn: {
                    from: { opacity: '0' },
                    to: { opacity: '1' },
                },
                fadeInUp: {
                    from: { opacity: '0', transform: 'translateY(12px)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
                scaleIn: {
                    from: { opacity: '0', transform: 'scale(0.95)' },
                    to: { opacity: '1', transform: 'scale(1)' },
                },
                // A sheet comes up from the edge it is attached to. Scaling it
                // from the middle would read as a dialog wearing a sheet's
                // shape.
                sheetUp: {
                    from: { transform: 'translateY(100%)' },
                    to: { transform: 'translateY(0)' },
                },
                pulseSoft: {
                    '0%, 100%': { opacity: '1' },
                    '50%': { opacity: '0.7' },
                },
            },
        },
    },

    plugins: [forms],
};
