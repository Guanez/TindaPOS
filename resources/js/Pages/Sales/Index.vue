<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Dialog.vue';
import Receipt from '@/Components/Receipt.vue';
import { useCurrency } from '@/Composables/currency';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { formatDateTime, debounce } from '@/Composables/helpers';
import {
    EyeIcon,
    XMarkIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    ClipboardDocumentListIcon,
    NoSymbolIcon,
    PrinterIcon,
    ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline';

const { money } = useCurrency();

const props = defineProps({
    sales: Object,
    filters: Object,
    saleDetail: { type: Object, default: null },
});

// Filters
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');
const statusFilter = ref(props.filters?.status ?? '');
const paymentFilter = ref(props.filters?.payment_method ?? '');

const applyFilters = debounce(() => {
    router.get(route('sales.index'), {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        status: statusFilter.value || undefined,
        payment_method: paymentFilter.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}, 300);

watch([dateFrom, dateTo, statusFilter, paymentFilter], applyFilters);

// The export takes the filters as they stand, so the file matches the screen.
const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (dateFrom.value) params.set('date_from', dateFrom.value);
    if (dateTo.value) params.set('date_to', dateTo.value);
    if (statusFilter.value) params.set('status', statusFilter.value);
    if (paymentFilter.value) params.set('payment_method', paymentFilter.value);

    const qs = params.toString();
    return route('sales.export') + (qs ? `?${qs}` : '');
});

// Sale Detail Modal — opens straight away when /sales/{id} is visited directly.
const resolveDetail = (detail) => detail?.data ?? detail ?? null;

const showDetail = ref(props.saleDetail !== null);
const selectedSale = ref(resolveDetail(props.saleDetail));
const loadingDetail = ref(false);

const viewSale = (sale) => {
    loadingDetail.value = true;
    showDetail.value = true;

    // Partial reload: only saleDetail comes back, so the list stays put.
    router.get(route('sales.show', sale.id), {}, {
        only: ['saleDetail'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            selectedSale.value = resolveDetail(page.props.saleDetail);
            loadingDetail.value = false;
        },
        onError: () => { loadingDetail.value = false; },
    });
};

const closeSaleDetail = () => {
    showDetail.value = false;
    selectedSale.value = null;
};

// ── Reprint ─────────────────────────────────────────────────────────────
// The detail panel already holds everything a receipt needs, so a reprint is
// a second rendering of a sale that is already loaded rather than a second
// trip to the server.
const reprinting = ref(null);

const reprint = (sale) => {
    closeSaleDetail();
    reprinting.value = sale;
};

const printNow = () => window.print();

// Void
const confirmingVoid = ref(null);
const voidReason = ref('');

const startVoid = (saleId) => { confirmingVoid.value = saleId; voidReason.value = ''; };

const confirmVoid = () => {
    if (!confirmingVoid.value) return;
    router.post(route('sales.void', confirmingVoid.value), {
        reason: voidReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { confirmingVoid.value = null; voidReason.value = ''; closeSaleDetail(); },
    });
};

const cancelVoid = () => { confirmingVoid.value = null; voidReason.value = ''; };

// Pagination
const goToPage = (url) => {
    if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const isManager = computed(() => usePage().props.auth?.user?.is_manager);

// The shop in context, shared by the layout — the receipt needs its name,
// address and footer, which are the same ones the till prints.
const store = computed(() => usePage().props.store ?? {});
</script>

<template>
    <AppLayout>
        <Head title="Sales History" />

        <div class="space-y-5">
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-heading font-bold tracking-tight text-ink-1">Sales History</h1>
                    <p class="mt-0.5 text-ui text-ink-3">View and manage all transactions</p>
                </div>

                <!--
                    A plain link, not a fetch: the browser's own download
                    handling is what a manager expects, and it survives a file
                    big enough to take a while. Carries the current filters, so
                    what downloads is what is on screen.
                -->
                <a
                    v-if="isManager"
                    :href="exportUrl"
                    class="btn-secondary shrink-0"
                >
                    <ArrowDownTrayIcon class="h-4 w-4" aria-hidden="true" />
                    Export CSV
                </a>
            </div>

            <!-- Filters -->
            <div class="card flex flex-wrap items-end gap-3 p-4">
                <div>
                    <label for="sales-from" class="block text-label font-semibold uppercase tracking-wider text-ink-3">From</label>
                    <input id="sales-from" v-model="dateFrom" type="date"
                        class="mt-1 rounded-control border-line px-3 py-2 text-ui focus:border-accent focus:ring-accent/20" />
                </div>
                <div>
                    <label for="sales-to" class="block text-label font-semibold uppercase tracking-wider text-ink-3">To</label>
                    <input id="sales-to" v-model="dateTo" type="date"
                        class="mt-1 rounded-control border-line px-3 py-2 text-ui focus:border-accent focus:ring-accent/20" />
                </div>
                <div>
                    <label for="sales-status" class="block text-label font-semibold uppercase tracking-wider text-ink-3">Status</label>
                    <select id="sales-status" v-model="statusFilter"
                        class="mt-1 rounded-control border-line py-2 pl-3 pr-8 text-ui focus:border-accent focus:ring-accent/20">
                        <option value="">All</option>
                        <option value="completed">Completed</option>
                        <option value="voided">Voided</option>
                    </select>
                </div>
                <div>
                    <label for="sales-payment" class="block text-label font-semibold uppercase tracking-wider text-ink-3">Payment</label>
                    <select id="sales-payment" v-model="paymentFilter"
                        class="mt-1 rounded-control border-line py-2 pl-3 pr-8 text-ui focus:border-accent focus:ring-accent/20">
                        <option value="">All</option>
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="maya">Maya</option>
                        <option value="card">Card</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <!--
                Two renderings of the same list, not one squeezed into both.
                Eight columns inside 360px is a horizontal scrollbar hiding the
                total, and the total is the column anyone opens this page for.
                The phone gets the four fields that answer "which sale was
                that"; the counter gets the ledger.
            -->
            <div class="card overflow-hidden">
                <!-- Phone: one card per sale -->
                <ul class="divide-y divide-line md:hidden">
                    <li
                        v-for="sale in sales.data" :key="sale.id"
                        :class="['p-4', sale.status === 'voided' ? 'bg-stop-tint/30' : '']"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-mono text-ui font-semibold text-ink-1">{{ sale.receipt_number }}</p>
                                <p class="mt-0.5 text-meta text-ink-3">{{ formatDateTime(sale.created_at) }}</p>
                            </div>
                            <p class="shrink-0 text-title font-bold tabular-nums text-ink-1">{{ money(sale.total) }}</p>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span :class="['badge', sale.status === 'completed' ? 'badge-success' : 'badge-danger']">
                                {{ sale.status === 'completed' ? 'Completed' : 'Voided' }}
                            </span>
                            <span class="badge badge-neutral">{{ sale.payment_method.toUpperCase() }}</span>
                            <span class="text-meta text-ink-3">
                                {{ sale.item_count }} item{{ sale.item_count === 1 ? '' : 's' }}
                                &middot; {{ sale.user?.name ?? '&mdash;' }}
                            </span>
                        </div>

                        <div class="mt-3 flex gap-1.5">
                            <button
                                type="button"
                                @click="viewSale(sale)"
                                :aria-label="`View sale ${sale.receipt_number}`"
                                class="flex flex-1 items-center justify-center gap-1 rounded-control border border-line px-2.5 py-2 text-meta font-semibold text-ink-2 hover:bg-surface-2"
                                style="transition: background-color var(--t-fast);"
                            >
                                <EyeIcon class="h-3.5 w-3.5" aria-hidden="true" /> View
                            </button>
                            <button
                                v-if="sale.status === 'completed' && isManager"
                                type="button"
                                @click="startVoid(sale.id)"
                                :aria-label="`Void sale ${sale.receipt_number}`"
                                class="flex flex-1 items-center justify-center gap-1 rounded-control border border-stop-tint px-2.5 py-2 text-meta font-semibold text-stop-ink hover:bg-stop-tint"
                                style="transition: background-color var(--t-fast);"
                            >
                                <NoSymbolIcon class="h-3.5 w-3.5" aria-hidden="true" /> Void
                            </button>
                        </div>
                    </li>
                </ul>

                <!-- Counter: the full ledger -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-line">
                        <caption class="sr-only">Sales, most recent first</caption>
                        <thead class="bg-surface-2/80">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Receipt #</th>
                                <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Date &amp; Time</th>
                                <th scope="col" class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Items</th>
                                <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Total</th>
                                <th scope="col" class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Payment</th>
                                <th scope="col" class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Cashier</th>
                                <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr
                                v-for="sale in sales.data" :key="sale.id"
                                :class="['transition-colors hover:bg-surface-2/50', sale.status === 'voided' ? 'bg-stop-tint/30' : '']"
                            >
                                <td class="px-4 py-3 font-mono text-ui font-semibold text-ink-1">{{ sale.receipt_number }}</td>
                                <td class="px-4 py-3 text-ui text-ink-3">{{ formatDateTime(sale.created_at) }}</td>
                                <td class="px-4 py-3 text-center text-ui tabular-nums text-ink-3">{{ sale.item_count }}</td>
                                <td class="px-4 py-3 text-right text-ui font-bold tabular-nums text-ink-1">{{ money(sale.total) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge badge-neutral">{{ sale.payment_method.toUpperCase() }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['badge', sale.status === 'completed' ? 'badge-success' : 'badge-danger']">
                                        {{ sale.status === 'completed' ? 'Completed' : 'Voided' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-ui text-ink-3">{{ sale.user?.name ?? '&mdash;' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <!--
                                            The receipt number is in the label
                                            because a screen reader reads these
                                            buttons out of the row that gives
                                            "View" its meaning.
                                        -->
                                        <button type="button" @click="viewSale(sale)" :aria-label="`View sale ${sale.receipt_number}`"
                                            class="flex items-center gap-1 rounded-control border border-line px-2.5 py-1.5 text-meta font-semibold text-ink-2 hover:bg-surface-2" style="transition: background-color var(--t-fast);">
                                            <EyeIcon class="h-3.5 w-3.5" aria-hidden="true" /> View
                                        </button>
                                        <button v-if="sale.status === 'completed' && isManager" type="button" @click="startVoid(sale.id)" :aria-label="`Void sale ${sale.receipt_number}`"
                                            class="flex items-center gap-1 rounded-control border border-stop-tint px-2.5 py-1.5 text-meta font-semibold text-stop-ink hover:bg-stop-tint" style="transition: background-color var(--t-fast);">
                                            <NoSymbolIcon class="h-3.5 w-3.5" aria-hidden="true" /> Void
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-if="sales.data.length === 0" class="flex flex-col items-center justify-center py-14">
                    <div class="flex h-14 w-14 items-center justify-center rounded-card bg-surface-2">
                        <ClipboardDocumentListIcon class="h-7 w-7 text-ink-3" aria-hidden="true" />
                    </div>
                    <p class="mt-3 text-ui font-medium text-ink-3">No sales found</p>
                </div>

                <!-- Pagination -->
                <nav v-if="sales.last_page > 1" aria-label="Sales pages" class="flex items-center justify-between border-t border-line px-4 py-3">
                    <p class="text-meta text-ink-3">
                        Showing {{ sales.from }}&ndash;{{ sales.to }} of {{ sales.total }}
                    </p>
                    <div class="flex gap-1">
                        <button
                            v-for="link in sales.links" :key="link.label"
                            type="button"
                            @click="goToPage(link.url)" :disabled="!link.url"
                            :aria-current="link.active ? 'page' : undefined"
                            :class="[
                                'rounded-control px-3 py-1 text-meta font-medium transition-all',
                                link.active ? 'bg-accent text-accent-fg' : link.url ? 'text-ink-3 hover:bg-surface-2' : 'text-ink-3 cursor-default'
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </nav>
            </div>
        </div>

        <!-- SALE DETAIL -->
        <Dialog
            :show="showDetail"
            title="Sale Details"
            max-width="lg"
            @close="closeSaleDetail"
        >
            <template #header="{ close }">
                <div class="flex shrink-0 items-start justify-between gap-3 px-6 pt-6">
                    <div class="flex items-center gap-2">
                        <DocumentTextIcon class="h-5 w-5 text-accent-ink" aria-hidden="true" />
                        <div>
                            <h2 class="text-title font-bold text-ink-1">Sale Details</h2>
                            <p v-if="selectedSale" class="font-mono text-meta text-ink-3">{{ selectedSale.receipt_number }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="selectedSale" :class="['badge', selectedSale.status === 'completed' ? 'badge-success' : 'badge-danger']">
                            {{ selectedSale.status === 'completed' ? 'Completed' : 'Voided' }}
                        </span>
                        <button
                            type="button"
                            class="shrink-0 rounded-control p-1 text-ink-3 transition-colors hover:bg-surface-2 hover:text-ink-1"
                            aria-label="Close dialog"
                            @click="close"
                        >
                            <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </template>

            <div v-if="loadingDetail" role="status" class="flex flex-col items-center justify-center py-12">
                <svg class="h-8 w-8 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <p class="mt-2 text-ui text-ink-3">Loading details&hellip;</p>
            </div>

            <template v-else-if="selectedSale">
                <!-- Info Grid -->
                <dl class="grid grid-cols-2 gap-3">
                    <div>
                        <dt class="text-label font-semibold uppercase tracking-wider text-ink-3">Date</dt>
                        <dd class="mt-0.5 text-ui font-medium text-ink-2">{{ formatDateTime(selectedSale.created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-label font-semibold uppercase tracking-wider text-ink-3">Cashier</dt>
                        <dd class="mt-0.5 text-ui font-medium text-ink-2">{{ selectedSale.user?.name ?? '&mdash;' }}</dd>
                    </div>
                    <div>
                        <dt class="text-label font-semibold uppercase tracking-wider text-ink-3">Payment</dt>
                        <dd class="mt-0.5 text-ui font-medium text-ink-2">{{ selectedSale.payment_method?.toUpperCase() }}</dd>
                    </div>
                    <div v-if="selectedSale.cash_received">
                        <dt class="text-label font-semibold uppercase tracking-wider text-ink-3">Cash / Change</dt>
                        <dd class="mt-0.5 text-ui font-medium text-ink-2">{{ money(selectedSale.cash_received) }} / {{ money(selectedSale.change_amount) }}</dd>
                    </div>
                </dl>

                <!-- Items -->
                <div class="mt-4">
                    <h3 class="text-ui font-semibold text-ink-2">Items</h3>
                    <ul class="mt-2 divide-y divide-line rounded-control border border-line">
                        <li v-for="item in selectedSale.items" :key="item.id"
                            class="flex items-center justify-between px-3 py-2.5">
                            <div>
                                <p class="text-ui font-semibold text-ink-1">{{ item.product_name }}</p>
                                <p class="text-meta text-ink-3">{{ money(item.selling_price) }} x {{ item.quantity }}</p>
                            </div>
                            <p class="text-ui font-bold text-ink-1">{{ money(item.line_total) }}</p>
                        </li>
                    </ul>
                </div>

                <!-- Totals -->
                <div class="mt-4 space-y-1 text-ui">
                    <div class="flex justify-between text-ink-3">
                        <span>Subtotal</span>
                        <span>{{ money(selectedSale.subtotal) }}</span>
                    </div>
                    <div v-if="parseFloat(selectedSale.discount) > 0" class="flex justify-between text-ready-ink">
                        <span>Discount</span>
                        <span>-{{ money(selectedSale.discount) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-line pt-2 text-title font-bold text-ink-1">
                        <span>Total</span>
                        <span>{{ money(selectedSale.total) }}</span>
                    </div>
                </div>

                <!-- Void info -->
                <div v-if="selectedSale.status === 'voided'" class="mt-4 rounded-control bg-stop-tint p-3">
                    <p class="text-ui font-semibold text-stop-ink">Voided</p>
                    <p class="text-ui text-stop-ink">{{ selectedSale.void_reason }}</p>
                    <p class="mt-1 text-meta text-stop-ink">
                        By {{ selectedSale.voided_by_user?.name ?? '&mdash;' }} &middot; {{ formatDateTime(selectedSale.voided_at) }}
                    </p>
                </div>
            </template>

            <template #footer>
                <div class="flex gap-3">
                    <button type="button" @click="closeSaleDetail"
                        class="flex-1 rounded-control border border-line py-2.5 text-ui font-semibold text-ink-2 transition-all hover:bg-surface-2">
                        Close
                    </button>
                    <button v-if="selectedSale" type="button" @click="reprint(selectedSale)"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-control border border-line py-2.5 text-ui font-semibold text-ink-2 transition-all hover:bg-surface-2">
                        <PrinterIcon class="h-4 w-4" aria-hidden="true" /> Reprint
                    </button>
                    <button v-if="selectedSale && selectedSale.status === 'completed' && isManager" type="button" @click="startVoid(selectedSale.id)"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-control border border-stop-tint bg-stop-tint py-2.5 text-ui font-bold text-stop-ink transition-all hover:bg-stop-mark/20">
                        <NoSymbolIcon class="h-4 w-4" aria-hidden="true" /> Void Sale
                    </button>
                </div>
            </template>
        </Dialog>

        <!--
            REPRINT

            Its own dialog rather than printing the detail panel, because the
            detail panel is a record and a receipt is a document — different
            things, and only one of them belongs in a customer's hand.
            Headerless for the same reason the till's is: the paper opens with
            the shop's name.
        -->
        <Dialog
            :show="reprinting !== null"
            title="Reprint receipt"
            max-width="sm"
            headerless
            @close="reprinting = null"
        >
            <Receipt
                v-if="reprinting"
                :sale="reprinting"
                :store="store"
                :cashier-name="reprinting.user?.name ?? null"
                :printed-at="formatDateTime(reprinting.created_at)"
                reprint
            />

            <template #footer>
                <div class="flex gap-2 print:hidden">
                    <button type="button" @click="reprinting = null"
                        class="flex-1 rounded-control border border-line py-3 text-ui font-semibold text-ink-2 hover:bg-surface-2">
                        Close
                    </button>
                    <button type="button" data-autofocus @click="printNow"
                        class="flex flex-[2] items-center justify-center gap-2 rounded-control bg-action py-3 text-ui font-bold text-action-fg hover:bg-action-hover">
                        <PrinterIcon class="h-4 w-4" aria-hidden="true" /> Print
                    </button>
                </div>
            </template>
        </Dialog>

        <!-- VOID CONFIRMATION -->
        <Dialog
            :show="confirmingVoid !== null"
            title="Void Sale"
            max-width="sm"
            @close="cancelVoid"
        >
            <template #header>
                <div class="flex shrink-0 items-center gap-2 px-6 pt-6">
                    <div class="flex h-9 w-9 items-center justify-center rounded-control bg-stop-tint">
                        <ExclamationTriangleIcon class="h-5 w-5 text-stop-ink" aria-hidden="true" />
                    </div>
                    <h2 class="text-title font-bold text-stop-ink">Void Sale</h2>
                </div>
            </template>

            <p id="void-help" class="text-ui text-ink-3">
                This will mark the sale as voided and return all items to inventory. This action cannot be undone.
            </p>

            <div class="mt-4">
                <label for="void-reason" class="block text-ui font-semibold text-ink-2">
                    Reason <span class="text-stop-ink" aria-hidden="true">*</span>
                </label>
                <input
                    id="void-reason"
                    v-model="voidReason"
                    data-autofocus
                    type="text"
                    required
                    aria-required="true"
                    aria-describedby="void-help"
                    class="mt-1.5 w-full rounded-control border-line py-2.5 text-ui focus:border-stop-mark focus:ring-stop-mark/25"
                    placeholder="e.g. Customer returned items"
                />
            </div>

            <template #footer>
                <div class="flex gap-3">
                    <button type="button" @click="cancelVoid"
                        class="flex-1 rounded-control border border-line py-2.5 text-ui font-semibold text-ink-2 transition-all hover:bg-surface-2">
                        Cancel
                    </button>
                    <button type="button" @click="confirmVoid" :disabled="!voidReason.trim()"
                        class="flex-1 rounded-control bg-stop-solid py-2.5 text-ui font-bold text-on-solid transition-all hover:bg-stop-mark disabled:opacity-50">
                        Confirm Void
                    </button>
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
