<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/Composables/currency';
import StockBadge from '@/Components/StockBadge.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';
import { debounce } from '@/Composables/helpers';
import { useVocabulary } from '@/Composables/vocabulary';
import {
    MagnifyingGlassIcon,
    PlusIcon,
    PencilSquareIcon,
    CubeIcon,
    TrashIcon,
    ArrowPathIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon } from '@heroicons/vue/16/solid';

const { money } = useCurrency();


const props = defineProps({
    products: Object,
    categories: Array,
    modifierGroups: { type: Array, default: () => [] },
    filters: Object,
});

// ProductResource::collection() wraps the paginator as { data, links, meta },
// so the paginator fields live under meta — not on the prop root.
const productList = computed(() => props.products?.data ?? []);
const pagination = computed(() => props.products?.meta ?? {});

// Filters
const search = ref(props.filters?.search ?? '');
const categoryFilter = ref(props.filters?.category ?? '');
const stockFilter = ref(props.filters?.stock ?? '');

const applyFilters = debounce(() => {
    router.get(route('inventory.index'), {
        search: search.value || undefined,
        category: categoryFilter.value || undefined,
        stock: stockFilter.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}, 300);

watch([search, categoryFilter, stockFilter], applyFilters);

// Add/Edit Product Modal
const showProductModal = ref(false);
const editingProduct = ref(null);

const words = useVocabulary();

const activeTab = ref('details');
const productFormEl = ref(null);

const productForm = useForm({
    name: '', sku: '', barcode: '', category_id: '',
    cost_price: '', selling_price: '', stock_quantity: 0,
    low_stock_threshold: 10, is_favorite: false, description: '',
    track_stock: true, is_available: true,
    variants: [], modifier_group_ids: [],
});

// ── Sizes ──────────────────────────────────────────────────────────────
const addVariant = () => {
    productForm.variants.push({ id: null, name: '', cost_price: '', selling_price: '' });
};

const removeVariant = (index) => productForm.variants.splice(index, 1);

// ── Add-on groups ──────────────────────────────────────────────────────
const toggleGroup = (groupId) => {
    const index = productForm.modifier_group_ids.indexOf(groupId);
    if (index === -1) productForm.modifier_group_ids.push(groupId);
    else productForm.modifier_group_ids.splice(index, 1);
};

const showGroupForm = ref(false);

const groupForm = useForm({
    name: '', min_select: 0, max_select: 1,
    modifiers: [{ name: '', price_delta: 0 }],
});

const addGroupOption = () => groupForm.modifiers.push({ name: '', price_delta: 0 });
const removeGroupOption = (index) => groupForm.modifiers.splice(index, 1);

const openGroupForm = () => {
    groupForm.reset();
    groupForm.clearErrors();
    groupForm.modifiers = [{ name: '', price_delta: 0 }];
    showGroupForm.value = true;
};

const saveGroup = () => {
    groupForm.post(route('modifierGroups.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { showGroupForm.value = false; },
    });
};

const deleteGroup = (group) => {
    router.delete(route('modifierGroups.destroy', group.id), {
        preserveScroll: true,
        preserveState: true,
    });
};

const openAddModal = () => {
    editingProduct.value = null;
    productForm.reset(); productForm.clearErrors();
    productForm.variants = [];
    productForm.modifier_group_ids = [];
    activeTab.value = 'details';
    showProductModal.value = true;
};

const openEditModal = (product) => {
    editingProduct.value = product;
    Object.assign(productForm, {
        name: product.name, sku: product.sku || '', barcode: product.barcode || '',
        category_id: product.category_id || '', cost_price: product.cost_price,
        selling_price: product.selling_price, stock_quantity: product.stock_quantity,
        low_stock_threshold: product.low_stock_threshold, is_favorite: product.is_favorite,
        description: product.description || '',
        track_stock: product.track_stock !== false,
        is_available: product.is_available !== false,
        variants: (product.variants ?? []).map(v => ({
            id: v.id, name: v.name,
            cost_price: v.cost_price ?? '',
            selling_price: v.selling_price,
        })),
        modifier_group_ids: (product.modifier_groups ?? []).map(g => g.id),
    });
    productForm.clearErrors();
    activeTab.value = 'details';
    showProductModal.value = true;
};

const saveProduct = () => {
    // Required inputs on a hidden tab cannot be focused by the browser, so
    // surface the Details tab before reporting them.
    if (productFormEl.value && !productFormEl.value.checkValidity()) {
        activeTab.value = 'details';
        nextTick(() => productFormEl.value.reportValidity());
        return;
    }

    if (editingProduct.value) {
        productForm.put(route('inventory.update', editingProduct.value.id), {
            preserveScroll: true, onSuccess: () => { showProductModal.value = false; },
        });
    } else {
        productForm.post(route('inventory.store'), {
            preserveScroll: true, onSuccess: () => { showProductModal.value = false; },
        });
    }
};

// Restock Modal
const showRestockModal = ref(false);
const restockProduct = ref(null);
const restockForm = useForm({ product_id: '', quantity: '', reason: '' });

const openRestockModal = (product) => {
    restockProduct.value = product;
    restockForm.product_id = product.id;
    restockForm.quantity = ''; restockForm.reason = '';
    restockForm.clearErrors();
    showRestockModal.value = true;
};

const submitRestock = () => {
    restockForm.post(route('inventory.restock'), {
        preserveScroll: true, onSuccess: () => { showRestockModal.value = false; },
    });
};

// Delete
const confirmingDelete = ref(null);
const deleteProduct = (product) => {
    if (confirmingDelete.value === product.id) {
        router.delete(route('inventory.destroy', product.id), { preserveScroll: true });
        confirmingDelete.value = null;
    } else {
        confirmingDelete.value = product.id;
        setTimeout(() => { confirmingDelete.value = null; }, 3000);
    }
};

// Pagination
const goToPage = (url) => {
    if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <Head :title="words.catalogue" />

        <div class="space-y-5">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-ink-1">{{ words.catalogue }}</h1>
                    <p class="mt-0.5 text-ui text-ink-3">Manage your products and stock levels</p>
                </div>
                <button
                    @click="openAddModal"
                    class="flex items-center gap-1.5 rounded-control bg-accent px-4 py-2 text-ui font-semibold text-accent-fg shadow-rest hover:bg-accent-hover" style="transition: background-color var(--t-fast);"
                >
                    <PlusIcon aria-hidden="true" class="h-4 w-4" />
                    Add {{ words.item }}
                </button>
            </div>

            <!-- Filters Bar -->
            <div class="card flex flex-wrap items-center gap-3 p-4">
                <div class="relative min-w-[200px] flex-1">
                    <MagnifyingGlassIcon aria-hidden="true" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-3" />
                    <input
                        v-model="search" type="text" placeholder="Search products…"
                        class="input-field w-full pl-10 pr-4"
                    />
                </div>
                <select v-model="categoryFilter" aria-label="Filter by category" class="select-field">
                    <option value="">All Categories</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
                <select v-model="stockFilter" aria-label="Filter by stock level" class="select-field">
                    <option value="">All Stock</option>
                    <option value="low">Low Stock</option>
                    <option value="out">Out of Stock</option>
                </select>
            </div>

            <!-- Products Table -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line">
                        <thead class="bg-surface-2/80">
                            <tr>
                                <th class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Product</th>
                                <th class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Category</th>
                                <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Cost</th>
                                <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Price</th>
                                <th class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Stock</th>
                                <th class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="product in productList" :key="product.id" class="transition-colors hover:bg-surface-2/50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <StarIcon v-if="product.is_favorite" aria-hidden="true" class="h-3.5 w-3.5 shrink-0 text-wait-mark" />
                                        <div>
                                            <p class="text-ui font-semibold text-ink-1">{{ product.name }}</p>
                                            <p class="text-meta text-ink-3">SKU: {{ product.sku || '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-ui text-ink-3">{{ product.category?.name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-ui tabular-nums text-ink-3">{{ money(product.cost_price) }}</td>
                                <td class="px-4 py-3 text-right text-ui tabular-nums font-semibold text-ink-1">{{ money(product.selling_price) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <StockBadge :stock="product.stock_quantity" :threshold="product.low_stock_threshold" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <button @click="openRestockModal(product)" :aria-label="`Restock ${product.name}`" class="flex items-center gap-1 rounded-control border border-ready-tint px-2.5 py-1.5 text-meta font-semibold text-ready-ink hover:bg-ready-tint" style="transition: background-color var(--t-fast), color var(--t-fast);">
                                            <ArrowPathIcon aria-hidden="true" class="h-3.5 w-3.5" />
                                            Restock
                                        </button>
                                        <button @click="openEditModal(product)" :aria-label="`Edit ${product.name}`" class="flex items-center gap-1 rounded-control border border-line px-2.5 py-1.5 text-meta font-semibold text-ink-2 hover:bg-surface-2" style="transition: background-color var(--t-fast), color var(--t-fast);">
                                            <PencilSquareIcon aria-hidden="true" class="h-3.5 w-3.5" />
                                            Edit
                                        </button>
                                        <button
                                            @click="deleteProduct(product)"
                                            :aria-label="confirmingDelete === product.id ? `Confirm delete ${product.name}` : `Delete ${product.name}`"
                                            :class="[
                                                'flex items-center gap-1 rounded-control border px-2.5 py-1.5 text-meta font-semibold',
                                                confirmingDelete === product.id
                                                    ? 'border-red-300 bg-stop-tint text-stop-ink'
                                                    : 'border-line text-ink-2 hover:bg-surface-2'
                                            ]"
                                        >
                                            <TrashIcon aria-hidden="true" class="h-3.5 w-3.5" />
                                            {{ confirmingDelete === product.id ? 'Confirm?' : 'Delete' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-if="productList.length === 0" class="flex flex-col items-center justify-center py-14">
                    <div class="flex h-14 w-14 items-center justify-center rounded-card bg-surface-2">
                        <CubeIcon aria-hidden="true" class="h-7 w-7 text-ink-3" />
                    </div>
                    <p class="mt-3 text-sm font-medium text-ink-3">No {{ words.items.toLowerCase() }} found</p>
                </div>

                <!-- Pagination -->
                <div v-if="pagination.last_page > 1" class="flex items-center justify-between border-t border-line px-4 py-3">
                    <p class="text-meta text-ink-3">
                        Showing {{ pagination.from }}&ndash;{{ pagination.to }} of {{ pagination.total }}
                    </p>
                    <div class="flex gap-1">
                        <button
                            v-for="link in pagination.links" :key="link.label"
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

        <!-- ADD/EDIT PRODUCT MODAL -->
        <Teleport to="body">
            <div v-if="showProductModal" class="fixed inset-0 z-50 flex items-center justify-center bg-ink-1/40 backdrop-blur-sm p-4" role="dialog" aria-modal="true" :aria-label="editingProduct ? 'Edit product' : 'Add product'">
                <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-card bg-surface-1 p-6 shadow-overlay animate-scale-in" style="overscroll-behavior: contain;">
                    <div class="flex items-center gap-2">
                        <component :is="editingProduct ? PencilSquareIcon : PlusIcon" aria-hidden="true" class="h-5 w-5 text-accent" />
                        <h3 class="text-lg font-bold text-ink-1">
                            {{ editingProduct ? `Edit ${words.item}` : `Add ${words.item}` }}
                        </h3>
                    </div>

                    <!-- Tabs -->
                    <div class="mt-4 flex gap-1 border-b border-line" role="tablist">
                        <button
                            v-for="tab in [
                                { id: 'details', label: 'Details' },
                                { id: 'sizes', label: 'Sizes' },
                                { id: 'addons', label: 'Add-ons' },
                            ]"
                            :key="tab.id"
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === tab.id"
                            :class="[
                                'relative px-3.5 py-2 text-ui font-semibold',
                                activeTab === tab.id ? 'text-accent' : 'text-ink-3 hover:text-ink-2',
                            ]"
                            style="transition: color var(--t-fast);"
                            @click="activeTab = tab.id"
                        >
                            {{ tab.label }}
                            <span
                                v-if="tab.id === 'sizes' && productForm.variants.length"
                                class="ml-1 rounded-full bg-surface-3 px-1.5 text-label tabular-nums text-ink-3"
                            >{{ productForm.variants.length }}</span>
                            <span
                                v-if="tab.id === 'addons' && productForm.modifier_group_ids.length"
                                class="ml-1 rounded-full bg-surface-3 px-1.5 text-label tabular-nums text-ink-3"
                            >{{ productForm.modifier_group_ids.length }}</span>
                            <span
                                v-if="activeTab === tab.id"
                                class="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-accent"
                            />
                        </button>
                    </div>

                    <form ref="productFormEl" @submit.prevent="saveProduct" class="mt-5 space-y-4">
                        <div v-show="activeTab === 'details'" class="space-y-4">
                        <div>
                            <label class="block text-ui font-semibold text-ink-2">Product Name <span class="text-stop-ink">*</span></label>
                            <input v-model="productForm.name" type="text" required
                                class="input-field mt-1.5 w-full"
                                placeholder="e.g. Coca-Cola Mismo 295ml" />
                            <p v-if="productForm.errors.name" class="mt-1 text-meta text-stop-ink">{{ productForm.errors.name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-ui font-semibold text-ink-2">SKU <span class="text-stop-ink">*</span></label>
                                <input v-model="productForm.sku" type="text" required
                                    class="input-field mt-1.5 w-full"
                                    placeholder="e.g. BEV-001" />
                                <p v-if="productForm.errors.sku" class="mt-1 text-meta text-stop-ink">{{ productForm.errors.sku }}</p>
                            </div>
                            <div>
                                <label class="block text-ui font-semibold text-ink-2">Barcode</label>
                                <input v-model="productForm.barcode" type="text"
                                    class="input-field mt-1.5 w-full"
                                    placeholder="Optional" />
                                <p v-if="productForm.errors.barcode" class="mt-1 text-meta text-stop-ink">{{ productForm.errors.barcode }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-ui font-semibold text-ink-2">Category <span class="text-stop-ink">*</span></label>
                            <select v-model="productForm.category_id" required
                                class="select-field mt-1.5 w-full">
                                <option value="">Select category</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <p v-if="productForm.errors.category_id" class="mt-1 text-meta text-stop-ink">{{ productForm.errors.category_id }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-ui font-semibold text-ink-2">Cost Price <span class="text-stop-ink">*</span></label>
                                <input v-model="productForm.cost_price" type="number" step="0.01" min="0" required
                                    class="input-field mt-1.5 w-full tabular-nums" />
                                <p v-if="productForm.errors.cost_price" class="mt-1 text-meta text-stop-ink">{{ productForm.errors.cost_price }}</p>
                            </div>
                            <div>
                                <label class="block text-ui font-semibold text-ink-2">Selling Price <span class="text-stop-ink">*</span></label>
                                <input v-model="productForm.selling_price" type="number" step="0.01" min="0" required
                                    class="input-field mt-1.5 w-full tabular-nums" />
                                <p v-if="productForm.errors.selling_price" class="mt-1 text-meta text-stop-ink">{{ productForm.errors.selling_price }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-ui font-semibold text-ink-2">Initial Stock</label>
                                <input v-model="productForm.stock_quantity" type="number" min="0"
                                    class="input-field mt-1.5 w-full tabular-nums" />
                            </div>
                            <div>
                                <label class="block text-ui font-semibold text-ink-2">Low Stock Threshold</label>
                                <input v-model="productForm.low_stock_threshold" type="number" min="1"
                                    class="input-field mt-1.5 w-full tabular-nums" />
                            </div>
                        </div>

                        <label class="flex items-center gap-2.5">
                            <input v-model="productForm.is_favorite" type="checkbox"
                                class="rounded border-line-strong text-accent focus:ring-accent" />
                            <span class="flex items-center gap-1 text-ui text-ink-2">
                                <StarIcon aria-hidden="true" class="h-3.5 w-3.5 text-wait-mark" />
                                Mark as favorite (prioritized in POS)
                            </span>
                        </label>

                        <label class="flex items-center gap-2.5">
                            <input v-model="productForm.track_stock" type="checkbox"
                                class="rounded border-line-strong text-accent focus:ring-accent" />
                            <span class="text-ui text-ink-2">
                                Count stock for this item
                                <span class="block text-meta text-ink-3">Turn off for made-to-order items like coffee</span>
                            </span>
                        </label>

                        <label class="flex items-center gap-2.5">
                            <input v-model="productForm.is_available" type="checkbox"
                                class="rounded border-line-strong text-accent focus:ring-accent" />
                            <span class="text-ui text-ink-2">
                                Available today
                                <span class="block text-meta text-ink-3">Untick to hide it from the POS without deactivating it</span>
                            </span>
                        </label>
                        </div>

                        <!-- SIZES -->
                        <div v-show="activeTab === 'sizes'" class="space-y-3">
                            <p class="text-meta text-ink-3">
                                Leave this empty for a single-price item. When sizes exist, the price on the
                                Details tab is what the POS shows as the &ldquo;from&rdquo; price.
                            </p>

                            <div v-if="productForm.variants.length === 0" class="rounded-control border border-dashed border-line py-8 text-center">
                                <p class="text-ui font-medium text-ink-3">No sizes yet</p>
                            </div>

                            <div v-for="(variant, index) in productForm.variants" :key="index" class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">Name</label>
                                    <input v-model="variant.name" type="text" placeholder="16oz"
                                        class="input-field mt-1 w-full" />
                                </div>
                                <div class="w-24">
                                    <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">Cost</label>
                                    <input v-model="variant.cost_price" type="number" step="0.01" min="0"
                                        class="input-field mt-1 w-full tabular-nums" />
                                </div>
                                <div class="w-24">
                                    <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">Price</label>
                                    <input v-model="variant.selling_price" type="number" step="0.01" min="0"
                                        class="input-field mt-1 w-full tabular-nums" />
                                </div>
                                <button type="button" :aria-label="`Remove size ${variant.name || index + 1}`"
                                    class="mb-2 rounded-control p-1.5 text-ink-3 hover:bg-stop-tint hover:text-stop-ink"
                                    style="transition: background-color var(--t-fast), color var(--t-fast);"
                                    @click="removeVariant(index)">
                                    <TrashIcon class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </div>

                            <p v-if="productForm.errors.variants" class="text-meta text-stop-ink">{{ productForm.errors.variants }}</p>

                            <button type="button" class="btn-secondary w-full justify-center" @click="addVariant">
                                <PlusIcon class="h-4 w-4" aria-hidden="true" />
                                Add size
                            </button>
                        </div>

                        <!-- ADD-ONS -->
                        <div v-show="activeTab === 'addons'" class="space-y-3">
                            <p class="text-meta text-ink-3">
                                Add-on groups are shared across products. Tick the ones this item offers.
                            </p>

                            <div v-if="modifierGroups.length === 0 && !showGroupForm" class="rounded-control border border-dashed border-line py-8 text-center">
                                <p class="text-ui font-medium text-ink-3">No add-on groups yet</p>
                            </div>

                            <label
                                v-for="group in modifierGroups"
                                :key="group.id"
                                class="flex cursor-pointer items-start gap-3 rounded-control border p-3"
                                :class="productForm.modifier_group_ids.includes(group.id)
                                    ? 'border-accent bg-accent-tint/60'
                                    : 'border-line hover:bg-surface-2'"
                                style="transition: background-color var(--t-fast), border-color var(--t-fast);"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 rounded border-line-strong text-accent focus:ring-accent"
                                    :checked="productForm.modifier_group_ids.includes(group.id)"
                                    @change="toggleGroup(group.id)"
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="block text-ui font-semibold text-ink-1">
                                        {{ group.name }}
                                        <span class="ml-1 text-meta font-medium text-ink-3">
                                            {{ group.min_select > 0 ? `choose ${group.min_select}` : 'optional' }}<template v-if="group.max_select > 1">, up to {{ group.max_select }}</template>
                                        </span>
                                    </span>
                                    <span class="mt-0.5 block truncate text-meta text-ink-3">
                                        {{ (group.modifiers ?? []).map(m => m.name).join(', ') }}
                                    </span>
                                </span>
                                <button type="button" :aria-label="`Delete ${group.name}`"
                                    class="rounded-control p-1 text-ink-3 hover:bg-stop-tint hover:text-stop-ink"
                                    style="transition: background-color var(--t-fast), color var(--t-fast);"
                                    @click.prevent="deleteGroup(group)">
                                    <TrashIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                </button>
                            </label>

                            <!-- New group -->
                            <div v-if="showGroupForm" class="space-y-3 rounded-control border border-accent-line bg-accent-tint/40 p-3">
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="col-span-3">
                                        <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">Group name</label>
                                        <input v-model="groupForm.name" type="text" placeholder="Milk"
                                            class="input-field mt-1 w-full" />
                                        <p v-if="groupForm.errors.name" class="mt-1 text-meta text-stop-ink">{{ groupForm.errors.name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">Least</label>
                                        <input v-model="groupForm.min_select" type="number" min="0" max="20"
                                            class="input-field mt-1 w-full tabular-nums" />
                                    </div>
                                    <div>
                                        <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">Most</label>
                                        <input v-model="groupForm.max_select" type="number" min="1" max="20"
                                            class="input-field mt-1 w-full tabular-nums" />
                                        <p v-if="groupForm.errors.max_select" class="mt-1 text-meta text-stop-ink">{{ groupForm.errors.max_select }}</p>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="block text-label font-semibold uppercase tracking-wider text-ink-3">Options</label>
                                    <div v-for="(option, index) in groupForm.modifiers" :key="index" class="flex items-center gap-2">
                                        <input v-model="option.name" type="text" placeholder="Oat milk"
                                            class="input-field flex-1" />
                                        <input v-model="option.price_delta" type="number" step="0.01" min="0" placeholder="0"
                                            class="input-field w-24 tabular-nums" />
                                        <button type="button" :aria-label="`Remove option ${index + 1}`"
                                            class="rounded-control p-1.5 text-ink-3 hover:bg-stop-tint hover:text-stop-ink"
                                            style="transition: background-color var(--t-fast), color var(--t-fast);"
                                            @click="removeGroupOption(index)">
                                            <TrashIcon class="h-4 w-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                    <p v-if="groupForm.errors.modifiers" class="text-meta text-stop-ink">{{ groupForm.errors.modifiers }}</p>
                                    <button type="button" class="text-meta font-semibold text-accent hover:text-accent" @click="addGroupOption">
                                        + Add option
                                    </button>
                                </div>

                                <div class="flex gap-2">
                                    <button type="button" class="btn-secondary flex-1 justify-center py-2" @click="showGroupForm = false">Cancel</button>
                                    <button type="button" :disabled="groupForm.processing"
                                        class="btn-primary flex-1 justify-center py-2 disabled:opacity-50" @click="saveGroup">
                                        Save group
                                    </button>
                                </div>
                            </div>

                            <button v-else type="button" class="btn-secondary w-full justify-center" @click="openGroupForm">
                                <PlusIcon class="h-4 w-4" aria-hidden="true" />
                                New add-on group
                            </button>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showProductModal = false"
                                class="flex-1 rounded-control border border-line py-2.5 text-ui font-semibold text-ink-2 hover:bg-surface-2" style="transition: background-color var(--t-fast);">
                                Cancel
                            </button>
                            <button type="submit" :disabled="productForm.processing"
                                class="btn-primary flex flex-1 items-center justify-center gap-2 py-2.5 disabled:opacity-50">
                                <svg v-if="productForm.processing" aria-hidden="true" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                {{ productForm.processing ? 'Saving…' : (editingProduct ? `Update ${words.item}` : `Add ${words.item}`) }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- RESTOCK MODAL -->
        <Teleport to="body">
            <div v-if="showRestockModal" class="fixed inset-0 z-50 flex items-center justify-center bg-ink-1/40 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-label="Restock product">
                <div class="w-full max-w-sm rounded-card bg-surface-1 p-6 shadow-overlay animate-scale-in">
                    <div class="flex items-center gap-2">
                        <ArrowPathIcon aria-hidden="true" class="h-5 w-5 text-ready-ink" />
                        <h3 class="text-lg font-bold text-ink-1">Restock</h3>
                    </div>
                    <p class="mt-1 text-ui font-medium text-ink-2">{{ restockProduct?.name }}</p>
                    <p class="text-meta text-ink-3">
                        Current stock: <span class="font-semibold text-ink-2">{{ restockProduct?.stock_quantity }}</span>
                    </p>

                    <form @submit.prevent="submitRestock" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-ui font-semibold text-ink-2">Quantity to Add <span class="text-stop-ink">*</span></label>
                            <input v-model="restockForm.quantity" type="number" min="1" required
                                class="input-field mt-1.5 w-full tabular-nums"
                                placeholder="e.g. 24" />
                            <p v-if="restockForm.errors.quantity" class="mt-1 text-meta text-stop-ink">{{ restockForm.errors.quantity }}</p>
                        </div>
                        <div>
                            <label class="block text-ui font-semibold text-ink-2">Reason (optional)</label>
                            <input v-model="restockForm.reason" type="text"
                                class="input-field mt-1.5 w-full"
                                placeholder="e.g. Weekly delivery" />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showRestockModal = false"
                                class="flex-1 rounded-control border border-line py-2.5 text-ui font-semibold text-ink-2 hover:bg-surface-2" style="transition: background-color var(--t-fast);">
                                Cancel
                            </button>
                            <button type="submit" :disabled="restockForm.processing"
                                class="flex-1 rounded-control bg-ready-solid py-2.5 text-ui font-bold text-on-solid hover:bg-ready-solid disabled:opacity-50" style="transition: background-color var(--t-fast);">
                                {{ restockForm.processing ? 'Restocking…' : 'Confirm Restock' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
