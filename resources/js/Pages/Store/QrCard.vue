<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { PrinterIcon } from '@heroicons/vue/24/outline';

defineProps({
    store: { type: Object, required: true },
    orderUrl: { type: String, required: true },
    qrSvg: { type: String, required: true },
});

// Deliberately not auto-printing: a shop should see the card before it
// commits paper to it.
onMounted(() => document.title = 'Order here');
</script>

<template>
    <div class="min-h-screen bg-slate-100 p-6 print:bg-white print:p-0">
        <Head title="Order here" />

        <!-- Screen-only controls -->
        <div class="mx-auto mb-5 flex max-w-[420px] items-center justify-between print:hidden">
            <p class="text-[12px] text-slate-500">Print at A5 or larger for a counter card.</p>
            <button class="btn-primary !py-2 text-[12px]" @click="window.print()">
                <PrinterIcon class="h-3.5 w-3.5" aria-hidden="true" />
                Print
            </button>
        </div>

        <!-- The card itself -->
        <div class="mx-auto flex max-w-[420px] flex-col items-center rounded-3xl bg-white px-8 py-10 text-center shadow-card print:max-w-none print:rounded-none print:shadow-none">
            <p class="text-[12px] font-semibold uppercase tracking-[0.2em] text-brand-600">Scan to order</p>

            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ store.name }}</h1>
            <p v-if="store.address" class="mt-1 text-[13px] text-slate-500">{{ store.address }}</p>

            <div class="mt-7 w-full max-w-[300px]" v-html="qrSvg" />

            <ol class="mt-7 space-y-2 text-left">
                <li class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[12px] font-bold text-brand-700">1</span>
                    <span class="text-[14px] text-slate-700">Scan the code and choose what you want</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[12px] font-bold text-brand-700">2</span>
                    <span class="text-[14px] text-slate-700">Pay at the counter with your number</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[12px] font-bold text-brand-700">3</span>
                    <span class="text-[14px] text-slate-700">Your phone tells you when it&rsquo;s ready</span>
                </li>
            </ol>

            <p class="mt-7 break-all font-mono text-[11px] text-slate-400">{{ orderUrl }}</p>
        </div>
    </div>
</template>

<style>
@media print {
    @page { margin: 12mm; }
}
</style>
