import { computed, readonly, ref } from 'vue'

/*
 * ─── Colour theme ───────────────────────────────────────────────────────
 *
 * Three states, not two. "system" is the default and follows the handset,
 * which is what a customer scanning a QR code in the evening wants without
 * being asked; "light" and "dark" are a deliberate override of that, and
 * both have to survive a reload — a barista should not re-pick the theme
 * every time the till navigates to another page.
 *
 * The whole mechanism is one attribute on <html>. app.css defines light on
 * bare :root, dark under prefers-color-scheme guarded by
 * :not([data-theme='light']), and dark again under [data-theme='dark'] — so
 * stamping the attribute overrides the OS in either direction, and removing
 * it hands the question back. No component reads any of this.
 *
 * State lives at module scope on purpose: the toggle in the topbar and the
 * one on the login screen are the same control, and two independent copies
 * would disagree the moment one of them was used.
 */

/*
 * Shared with the inline script in app.blade.php, which stamps the
 * attribute before the first paint. If this key or these two values change,
 * that script changes with them.
 */
const STORAGE_KEY = 'tindapos-theme'

/*
 * The browser's own chrome — the address bar on Android, the title bar of
 * an installed PWA. It cannot read a custom property, so these are a hand
 * copy of --surface-2 in each theme and have to be changed alongside it.
 */
const CHROME = { light: '#f6f5f9', dark: '#100d16' }

function readStored() {
    try {
        const value = window.localStorage.getItem(STORAGE_KEY)

        return value === 'light' || value === 'dark' ? value : 'system'
    } catch {
        // Safari in private browsing throws on localStorage rather than
        // returning null, and a colour theme is never worth failing boot
        // over. Falling back to the OS is the correct answer anyway.
        return 'system'
    }
}

const preference = ref(readStored())
const query = window.matchMedia('(prefers-color-scheme: dark)')
const systemPrefersDark = ref(query.matches)

/* What is actually on screen, once "system" has been resolved. */
const resolved = computed(() =>
    preference.value === 'system'
        ? (systemPrefersDark.value ? 'dark' : 'light')
        : preference.value,
)

function apply() {
    const root = document.documentElement

    if (preference.value === 'system') {
        root.removeAttribute('data-theme')
    } else {
        root.setAttribute('data-theme', preference.value)
    }

    const meta = document.querySelector('meta[name="theme-color"]')

    if (meta !== null) {
        meta.setAttribute('content', CHROME[resolved.value])
    }
}

/*
 * A phone switching itself to dark at sunset has to move the page with it,
 * but only while the shop is on "system" — apply() reads the preference, so
 * an explicit choice is left alone and just the resolved value updates.
 */
query.addEventListener('change', (event) => {
    systemPrefersDark.value = event.matches
    apply()
})

function setTheme(next) {
    preference.value = next === 'light' || next === 'dark' ? next : 'system'

    try {
        if (preference.value === 'system') {
            // Removed rather than stored as the string "system", so that the
            // inline script's check is a plain "is this light or dark" and
            // the absent case needs no special handling on either side.
            window.localStorage.removeItem(STORAGE_KEY)
        } else {
            window.localStorage.setItem(STORAGE_KEY, preference.value)
        }
    } catch {
        // The choice still applies to this session; it just will not be
        // remembered. Silently keeping the page usable beats throwing.
    }

    apply()
}

// The inline script has already stamped the attribute by now. This runs
// anyway to cover the theme-color meta and the case where storage was
// unreadable there but readable here.
apply()

export function useTheme() {
    return {
        /* 'light' | 'dark' | 'system' — what the user chose. */
        preference: readonly(preference),
        /* 'light' | 'dark' — what they are looking at. */
        resolved,
        setTheme,
    }
}
