<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/Composables/currency';
import StatCard from '@/Components/StatCard.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { formatDate, today } from '@/Composables/helpers';
import {
    CalendarIcon,
    ChartBarIcon,
    TrophyIcon,
    BanknotesIcon,
    ReceiptPercentIcon,
    ArrowTrendingUpIcon,
    TagIcon,
    ArrowTrendingDownIcon,
} from '@heroicons/vue/24/outline';

const { money } = useCurrency();

const props = defineProps({
    dailyReport: { type: Object, default: null },
    rangeReport: { type: Object, default: null },
    topProducts: { type: Array, default: null },
    activeTab: { type: String, default: null },
});

// Tabs
const currentTab = ref(props.activeTab || 'daily');

const tabs = [
    { id: 'daily', label: 'Daily Report', icon: CalendarIcon },
    { id: 'range', label: 'Date Range', icon: ChartBarIcon },
    { id: 'top', label: 'Top Products', icon: TrophyIcon },
];

// Daily Report
const dailyDate = ref(today());
const dailyData = ref(props.dailyReport);
const dailyLoading = ref(false);

const fetchDaily = () => {
    dailyLoading.value = true;
    router.get(route('reports.daily'), { date: dailyDate.value }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            dailyData.value = page.props.dailyReport;
            dailyLoading.value = false;
        },
        onError: () => { dailyLoading.value = false; },
    });
};

watch(dailyDate, fetchDaily);

// Range Report
const rangeFrom = ref(today());
const rangeTo = ref(today());
const rangeData = ref(props.rangeReport);
const rangeLoading = ref(false);

const fetchRange = () => {
    rangeLoading.value = true;
    router.get(route('reports.range'), { start_date: rangeFrom.value, end_date: rangeTo.value }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            rangeData.value = page.props.rangeReport;
            rangeLoading.value = false;
        },
        onError: () => { rangeLoading.value = false; },
    });
};

// Top Products
const topFrom = ref(today());
const topTo = ref(today());
const topLimit = ref(10);
const topData = ref(props.topProducts);
const topLoading = ref(false);

const fetchTop = () => {
    topLoading.value = true;
    router.get(route('reports.topProducts'), { start_date: topFrom.value, end_date: topTo.value, limit: topLimit.value }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            topData.value = page.props.topProducts;
            topLoading.value = false;
        },
        onError: () => { topLoading.value = false; },
    });
};

// Hourly chart helper
const maxHourlyRevenue = computed(() => {
    if (!dailyData.value?.hourly) return 1;
    return Math.max(...dailyData.value.hourly.map(h => parseFloat(h.revenue)), 1);
});

// Tab data auto-load
watch(currentTab, (tab) => {
    if (tab === 'daily' && !dailyData.value) fetchDaily();
    if (tab === 'range' && !rangeData.value) fetchRange();
    if (tab === 'top' && !topData.value) fetchTop();
});
</script>

