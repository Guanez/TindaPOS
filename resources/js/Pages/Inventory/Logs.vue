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
        restock: 'bg-ready-tint text-ready-ink ring-emerald-600/20',
        sale: 'bg-accent-tint text-accent ring-accent/20',
        void: 'bg-wait-tint text-wait-ink ring-amber-600/20',
        adjustment: 'bg-surface-2 text-ink-2 ring-ink-2/20',
    };
    return classes[type] || 'bg-surface-2 text-ink-2 ring-ink-2/20';
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
                            class="rounded-control p-1.5 text-ink-3 hover:bg-surface-3 hover:text-ink-2" style="transition: background-color var(--t-fast), color var(--t-fast);"
                        >
                            <ArrowLeftIcon aria-hidden="true" class="h-4 w-4" />
                        </Link>
                        <div>
                            <h1 class="text-xl font-bold tracking-tight text-ink-1">Stock Logs</h1>
                            <p class="mt-0.5 text-ui text-ink-3">Audit trail of all stock movements</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line">
                        <thead class="bg-surface-2/80">
                            <tr>
                                <th class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Date</th>
                                <th class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Product</th>
                                <th class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Type</th>
                                <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Change</th>
                                <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Before</th>
                                <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">After</th>
                                <th class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">User</th>
                                <th class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-if="!logs.data.length">
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <ClockIcon aria-hidden="true" class="mx-auto h-10 w-10 text-ink-3" />
                                    <p class="mt-2 text-sm font-medium text-ink-3">No stock logs yet</p>
                                    <p class="mt-1 text-ui text-ink-3">Stock movements will appear here</p>
                                </td>
                            </tr>
                            <tr v-for="log in logs.data" :key="log.id" class="transition-colors hover:bg-surface-2/50">
                                <td class="whitespace-nowrap px-4 py-3 text-ui text-ink-3">
                                    {{ formatDate(log.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-ui font-semibold text-ink-1">
                                    {{ log.product?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        :class="typeBadgeClass(log.type)"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-meta font-semibold ring-1 ring-inset"
                                    >
                                        {{ typeLabel(log.type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-ui font-semibold tabular-nums"
                                    :class="log.quantity_change > 0 ? 'text-ready-ink' : 'text-stop-ink'"
                                >
                                    {{ log.quantity_change > 0 ? '+' : '' }}{{ log.quantity_change }}
                                </td>
                                <td class="px-4 py-3 text-right text-ui tabular-nums text-ink-3">
                                    {{ log.stock_before }}
                                </td>
                                <td class="px-4 py-3 text-right text-ui tabular-nums text-ink-3">
                                    {{ log.stock_after }}
                                </td>
                                <td class="px-4 py-3 text-ui text-ink-3">
                                    {{ log.user?.name ?? '—' }}
                                </td>
                                <td class="max-w-[200px] truncate px-4 py-3 text-ui text-ink-3">
                                    {{ log.reason || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="flex items-center justify-between border-t border-line px-4 py-3">
                    <p class="text-ui text-ink-3">
                        Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }}
                    </p>
                    <div class="flex gap-1">
                        <button
                            v-for="link in logs.links" :key="link.label"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            :class="[
                                'rounded-control px-3 py-1.5 text-meta font-medium transition-all',
                                link.active
                                    ? 'bg-accent text-white shadow-rest'
                                    : link.url
                                        ? 'text-ink-2 hover:bg-surface-3'
                                        : 'cursor-not-allowed text-ink-3',
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
