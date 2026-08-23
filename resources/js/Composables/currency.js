import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

/**
 * Money, in the symbol the shop actually chose.
 *
 * stores.currency_symbol was editable long before anything read it — the
 * settings form saved it, showed a success toast, and every figure on every
 * screen went on saying ₱ regardless. A control that lies about having an
 * effect is worse than no control, so this is the one place that decides.
 *
 * The symbol comes from the shared `store` prop, which means it follows the
 * store in context: a platform admin standing inside a client shop formats
 * money the way that shop does, not the way the platform does.
 *
 * Not everyone wants the ₱ glyph. Thermal receipt printers routinely cannot
 * render it and produce a black box or drop the character, which is exactly
 * why a shop would set a plain "P" instead.
 */
export function useCurrency() {
    const page = usePage()

    // No store in context — the platform console — falls back to the peso
    // rather than to nothing, since every shop on this platform is Filipino.
    const symbol = computed(() => page.props.store?.currency_symbol || '₱')

    const money = (amount) => formatWith(symbol.value, amount)

    return { symbol, money }
}

/**
 * The formatting itself, free of any store. Exported for the rare caller that
 * already knows its symbol — a list of several shops at once, where each row
 * has to be shown in its own.
 */
export function formatWith(symbol, amount) {
    const num = parseFloat(amount) || 0

    return symbol + num.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}