<template>
    <AppLayout>
        <Head title="Reports" />

        <div class="space-y-5">
            <!-- Header -->
            <div>
                <h1 class="text-xl font-bold tracking-tight text-ink-1">Reports</h1>
                <p class="mt-0.5 text-ui text-ink-3">Sales analytics and performance insights</p>
            </div>

            <!-- Tab Navigation -->
            <div class="card flex gap-1 p-1">
                <button
                    v-for="tab in tabs" :key="tab.id"
                    @click="currentTab = tab.id"
                    :class="[
                        'flex flex-1 items-center justify-center gap-1.5 rounded-control py-2.5 text-ui font-semibold transition-all',
                        currentTab === tab.id
                            ? 'bg-accent text-accent-fg shadow-rest'
                            : 'text-ink-3 hover:bg-surface-2'
                    ]"
                >
                    <component :is="tab.icon" class="h-4 w-4" aria-hidden="true" />
                    {{ tab.label }}
                </button>
            </div>

            <!-- DAILY REPORT TAB -->
            <div v-show="currentTab === 'daily'" class="space-y-5">
                <div class="card flex items-end gap-3 p-4">
                    <div>
                        <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">Select Date</label>
                        <input v-model="dailyDate" type="date"
                            class="mt-1 rounded-control border-line px-3 py-2 text-sm focus:border-accent focus:ring-accent/20" />
                    </div>
                    <button @click="dailyDate = today()"
                        class="rounded-control border border-line px-3 py-2 text-ui font-semibold text-ink-2 transition-all hover:bg-surface-2">
                        Today
                    </button>
                </div>

                <div v-if="dailyLoading" class="flex items-center justify-center py-14">
                    <svg class="h-8 w-8 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </div>

                <template v-else-if="dailyData">
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <StatCard label="Revenue" :value="money(dailyData.revenue ?? 0)" :icon="BanknotesIcon" color="success" />
                        <StatCard label="Transactions" :value="dailyData.transactions ?? 0" :icon="ReceiptPercentIcon" />
                        <StatCard label="Profit" :value="money(dailyData.profit ?? 0)" :icon="ArrowTrendingUpIcon" color="success" />
                        <StatCard label="Discounts" :value="money(dailyData.discounts ?? 0)" :icon="TagIcon" color="warning" />
                    </div>

                    <!-- Hourly Chart -->
                    <div class="card p-6">
                        <h3 class="text-body font-bold text-ink-1">Hourly Sales</h3>
                        <p class="text-meta text-ink-3">Revenue per hour for {{ formatDate(dailyDate) }}</p>

                        <div class="mt-4 flex items-end gap-1" style="height: 200px;">
                            <div
                                v-for="h in dailyData.hourly" :key="h.hour"
                                class="group relative flex-1"
                                :title="`${h.hour}:00 — ${money(h.revenue)} (${h.transactions} txn)`"
                            >
                                <div
                                    class="absolute bottom-0 w-full rounded-t bg-accent transition-all hover:bg-accent-hover"
                                    :style="{ height: `${Math.max((parseFloat(h.revenue) / maxHourlyRevenue) * 100, 2)}%` }"
                                />
                                <span v-if="h.hour % 3 === 0"
                                    class="absolute -bottom-5 left-1/2 -translate-x-1/2 text-label text-ink-3">
                                    {{ h.hour }}
                                </span>
                                <div class="pointer-events-none absolute -top-16 left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded-control bg-ink-1 px-2 py-1 text-meta text-white shadow-overlay group-hover:block">
                                    <p class="font-bold">{{ h.hour }}:00</p>
                                    <p>{{ money(h.revenue) }}</p>
                                    <p>{{ h.transactions }} txn</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-between text-label text-ink-3">
                            <span>12 AM</span>
                            <span>12 PM</span>
                            <span>11 PM</span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- DATE RANGE TAB -->
            <div v-show="currentTab === 'range'" class="space-y-5">
                <div class="card flex flex-wrap items-end gap-3 p-4">
                    <div>
                        <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">From</label>
                        <input v-model="rangeFrom" type="date"
                            class="mt-1 rounded-control border-line px-3 py-2 text-sm focus:border-accent focus:ring-accent/20" />
                    </div>
                    <div>
                        <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">To</label>
                        <input v-model="rangeTo" type="date"
                            class="mt-1 rounded-control border-line px-3 py-2 text-sm focus:border-accent focus:ring-accent/20" />
                    </div>
                    <button @click="fetchRange"
                        class="rounded-control bg-accent px-4 py-2 text-ui font-semibold text-accent-fg shadow-rest transition-all hover:bg-accent-hover">
                        Generate Report
                    </button>
                </div>

                <div v-if="rangeLoading" class="flex items-center justify-center py-14">
                    <svg class="h-8 w-8 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </div>

                <template v-else-if="rangeData">
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <StatCard label="Total Revenue" :value="money(rangeData.revenue ?? 0)" :icon="BanknotesIcon" color="success" />
                        <StatCard label="Transactions" :value="rangeData.transactions ?? 0" :icon="ReceiptPercentIcon" />
                        <StatCard label="Total Profit" :value="money(rangeData.profit ?? 0)" :icon="ArrowTrendingUpIcon" color="success" />
                        <StatCard label="Avg per Day" :value="money(rangeData.daily?.length ? (rangeData.revenue ?? 0) / rangeData.daily.length : 0)" :icon="ArrowTrendingDownIcon" />
                    </div>

                    <!-- Payment Methods -->
                    <div v-if="rangeData.payment_methods" class="card p-6">
                        <h3 class="text-body font-bold text-ink-1">Payment Methods</h3>
                        <div class="mt-4 space-y-3">
                            <div v-for="pm in rangeData.payment_methods" :key="pm.payment_method" class="flex items-center gap-3">
                                <span class="w-16 text-label font-semibold uppercase tracking-wider text-ink-3">{{ pm.payment_method }}</span>
                                <div class="h-6 flex-1 overflow-hidden rounded-full bg-surface-3">
                                    <div class="h-full rounded-full bg-accent transition-all"
                                        :style="{ width: `${(pm.count / (rangeData.transactions || 1)) * 100}%` }" />
                                </div>
                                <span class="w-20 text-right text-ui font-semibold tabular-nums text-ink-2">{{ money(pm.revenue) }}</span>
                                <span class="w-12 text-right text-meta tabular-nums text-ink-3">{{ pm.count }} txn</span>
                            </div>
                        </div>
                    </div>

                    <!-- Daily Breakdown Table -->
                    <div v-if="rangeData.daily?.length" class="card overflow-hidden">
                        <table class="min-w-full divide-y divide-line">
                            <thead class="bg-surface-2/80">
                                <tr>
                                    <th class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Date</th>
                                    <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Revenue</th>
                                    <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Profit</th>
                                    <th class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Txns</th>
                                    <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Discounts</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr v-for="day in rangeData.daily" :key="day.date" class="transition-colors hover:bg-surface-2/50">
                                    <td class="px-4 py-2.5 text-ui font-semibold text-ink-1">{{ formatDate(day.date) }}</td>
                                    <td class="px-4 py-2.5 text-right text-ui tabular-nums text-ink-1">{{ money(day.revenue) }}</td>
                                    <td class="px-4 py-2.5 text-right text-ui font-semibold tabular-nums text-ready-ink">{{ money(day.profit) }}</td>
                                    <td class="px-4 py-2.5 text-center text-ui tabular-nums text-ink-3">{{ day.transactions }}</td>
                                    <td class="px-4 py-2.5 text-right text-ui text-ink-3">{{ money(day.discounts) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>

            <!-- TOP PRODUCTS TAB -->
            <div v-show="currentTab === 'top'" class="space-y-5">
                <div class="card flex flex-wrap items-end gap-3 p-4">
                    <div>
                        <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">From</label>
                        <input v-model="topFrom" type="date"
                            class="mt-1 rounded-control border-line px-3 py-2 text-sm focus:border-accent focus:ring-accent/20" />
                    </div>
                    <div>
                        <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">To</label>
                        <input v-model="topTo" type="date"
                            class="mt-1 rounded-control border-line px-3 py-2 text-sm focus:border-accent focus:ring-accent/20" />
                    </div>
                    <div>
                        <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">Top</label>
                        <select v-model="topLimit"
                            class="mt-1 rounded-control border-line py-2 pl-3 pr-8 text-sm focus:border-accent focus:ring-accent/20">
                            <option :value="5">5</option>
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                    <button @click="fetchTop"
                        class="rounded-control bg-accent px-4 py-2 text-ui font-semibold text-accent-fg shadow-rest transition-all hover:bg-accent-hover">
                        Generate
                    </button>
                </div>

                <div v-if="topLoading" class="flex items-center justify-center py-14">
                    <svg class="h-8 w-8 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </div>

                <div v-else-if="topData?.length" class="card overflow-hidden">
                    <table class="min-w-full divide-y divide-line">
                        <thead class="bg-surface-2/80">
                            <tr>
                                <th class="w-12 px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">#</th>
                                <th class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Product</th>
                                <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Qty Sold</th>
                                <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Revenue</th>
                                <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Orders</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="(product, i) in topData" :key="product.product_id" class="transition-colors hover:bg-surface-2/50">
                                <td class="px-4 py-3 text-center">
                                    <span :class="[
                                        'inline-flex h-6 w-6 items-center justify-center rounded-full text-meta font-bold',
                                        i === 0 ? 'bg-wait-tint text-wait-ink' :
                                        i === 1 ? 'bg-surface-3 text-ink-2' :
                                        i === 2 ? 'bg-wait-tint text-wait-ink' :
                                        'text-ink-3'
                                    ]">
                                        {{ i + 1 }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-ui font-semibold text-ink-1">{{ product.product_name }}</td>
                                <td class="px-4 py-3 text-right text-ui font-bold text-ink-1">{{ product.total_quantity }}</td>
                                <td class="px-4 py-3 text-right text-ui text-ink-1">{{ money(product.total_revenue) }}</td>
                                <td class="px-4 py-3 text-right text-ui text-ink-3">{{ product.order_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else-if="topData && topData.length === 0" class="flex flex-col items-center justify-center py-14">
                    <div class="flex h-14 w-14 items-center justify-center rounded-card bg-surface-2">
                        <TrophyIcon class="h-7 w-7 text-ink-3" />
                    </div>
                    <p class="mt-3 text-sm font-medium text-ink-3">No sales data for selected period</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
