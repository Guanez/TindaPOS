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

/*
 * Arrow keys move between tabs, Home and End jump to the ends — the behaviour
 * `role="tablist"` announces and, before this, did not have.
 */
const moveTab = (event, currentId) => {
    const offsets = { ArrowRight: 1, ArrowLeft: -1 };
    const at = tabs.findIndex((t) => t.id === currentId);
    let next = null;

    if (event.key in offsets) {
        next = tabs[(at + offsets[event.key] + tabs.length) % tabs.length];
    } else if (event.key === 'Home') {
        next = tabs[0];
    } else if (event.key === 'End') {
        next = tabs[tabs.length - 1];
    }

    if (!next) return;

    event.preventDefault();
    currentTab.value = next.id;
    document.getElementById(`report-tab-${next.id}`)?.focus();
};

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

/*
 * The four figures worth comparing, with their direction of "good".
 *
 * Discounts are the one that inverts: more of them is not an improvement, so
 * a rise there is red where a rise in revenue is green. Colouring every
 * increase green is how a dashboard ends up congratulating a shop for giving
 * money away.
 */
const comparisonRows = computed(() => {
    const c = rangeData.value?.comparison;
    if (!c) return [];

    const row = (key, label, format, higherIsBetter = true) => {
        const change = c.change[key];
        return {
            key,
            label,
            now: format(rangeData.value[key] ?? 0),
            before: format(c.previous[key] ?? 0),
            change,
            good: change === null ? true : (higherIsBetter ? change >= 0 : change <= 0),
        };
    };

    const count = (n) => String(Math.round(n));

    return [
        row('revenue', 'Revenue', money),
        row('transactions', 'Transactions', count),
        row('profit', 'Profit', money),
        row('discounts', 'Discounts', money, false),
    ];
});
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
                <h1 class="text-heading font-bold tracking-tight text-ink-1">Reports</h1>
                <p class="mt-0.5 text-ui text-ink-3">Sales analytics and performance insights</p>
            </div>

            <!-- Tab Navigation -->
            <div class="card flex gap-1 p-1" role="tablist" aria-label="Report types">
                <button
                    v-for="tab in tabs" :key="tab.id"
                    type="button"
                    role="tab"
                    :id="`report-tab-${tab.id}`"
                    :aria-selected="currentTab === tab.id"
                    :aria-controls="`report-panel-${tab.id}`"
                    :tabindex="currentTab === tab.id ? 0 : -1"
                    @click="currentTab = tab.id"
                    @keydown="moveTab($event, tab.id)"
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
            <div
                v-show="currentTab === 'daily'"
                id="report-panel-daily"
                role="tabpanel"
                aria-labelledby="report-tab-daily"
                class="space-y-5"
            >
                <div class="card flex items-end gap-3 p-4">
                    <div>
                        <label for="daily-date" class="block text-label font-semibold uppercase tracking-wider text-ink-3">Select Date</label>
                        <input id="daily-date" v-model="dailyDate" type="date"
                            class="mt-1 rounded-control border-line px-3 py-2 text-ui focus:border-accent focus:ring-accent/20" />
                    </div>
                    <button type="button" @click="dailyDate = today()"
                        class="rounded-control border border-line px-3 py-2 text-ui font-semibold text-ink-2 transition-all hover:bg-surface-2">
                        Today
                    </button>
                </div>

                <div v-if="dailyLoading" role="status" class="flex items-center justify-center py-14">
                    <svg class="h-8 w-8 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span class="sr-only">Loading the daily report</span>
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
                        <h2 class="text-body font-bold text-ink-1">Hourly Sales</h2>
                        <p class="text-meta text-ink-3">Revenue per hour for {{ formatDate(dailyDate) }}</p>

                        <!--
                            The bars carry no text, so to anything that is not
                            a pair of eyes this chart is twenty-four empty
                            divs. Hidden from the reading order, with the
                            figures themselves published below it.
                        -->
                        <div class="mt-4 flex items-end gap-1" style="height: 200px;" aria-hidden="true">
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
                                <div class="pointer-events-none absolute -top-16 left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded-control bg-action px-2 py-1 text-meta text-action-fg shadow-overlay group-hover:block">
                                    <p class="font-bold">{{ h.hour }}:00</p>
                                    <p>{{ money(h.revenue) }}</p>
                                    <p>{{ h.transactions }} txn</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-between text-label text-ink-3" aria-hidden="true">
                            <span>12 AM</span>
                            <span>12 PM</span>
                            <span>11 PM</span>
                        </div>

                        <!--
                            Open to anyone, not just to a screen reader. The
                            hover tooltip was the only way to read an exact
                            figure, and a tooltip is not a way to read
                            twenty-four of them.
                        -->
                        <details class="mt-6 border-t border-line pt-4">
                            <summary class="cursor-pointer text-ui font-semibold text-accent-ink">
                                Hourly figures as a table
                            </summary>
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full divide-y divide-line">
                                    <caption class="sr-only">Revenue and transactions per hour for {{ formatDate(dailyDate) }}</caption>
                                    <thead class="bg-surface-2/80">
                                        <tr>
                                            <th scope="col" class="px-3 py-2 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Hour</th>
                                            <th scope="col" class="px-3 py-2 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Revenue</th>
                                            <th scope="col" class="px-3 py-2 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Transactions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        <tr v-for="h in dailyData.hourly" :key="h.hour">
                                            <th scope="row" class="px-3 py-1.5 text-left text-ui font-medium tabular-nums text-ink-2">{{ h.hour }}:00</th>
                                            <td class="px-3 py-1.5 text-right text-ui tabular-nums text-ink-1">{{ money(h.revenue) }}</td>
                                            <td class="px-3 py-1.5 text-right text-ui tabular-nums text-ink-3">{{ h.transactions }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </div>
                </template>
            </div>

            <!-- DATE RANGE TAB -->
            <div
                v-show="currentTab === 'range'"
                id="report-panel-range"
                role="tabpanel"
                aria-labelledby="report-tab-range"
                class="space-y-5"
            >
                <div class="card flex flex-wrap items-end gap-3 p-4">
                    <div>
                        <label for="range-from" class="block text-label font-semibold uppercase tracking-wider text-ink-3">From</label>
                        <input id="range-from" v-model="rangeFrom" type="date"
                            class="mt-1 rounded-control border-line px-3 py-2 text-ui focus:border-accent focus:ring-accent/20" />
                    </div>
                    <div>
                        <label for="range-to" class="block text-label font-semibold uppercase tracking-wider text-ink-3">To</label>
                        <input id="range-to" v-model="rangeTo" type="date"
                            class="mt-1 rounded-control border-line px-3 py-2 text-ui focus:border-accent focus:ring-accent/20" />
                    </div>
                    <button type="button" @click="fetchRange"
                        class="rounded-control bg-accent px-4 py-2 text-ui font-semibold text-accent-fg shadow-rest transition-all hover:bg-accent-hover">
                        Generate Report
                    </button>
                </div>

                <div v-if="rangeLoading" role="status" class="flex items-center justify-center py-14">
                    <svg class="h-8 w-8 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span class="sr-only">Building the range report</span>
                </div>

                <template v-else-if="rangeData">
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <StatCard label="Total Revenue" :value="money(rangeData.revenue ?? 0)" :icon="BanknotesIcon" color="success" />
                        <StatCard label="Transactions" :value="rangeData.transactions ?? 0" :icon="ReceiptPercentIcon" />
                        <StatCard label="Total Profit" :value="money(rangeData.profit ?? 0)" :icon="ArrowTrendingUpIcon" color="success" />
                        <StatCard label="Avg per Day" :value="money(rangeData.daily?.length ? (rangeData.revenue ?? 0) / rangeData.daily.length : 0)" :icon="ArrowTrendingDownIcon" />
                    </div>

                    <!--
                        Against the same span of days immediately before.
                        ₱48,000 is a good week or a bad one entirely depending
                        on the week before it, and that is the comparison an
                        owner is making in their head regardless.
                    -->
                    <div v-if="rangeData.comparison" class="card p-6">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 class="text-body font-bold text-ink-1">Compared with the previous {{ rangeData.comparison.days }} days</h2>
                            <p class="text-meta tabular-nums text-ink-3">
                                {{ rangeData.comparison.start_date }} &ndash; {{ rangeData.comparison.end_date }}
                            </p>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
                            <div v-for="row in comparisonRows" :key="row.key">
                                <dt class="text-label font-semibold uppercase tracking-wider text-ink-3">{{ row.label }}</dt>
                                <dd class="mt-1 flex items-baseline gap-2">
                                    <span class="text-title font-bold tabular-nums text-ink-1">{{ row.now }}</span>
                                    <span
                                        v-if="row.change !== null"
                                        class="text-meta font-bold tabular-nums"
                                        :class="row.good ? 'text-ready-ink' : 'text-stop-ink'"
                                    >{{ row.change > 0 ? '+' : '' }}{{ row.change }}%</span>
                                </dd>
                                <dd class="mt-0.5 text-meta tabular-nums text-ink-3">
                                    <template v-if="row.change !== null">was {{ row.before }}</template>
                                    <!--
                                        No percentage against a period with
                                        nothing in it. "+100%" for a shop's
                                        first week is a made-up number.
                                    -->
                                    <template v-else>nothing to compare</template>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Payment Methods -->
                    <div v-if="rangeData.payment_methods" class="card p-6">
                        <h2 class="text-body font-bold text-ink-1">Payment Methods</h2>
                        <ul class="mt-4 space-y-3">
                            <li v-for="pm in rangeData.payment_methods" :key="pm.payment_method" class="flex items-center gap-3">
                                <span class="w-16 text-label font-semibold uppercase tracking-wider text-ink-3">{{ pm.payment_method }}</span>
                                <div class="h-6 flex-1 overflow-hidden rounded-full bg-surface-3" aria-hidden="true">
                                    <div class="h-full rounded-full bg-accent transition-all"
                                        :style="{ width: `${(pm.count / (rangeData.transactions || 1)) * 100}%` }" />
                                </div>
                                <span class="w-20 text-right text-ui font-semibold tabular-nums text-ink-2">{{ money(pm.revenue) }}</span>
                                <span class="w-12 text-right text-meta tabular-nums text-ink-3">{{ pm.count }} txn</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Daily Breakdown -->
                    <div v-if="rangeData.daily?.length" class="card overflow-hidden">
                        <ul class="divide-y divide-line md:hidden">
                            <li v-for="day in rangeData.daily" :key="day.date" class="p-4">
                                <div class="flex items-baseline justify-between gap-3">
                                    <p class="text-ui font-semibold text-ink-1">{{ formatDate(day.date) }}</p>
                                    <p class="text-ui font-bold tabular-nums text-ink-1">{{ money(day.revenue) }}</p>
                                </div>
                                <p class="mt-1 text-meta text-ink-3">
                                    Profit <span class="font-semibold text-ready-ink">{{ money(day.profit) }}</span>
                                    &middot; {{ day.transactions }} txn
                                    &middot; {{ money(day.discounts) }} discounts
                                </p>
                            </li>
                        </ul>

                        <div class="hidden overflow-x-auto md:block">
                            <table class="min-w-full divide-y divide-line">
                                <caption class="sr-only">Revenue, profit and transactions per day</caption>
                                <thead class="bg-surface-2/80">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Date</th>
                                        <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Revenue</th>
                                        <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Profit</th>
                                        <th scope="col" class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Txns</th>
                                        <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Discounts</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <tr v-for="day in rangeData.daily" :key="day.date" class="transition-colors hover:bg-surface-2/50">
                                        <th scope="row" class="px-4 py-2.5 text-left text-ui font-semibold text-ink-1">{{ formatDate(day.date) }}</th>
                                        <td class="px-4 py-2.5 text-right text-ui tabular-nums text-ink-1">{{ money(day.revenue) }}</td>
                                        <td class="px-4 py-2.5 text-right text-ui font-semibold tabular-nums text-ready-ink">{{ money(day.profit) }}</td>
                                        <td class="px-4 py-2.5 text-center text-ui tabular-nums text-ink-3">{{ day.transactions }}</td>
                                        <td class="px-4 py-2.5 text-right text-ui text-ink-3">{{ money(day.discounts) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>

            <!-- TOP PRODUCTS TAB -->
            <div
                v-show="currentTab === 'top'"
                id="report-panel-top"
                role="tabpanel"
                aria-labelledby="report-tab-top"
                class="space-y-5"
            >
                <div class="card flex flex-wrap items-end gap-3 p-4">
                    <div>
                        <label for="top-from" class="block text-label font-semibold uppercase tracking-wider text-ink-3">From</label>
                        <input id="top-from" v-model="topFrom" type="date"
                            class="mt-1 rounded-control border-line px-3 py-2 text-ui focus:border-accent focus:ring-accent/20" />
                    </div>
                    <div>
                        <label for="top-to" class="block text-label font-semibold uppercase tracking-wider text-ink-3">To</label>
                        <input id="top-to" v-model="topTo" type="date"
                            class="mt-1 rounded-control border-line px-3 py-2 text-ui focus:border-accent focus:ring-accent/20" />
                    </div>
                    <div>
                        <label for="top-limit" class="block text-label font-semibold uppercase tracking-wider text-ink-3">Top</label>
                        <select id="top-limit" v-model="topLimit"
                            class="mt-1 rounded-control border-line py-2 pl-3 pr-8 text-ui focus:border-accent focus:ring-accent/20">
                            <option :value="5">5</option>
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                    <button type="button" @click="fetchTop"
                        class="rounded-control bg-accent px-4 py-2 text-ui font-semibold text-accent-fg shadow-rest transition-all hover:bg-accent-hover">
                        Generate
                    </button>
                </div>

                <div v-if="topLoading" role="status" class="flex items-center justify-center py-14">
                    <svg class="h-8 w-8 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span class="sr-only">Finding the best sellers</span>
                </div>

                <div v-else-if="topData?.length" class="card overflow-hidden">
                    <ol class="divide-y divide-line md:hidden">
                        <li v-for="(product, i) in topData" :key="product.product_id" class="flex items-start gap-3 p-4">
                            <span :class="[
                                'mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-meta font-bold',
                                i === 0 ? 'bg-wait-tint text-wait-ink' :
                                i === 1 ? 'bg-surface-3 text-ink-2' :
                                i === 2 ? 'bg-wait-tint text-wait-ink' :
                                'text-ink-3'
                            ]">{{ i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-ui font-semibold text-ink-1">{{ product.product_name }}</p>
                                <p class="mt-0.5 text-meta text-ink-3">
                                    {{ product.total_quantity }} sold
                                    &middot; {{ money(product.total_revenue) }}
                                    &middot; {{ product.order_count }} orders
                                </p>
                            </div>
                        </li>
                    </ol>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="min-w-full divide-y divide-line">
                            <caption class="sr-only">Best-selling products for the chosen period</caption>
                            <thead class="bg-surface-2/80">
                                <tr>
                                    <th scope="col" class="w-12 px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">#</th>
                                    <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Product</th>
                                    <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Qty Sold</th>
                                    <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Revenue</th>
                                    <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Orders</th>
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
                                    <th scope="row" class="px-4 py-3 text-left text-ui font-semibold text-ink-1">{{ product.product_name }}</th>
                                    <td class="px-4 py-3 text-right text-ui font-bold text-ink-1">{{ product.total_quantity }}</td>
                                    <td class="px-4 py-3 text-right text-ui text-ink-1">{{ money(product.total_revenue) }}</td>
                                    <td class="px-4 py-3 text-right text-ui text-ink-3">{{ product.order_count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else-if="topData && topData.length === 0" class="flex flex-col items-center justify-center py-14">
                    <div class="flex h-14 w-14 items-center justify-center rounded-card bg-surface-2">
                        <TrophyIcon class="h-7 w-7 text-ink-3" aria-hidden="true" />
                    </div>
                    <p class="mt-3 text-ui font-medium text-ink-3">No sales data for selected period</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
