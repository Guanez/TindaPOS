<script setup>
import { useCurrency } from '@/Composables/currency'
import { CheckCircleIcon } from '@heroicons/vue/24/outline'

/*
 * The receipt, wherever it is printed from.
 *
 * It used to live inside the till screen, which meant the sales history had
 * no way to reprint one without a second copy of the same markup — and a
 * second copy is a second thing to forget when the shop changes its footer.
 *
 * The print rules travel with it. They are deliberately global rather than
 * scoped: `@media print` here hides everything in the document except this
 * element, which is a statement about the page, not about this component.
 */

const props = defineProps({
    sale: { type: Object, required: true },
    store: { type: Object, default: () => ({}) },
    /* Who served it. Absent on a reprint by someone who did not. */
    cashierName: { type: String, default: null },
    /* Rendered as given — the caller knows whether this is now or then. */
    printedAt: { type: String, default: '' },
    /*
     * A reprint says so, on the paper.
     *
     * Two people holding what look like two original receipts for one sale is
     * a real problem in a shop that reconciles cash by hand, and the person
     * who can tell them apart is not the one holding them.
     */
    reprint: { type: Boolean, default: false },
})

const { money } = useCurrency()

const hasDiscount = () => parseFloat(props.sale.discount ?? 0) > 0
</script>

<template>
    <div id="receipt">
        <div class="text-center">
            <!-- The tick is feedback for the till, not part of the paper. -->
            <div v-if="!reprint" class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-ready-tint print:hidden">
                <CheckCircleIcon class="h-7 w-7 text-ready-ink" aria-hidden="true" />
            </div>

            <p v-if="reprint" class="mb-2 text-label font-bold uppercase tracking-widest text-ink-3">
                Reprint
            </p>

            <h2 class="text-title font-bold text-ink-1" :class="reprint || 'mt-3 print:mt-0'">
                {{ store.name || 'Sale' }}
            </h2>
            <p v-if="store.address" class="text-meta leading-snug text-ink-3">{{ store.address }}</p>
            <p v-if="store.phone" class="text-meta text-ink-3">{{ store.phone }}</p>

            <p class="mt-2 font-mono text-ui text-ink-3">{{ sale.receipt_number }}</p>
            <p class="text-meta text-ink-3">
                {{ printedAt }}<span v-if="cashierName"> &middot; {{ cashierName }}</span>
            </p>

            <!--
                A voided sale can still be reprinted — someone asking for a
                copy of one usually has a question about it — but the paper
                has to say so, or it reads as proof of a sale that was undone.
            -->
            <p v-if="sale.status === 'voided'" class="mt-2 rounded-control bg-stop-tint px-3 py-1.5 text-meta font-bold uppercase tracking-widest text-stop-ink">
                Voided
            </p>
        </div>

        <!-- What was actually sold -->
        <ul v-if="sale.items?.length" class="mt-4 space-y-1.5 border-t border-line pt-3">
            <li v-for="item in sale.items" :key="item.id" class="flex items-start gap-2 text-meta">
                <span class="font-semibold tabular-nums text-ink-3">{{ item.quantity }}&times;</span>
                <span class="min-w-0 flex-1 text-ink-2">
                    {{ item.product_name }}<span v-if="item.variant_name" class="text-ink-3"> ({{ item.variant_name }})</span>
                    <span v-if="item.modifiers?.length" class="block text-meta text-ink-3">
                        + {{ item.modifiers.map(m => m.name).join(', ') }}
                    </span>
                </span>
                <span class="tabular-nums text-ink-2">{{ money(item.line_total) }}</span>
            </li>
        </ul>

        <div class="mt-5 space-y-2 rounded-control bg-surface-2 p-4 text-ui">
            <div class="flex justify-between text-ink-3">
                <span>Items</span>
                <span class="font-medium text-ink-2">{{ sale.item_count }}</span>
            </div>
            <div class="flex justify-between text-ink-3">
                <span>Subtotal</span>
                <span class="text-ink-2">{{ money(sale.subtotal) }}</span>
            </div>
            <div v-if="hasDiscount()" class="flex justify-between text-ready-ink">
                <span>Discount</span>
                <span>-{{ money(sale.discount) }}</span>
            </div>
            <div class="flex justify-between border-t border-line pt-2 text-title font-bold text-ink-1">
                <span>Total</span>
                <span>{{ money(sale.total) }}</span>
            </div>
            <div class="flex justify-between text-ink-3">
                <span>Payment</span>
                <span class="font-medium text-ink-2">{{ sale.payment_method?.toUpperCase() }}</span>
            </div>
            <div v-if="sale.cash_received" class="flex justify-between text-ink-3">
                <span>Cash Received</span>
                <span class="text-ink-2">{{ money(sale.cash_received) }}</span>
            </div>
            <div v-if="sale.change_amount" class="flex justify-between font-bold text-ready-ink">
                <span>Change</span>
                <span>{{ money(sale.change_amount) }}</span>
            </div>
        </div>

        <p v-if="store.receipt_footer" class="mt-4 text-center text-meta italic text-ink-3">
            {{ store.receipt_footer }}
        </p>
    </div>
</template>

<style>
/* Printing from anywhere in the app should produce the receipt, nothing else. */
@media print {
    body * { visibility: hidden; }
    #receipt, #receipt * { visibility: visible; }
    #receipt {
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        max-width: none;
        box-shadow: none;
        padding: 0;
    }
    @page { margin: 8mm; }
}
</style>
