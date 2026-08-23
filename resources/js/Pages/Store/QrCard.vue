<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { PrinterIcon } from '@heroicons/vue/24/outline';

defineProps({
    store: { type: Object, required: true },
    orderUrl: { type: String, required: true },
    qrSvg: { type: String, required: true },
    qrUnreachable: { type: Boolean, default: false },
});

// Deliberately not auto-printing: a shop should see the card before it
// commits paper to it.
onMounted(() => document.title = 'Order here');
</script>

<template>
    <div class="min-h-screen bg-surface-3 p-6 print:bg-surface-1 print:p-0">
        <Head title="Order here" />

        <!-- Screen-only controls -->
        <div class="mx-auto mb-5 flex max-w-[420px] items-center justify-between print:hidden">
            <p class="text-meta text-ink-3">Print at A5 or larger for a counter card.</p>
            <button class="btn-primary !py-2 text-meta" @click="window.print()">
                <PrinterIcon class="h-3.5 w-3.5" aria-hidden="true" />
                Print
            </button>
        </div>

        <!-- The card itself -->
        <div class="mx-auto flex max-w-[420px] flex-col items-center rounded-sheet bg-surface-1 px-8 py-10 text-center shadow-rest print:max-w-none print:rounded-none print:shadow-none">
            <p class="text-meta font-semibold uppercase tracking-[0.2em] text-accent-ink">Scan to order</p>

            <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink-1">{{ store.name }}</h1>
            <p v-if="store.address" class="mt-1 text-ui text-ink-3">{{ store.address }}</p>

            <div class="mt-7 w-full max-w-[300px]" v-html="qrSvg" />

            <ol class="mt-7 space-y-2 text-left">
                <li class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-tint text-meta font-bold text-accent-ink">1</span>
                    <span class="text-body text-ink-2">Scan the code and choose what you want</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-tint text-meta font-bold text-accent-ink">2</span>
                    <span class="text-body text-ink-2">Pay at the counter with your number</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-tint text-meta font-bold text-accent-ink">3</span>
                    <span class="text-body text-ink-2">Your phone tells you when it&rsquo;s ready</span>
                </li>
            </ol>


            <!--
                A code built from APP_URL=localhost prints and scans perfectly
                — it just resolves to nothing on a customer's phone. The
                failure has no symptom on this screen, so it gets stated here
                rather than discovered at a counter.
            -->
            <div
                v-if="qrUnreachable"
                role="alert"
                class="mt-6 rounded-control border border-wait-mark bg-wait-tint p-3 text-left print:hidden"
            >
                <p class="text-ui font-bold text-wait-ink">Do not print this yet</p>
                <p class="mt-1 text-meta leading-relaxed text-wait-ink">
                    This code points at <span class="font-mono">{{ orderUrl }}</span>, which only
                    works on the computer running the app. Set <span class="font-mono">APP_URL</span>
                    to the address customers will use, then reload this page.
                </p>
            </div>

            <p class="mt-7 break-all font-mono text-meta text-ink-3">{{ orderUrl }}</p>
        </div>
    </div>
</template>

<style>
@media print {
    @page { margin: 12mm; }
}
</style>
