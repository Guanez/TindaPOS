<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/Composables/currency';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { formatDateTime, formatDate, debounce } from '@/Composables/helpers';
import {
    EyeIcon,
    XMarkIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    ClipboardDocumentListIcon,
    NoSymbolIcon,
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
</script>

<template>
    <AppLayout>
        <Head title="Sales History" />

        <div class="space-y-5">
            <!-- Header -->
            <div>
                <h1 class="text-xl font-bold tracking-tight text-ink-1">Sales History</h1>
                <p class="mt-0.5 text-ui text-ink-3">View and manage all transactions</p>
            </div>

            <!-- Filters -->
            <div class="card flex flex-wrap items-end gap-3 p-4">
                <div>
                    <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">From</label>
                    <input v-model="dateFrom" type="date"
                        class="mt-1 rounded-control border-line px-3 py-2 text-sm focus:border-accent focus:ring-accent/20" />
                </div>
                <div>
                    <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">To</label>
                    <input v-model="dateTo" type="date"
                        class="mt-1 rounded-control border-line px-3 py-2 text-sm focus:border-accent focus:ring-accent/20" />
                </div>
                <div>
                    <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">Status</label>
                    <select v-model="statusFilter"
                        class="mt-1 rounded-control border-line py-2 pl-3 pr-8 text-sm focus:border-accent focus:ring-accent/20">
                        <option value="">All</option>
                        <option value="completed">Completed</option>
                        <option value="voided">Voided</option>
                    </select>
                </div>
                <div>
                    <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">Payment</label>
                    <select v-model="paymentFilter"
                        class="mt-1 rounded-control border-line py-2 pl-3 pr-8 text-sm focus:border-accent focus:ring-accent/20">
                        <option value="">All</option>
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="maya">Maya</option>
                        <option value="card">Card</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line">
                        <thead class="bg-surface-2/80">
                            <tr>
                                <th class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Receipt #</th>
                                <th class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Date &amp; Time</th>
                                <th class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Items</th>
                                <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Total</th>
                                <th class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Payment</th>
                                <th class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Status</th>
                                <th class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Cashier</th>
                                <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Actions</th>
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
                                <td class="px-4 py-3 text-ui text-ink-3">{{ sale.user?.name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <button @click="viewSale(sale)" aria-label="View sale details"
                                            class="flex items-center gap-1 rounded-control border border-line px-2.5 py-1.5 text-meta font-semibold text-ink-2 hover:bg-surface-2" style="transition: background-color var(--t-fast);">
                                            <EyeIcon class="h-3.5 w-3.5" aria-hidden="true" /> View
                                        </button>
                                        <button v-if="sale.status === 'completed' && isManager" @click="startVoid(sale.id)" aria-label="Void this sale"
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
                        <ClipboardDocumentListIcon class="h-7 w-7 text-ink-3" />
                    </div>
                    <p class="mt-3 text-sm font-medium text-ink-3">No sales found</p>
                </div>

                <!-- Pagination -->
                <div v-if="sales.last_page > 1" class="flex items-center justify-between border-t border-line px-4 py-3">
                    <p class="text-meta text-ink-3">
                        Showing {{ sales.from }}&ndash;{{ sales.to }} of {{ sales.total }}
                    </p>
                    <div class="flex gap-1">
                        <button
                            v-for="link in sales.links" :key="link.label"
                            @click="goToPage(link.url)" :disabled="!link.url"
                            :class="[
                                'rounded-control px-3 py-1 text-meta font-medium transition-all',
                                link.active ? 'bg-accent text-accent-fg' : link.url ? 'text-ink-3 hover:bg-surface-2' : 'text-ink-3 cursor-default'
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- SALE DETAIL MODAL -->
        <Teleport to="body">
            <div v-if="showDetail" class="fixed inset-0 z-50 flex items-center justify-center bg-ink-1/40 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-label="Sale details">
                <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-card bg-surface-1 p-6 shadow-overlay animate-scale-in" style="overscroll-behavior: contain;">
                    <div v-if="loadingDetail" class="flex flex-col items-center justify-center py-12">
                        <svg class="h-8 w-8 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <p class="mt-2 text-ui text-ink-3">Loading details…</p>
                    </div>

                    <template v-else-if="selectedSale">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <DocumentTextIcon class="h-5 w-5 text-accent-ink" aria-hidden="true" />
                                <div>
                                    <h3 class="text-lg font-bold text-ink-1">Sale Details</h3>
                                    <p class="font-mono text-meta text-ink-3">{{ selectedSale.receipt_number }}</p>
                                </div>
                            </div>
                            <span :class="['badge', selectedSale.status === 'completed' ? 'badge-success' : 'badge-danger']">
                                {{ selectedSale.status === 'completed' ? 'Completed' : 'Voided' }}
                            </span>
                        </div>

                        <!-- Info Grid -->
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-label font-semibold uppercase tracking-wider text-ink-3">Date</p>
                                <p class="mt-0.5 text-ui font-medium text-ink-2">{{ formatDateTime(selectedSale.created_at) }}</p>
                            </div>
                            <div>
                                <p class="text-label font-semibold uppercase tracking-wider text-ink-3">Cashier</p>
                                <p class="mt-0.5 text-ui font-medium text-ink-2">{{ selectedSale.user?.name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-label font-semibold uppercase tracking-wider text-ink-3">Payment</p>
                                <p class="mt-0.5 text-ui font-medium text-ink-2">{{ selectedSale.payment_method?.toUpperCase() }}</p>
                            </div>
                            <div v-if="selectedSale.cash_received">
                                <p class="text-label font-semibold uppercase tracking-wider text-ink-3">Cash / Change</p>
                                <p class="mt-0.5 text-ui font-medium text-ink-2">{{ money(selectedSale.cash_received) }} / {{ money(selectedSale.change_amount) }}</p>
                            </div>
                        </div>

                        <!-- Items -->
                        <div class="mt-4">
                            <h4 class="text-ui font-semibold text-ink-2">Items</h4>
                            <div class="mt-2 divide-y divide-line rounded-control border border-line">
                                <div v-for="item in selectedSale.items" :key="item.id"
                                    class="flex items-center justify-between px-3 py-2.5">
                                    <div>
                                        <p class="text-ui font-semibold text-ink-1">{{ item.product_name }}</p>
                                        <p class="text-meta text-ink-3">{{ money(item.selling_price) }} x {{ item.quantity }}</p>
                                    </div>
                                    <p class="text-ui font-bold text-ink-1">{{ money(item.line_total) }}</p>
                                </div>
                            </div>
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
                            <div class="flex justify-between border-t border-line pt-2 text-lg font-bold text-ink-1">
                                <span>Total</span>
                                <span>{{ money(selectedSale.total) }}</span>
                            </div>
                        </div>

                        <!-- Void info -->
                        <div v-if="selectedSale.status === 'voided'" class="mt-4 rounded-control bg-stop-tint p-3">
                            <p class="text-ui font-semibold text-stop-ink">Voided</p>
                            <p class="text-ui text-stop-ink">{{ selectedSale.void_reason }}</p>
                            <p class="mt-1 text-meta text-stop-ink">
                                By {{ selectedSale.voided_by_user?.name ?? '—' }} &middot; {{ formatDateTime(selectedSale.voided_at) }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="mt-6 flex gap-3">
                            <button @click="closeSaleDetail"
                                class="flex-1 rounded-control border border-line py-2.5 text-ui font-semibold text-ink-2 transition-all hover:bg-surface-2">
                                Close
                            </button>
                            <button v-if="selectedSale.status === 'completed' && isManager" @click="startVoid(selectedSale.id)"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-control border border-stop-tint bg-stop-tint py-2.5 text-ui font-bold text-stop-ink transition-all hover:bg-stop-tint">
                                <NoSymbolIcon class="h-4 w-4" /> Void Sale
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Teleport>

        <!-- VOID CONFIRMATION MODAL -->
        <Teleport to="body">
            <div v-if="confirmingVoid" class="fixed inset-0 z-[60] flex items-center justify-center bg-ink-1/40 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-label="Confirm void sale">
                <div class="w-full max-w-sm rounded-card bg-surface-1 p-6 shadow-overlay">
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-control bg-stop-tint">
                            <ExclamationTriangleIcon class="h-5 w-5 text-stop-ink" />
                        </div>
                        <h3 class="text-lg font-bold text-stop-ink">Void Sale</h3>
                    </div>
                    <p class="mt-2 text-ui text-ink-3">
                        This will mark the sale as voided and return all items to inventory. This action cannot be undone.
                    </p>

                    <div class="mt-4">
                        <label class="block text-ui font-semibold text-ink-2">Reason <span class="text-stop-ink">*</span></label>
                        <input v-model="voidReason" type="text" required
                            class="mt-1.5 w-full rounded-control border-line py-2.5 text-sm focus:border-stop-mark focus:ring-red-500/20"
                            placeholder="e.g. Customer returned items" />
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button @click="cancelVoid"
                            class="flex-1 rounded-control border border-line py-2.5 text-ui font-semibold text-ink-2 transition-all hover:bg-surface-2">
                            Cancel
                        </button>
                        <button @click="confirmVoid" :disabled="!voidReason.trim()"
                            class="flex-1 rounded-control bg-stop-solid py-2.5 text-ui font-bold text-on-solid transition-all hover:bg-stop-solid disabled:opacity-50">
                            Confirm Void
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
