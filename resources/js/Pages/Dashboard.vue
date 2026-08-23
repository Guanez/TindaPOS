<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/Composables/currency';
import StatCard from '@/Components/StatCard.vue';
import StockBadge from '@/Components/StockBadge.vue';
import { Head, Link } from '@inertiajs/vue3';
import { formatDateTime } from '@/Composables/helpers';
import { useVocabulary } from '@/Composables/vocabulary';
import {
    BanknotesIcon,
    ReceiptPercentIcon,
    ArrowTrendingUpIcon,
    TagIcon,
    ExclamationTriangleIcon,
    CubeIcon,
    ShoppingCartIcon,
    ClipboardDocumentListIcon,
    ChartBarIcon,
    ArrowRightIcon,
} from '@heroicons/vue/24/outline';

import { computed } from 'vue';

const { money } = useCurrency();


const props = defineProps({
    stats: Object,
    recentSales: Array,
    lowStockProducts: Object,
});

const words = useVocabulary();

// Resolve resource collection (handles {data:[...]} or plain [...])
const lowStockList = computed(() => props.lowStockProducts?.data ?? props.lowStockProducts ?? []);
</script>

<template>
    <AppLayout>
        <Head title="Dashboard" />

        <div class="space-y-6">
            <!-- Page Header -->
            <div class="fade-in-up">
                <h1 class="text-xl font-bold tracking-tight text-ink-1">Dashboard</h1>
                <p class="mt-0.5 text-ui text-ink-3">
                    {{ new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                </p>
            </div>

            <!-- Stat Cards Grid — staggered entrance -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
                <div class="fade-in-up delay-1"><StatCard label="Revenue Today" :value="money(stats.revenue)" :icon="BanknotesIcon" color="success" /></div>
                <div class="fade-in-up delay-2"><StatCard label="Transactions" :value="stats.transactions" :icon="ReceiptPercentIcon" /></div>
                <div class="fade-in-up delay-3"><StatCard label="Profit" :value="money(stats.profit)" :icon="ArrowTrendingUpIcon" color="success" /></div>
                <div class="fade-in-up delay-4"><StatCard label="Discounts Given" :value="money(stats.discounts)" :icon="TagIcon" color="warning" /></div>
                <div class="fade-in-up delay-5"><StatCard label="Low Stock Items" :value="stats.low_stock_count" :icon="ExclamationTriangleIcon" :color="stats.low_stock_count > 0 ? 'danger' : 'default'" /></div>
                <div class="fade-in-up delay-6"><StatCard :label="`Total ${words.items}`" :value="stats.total_products" :icon="CubeIcon" /></div>
            </div>

            <!-- Two-Column Layout -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Recent Sales -->
                <div class="card overflow-hidden fade-in-up" style="animation-delay: 200ms;">
                    <div class="flex items-center justify-between border-b border-line px-5 py-4">
                        <h2 class="text-body font-semibold text-ink-1">Recent Sales</h2>
                        <Link
                            :href="route('sales.index')"
                            class="flex items-center gap-1 text-ui font-medium text-accent transition-colors hover:text-accent"
                        >
                            View all
                            <ArrowRightIcon class="h-3.5 w-3.5" aria-hidden="true" />
                        </Link>
                    </div>

                    <div v-if="recentSales.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-card bg-surface-2">
                            <ClipboardDocumentListIcon class="h-6 w-6 text-ink-3" aria-hidden="true" />
                        </div>
                        <p class="mt-3 text-sm text-ink-3">No sales yet today</p>
                        <Link :href="route('pos.index')" class="mt-2 text-ui font-medium text-accent hover:text-accent">
                            Open POS to start selling
                        </Link>
                    </div>

                    <div v-else class="divide-y divide-line">
                        <div
                            v-for="sale in recentSales"
                            :key="sale.id"
                            class="flex items-center justify-between px-5 py-3 transition-colors hover:bg-surface-2/50"
                        >
                            <div>
                                <p class="text-ui font-semibold text-ink-1">
                                    {{ sale.receipt_number }}
                                </p>
                                <p class="mt-0.5 text-meta text-ink-3">
                                    {{ sale.item_count }} item{{ sale.item_count !== 1 ? 's' : '' }}
                                    &middot; {{ sale.payment_method.toUpperCase() }}
                                    &middot; {{ formatDateTime(sale.created_at) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-ui font-bold tabular-nums text-ink-1">
                                    {{ money(sale.total) }}
                                </p>
                                <span
                                    v-if="sale.status === 'voided'"
                                    class="badge badge-danger"
                                >
                                    Voided
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Products -->
                <div class="card overflow-hidden fade-in-up" style="animation-delay: 250ms;">
                    <div class="flex items-center justify-between border-b border-line px-5 py-4">
                        <h2 class="text-body font-semibold text-ink-1">Low Stock Alert</h2>
                        <Link
                            :href="route('inventory.index')"
                            class="flex items-center gap-1 text-ui font-medium text-accent transition-colors hover:text-accent"
                        >
                            Manage
                            <ArrowRightIcon class="h-3.5 w-3.5" aria-hidden="true" />
                        </Link>
                    </div>

                    <div v-if="lowStockList.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-card bg-ready-tint">
                            <CubeIcon class="h-6 w-6 text-ready-mark" aria-hidden="true" />
                        </div>
                        <p class="mt-3 text-sm text-ink-3">All products are well stocked</p>
                    </div>

                    <div v-else class="divide-y divide-line">
                        <div
                            v-for="product in lowStockList"
                            :key="product.id"
                            class="flex items-center justify-between px-5 py-3"
                        >
                            <div>
                                <p class="text-ui font-semibold text-ink-1">{{ product.name }}</p>
                                <p class="text-meta text-ink-3">{{ product.category?.name ?? 'Uncategorized' }}</p>
                            </div>
                            <StockBadge
                                :stock="product.stock_quantity"
                                :threshold="product.low_stock_threshold"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Link
                    :href="route('pos.index')"
                    class="card card-hover group flex items-center gap-3 p-4 fade-in-up" style="animation-delay: 300ms;"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-control bg-accent-tint group-hover:bg-accent-tint" style="transition: background-color var(--t-fast);">
                        <ShoppingCartIcon class="h-5 w-5 text-accent" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-ui font-semibold text-ink-1">Open POS</p>
                        <p class="text-meta text-ink-3">Start selling</p>
                    </div>
                </Link>
                <Link
                    :href="route('inventory.index')"
                    class="card card-hover group flex items-center gap-3 p-4 fade-in-up" style="animation-delay: 350ms;"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-control bg-wait-tint group-hover:bg-wait-tint" style="transition: background-color var(--t-fast);">
                        <CubeIcon class="h-5 w-5 text-wait-ink" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-ui font-semibold text-ink-1">Inventory</p>
                        <p class="text-meta text-ink-3">Manage stock</p>
                    </div>
                </Link>
                <Link
                    :href="route('sales.index')"
                    class="card card-hover group flex items-center gap-3 p-4 fade-in-up" style="animation-delay: 400ms;"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-control bg-ready-tint group-hover:bg-ready-tint" style="transition: background-color var(--t-fast);">
                        <ClipboardDocumentListIcon class="h-5 w-5 text-ready-ink" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-ui font-semibold text-ink-1">Sales</p>
                        <p class="text-meta text-ink-3">View history</p>
                    </div>
                </Link>
                <Link
                    :href="route('reports.index')"
                    class="card card-hover group flex items-center gap-3 p-4 fade-in-up" style="animation-delay: 450ms;"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-control bg-accent-tint group-hover:bg-accent-tint" style="transition: background-color var(--t-fast);">
                        <ChartBarIcon class="h-5 w-5 text-accent" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-ui font-semibold text-ink-1">Reports</p>
                        <p class="text-meta text-ink-3">View analytics</p>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
