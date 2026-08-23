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
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Reports</h1>
                <p class="mt-0.5 text-[13px] text-slate-500">Sales analytics and performance insights</p>
            </div>

            <!-- Tab Navigation -->
            <div class="card flex gap-1 p-1">
                <button
                    v-for="tab in tabs" :key="tab.id"
                    @click="currentTab = tab.id"
                    :class="[
                        'flex flex-1 items-center justify-center gap-1.5 rounded-xl py-2.5 text-[13px] font-semibold transition-all',
                        currentTab === tab.id
                            ? 'bg-brand-600 text-white shadow-sm'
                            : 'text-slate-500 hover:bg-slate-50'
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
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Select Date</label>
                        <input v-model="dailyDate" type="date"
                            class="mt-1 rounded-xl border-slate-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500/20" />
                    </div>
                    <button @click="dailyDate = today()"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-[13px] font-semibold text-slate-600 transition-all hover:bg-slate-50">
                        Today
                    </button>
                </div>

                <div v-if="dailyLoading" class="flex items-center justify-center py-14">
                    <svg class="h-8 w-8 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
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
                        <h3 class="text-[15px] font-bold text-slate-900">Hourly Sales</h3>
                        <p class="text-[12px] text-slate-400">Revenue per hour for {{ formatDate(dailyDate) }}</p>

                        <div class="mt-4 flex items-end gap-1" style="height: 200px;">
                            <div
                                v-for="h in dailyData.hourly" :key="h.hour"
                                class="group relative flex-1"
                                :title="`${h.hour}:00 — ${money(h.revenue)} (${h.transactions} txn)`"
                            >
                                <div
                                    class="absolute bottom-0 w-full rounded-t bg-brand-500 transition-all hover:bg-brand-600"
                                    :style="{ height: `${Math.max((parseFloat(h.revenue) / maxHourlyRevenue) * 100, 2)}%` }"
                                />
                                <span v-if="h.hour % 3 === 0"
                                    class="absolute -bottom-5 left-1/2 -translate-x-1/2 text-[10px] text-slate-400">
                                    {{ h.hour }}
                                </span>
                                <div class="pointer-events-none absolute -top-16 left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-800 px-2 py-1 text-[11px] text-white shadow-lg group-hover:block">
                                    <p class="font-bold">{{ h.hour }}:00</p>
                                    <p>{{ money(h.revenue) }}</p>
                                    <p>{{ h.transactions }} txn</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-between text-[10px] text-slate-400">
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
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">From</label>
                        <input v-model="rangeFrom" type="date"
                            class="mt-1 rounded-xl border-slate-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500/20" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">To</label>
                        <input v-model="rangeTo" type="date"
                            class="mt-1 rounded-xl border-slate-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500/20" />
                    </div>
                    <button @click="fetchRange"
                        class="rounded-xl bg-brand-600 px-4 py-2 text-[13px] font-semibold text-white shadow-sm transition-all hover:bg-brand-700">
                        Generate Report
                    </button>
                </div>

                <div v-if="rangeLoading" class="flex items-center justify-center py-14">
                    <svg class="h-8 w-8 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
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
                        <h3 class="text-[15px] font-bold text-slate-900">Payment Methods</h3>
                        <div class="mt-4 space-y-3">
                            <div v-for="pm in rangeData.payment_methods" :key="pm.payment_method" class="flex items-center gap-3">
                                <span class="w-16 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ pm.payment_method }}</span>
                                <div class="h-6 flex-1 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-brand-500 transition-all"
                                        :style="{ width: `${(pm.count / (rangeData.transactions || 1)) * 100}%` }" />
                                </div>
                                <span class="w-20 text-right text-[13px] font-semibold tabular-nums text-slate-700">{{ money(pm.revenue) }}</span>
                                <span class="w-12 text-right text-[11px] tabular-nums text-slate-400">{{ pm.count }} txn</span>
                            </div>
                        </div>
                    </div>

                    <!-- Daily Breakdown Table -->
                    <div v-if="rangeData.daily?.length" class="card overflow-hidden">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50/80">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Date</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Revenue</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Profit</th>
                                    <th class="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">Txns</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Discounts</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="day in rangeData.daily" :key="day.date" class="transition-colors hover:bg-slate-50/50">
                                    <td class="px-4 py-2.5 text-[13px] font-semibold text-slate-800">{{ formatDate(day.date) }}</td>
                                    <td class="px-4 py-2.5 text-right text-[13px] tabular-nums text-slate-800">{{ money(day.revenue) }}</td>
                                    <td class="px-4 py-2.5 text-right text-[13px] font-semibold tabular-nums text-emerald-600">{{ money(day.profit) }}</td>
                                    <td class="px-4 py-2.5 text-center text-[13px] tabular-nums text-slate-500">{{ day.transactions }}</td>
                                    <td class="px-4 py-2.5 text-right text-[13px] text-slate-500">{{ money(day.discounts) }}</td>
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
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">From</label>
                        <input v-model="topFrom" type="date"
                            class="mt-1 rounded-xl border-slate-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500/20" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">To</label>
                        <input v-model="topTo" type="date"
                            class="mt-1 rounded-xl border-slate-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500/20" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Top</label>
                        <select v-model="topLimit"
                            class="mt-1 rounded-xl border-slate-200 py-2 pl-3 pr-8 text-sm focus:border-brand-500 focus:ring-brand-500/20">
                            <option :value="5">5</option>
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                    <button @click="fetchTop"
                        class="rounded-xl bg-brand-600 px-4 py-2 text-[13px] font-semibold text-white shadow-sm transition-all hover:bg-brand-700">
                        Generate
                    </button>
                </div>

                <div v-if="topLoading" class="flex items-center justify-center py-14">
                    <svg class="h-8 w-8 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </div>

                <div v-else-if="topData?.length" class="card overflow-hidden">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="w-12 px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">#</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Product</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Qty Sold</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Revenue</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Orders</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(product, i) in topData" :key="product.product_id" class="transition-colors hover:bg-slate-50/50">
                                <td class="px-4 py-3 text-center">
                                    <span :class="[
                                        'inline-flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold',
                                        i === 0 ? 'bg-amber-100 text-amber-700' :
                                        i === 1 ? 'bg-slate-100 text-slate-600' :
                                        i === 2 ? 'bg-orange-100 text-orange-700' :
                                        'text-slate-400'
                                    ]">
                                        {{ i + 1 }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[13px] font-semibold text-slate-800">{{ product.product_name }}</td>
                                <td class="px-4 py-3 text-right text-[13px] font-bold text-slate-800">{{ product.total_quantity }}</td>
                                <td class="px-4 py-3 text-right text-[13px] text-slate-800">{{ money(product.total_revenue) }}</td>
                                <td class="px-4 py-3 text-right text-[13px] text-slate-500">{{ product.order_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else-if="topData && topData.length === 0" class="flex flex-col items-center justify-center py-14">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50">
                        <TrophyIcon class="h-7 w-7 text-slate-300" />
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-400">No sales data for selected period</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
