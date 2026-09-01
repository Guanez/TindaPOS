<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Dialog from '@/Components/Dialog.vue';
import { formatWith } from '@/Composables/currency';
import { ref } from 'vue';
import {
    PlusIcon,
    ArrowRightOnRectangleIcon,
    PencilSquareIcon,
    PauseCircleIcon,
    PlayCircleIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    stores: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
});

// Each row is a different shop, so revenue is shown in that shop's own
// symbol rather than the console's — which has no store and no symbol.
const peso = (store, n) => formatWith(store.currency_symbol || '₱', n);

// Suspension is the one destructive control on this page, so it asks first
// rather than relying on a browser confirm() — which would block the tab.
const pendingSuspend = ref(null);

const enter = (store) => router.post(route('platform.stores.enter', store.id));

const confirmSuspension = () => {
    const store = pendingSuspend.value;
    pendingSuspend.value = null;
    router.post(route('platform.stores.suspension', store.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Stores" />

    <PlatformLayout>
        <div class="animate-fade-in-up">
            <!-- Header -->
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-ink-1">Client stores</h2>
                    <p class="mt-0.5 text-ui text-ink-3">
                        Every shop running on TindaPOS.
                    </p>
                </div>
                <Link
                    :href="route('platform.stores.create')"
                    class="flex items-center gap-2 rounded-control bg-action px-4 py-2.5 text-ui font-semibold text-action-fg shadow-rest hover:bg-action-hover"
                    style="transition: background-color var(--t-fast);"
                >
                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                    New store
                </Link>
            </div>

            <!-- Totals -->
            <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-card bg-surface-1 p-4 shadow-rest">
                    <p class="text-label font-bold uppercase tracking-widest text-ink-3">Stores</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-ink-1">{{ totals.stores ?? 0 }}</p>
                </div>
                <div class="rounded-card bg-surface-1 p-4 shadow-rest">
                    <p class="text-label font-bold uppercase tracking-widest text-ink-3">Active</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-ready-ink">{{ totals.active ?? 0 }}</p>
                </div>
                <div class="rounded-card bg-surface-1 p-4 shadow-rest">
                    <p class="text-label font-bold uppercase tracking-widest text-ink-3">Staff</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-ink-1">{{ totals.staff ?? 0 }}</p>
                </div>
                <div class="rounded-card bg-surface-1 p-4 shadow-rest">
                    <p class="text-label font-bold uppercase tracking-widest text-ink-3">Revenue MTD</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-ink-1">{{ formatWith('₱', totals.revenue_month) }}</p>
                </div>
            </div>

            <!-- Store list -->
            <div v-if="stores.length" class="space-y-3">
                <div
                    v-for="store in stores"
                    :key="store.id"
                    class="rounded-card bg-surface-1 p-4 shadow-rest"
                    :class="store.is_active ? '' : 'opacity-70'"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-body font-bold text-ink-1">{{ store.name }}</h3>
                                <span class="rounded-control bg-surface-3 px-1.5 py-0.5 text-meta font-semibold text-ink-3">
                                    {{ store.type === 'cafe' ? 'Cafe' : 'Sari-Sari' }}
                                </span>
                                <span
                                    class="flex items-center gap-1 rounded-control px-1.5 py-0.5 text-meta font-semibold"
                                    :class="store.is_active
                                        ? 'bg-ready-tint text-ready-ink'
                                        : 'bg-stop-tint text-stop-ink'"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="store.is_active ? 'bg-ready-mark' : 'bg-stop-solid'"
                                    />
                                    {{ store.is_active ? 'Active' : 'Suspended' }}
                                </span>
                                <span
                                    v-if="store.online_ordering_enabled"
                                    class="rounded-control bg-accent-tint px-1.5 py-0.5 text-meta font-semibold text-accent-ink"
                                >
                                    QR ordering
                                </span>
                            </div>
                            <p class="mt-1 text-meta text-ink-3">
                                <span class="font-mono">/s/{{ store.slug }}</span>
                                <span v-if="store.address"> &middot; {{ store.address }}</span>
                            </p>
                            <p class="mt-1.5 text-ui text-ink-2">
                                <span class="font-semibold tabular-nums">{{ store.staff_count }}</span> staff
                                &middot;
                                <span class="font-semibold tabular-nums">{{ peso(store, store.revenue_month) }}</span>
                                this month
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <button
                                v-if="store.is_active"
                                @click="enter(store)"
                                class="flex items-center gap-1.5 rounded-control bg-accent px-3 py-2 text-ui font-semibold text-accent-fg hover:bg-accent-hover"
                                style="transition: background-color var(--t-fast);"
                            >
                                <ArrowRightOnRectangleIcon class="h-4 w-4" aria-hidden="true" />
                                Enter store
                            </button>

                            <Link
                                :href="route('platform.stores.edit', store.id)"
                                class="flex items-center gap-1.5 rounded-control border border-line px-3 py-2 text-ui font-medium text-ink-2 hover:bg-surface-2"
                                style="transition: background-color var(--t-fast);"
                            >
                                <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
                                Edit
                            </Link>

                            <button
                                @click="pendingSuspend = store"
                                class="flex items-center gap-1.5 rounded-control border px-3 py-2 text-ui font-medium"
                                :class="store.is_active
                                    ? 'border-line text-ink-2 hover:border-stop-tint hover:bg-stop-tint hover:text-stop-ink'
                                    : 'border-ready-tint bg-ready-tint text-ready-ink hover:bg-ready-mark/25'"
                                style="transition: background-color var(--t-fast), color var(--t-fast), border-color var(--t-fast);"
                            >
                                <component
                                    :is="store.is_active ? PauseCircleIcon : PlayCircleIcon"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                                {{ store.is_active ? 'Suspend' : 'Reactivate' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-card bg-surface-1 p-12 text-center shadow-rest">
                <p class="text-body font-semibold text-ink-1">No stores yet</p>
                <p class="mt-1 text-ui text-ink-3">Add your first client to get started.</p>
            </div>
        </div>

        <!-- Suspension confirmation -->
        <Dialog
            :show="pendingSuspend !== null"
            :title="pendingSuspend
                ? `${pendingSuspend.is_active ? 'Suspend' : 'Reactivate'} ${pendingSuspend.name}?`
                : 'Confirm'"
            max-width="md"
            @close="pendingSuspend = null"
        >
            <template v-if="pendingSuspend">
                <p v-if="pendingSuspend.is_active" class="text-ui leading-relaxed text-ink-2">
                    Their {{ pendingSuspend.staff_count }} staff will be signed out on their next action
                    and cannot sign back in. Their QR menu will stop working. Sales history is kept.
                </p>
                <p v-else class="text-ui leading-relaxed text-ink-2">
                    Their staff will be able to sign in again immediately.
                </p>
            </template>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        @click="pendingSuspend = null"
                        class="rounded-control border border-line px-4 py-2 text-ui font-medium text-ink-2 hover:bg-surface-2"
                    >
                        Cancel
                    </button>
                    <button
                        v-if="pendingSuspend"
                        type="button"
                        @click="confirmSuspension"
                        class="rounded-control px-4 py-2 text-ui font-semibold text-on-solid"
                        :class="pendingSuspend.is_active
                            ? 'bg-stop-solid hover:bg-stop-mark'
                            : 'bg-ready-solid hover:bg-ready-mark'"
                    >
                        {{ pendingSuspend.is_active ? 'Suspend store' : 'Reactivate store' }}
                    </button>
                </div>
            </template>
        </Dialog>
    </PlatformLayout>
</template>
