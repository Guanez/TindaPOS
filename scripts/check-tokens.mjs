#!/usr/bin/env node
/**
 * Keeps the design system from leaking.
 *
 * The token layer only pays for itself if screens actually use it. A single
 * `text-sm` is invisible in review and costs nothing on the day — but the
 * density switch works by swapping the type ROLES underneath the app, so a
 * raw size is a control that quietly opts that element out of the whole
 * mechanism. Eighty-five of them had accumulated before anyone noticed the
 * customer pages were not growing on a phone.
 *
 * The count is zero now, so this runs with no baseline: any violation fails.
 * If a large batch ever needs grandfathering again, add the baseline back —
 * do not weaken the rule to let one through.
 *
 *   npm run lint:tokens
 */

import { readdirSync, readFileSync, statSync, existsSync } from 'node:fs';
import { join, relative, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

const SCAN = [
    { dir: 'resources/js', ext: '.vue' },
    { dir: 'resources/views', ext: '.blade.php' },
];

/*
 * Each rule names what to reach for instead. A checker that only says "no"
 * gets suppressed; one that says what to write gets followed.
 */
const RULES = [
    {
        id: 'type-size',
        // Variant prefixes (`sm:`, `hover:`, `print:`) end in `:`, which \b treats
        // as a boundary, so `sm:text-lg` is caught by the same pattern.
        pattern: /\btext-(xs|sm|base|lg|xl|[2-9]xl)\b/g,
        hint: 'use a type role',
        // How the sweep mapped them, so the next person makes the same call.
        // The two judgement calls worth keeping: `figure` is the number a
        // component exists to communicate — the queue number a barista reads
        // across a counter — not any number; a summary tile in a grid takes
        // `heading`, because 28px of peso figure clips in a four-up.
        suggest: {
            'text-xs': 'text-meta',
            'text-sm': 'text-ui',
            'text-base': 'text-body',
            'text-lg': 'text-title',
            'text-xl': 'text-heading',
            'text-2xl': 'text-heading (text-figure if the number is the point)',
            'text-3xl': 'text-figure',
        },
    },
    {
        id: 'type-arbitrary',
        pattern: /\btext-\[[0-9.]+(px|rem|em)\]/g,
        hint: 'a one-off size cannot follow the density switch — add a role or reuse one',
    },
    {
        id: 'raw-colour',
        pattern: /\b(?:bg|text|border|ring|divide|placeholder|from|via|to|shadow|outline|accent|caret|decoration)-(?:slate|gray|grey|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b/g,
        hint: 'use a semantic colour: surface ink line action accent identity wait ready stop',
    },
    {
        id: 'absolute-colour',
        // White and black do not invert. `text-white` on `bg-ink-1` was white
        // text on a near-white surface the moment the OS was set to dark.
        pattern: /\b(?:bg|text|border|ring|divide|placeholder|from|via|to)-(?:white|black)(?:\/\d{1,3})?\b/g,
        hint: 'use action-fg / accent-fg / on-solid, which flip with the theme',
    },
    {
        id: 'inert-shadow',
        // The elevation roles resolve to a `var()`, and Tailwind can only apply
        // an opacity modifier to a shadow whose colour it can see. `/50` on one
        // of these emits no rule at all — the class reads as intent and does
        // nothing, which is worse than being absent.
        pattern: /\bshadow-(?:rest|raised|overlay)\/\d{1,3}\b/g,
        hint: 'drop the modifier, or set the alpha in the --e-* token itself',
    },
    {
        id: 'removed-alias',
        pattern: /\b(?:shadow-(?:card|card-hover|elevated|glow)|(?:bg|text|border|ring|from|via|to)-brand-\d{2,3})\b/g,
        hint: 'removed in the token pass — use shadow-rest/raised/overlay, or an accent token',
    },
];

/*
 * `file:rule` pairs that are deliberately allowed, each with the reason.
 * Empty today — everything the app renders belongs to a theme. The likely
 * first entry is something printed rather than displayed, where black on
 * white is a specification rather than a colour choice.
 */
const EXEMPT = [];

function walk(dir, ext, out = []) {
    if (!existsSync(dir)) return out;
    for (const entry of readdirSync(dir)) {
        const path = join(dir, entry);
        if (statSync(path).isDirectory()) walk(path, ext, out);
        else if (path.endsWith(ext)) out.push(path);
    }
    return out;
}

const findings = [];

for (const { dir, ext } of SCAN) {
    for (const file of walk(join(ROOT, dir), ext)) {
        const rel = relative(ROOT, file).split('\\').join('/');
        const lines = readFileSync(file, 'utf8').split('\n');

        lines.forEach((line, index) => {
            for (const rule of RULES) {
                if (EXEMPT.includes(`${rel}:${rule.id}`)) continue;
                for (const match of line.matchAll(rule.pattern)) {
                    findings.push({
                        file: rel,
                        line: index + 1,
                        rule: rule.id,
                        token: match[0],
                        hint: rule.hint,
                        suggest: rule.suggest?.[match[0]],
                    });
                }
            }
        });
    }
}

if (!findings.length) {
    console.log('\nDesign tokens: clean.\n');
    process.exit(0);
}

console.error(`\n${findings.length} design-token violation(s):\n`);

const byRule = {};
for (const f of findings) (byRule[f.rule] ??= []).push(f);

for (const [rule, items] of Object.entries(byRule)) {
    console.error(`  ${rule} — ${items[0].hint}`);
    for (const f of items) {
        const fix = f.suggest ? `  →  ${f.suggest}` : '';
        console.error(`    ${f.file}:${f.line}  ${f.token}${fix}`);
    }
    console.error('');
}

console.error('If a violation is genuinely correct, add it to EXEMPT in this script');
console.error('with the reason.\n');
process.exit(1);
