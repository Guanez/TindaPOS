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
        restock: 'bg-ready-tint text-ready-ink ring-ready-mark/30',
        sale: 'bg-accent-tint text-accent-ink ring-accent/20',
        void: 'bg-wait-tint text-wait-ink ring-wait-mark/30',
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

            <!-- Logs -->
            <div class="card overflow-hidden">
                <!-- Phone -->
                <ul v-if="logs.data.length" class="divide-y divide-line md:hidden">
                    <li v-for="log in logs.data" :key="log.id" class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-ui font-semibold text-ink-1">{{ log.product?.name ?? '&mdash;' }}</p>
                                <p class="mt-0.5 text-meta text-ink-3">
                                    {{ formatDate(log.created_at) }} &middot; {{ log.user?.name ?? '&mdash;' }}
                                </p>
                            </div>
                            <p class="shrink-0 text-ui font-semibold tabular-nums"
                                :class="log.quantity_change > 0 ? 'text-ready-ink' : 'text-stop-ink'"
                            >
                                {{ log.quantity_change > 0 ? '+' : '' }}{{ log.quantity_change }}
                            </p>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span
                                :class="typeBadgeClass(log.type)"
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-meta font-semibold ring-1 ring-inset"
                            >
                                {{ typeLabel(log.type) }}
                            </span>
                            <span class="text-meta tabular-nums text-ink-3">
                                {{ log.stock_before }} &rarr; {{ log.stock_after }}
                            </span>
                        </div>

                        <p v-if="log.reason" class="mt-1.5 text-meta text-ink-3">{{ log.reason }}</p>
                    </li>
                </ul>

                <!-- Counter -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-line">
                        <caption class="sr-only">Every stock movement, most recent first</caption>
                        <thead class="bg-surface-2/80">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Product</th>
                                <th scope="col" class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Type</th>
                                <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Change</th>
                                <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Before</th>
                                <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">After</th>
                                <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">User</th>
                                <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="log in logs.data" :key="log.id" class="transition-colors hover:bg-surface-2/50">
                                <td class="whitespace-nowrap px-4 py-3 text-ui text-ink-3">
                                    {{ formatDate(log.created_at) }}
                                </td>
                                <th scope="row" class="px-4 py-3 text-left text-ui font-semibold text-ink-1">
                                    {{ log.product?.name ?? '&mdash;' }}
                                </th>
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
                                    {{ log.user?.name ?? '&mdash;' }}
                                </td>
                                <td class="max-w-[200px] truncate px-4 py-3 text-ui text-ink-3">
                                    {{ log.reason || '&mdash;' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!--
                    Out of the table rather than inside it as a spanning cell:
                    an empty state is not a row, and announcing it as one told
                    a screen-reader user there was data when there was none.
                -->
                <div v-if="!logs.data.length" class="flex flex-col items-center justify-center py-12 text-center">
                    <ClockIcon aria-hidden="true" class="h-10 w-10 text-ink-3" />
                    <p class="mt-2 text-ui font-medium text-ink-3">No stock logs yet</p>
                    <p class="mt-1 text-ui text-ink-3">Stock movements will appear here</p>
                </div>

                <!-- Pagination -->
                <nav v-if="logs.last_page > 1" aria-label="Stock log pages" class="flex items-center justify-between border-t border-line px-4 py-3">
                    <p class="text-ui text-ink-3">
                        Showing {{ logs.from }}&ndash;{{ logs.to }} of {{ logs.total }}
                    </p>
                    <div class="flex gap-1">
                        <button
                            v-for="link in logs.links" :key="link.label"
                            type="button"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            :aria-current="link.active ? 'page' : undefined"
                            :class="[
                                'rounded-control px-3 py-1.5 text-meta font-medium transition-all',
                                link.active
                                    ? 'bg-accent text-accent-fg shadow-rest'
                                    : link.url
                                        ? 'text-ink-2 hover:bg-surface-3'
                                        : 'cursor-not-allowed text-ink-3',
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </nav>
            </div>
        </div>
    </AppLayout>
</template>
