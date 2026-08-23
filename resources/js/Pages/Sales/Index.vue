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
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Sales History</h1>
                <p class="mt-0.5 text-[13px] text-slate-500">View and manage all transactions</p>
            </div>

            <!-- Filters -->
            <div class="card flex flex-wrap items-end gap-3 p-4">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">From</label>
                    <input v-model="dateFrom" type="date"
                        class="mt-1 rounded-xl border-slate-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500/20" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">To</label>
                    <input v-model="dateTo" type="date"
                        class="mt-1 rounded-xl border-slate-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500/20" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Status</label>
                    <select v-model="statusFilter"
                        class="mt-1 rounded-xl border-slate-200 py-2 pl-3 pr-8 text-sm focus:border-brand-500 focus:ring-brand-500/20">
                        <option value="">All</option>
                        <option value="completed">Completed</option>
                        <option value="voided">Voided</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Payment</label>
                    <select v-model="paymentFilter"
                        class="mt-1 rounded-xl border-slate-200 py-2 pl-3 pr-8 text-sm focus:border-brand-500 focus:ring-brand-500/20">
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
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Receipt #</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Date &amp; Time</th>
                                <th class="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">Items</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total</th>
                                <th class="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">Payment</th>
                                <th class="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">Status</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Cashier</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="sale in sales.data" :key="sale.id"
                                :class="['transition-colors hover:bg-slate-50/50', sale.status === 'voided' ? 'bg-red-50/30' : '']"
                            >
                                <td class="px-4 py-3 font-mono text-[13px] font-semibold text-slate-800">{{ sale.receipt_number }}</td>
                                <td class="px-4 py-3 text-[13px] text-slate-500">{{ formatDateTime(sale.created_at) }}</td>
                                <td class="px-4 py-3 text-center text-[13px] tabular-nums text-slate-500">{{ sale.item_count }}</td>
                                <td class="px-4 py-3 text-right text-[13px] font-bold tabular-nums text-slate-800">{{ money(sale.total) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge badge-neutral">{{ sale.payment_method.toUpperCase() }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['badge', sale.status === 'completed' ? 'badge-success' : 'badge-danger']">
                                        {{ sale.status === 'completed' ? 'Completed' : 'Voided' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[13px] text-slate-500">{{ sale.user?.name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <button @click="viewSale(sale)" aria-label="View sale details"
                                            class="flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-50" style="transition: background-color 0.15s;">
                                            <EyeIcon class="h-3.5 w-3.5" aria-hidden="true" /> View
                                        </button>
                                        <button v-if="sale.status === 'completed' && isManager" @click="startVoid(sale.id)" aria-label="Void this sale"
                                            class="flex items-center gap-1 rounded-lg border border-red-200 px-2.5 py-1.5 text-[11px] font-semibold text-red-600 hover:bg-red-50" style="transition: background-color 0.15s;">
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
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50">
                        <ClipboardDocumentListIcon class="h-7 w-7 text-slate-300" />
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-400">No sales found</p>
                </div>

                <!-- Pagination -->
                <div v-if="sales.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                    <p class="text-[12px] text-slate-500">
                        Showing {{ sales.from }}&ndash;{{ sales.to }} of {{ sales.total }}
                    </p>
                    <div class="flex gap-1">
                        <button
                            v-for="link in sales.links" :key="link.label"
                            @click="goToPage(link.url)" :disabled="!link.url"
                            :class="[
                                'rounded-lg px-3 py-1 text-[12px] font-medium transition-all',
                                link.active ? 'bg-brand-600 text-white' : link.url ? 'text-slate-500 hover:bg-slate-50' : 'text-slate-300 cursor-default'
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- SALE DETAIL MODAL -->
        <Teleport to="body">
            <div v-if="showDetail" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-label="Sale details">
                <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-elevated animate-scale-in" style="overscroll-behavior: contain;">
                    <div v-if="loadingDetail" class="flex flex-col items-center justify-center py-12">
                        <svg class="h-8 w-8 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <p class="mt-2 text-[13px] text-slate-400">Loading details…</p>
                    </div>

                    <template v-else-if="selectedSale">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <DocumentTextIcon class="h-5 w-5 text-brand-600" aria-hidden="true" />
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Sale Details</h3>
                                    <p class="font-mono text-[12px] text-slate-400">{{ selectedSale.receipt_number }}</p>
                                </div>
                            </div>
                            <span :class="['badge', selectedSale.status === 'completed' ? 'badge-success' : 'badge-danger']">
                                {{ selectedSale.status === 'completed' ? 'Completed' : 'Voided' }}
                            </span>
                        </div>

                        <!-- Info Grid -->
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Date</p>
                                <p class="mt-0.5 text-[13px] font-medium text-slate-700">{{ formatDateTime(selectedSale.created_at) }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Cashier</p>
                                <p class="mt-0.5 text-[13px] font-medium text-slate-700">{{ selectedSale.user?.name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Payment</p>
                                <p class="mt-0.5 text-[13px] font-medium text-slate-700">{{ selectedSale.payment_method?.toUpperCase() }}</p>
                            </div>
                            <div v-if="selectedSale.cash_received">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Cash / Change</p>
                                <p class="mt-0.5 text-[13px] font-medium text-slate-700">{{ money(selectedSale.cash_received) }} / {{ money(selectedSale.change_amount) }}</p>
                            </div>
                        </div>

                        <!-- Items -->
                        <div class="mt-4">
                            <h4 class="text-[13px] font-semibold text-slate-700">Items</h4>
                            <div class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200">
                                <div v-for="item in selectedSale.items" :key="item.id"
                                    class="flex items-center justify-between px-3 py-2.5">
                                    <div>
                                        <p class="text-[13px] font-semibold text-slate-800">{{ item.product_name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ money(item.selling_price) }} x {{ item.quantity }}</p>
                                    </div>
                                    <p class="text-[13px] font-bold text-slate-800">{{ money(item.line_total) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="mt-4 space-y-1 text-[13px]">
                            <div class="flex justify-between text-slate-500">
                                <span>Subtotal</span>
                                <span>{{ money(selectedSale.subtotal) }}</span>
                            </div>
                            <div v-if="parseFloat(selectedSale.discount) > 0" class="flex justify-between text-emerald-600">
                                <span>Discount</span>
                                <span>-{{ money(selectedSale.discount) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-slate-100 pt-2 text-lg font-bold text-slate-900">
                                <span>Total</span>
                                <span>{{ money(selectedSale.total) }}</span>
                            </div>
                        </div>

                        <!-- Void info -->
                        <div v-if="selectedSale.status === 'voided'" class="mt-4 rounded-xl bg-red-50 p-3">
                            <p class="text-[13px] font-semibold text-red-700">Voided</p>
                            <p class="text-[13px] text-red-600">{{ selectedSale.void_reason }}</p>
                            <p class="mt-1 text-[11px] text-red-400">
                                By {{ selectedSale.voided_by_user?.name ?? '—' }} &middot; {{ formatDateTime(selectedSale.voided_at) }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="mt-6 flex gap-3">
                            <button @click="closeSaleDetail"
                                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-[13px] font-semibold text-slate-600 transition-all hover:bg-slate-50">
                                Close
                            </button>
                            <button v-if="selectedSale.status === 'completed' && isManager" @click="startVoid(selectedSale.id)"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-red-200 bg-red-50 py-2.5 text-[13px] font-bold text-red-700 transition-all hover:bg-red-100">
                                <NoSymbolIcon class="h-4 w-4" /> Void Sale
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Teleport>

        <!-- VOID CONFIRMATION MODAL -->
        <Teleport to="body">
            <div v-if="confirmingVoid" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-label="Confirm void sale">
                <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-elevated">
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-50">
                            <ExclamationTriangleIcon class="h-5 w-5 text-red-600" />
                        </div>
                        <h3 class="text-lg font-bold text-red-700">Void Sale</h3>
                    </div>
                    <p class="mt-2 text-[13px] text-slate-500">
                        This will mark the sale as voided and return all items to inventory. This action cannot be undone.
                    </p>

                    <div class="mt-4">
                        <label class="block text-[13px] font-semibold text-slate-700">Reason <span class="text-red-400">*</span></label>
                        <input v-model="voidReason" type="text" required
                            class="mt-1.5 w-full rounded-xl border-slate-200 py-2.5 text-sm focus:border-red-500 focus:ring-red-500/20"
                            placeholder="e.g. Customer returned items" />
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button @click="cancelVoid"
                            class="flex-1 rounded-xl border border-slate-200 py-2.5 text-[13px] font-semibold text-slate-600 transition-all hover:bg-slate-50">
                            Cancel
                        </button>
                        <button @click="confirmVoid" :disabled="!voidReason.trim()"
                            class="flex-1 rounded-xl bg-red-600 py-2.5 text-[13px] font-bold text-white transition-all hover:bg-red-700 disabled:opacity-50">
                            Confirm Void
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
