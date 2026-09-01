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
 * So the rule is enforced by a script rather than by remembering.
 *
 * Usage:
 *   node scripts/check-tokens.mjs              # fail on anything new
 *   node scripts/check-tokens.mjs --update-baseline
 *
 * The baseline exists because the fix lands in stages: known violations are
 * recorded per file, and the check fails only when a count goes UP or a new
 * file appears. Lowering a count is free — the script tells you when the
 * baseline is looser than reality so it can be tightened as work lands.
 * When the baseline reaches zero it should be deleted along with this note.
 */

import { readdirSync, readFileSync, writeFileSync, statSync, existsSync } from 'node:fs';
import { join, relative, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const BASELINE = join(ROOT, 'scripts', 'token-baseline.json');

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
        hint: 'use a type role: label meta ui body title heading figure hero',
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
                    });
                }
            }
        });
    }
}

/* Counts per file per rule — line numbers move under every edit, counts do not. */
const tally = {};
for (const f of findings) {
    tally[f.file] ??= {};
    tally[f.file][f.rule] = (tally[f.file][f.rule] ?? 0) + 1;
}

if (process.argv.includes('--update-baseline')) {
    writeFileSync(BASELINE, `${JSON.stringify(tally, null, 4)}\n`);
    const total = findings.length;
    console.log(`Baseline written: ${total} known violation${total === 1 ? '' : 's'} across ${Object.keys(tally).length} file(s).`);
    process.exit(0);
}

const baseline = existsSync(BASELINE) ? JSON.parse(readFileSync(BASELINE, 'utf8')) : {};

const regressions = findings.filter(
    (f) => (tally[f.file][f.rule] ?? 0) > (baseline[f.file]?.[f.rule] ?? 0),
);

/* Where reality is now better than the baseline, say so — that is the point. */
const improved = [];
for (const [file, rules] of Object.entries(baseline)) {
    for (const [rule, was] of Object.entries(rules)) {
        const now = tally[file]?.[rule] ?? 0;
        if (now < was) improved.push(`  ${file}  ${rule}  ${was} → ${now}`);
    }
}

if (regressions.length) {
    console.error(`\n${regressions.length} new design-token violation(s):\n`);
    const byRule = {};
    for (const f of regressions) (byRule[f.rule] ??= []).push(f);
    for (const [rule, items] of Object.entries(byRule)) {
        console.error(`  ${rule} — ${items[0].hint}`);
        for (const f of items) console.error(`    ${f.file}:${f.line}  ${f.token}`);
        console.error('');
    }
    console.error('If a violation is genuinely correct, add it to EXEMPT in this script');
    console.error('with the reason, rather than raising the baseline.\n');
    process.exit(1);
}

if (improved.length) {
    console.log('\nBaseline is looser than the code — run with --update-baseline to lock the gains in:');
    console.log(improved.join('\n'));
}

const remaining = Object.values(tally).reduce(
    (sum, rules) => sum + Object.values(rules).reduce((a, b) => a + b, 0),
    0,
);
console.log(`\nNo new violations. ${remaining} known violation(s) still to sweep.\n`);
