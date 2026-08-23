<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/Composables/currency';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

import {
    BanknotesIcon,
    CheckCircleIcon,
    NoSymbolIcon,
    QueueListIcon,
    BellAlertIcon,
    XMarkIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline';

const { money } = useCurrency();

const props = defineProps({
    orders: { type: Array, default: () => [] },
    recentlyFinished: { type: Array, default: () => [] },
});

const awaitingPayment = computed(() => props.orders.filter((o) => o.status === 'placed'));
const preparing = computed(() => props.orders.filter((o) => o.status === 'paid'));
const ready = computed(() => props.orders.filter((o) => o.status === 'ready'));

// ── Live refresh ────────────────────────────────────────────────────────
// Polling rather than websockets: it needs no extra infrastructure and cafe
// volume does not justify any. Only the orders prop is refetched.
const POLL_MS = 5000;
let poller = null;
const paused = ref(false);

const refresh = () => {
    if (paused.value) return;
    router.reload({ only: ['orders', 'recentlyFinished'] });
};

onMounted(() => { poller = setInterval(refresh, POLL_MS); });
onUnmounted(() => clearInterval(poller));

// ── Chime when something new arrives ────────────────────────────────────
const soundOn = ref(true);

const chime = () => {
    if (!soundOn.value) return;
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.setValueAtTime(1320, ctx.currentTime + 0.12);
        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.45);
        osc.start();
        osc.stop(ctx.currentTime + 0.5);
    } catch {
        /* Audio unavailable — the badge still updates. */
    }
};

watch(
    () => awaitingPayment.value.length,
    (now, before) => { if (before !== undefined && now > before) chime(); },
);

// ── Settle ──────────────────────────────────────────────────────────────
const settling = ref(null);

const settleForm = useForm({
    payment_method: 'cash',
    cash_received: '',
    discount: 0,
});

const openSettle = (order) => {
    settling.value = order;
    settleForm.reset();
    settleForm.clearErrors();
    paused.value = true;
};

const closeSettle = () => {
    settling.value = null;
    paused.value = false;
};

const settleTotal = computed(() => parseFloat(settling.value?.total ?? 0));
const cashReceived = computed(() => parseFloat(settleForm.cash_received) || 0);
const change = computed(() => Math.max(0, cashReceived.value - settleTotal.value));

const canSettle = computed(
    () => settleForm.payment_method !== 'cash' || cashReceived.value >= settleTotal.value,
);

const submitSettle = () => {
    if (!canSettle.value) return;

    settleForm
        .transform((data) => ({
            ...data,
            cash_received: data.payment_method === 'cash' ? cashReceived.value : null,
        }))
        .post(route('orders.settle', settling.value.id), {
            preserveScroll: true,
            onSuccess: closeSettle,
        });
};

// ── Reject ──────────────────────────────────────────────────────────────
const rejecting = ref(null);
const rejectForm = useForm({ reason: '' });

const openReject = (order) => {
    rejecting.value = order;
    rejectForm.reset();
    rejectForm.clearErrors();
    paused.value = true;
};

const closeReject = () => {
    rejecting.value = null;
    paused.value = false;
};

const submitReject = () => {
    rejectForm.post(route('orders.reject', rejecting.value.id), {
        preserveScroll: true,
        onSuccess: closeReject,
    });
};

// ── Simple transitions ──────────────────────────────────────────────────
const advance = (order, action) => {
    router.post(route(`orders.${action}`, order.id), {}, { preserveScroll: true });
};

const waitingSince = (order) => {
    const minutes = Math.floor((Date.now() - new Date(order.placed_at).getTime()) / 60000);
    if (minutes < 1) return 'just now';
    return `${minutes} min ago`;
};
</script>

