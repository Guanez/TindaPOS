<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import {
    ClockIcon,
    ArrowPathIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    logs: Object,
});

const typeLabel = (type) => {
    const labels = {
        restock: 'Restock',
        sale: 'Sale',
        void: 'Void Reversal',
        adjustment: 'Adjustment',
    };
    return labels[type] || type;
};

const typeBadgeClass = (type) => {
    const classes = {
        restock: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        sale: 'bg-blue-50 text-blue-700 ring-blue-600/20',
        void: 'bg-amber-50 text-amber-700 ring-amber-600/20',
        adjustment: 'bg-slate-50 text-slate-700 ring-slate-600/20',
    };
    return classes[type] || 'bg-slate-50 text-slate-700 ring-slate-600/20';
};

const formatDate = (date) => {
    return new Date(date).toLocaleString('en-PH', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

// Pagination
const goToPage = (url) => {
    if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <Head title="Stock Logs" />

        <div class="space-y-5">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('inventory.index')"
                            aria-label="Back to inventory"
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" style="transition: background-color 0.15s, color 0.15s;"
                        >
                            <ArrowLeftIcon aria-hidden="true" class="h-4 w-4" />
                        </Link>
                        <div>
                            <h1 class="text-xl font-bold tracking-tight text-slate-900">Stock Logs</h1>
                            <p class="mt-0.5 text-[13px] text-slate-500">Audit trail of all stock movements</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Date</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Product</th>
                                <th class="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">Type</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Change</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Before</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">After</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">User</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!logs.data.length">
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <ClockIcon aria-hidden="true" class="mx-auto h-10 w-10 text-slate-300" />
                                    <p class="mt-2 text-sm font-medium text-slate-500">No stock logs yet</p>
                                    <p class="mt-1 text-[13px] text-slate-400">Stock movements will appear here</p>
                                </td>
                            </tr>
                            <tr v-for="log in logs.data" :key="log.id" class="transition-colors hover:bg-slate-50/50">
                                <td class="whitespace-nowrap px-4 py-3 text-[13px] text-slate-500">
                                    {{ formatDate(log.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-[13px] font-semibold text-slate-800">
                                    {{ log.product?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        :class="typeBadgeClass(log.type)"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                                    >
                                        {{ typeLabel(log.type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-[13px] font-semibold tabular-nums"
                                    :class="log.quantity_change > 0 ? 'text-emerald-600' : 'text-red-600'"
                                >
                                    {{ log.quantity_change > 0 ? '+' : '' }}{{ log.quantity_change }}
                                </td>
                                <td class="px-4 py-3 text-right text-[13px] tabular-nums text-slate-500">
                                    {{ log.stock_before }}
                                </td>
                                <td class="px-4 py-3 text-right text-[13px] tabular-nums text-slate-500">
                                    {{ log.stock_after }}
                                </td>
                                <td class="px-4 py-3 text-[13px] text-slate-500">
                                    {{ log.user?.name ?? '—' }}
                                </td>
                                <td class="max-w-[200px] truncate px-4 py-3 text-[13px] text-slate-400">
                                    {{ log.reason || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-4 py-3">
                    <p class="text-[13px] text-slate-500">
                        Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }}
                    </p>
                    <div class="flex gap-1">
                        <button
                            v-for="link in logs.links" :key="link.label"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-[12px] font-medium transition-all',
                                link.active
                                    ? 'bg-brand-600 text-white shadow-sm'
                                    : link.url
                                        ? 'text-slate-600 hover:bg-slate-100'
                                        : 'cursor-not-allowed text-slate-300',
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
