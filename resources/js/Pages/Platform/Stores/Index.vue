<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
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

const peso = (n) =>
    '₱' + Number(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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
                    <h2 class="text-xl font-bold tracking-tight text-slate-900">Client stores</h2>
                    <p class="mt-0.5 text-[13px] text-slate-500">
                        Every shop running on TindaPOS.
                    </p>
                </div>
                <Link
                    :href="route('platform.stores.create')"
                    class="flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-[13px] font-semibold text-white shadow-sm hover:bg-slate-800"
                    style="transition: background-color 0.15s;"
                >
                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                    New store
                </Link>
            </div>

            <!-- Totals -->
            <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-2xl bg-white p-4 shadow-card">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Stores</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ totals.stores ?? 0 }}</p>
                </div>
                <div class="rounded-2xl bg-white p-4 shadow-card">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Active</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600">{{ totals.active ?? 0 }}</p>
                </div>
                <div class="rounded-2xl bg-white p-4 shadow-card">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Staff</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ totals.staff ?? 0 }}</p>
                </div>
                <div class="rounded-2xl bg-white p-4 shadow-card">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Revenue MTD</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ peso(totals.revenue_month) }}</p>
                </div>
            </div>

            <!-- Store list -->
            <div v-if="stores.length" class="space-y-3">
                <div
                    v-for="store in stores"
                    :key="store.id"
                    class="rounded-2xl bg-white p-4 shadow-card"
                    :class="store.is_active ? '' : 'opacity-70'"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-[15px] font-bold text-slate-900">{{ store.name }}</h3>
                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-500">
                                    {{ store.type === 'cafe' ? 'Cafe' : 'Sari-Sari' }}
                                </span>
                                <span
                                    class="flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-semibold"
                                    :class="store.is_active
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-red-50 text-red-700'"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="store.is_active ? 'bg-emerald-500' : 'bg-red-500'"
                                    />
                                    {{ store.is_active ? 'Active' : 'Suspended' }}
                                </span>
                                <span
                                    v-if="store.online_ordering_enabled"
                                    class="rounded-md bg-brand-50 px-1.5 py-0.5 text-[11px] font-semibold text-brand-700"
                                >
                                    QR ordering
                                </span>
                            </div>
                            <p class="mt-1 text-[12px] text-slate-500">
                                <span class="font-mono">/s/{{ store.slug }}</span>
                                <span v-if="store.address"> &middot; {{ store.address }}</span>
                            </p>
                            <p class="mt-1.5 text-[13px] text-slate-600">
                                <span class="font-semibold tabular-nums">{{ store.staff_count }}</span> staff
                                &middot;
                                <span class="font-semibold tabular-nums">{{ peso(store.revenue_month) }}</span>
                                this month
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <button
                                v-if="store.is_active"
                                @click="enter(store)"
                                class="flex items-center gap-1.5 rounded-xl bg-brand-600 px-3 py-2 text-[13px] font-semibold text-white hover:bg-brand-700"
                                style="transition: background-color 0.15s;"
                            >
                                <ArrowRightOnRectangleIcon class="h-4 w-4" aria-hidden="true" />
                                Enter store
                            </button>

                            <Link
                                :href="route('platform.stores.edit', store.id)"
                                class="flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-[13px] font-medium text-slate-600 hover:bg-slate-50"
                                style="transition: background-color 0.15s;"
                            >
                                <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
                                Edit
                            </Link>

                            <button
                                @click="pendingSuspend = store"
                                class="flex items-center gap-1.5 rounded-xl border px-3 py-2 text-[13px] font-medium"
                                :class="store.is_active
                                    ? 'border-slate-200 text-slate-600 hover:border-red-200 hover:bg-red-50 hover:text-red-600'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                                style="transition: background-color 0.15s, color 0.15s, border-color 0.15s;"
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

            <div v-else class="rounded-2xl bg-white p-12 text-center shadow-card">
                <p class="text-[15px] font-semibold text-slate-900">No stores yet</p>
                <p class="mt-1 text-[13px] text-slate-500">Add your first client to get started.</p>
            </div>
        </div>

        <!-- Suspension confirmation -->
        <Teleport to="body">
            <div
                v-if="pendingSuspend"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
                @click.self="pendingSuspend = null"
            >
                <div role="dialog" aria-modal="true" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-elevated animate-scale-in">
                    <h3 class="text-[15px] font-bold text-slate-900">
                        {{ pendingSuspend.is_active ? 'Suspend' : 'Reactivate' }} {{ pendingSuspend.name }}?
                    </h3>
                    <p v-if="pendingSuspend.is_active" class="mt-2 text-[13px] leading-relaxed text-slate-600">
                        Their {{ pendingSuspend.staff_count }} staff will be signed out on their next action
                        and cannot sign back in. Their QR menu will stop working. Sales history is kept.
                    </p>
                    <p v-else class="mt-2 text-[13px] leading-relaxed text-slate-600">
                        Their staff will be able to sign in again immediately.
                    </p>

                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            @click="pendingSuspend = null"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-[13px] font-medium text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            @click="confirmSuspension"
                            class="rounded-xl px-4 py-2 text-[13px] font-semibold text-white"
                            :class="pendingSuspend.is_active
                                ? 'bg-red-600 hover:bg-red-700'
                                : 'bg-emerald-600 hover:bg-emerald-700'"
                        >
                            {{ pendingSuspend.is_active ? 'Suspend store' : 'Reactivate store' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </PlatformLayout>
</template>