<template>
    <AppLayout>
        <Head title="Order Queue" />

        <div class="space-y-5">
            <!-- Header -->
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-ink-1">Order Queue</h1>
                    <p class="mt-0.5 text-ui text-ink-3">
                        Orders customers placed from the QR menu, updating live
                    </p>
                </div>

                <button
                    class="flex items-center gap-2 rounded-control border border-line px-3 py-2 text-meta font-semibold text-ink-2 hover:bg-surface-2"
                    style="transition: background-color var(--t-fast);"
                    :aria-pressed="soundOn"
                    @click="soundOn = !soundOn"
                >
                    <BellAlertIcon class="h-4 w-4" :class="soundOn ? 'text-accent-ink' : 'text-ink-3'" aria-hidden="true" />
                    {{ soundOn ? 'Sound on' : 'Sound off' }}
                </button>
            </div>

            <!-- Columns -->
            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Awaiting payment -->
                <section class="card flex flex-col overflow-hidden">
                    <header class="flex items-center justify-between border-b border-line bg-wait-tint/60 px-4 py-3">
                        <h2 class="text-ui font-bold text-wait-ink">Awaiting payment</h2>
                        <span class="badge badge-warning tabular-nums">{{ awaitingPayment.length }}</span>
                    </header>

                    <div class="flex-1 space-y-2 p-3">
                        <p v-if="awaitingPayment.length === 0" class="py-8 text-center text-ui text-ink-3">
                            Nothing waiting
                        </p>

                        <article
                            v-for="order in awaitingPayment"
                            :key="order.id"
                            class="rounded-control border border-amber-200 bg-surface-1 p-3 shadow-rest"
                        >
                            <div class="flex items-baseline justify-between">
                                <span class="text-lg font-bold tabular-nums text-ink-1">#{{ order.queue_number }}</span>
                                <span class="text-meta text-ink-3">
                                    <ClockIcon class="mr-0.5 inline h-3 w-3" aria-hidden="true" />{{ waitingSince(order) }}
                                </span>
                            </div>

                            <p v-if="order.customer_name" class="text-meta font-medium text-ink-2">{{ order.customer_name }}</p>

                            <ul class="mt-2 space-y-1">
                                <li v-for="item in order.items" :key="item.id" class="text-meta text-ink-2">
                                    <span class="font-semibold tabular-nums">{{ item.quantity }}&times;</span>
                                    {{ item.product_name }}<span v-if="item.variant_name" class="text-ink-3"> ({{ item.variant_name }})</span>
                                    <span v-if="item.modifiers?.length" class="block pl-5 text-meta text-accent-ink">
                                        + {{ item.modifiers.map(m => m.name).join(', ') }}
                                    </span>
                                </li>
                            </ul>

                            <p v-if="order.note" class="mt-2 rounded-control bg-surface-2 px-2 py-1 text-meta italic text-ink-3">
                                &ldquo;{{ order.note }}&rdquo;
                            </p>

                            <p class="mt-2 text-right text-body font-bold tabular-nums text-ink-1">
                                {{ money(order.total) }}
                            </p>

                            <div class="mt-3 flex gap-2">
                                <button class="btn-secondary flex-1 justify-center !py-2 text-meta" @click="openReject(order)">
                                    <NoSymbolIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                    Reject
                                </button>
                                <button class="btn-primary flex-1 justify-center !py-2 text-meta" @click="openSettle(order)">
                                    <BanknotesIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                    Take payment
                                </button>
                            </div>
                        </article>
                    </div>
                </section>

                <!-- Preparing -->
                <section class="card flex flex-col overflow-hidden">
                    <header class="flex items-center justify-between border-b border-line bg-accent-tint/60 px-4 py-3">
                        <h2 class="text-ui font-bold text-accent-ink">Preparing</h2>
                        <span class="badge badge-info tabular-nums">{{ preparing.length }}</span>
                    </header>

                    <div class="flex-1 space-y-2 p-3">
                        <p v-if="preparing.length === 0" class="py-8 text-center text-ui text-ink-3">
                            Nothing being made
                        </p>

                        <article
                            v-for="order in preparing"
                            :key="order.id"
                            class="rounded-control border border-line bg-surface-1 p-3 shadow-rest"
                        >
                            <div class="flex items-baseline justify-between">
                                <span class="text-lg font-bold tabular-nums text-ink-1">#{{ order.queue_number }}</span>
                                <span class="badge badge-success">Paid</span>
                            </div>

                            <ul class="mt-2 space-y-1">
                                <li v-for="item in order.items" :key="item.id" class="text-meta text-ink-2">
                                    <span class="font-semibold tabular-nums">{{ item.quantity }}&times;</span>
                                    {{ item.product_name }}<span v-if="item.variant_name" class="text-ink-3"> ({{ item.variant_name }})</span>
                                    <span v-if="item.modifiers?.length" class="block pl-5 text-meta text-accent-ink">
                                        + {{ item.modifiers.map(m => m.name).join(', ') }}
                                    </span>
                                </li>
                            </ul>

                            <button class="btn-primary mt-3 w-full justify-center !py-2 text-meta" @click="advance(order, 'ready')">
                                <CheckCircleIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                Mark ready
                            </button>
                        </article>
                    </div>
                </section>

                <!-- Ready -->
                <section class="card flex flex-col overflow-hidden">
                    <header class="flex items-center justify-between border-b border-line bg-ready-tint/60 px-4 py-3">
                        <h2 class="text-ui font-bold text-ready-ink">Ready for pickup</h2>
                        <span class="badge badge-success tabular-nums">{{ ready.length }}</span>
                    </header>

                    <div class="flex-1 space-y-2 p-3">
                        <p v-if="ready.length === 0" class="py-8 text-center text-ui text-ink-3">
                            Nothing waiting to be collected
                        </p>

                        <article
                            v-for="order in ready"
                            :key="order.id"
                            class="rounded-control border border-ready-tint bg-ready-tint/30 p-3 shadow-rest"
                        >
                            <div class="flex items-baseline justify-between">
                                <span class="text-2xl font-bold tabular-nums text-ready-ink">#{{ order.queue_number }}</span>
                                <span v-if="order.customer_name" class="text-meta font-medium text-ink-2">{{ order.customer_name }}</span>
                            </div>

                            <button class="btn-secondary mt-3 w-full justify-center !py-2 text-meta" @click="advance(order, 'collect')">
                                Collected
                            </button>
                        </article>
                    </div>
                </section>
            </div>

            <!-- Recently finished -->
            <section v-if="recentlyFinished.length" class="card p-4">
                <h2 class="text-label font-semibold uppercase tracking-wider text-ink-3">Finished today</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="order in recentlyFinished"
                        :key="order.id"
                        class="badge"
                        :class="order.status === 'collected' ? 'badge-neutral' : 'badge-danger'"
                        :title="order.reject_reason ?? ''"
                    >
                        #{{ order.queue_number }} · {{ order.status }}
                    </span>
                </div>
            </section>
        </div>

        <!-- SETTLE -->
        <Teleport to="body">
            <div
                v-if="settling"
                class="fixed inset-0 z-50 flex items-center justify-center bg-ink-1/40 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-label="Take payment"
            >
                <div class="w-full max-w-md rounded-card bg-surface-1 p-6 shadow-overlay animate-scale-in">
                    <div class="flex items-center gap-2">
                        <BanknotesIcon class="h-5 w-5 text-accent-ink" aria-hidden="true" />
                        <h3 class="text-lg font-bold text-ink-1">Order #{{ settling.queue_number }}</h3>
                    </div>

                    <p class="mt-4 flex items-baseline justify-between rounded-control bg-surface-2 px-4 py-3">
                        <span class="text-ui text-ink-3">Total</span>
                        <span class="text-2xl font-bold tabular-nums text-ink-1">{{ money(settling.total) }}</span>
                    </p>

                    <div class="mt-5">
                        <label class="text-ui font-semibold text-ink-2">Payment method</label>
                        <div class="mt-2 grid grid-cols-5 gap-1.5">
                            <button
                                v-for="method in ['cash', 'gcash', 'maya', 'card', 'other']"
                                :key="method"
                                type="button"
                                :class="[
                                    'rounded-control border py-2 text-meta font-semibold capitalize transition-all',
                                    settleForm.payment_method === method
                                        ? 'border-accent bg-accent-tint text-accent-ink'
                                        : 'border-line text-ink-3 hover:bg-surface-2',
                                ]"
                                @click="settleForm.payment_method = method"
                            >
                                {{ method }}
                            </button>
                        </div>
                    </div>

                    <div v-if="settleForm.payment_method === 'cash'" class="mt-5">
                        <label class="text-ui font-semibold text-ink-2">Cash received</label>
                        <input
                            v-model="settleForm.cash_received"
                            type="number" step="0.01" min="0"
                            class="input-field mt-1.5 w-full text-right text-lg font-bold tabular-nums"
                            :placeholder="`Min: ${money(settleTotal)}`"
                        />
                        <div v-if="cashReceived >= settleTotal" class="mt-3 rounded-control bg-ready-tint p-3 text-center">
                            <p class="text-meta font-medium text-ready-ink">Change</p>
                            <p class="text-2xl font-bold tabular-nums text-ready-ink">{{ money(change) }}</p>
                        </div>
                    </div>

                    <p v-if="settleForm.errors.order" class="mt-3 text-meta text-stop-ink">{{ settleForm.errors.order }}</p>
                    <p v-if="settleForm.errors.checkout" class="mt-3 text-meta text-stop-ink">{{ settleForm.errors.checkout }}</p>

                    <div class="mt-6 flex gap-3">
                        <button type="button" class="btn-secondary flex-1 justify-center" @click="closeSettle">Cancel</button>
                        <button
                            type="button"
                            :disabled="!canSettle || settleForm.processing"
                            class="btn-primary flex-1 justify-center disabled:opacity-50"
                            @click="submitSettle"
                        >
                            {{ settleForm.processing ? 'Saving…' : 'Confirm payment' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- REJECT -->
        <Teleport to="body">
            <div
                v-if="rejecting"
                class="fixed inset-0 z-50 flex items-center justify-center bg-ink-1/40 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-label="Reject order"
            >
                <div class="w-full max-w-sm rounded-card bg-surface-1 p-6 shadow-overlay animate-scale-in">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-ink-1">Reject #{{ rejecting.queue_number }}</h3>
                        <button class="text-ink-3 hover:text-ink-3" aria-label="Close" @click="closeReject">
                            <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </div>

                    <p class="mt-1 text-ui text-ink-3">The customer sees this on their phone.</p>

                    <input
                        v-model="rejectForm.reason"
                        type="text"
                        class="input-field mt-4 w-full"
                        placeholder="e.g. Sold out of oat milk"
                        @keyup.enter="submitReject"
                    />
                    <p v-if="rejectForm.errors.reason" class="mt-1 text-meta text-stop-ink">{{ rejectForm.errors.reason }}</p>

                    <div class="mt-5 flex gap-3">
                        <button type="button" class="btn-secondary flex-1 justify-center" @click="closeReject">Cancel</button>
                        <button
                            type="button"
                            :disabled="rejectForm.processing"
                            class="btn-danger flex-1 justify-center disabled:opacity-50"
                            @click="submitReject"
                        >
                            Reject order
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
