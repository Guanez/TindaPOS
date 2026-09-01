import { readonly, ref } from 'vue'

/*
 * ─── The sound a new order makes ────────────────────────────────────────
 *
 * Lives here rather than on the queue screen because the person who needs to
 * hear it is usually somewhere else — ringing up a walk-in on the POS, or
 * counting stock. An alert that only plays on the page you are already
 * watching is not an alert.
 *
 * State is at module scope for the same reason the theme's is: the toggle in
 * the topbar and the chime in the layout are one control, and two copies
 * would disagree the moment either was used.
 */

const STORAGE_KEY = 'tindapos-queue-sound'

function readStored() {
    try {
        // Absent means on. A shop that has never touched this should hear
        // its orders; only an explicit "off" is worth remembering.
        return window.localStorage.getItem(STORAGE_KEY) !== 'off'
    } catch {
        // Safari in private browsing throws rather than returning null, and
        // a preference is never worth failing boot over.
        return true
    }
}

const soundOn = ref(readStored())

function setSound(on) {
    soundOn.value = on

    try {
        if (on) window.localStorage.removeItem(STORAGE_KEY)
        else window.localStorage.setItem(STORAGE_KEY, 'off')
    } catch {
        // Applies to this session; it just will not be remembered.
    }
}

/*
 * One AudioContext for the life of the tab.
 *
 * Building a fresh one per chime leaks: browsers cap a page at a few dozen
 * contexts and then refuse to create more, so a busy morning would end in
 * silence. Created lazily on the first chime, which is also the earliest
 * point a user gesture has plausibly unlocked audio.
 */
let ctx = null

function tone() {
    if (ctx === null) {
        const Ctor = window.AudioContext || window.webkitAudioContext
        if (!Ctor) return
        ctx = new Ctor()
    }

    // Autoplay policy suspends a context created before any gesture. Resuming
    // is a no-op when it is already running.
    if (ctx.state === 'suspended') ctx.resume()

    const osc = ctx.createOscillator()
    const gain = ctx.createGain()

    osc.connect(gain)
    gain.connect(ctx.destination)

    osc.type = 'sine'
    osc.frequency.setValueAtTime(880, ctx.currentTime)
    osc.frequency.setValueAtTime(1320, ctx.currentTime + 0.12)

    gain.gain.setValueAtTime(0.0001, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + 0.02)
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.45)

    osc.start()
    osc.stop(ctx.currentTime + 0.5)
}

function chime() {
    if (!soundOn.value) return

    try {
        tone()
    } catch {
        // Audio unavailable — the badge still moves, which is the part that
        // cannot fail.
    }
}

export function useQueueAlert() {
    return {
        soundOn: readonly(soundOn),
        setSound,
        chime,
    }
}
