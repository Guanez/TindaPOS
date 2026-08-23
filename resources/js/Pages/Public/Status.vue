<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/currency';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

import { CheckCircleIcon, XCircleIcon, ClockIcon } from '@heroicons/vue/24/outline';

const { money } = useCurrency();


const props = defineProps({
    order: { type: Object, required: true },
    store: { type: Object, required: true },
});

const TOKEN_KEY = 'tindapos_order_token';

// ── Live updates ────────────────────────────────────────────────────────
// Polling, not web push: iOS only delivers push to home-screen installs, so a
// notification would silently never arrive for a large share of customers.
// A page that watches itself works the same on every phone.
const POLL_MS = 5000;
let poller = null;

const finished = computed(() =>
    ['collected', 'rejected', 'expired'].includes(props.order.status),
);

const refresh = () => {
    if (finished.value) return;
    router.reload({ only: ['order'] });
};

onMounted(() => {
    poller = setInterval(refresh, POLL_MS);

    try {
        if (finished.value) localStorage.removeItem(TOKEN_KEY);
        else localStorage.setItem(TOKEN_KEY, props.order.token);
    } catch { /* private browsing */ }
});

onUnmounted(() => clearInterval(poller));

// ── Alert the customer the moment it is ready ───────────────────────────
const celebrate = () => {
    try {
        navigator.vibrate?.([120, 60, 120]);
    } catch { /* unsupported */ }

    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(660, ctx.currentTime);
        osc.frequency.setValueAtTime(990, ctx.currentTime + 0.15);
        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.6);
        osc.start();
        osc.stop(ctx.currentTime + 0.65);
    } catch { /* audio unavailable — the screen still turns green */ }
};

watch(
    () => props.order.status,
    (now, before) => {
        if (before && before !== 'ready' && now === 'ready') celebrate();
        if (finished.value) {
            try { localStorage.removeItem(TOKEN_KEY); } catch { /* ignore */ }
        }
    },
);

// ── Presentation ────────────────────────────────────────────────────────
const steps = [
    { key: 'placed', label: 'Order sent' },
    { key: 'paid', label: 'Paid — being made' },
    { key: 'ready', label: 'Ready' },
];

const stepIndex = computed(() => {
    const order = ['placed', 'paid', 'ready', 'collected'];
    return order.indexOf(props.order.status);
});

const isReady = computed(() => props.order.status === 'ready');
const isRejected = computed(() => ['rejected', 'expired'].includes(props.order.status));

const headline = computed(() => {
    switch (props.order.status) {
        case 'placed': return 'Pay at the counter';
        case 'paid': return 'We’re making it';
        case 'ready': return 'Ready — come and get it';
        case 'collected': return 'Enjoy!';
        case 'rejected': return 'Order declined';
        case 'expired': return 'Order expired';
        default: return '';
    }
});

const subline = computed(() => {
    switch (props.order.status) {
        case 'placed': return `Show number ${props.order.queue_number} at the till. We start once you’ve paid.`;
        case 'paid': return 'Hang tight — we’ll tell you the moment it’s ready.';
        case 'ready': return 'Collect at the counter.';
        case 'collected': return 'Thanks for ordering.';
        case 'expired': return 'Nobody came to the till, so we released it. Order again any time.';
        default: return '';
    }
});
</script>

<template>
    <div
        class="min-h-screen px-5 py-10"
        :class="isReady ? 'bg-emerald-600' : isRejected ? 'bg-slate-100' : 'bg-slate-50'"
        style="transition: background-color 0.4s;"
    >
        <Head :title="`Order #${order.queue_number} · ${store.name}`" />

        <div class="mx-auto max-w-md">
            <!-- Queue number -->
            <div
                class="rounded-3xl p-8 text-center shadow-card"
                :class="isReady ? 'bg-white' : 'bg-white'"
            >
                <p class="text-[11px] font-semibold uppercase tracking-widest" :class="isReady ? 'text-emerald-600' : 'text-slate-400'">
                    {{ store.name }}
                </p>

                <p class="mt-3 text-[13px] font-medium text-slate-500">Your number</p>
                <p
                    class="text-[80px] font-bold leading-none tabular-nums tracking-tight"
                    :class="isReady ? 'text-emerald-600' : 'text-slate-900'"
                >{{ order.queue_number }}</p>

                <div class="mt-5 flex flex-col items-center gap-2">
                    <CheckCircleIcon v-if="isReady" class="h-9 w-9 text-emerald-600" aria-hidden="true" />
                    <XCircleIcon v-else-if="isRejected" class="h-9 w-9 text-slate-400" aria-hidden="true" />
                    <ClockIcon v-else class="h-9 w-9 text-brand-500" aria-hidden="true" />

                    <h1 class="text-xl font-bold text-slate-900">{{ headline }}</h1>
                    <p class="text-[13px] leading-snug text-slate-500">{{ subline }}</p>
                </div>

                <p
                    v-if="order.reject_reason"
                    class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-[13px] font-medium text-red-700"
                >
                    {{ order.reject_reason }}
                </p>
            </div>

            <!-- Progress -->
            <ol v-if="!isRejected" class="mt-5 space-y-2" aria-label="Order progress">
                <li
                    v-for="(step, index) in steps"
                    :key="step.key"
                    class="flex items-center gap-3 rounded-xl bg-white/90 px-4 py-3"
                >
                    <span
                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white"
                        :class="index <= stepIndex ? 'bg-emerald-600' : 'bg-slate-200'"
                    >
                        <CheckCircleIcon v-if="index <= stepIndex" class="h-3.5 w-3.5" aria-hidden="true" />
                    </span>
                    <span
                        class="text-[13px] font-semibold"
                        :class="index <= stepIndex ? 'text-slate-800' : 'text-slate-400'"
                    >{{ step.label }}</span>
                </li>
            </ol>

            <!-- Items -->
            <div class="mt-5 rounded-2xl bg-white p-5 shadow-card">
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Your order</h2>

                <ul class="mt-3 divide-y divide-slate-100">
                    <li v-for="item in order.items" :key="item.id" class="flex items-start gap-3 py-2.5">
                        <span class="text-[13px] font-bold tabular-nums text-slate-400">{{ item.quantity }}&times;</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-semibold text-slate-800">
                                {{ item.name }}<span v-if="item.variant_name" class="text-slate-500"> ({{ item.variant_name }})</span>
                            </span>
                            <span v-if="item.modifiers.length" class="block text-[12px] text-brand-600">
                                + {{ item.modifiers.join(', ') }}
                            </span>
                        </span>
                        <span class="text-[13px] font-semibold tabular-nums text-slate-700">{{ money(item.line_total) }}</span>
                    </li>
                </ul>

                <div class="mt-3 flex items-baseline justify-between border-t border-slate-100 pt-3">
                    <span class="text-[13px] font-semibold text-slate-500">Total</span>
                    <span class="text-xl font-bold tabular-nums text-slate-900">{{ money(order.total) }}</span>
                </div>

                <p v-if="order.note" class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-[12px] italic text-slate-500">
                    &ldquo;{{ order.note }}&rdquo;
                </p>
            </div>

            <Link
                :href="`/s/${store.slug}`"
                class="mt-5 block rounded-2xl bg-white py-3.5 text-center text-[13px] font-semibold text-slate-600 shadow-card"
            >
                Back to the menu
            </Link>

            <p v-if="store.receipt_footer" class="mt-6 text-center text-[12px]" :class="isReady ? 'text-white/80' : 'text-slate-400'">
                {{ store.receipt_footer }}
            </p>
        </div>
    </div>
</template>
