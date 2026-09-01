<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Dialog.vue';
import { useCurrency } from '@/Composables/currency';
import StockBadge from '@/Components/StockBadge.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick, onUnmounted } from 'vue';
import { debounce } from '@/Composables/helpers';
import { useVocabulary } from '@/Composables/vocabulary';
import {
    MagnifyingGlassIcon,
    PlusIcon,
    PencilSquareIcon,
    CubeIcon,
    TrashIcon,
    ArrowPathIcon,
    PhotoIcon,
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

const productTabs = [
    { id: 'details', label: 'Details' },
    { id: 'sizes', label: 'Sizes' },
    { id: 'addons', label: 'Add-ons' },
];

/*
 * Arrow keys move between tabs, Home and End jump to the ends. A `tablist`
 * announces itself as one stop with arrow-key navigation inside, so leaving
 * Tab to walk all three left the widget doing something other than what it
 * had just told the user it would do.
 */
const moveTab = (event, currentId) => {
    const offsets = { ArrowRight: 1, ArrowLeft: -1 };
    const at = productTabs.findIndex((t) => t.id === currentId);
    let next = null;

    if (event.key in offsets) {
        next = productTabs[(at + offsets[event.key] + productTabs.length) % productTabs.length];
    } else if (event.key === 'Home') {
        next = productTabs[0];
    } else if (event.key === 'End') {
        next = productTabs[productTabs.length - 1];
    }

    if (!next) return;

    event.preventDefault();
    activeTab.value = next.id;
    document.getElementById(`product-tab-${next.id}`)?.focus();
};

const productForm = useForm({
    name: '', sku: '', barcode: '', category_id: '',
    cost_price: '', selling_price: '', stock_quantity: 0,
    low_stock_threshold: 10, is_favorite: false, description: '',
    track_stock: true, is_available: true,
    variants: [], modifier_group_ids: [],
    image: null, remove_image: false,
});

// ── Photo ──────────────────────────────────────────────────────────────
// `preview` is either an object URL for a file chosen just now or the saved
// card URL for one already on the product, so the markup has one thing to
// render and does not care which.
const imagePreview = ref(null);

const revokePreview = () => {
    if (imagePreview.value?.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value);
};

const chooseImage = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    revokePreview();
    productForm.image = file;
    productForm.remove_image = false;
    imagePreview.value = URL.createObjectURL(file);
};

const clearImage = () => {
    revokePreview();
    productForm.image = null;
    // Only meaningful when editing: it tells the server to drop a photo that
    // is already saved, which sending no file at all would not.
    productForm.remove_image = true;
    imagePreview.value = null;
};

onUnmounted(revokePreview);

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
        image: null, remove_image: false,
    });
    revokePreview();
    imagePreview.value = product.image_card_url ?? null;
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
        // POST with _method, not put(). A PUT carrying multipart data never
        // reaches PHP's file parser, so the photo would vanish in silence
        // while every other field saved correctly.
        productForm
            .transform((data) => ({ ...data, _method: 'put' }))
            .post(route('inventory.update', editingProduct.value.id), {
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
                    <h1 class="text-heading font-bold tracking-tight text-ink-1">{{ words.catalogue }}</h1>
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
                    <label for="inventory-search" class="sr-only">Search {{ words.items.toLowerCase() }}</label>
                    <MagnifyingGlassIcon aria-hidden="true" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-3" />
                    <input
                        id="inventory-search"
                        v-model="search" type="search" placeholder="Search products&hellip;"
                        class="input-field w-full pl-10 pr-4"
                    />
                </div>
                <div>
                    <label for="inventory-category" class="sr-only">Filter by category</label>
                    <select id="inventory-category" v-model="categoryFilter" class="select-field">
                        <option value="">All Categories</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                </div>
                <div>
                    <label for="inventory-stock" class="sr-only">Filter by stock level</label>
                    <select id="inventory-stock" v-model="stockFilter" class="select-field">
                        <option value="">All Stock</option>
                        <option value="low">Low Stock</option>
                        <option value="out">Out of Stock</option>
                    </select>
                </div>
            </div>

            <!--
                The catalogue reads as cards on a phone and as a ledger at the
                counter. Six columns inside 360px pushed price and stock off
                the edge behind a scrollbar, and those are the two anyone opens
                this page to check.
            -->
            <div class="card overflow-hidden">
                <!-- Phone -->
                <ul class="divide-y divide-line md:hidden">
                    <li v-for="product in productList" :key="product.id" class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-2">
                                <StarIcon v-if="product.is_favorite" aria-hidden="true" class="mt-1 h-3.5 w-3.5 shrink-0 text-wait-mark" />
                                <div class="min-w-0">
                                    <p class="text-ui font-semibold text-ink-1">{{ product.name }}</p>
                                    <p class="text-meta text-ink-3">
                                        {{ product.category?.name ?? 'Uncategorised' }} &middot; SKU {{ product.sku || '&mdash;' }}
                                    </p>
                                </div>
                            </div>
                            <p class="shrink-0 text-ui font-bold tabular-nums text-ink-1">{{ money(product.selling_price) }}</p>
                        </div>

                        <div class="mt-2">
                            <StockBadge :stock="product.stock_quantity" :threshold="product.low_stock_threshold" />
                        </div>

                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <button type="button" @click="openRestockModal(product)" :aria-label="`Restock ${product.name}`"
                                class="flex flex-1 items-center justify-center gap-1 rounded-control border border-ready-tint px-2.5 py-2 text-meta font-semibold text-ready-ink hover:bg-ready-tint"
                                style="transition: background-color var(--t-fast), color var(--t-fast);">
                                <ArrowPathIcon aria-hidden="true" class="h-3.5 w-3.5" />
                                Restock
                            </button>
                            <button type="button" @click="openEditModal(product)" :aria-label="`Edit ${product.name}`"
                                class="flex flex-1 items-center justify-center gap-1 rounded-control border border-line px-2.5 py-2 text-meta font-semibold text-ink-2 hover:bg-surface-2"
                                style="transition: background-color var(--t-fast), color var(--t-fast);">
                                <PencilSquareIcon aria-hidden="true" class="h-3.5 w-3.5" />
                                Edit
                            </button>
                            <button
                                type="button"
                                @click="deleteProduct(product)"
                                :aria-label="confirmingDelete === product.id ? `Confirm delete ${product.name}` : `Delete ${product.name}`"
                                :class="[
                                    'flex flex-1 items-center justify-center gap-1 rounded-control border px-2.5 py-2 text-meta font-semibold',
                                    confirmingDelete === product.id
                                        ? 'border-stop-mark bg-stop-tint text-stop-ink'
                                        : 'border-line text-ink-2 hover:bg-surface-2'
                                ]"
                            >
                                <TrashIcon aria-hidden="true" class="h-3.5 w-3.5" />
                                {{ confirmingDelete === product.id ? 'Confirm?' : 'Delete' }}
                            </button>
                        </div>
                    </li>
                </ul>

                <!-- Counter -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-line">
                        <caption class="sr-only">{{ words.catalogue }}, with cost, price and stock</caption>
                        <thead class="bg-surface-2/80">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Product</th>
                                <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Category</th>
                                <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Cost</th>
                                <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Price</th>
                                <th scope="col" class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Stock</th>
                                <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="product in productList" :key="product.id" class="transition-colors hover:bg-surface-2/50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <StarIcon v-if="product.is_favorite" aria-hidden="true" class="h-3.5 w-3.5 shrink-0 text-wait-mark" />
                                        <div>
                                            <p class="text-ui font-semibold text-ink-1">{{ product.name }}</p>
                                            <p class="text-meta text-ink-3">SKU: {{ product.sku || '&mdash;' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-ui text-ink-3">{{ product.category?.name ?? '&mdash;' }}</td>
                                <td class="px-4 py-3 text-right text-ui tabular-nums text-ink-3">{{ money(product.cost_price) }}</td>
                                <td class="px-4 py-3 text-right text-ui tabular-nums font-semibold text-ink-1">{{ money(product.selling_price) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <StockBadge :stock="product.stock_quantity" :threshold="product.low_stock_threshold" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" @click="openRestockModal(product)" :aria-label="`Restock ${product.name}`" class="flex items-center gap-1 rounded-control border border-ready-tint px-2.5 py-1.5 text-meta font-semibold text-ready-ink hover:bg-ready-tint" style="transition: background-color var(--t-fast), color var(--t-fast);">
                                            <ArrowPathIcon aria-hidden="true" class="h-3.5 w-3.5" />
                                            Restock
                                        </button>
                                        <button type="button" @click="openEditModal(product)" :aria-label="`Edit ${product.name}`" class="flex items-center gap-1 rounded-control border border-line px-2.5 py-1.5 text-meta font-semibold text-ink-2 hover:bg-surface-2" style="transition: background-color var(--t-fast), color var(--t-fast);">
                                            <PencilSquareIcon aria-hidden="true" class="h-3.5 w-3.5" />
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteProduct(product)"
                                            :aria-label="confirmingDelete === product.id ? `Confirm delete ${product.name}` : `Delete ${product.name}`"
                                            :class="[
                                                'flex items-center gap-1 rounded-control border px-2.5 py-1.5 text-meta font-semibold',
                                                confirmingDelete === product.id
                                                    ? 'border-stop-mark bg-stop-tint text-stop-ink'
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
                    <p class="mt-3 text-ui font-medium text-ink-3">No {{ words.items.toLowerCase() }} found</p>
                </div>

                <!-- Pagination -->
                <nav v-if="pagination.last_page > 1" aria-label="Catalogue pages" class="flex items-center justify-between border-t border-line px-4 py-3">
                    <p class="text-meta text-ink-3">
                        Showing {{ pagination.from }}&ndash;{{ pagination.to }} of {{ pagination.total }}
                    </p>
                    <div class="flex gap-1">
                        <button
                            v-for="link in pagination.links" :key="link.label"
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

        <!-- ADD / EDIT PRODUCT -->
        <Dialog
            :show="showProductModal"
            :title="editingProduct ? `Edit ${words.item}` : `Add ${words.item}`"
            :icon="editingProduct ? PencilSquareIcon : PlusIcon"
            max-width="lg"
            @close="showProductModal = false"
        >
            <!--
                Real tabs, not three buttons that look like tabs. Each one now
                names the panel it controls, and only the selected tab is in
                the tab order — arrow keys move between them, which is what a
                screen reader tells the user to expect from `tablist`.
            -->
            <div class="-mt-1 flex gap-1 border-b border-line" role="tablist" aria-label="Product sections">
                <button
                    v-for="tab in productTabs"
                    :key="tab.id"
                    type="button"
                    role="tab"
                    :id="`product-tab-${tab.id}`"
                    :aria-selected="activeTab === tab.id"
                    :aria-controls="`product-panel-${tab.id}`"
                    :tabindex="activeTab === tab.id ? 0 : -1"
                    :class="[
                        'relative px-3.5 py-2 text-ui font-semibold',
                        activeTab === tab.id ? 'text-accent-ink' : 'text-ink-3 hover:text-ink-2',
                    ]"
                    style="transition: color var(--t-fast);"
                    @click="activeTab = tab.id"
                    @keydown="moveTab($event, tab.id)"
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
                        aria-hidden="true"
                    />
                </button>
            </div>

            <form id="product-form" ref="productFormEl" @submit.prevent="saveProduct" class="mt-5 space-y-4">
                <div
                    v-show="activeTab === 'details'"
                    id="product-panel-details"
                    role="tabpanel"
                    aria-labelledby="product-tab-details"
                    class="space-y-4"
                >
                    <div>
                        <label for="product-name" class="block text-ui font-semibold text-ink-2">
                            Product Name <span class="text-stop-ink" aria-hidden="true">*</span>
                        </label>
                        <input id="product-name" v-model="productForm.name" data-autofocus type="text" required aria-required="true"
                            :aria-invalid="productForm.errors.name ? 'true' : undefined"
                            :aria-describedby="productForm.errors.name ? 'product-name-error' : undefined"
                            class="input-field mt-1.5 w-full"
                            placeholder="e.g. Coca-Cola Mismo 295ml" />
                        <p v-if="productForm.errors.name" id="product-name-error" class="mt-1 text-meta text-stop-ink">{{ productForm.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="product-sku" class="block text-ui font-semibold text-ink-2">
                                SKU <span class="text-stop-ink" aria-hidden="true">*</span>
                            </label>
                            <input id="product-sku" v-model="productForm.sku" type="text" required aria-required="true"
                                :aria-invalid="productForm.errors.sku ? 'true' : undefined"
                                :aria-describedby="productForm.errors.sku ? 'product-sku-error' : undefined"
                                class="input-field mt-1.5 w-full"
                                placeholder="e.g. BEV-001" />
                            <p v-if="productForm.errors.sku" id="product-sku-error" class="mt-1 text-meta text-stop-ink">{{ productForm.errors.sku }}</p>
                        </div>
                        <div>
                            <label for="product-barcode" class="block text-ui font-semibold text-ink-2">Barcode</label>
                            <input id="product-barcode" v-model="productForm.barcode" type="text"
                                :aria-invalid="productForm.errors.barcode ? 'true' : undefined"
                                :aria-describedby="productForm.errors.barcode ? 'product-barcode-error' : undefined"
                                class="input-field mt-1.5 w-full"
                                placeholder="Optional" />
                            <p v-if="productForm.errors.barcode" id="product-barcode-error" class="mt-1 text-meta text-stop-ink">{{ productForm.errors.barcode }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="product-category" class="block text-ui font-semibold text-ink-2">
                            Category <span class="text-stop-ink" aria-hidden="true">*</span>
                        </label>
                        <select id="product-category" v-model="productForm.category_id" required aria-required="true"
                            :aria-invalid="productForm.errors.category_id ? 'true' : undefined"
                            :aria-describedby="productForm.errors.category_id ? 'product-category-error' : undefined"
                            class="select-field mt-1.5 w-full">
                            <option value="">Select category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                        <p v-if="productForm.errors.category_id" id="product-category-error" class="mt-1 text-meta text-stop-ink">{{ productForm.errors.category_id }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="product-cost" class="block text-ui font-semibold text-ink-2">
                                Cost Price <span class="text-stop-ink" aria-hidden="true">*</span>
                            </label>
                            <input id="product-cost" v-model="productForm.cost_price" type="number" step="0.01" min="0" required aria-required="true"
                                :aria-invalid="productForm.errors.cost_price ? 'true' : undefined"
                                :aria-describedby="productForm.errors.cost_price ? 'product-cost-error' : undefined"
                                class="input-field mt-1.5 w-full tabular-nums" />
                            <p v-if="productForm.errors.cost_price" id="product-cost-error" class="mt-1 text-meta text-stop-ink">{{ productForm.errors.cost_price }}</p>
                        </div>
                        <div>
                            <label for="product-price" class="block text-ui font-semibold text-ink-2">
                                Selling Price <span class="text-stop-ink" aria-hidden="true">*</span>
                            </label>
                            <input id="product-price" v-model="productForm.selling_price" type="number" step="0.01" min="0" required aria-required="true"
                                :aria-invalid="productForm.errors.selling_price ? 'true' : undefined"
                                :aria-describedby="productForm.errors.selling_price ? 'product-price-error' : undefined"
                                class="input-field mt-1.5 w-full tabular-nums" />
                            <p v-if="productForm.errors.selling_price" id="product-price-error" class="mt-1 text-meta text-stop-ink">{{ productForm.errors.selling_price }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="product-stock" class="block text-ui font-semibold text-ink-2">Initial Stock</label>
                            <input id="product-stock" v-model="productForm.stock_quantity" type="number" min="0"
                                class="input-field mt-1.5 w-full tabular-nums" />
                        </div>
                        <div>
                            <label for="product-threshold" class="block text-ui font-semibold text-ink-2">Low Stock Threshold</label>
                            <input id="product-threshold" v-model="productForm.low_stock_threshold" type="number" min="1"
                                class="input-field mt-1.5 w-full tabular-nums" />
                        </div>
                    </div>

                    <!-- Photo -->
                    <div>
                        <span class="block text-ui font-semibold text-ink-2">Photo</span>
                        <p id="product-photo-help" class="mt-0.5 text-meta text-ink-3">
                            Shown on the customer menu and the POS grid. Square works best.
                        </p>

                        <div class="mt-2 flex items-center gap-4">
                            <div
                                class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-card border border-line bg-surface-2"
                            >
                                <img
                                    v-if="imagePreview"
                                    :src="imagePreview"
                                    alt=""
                                    class="h-full w-full object-cover"
                                />
                                <PhotoIcon v-else class="h-7 w-7 text-ink-3" aria-hidden="true" />
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <label
                                    for="product-image"
                                    class="btn-secondary cursor-pointer !py-2"
                                    :class="productForm.progress ? 'pointer-events-none opacity-60' : ''"
                                >
                                    {{ imagePreview ? 'Replace' : 'Choose photo' }}
                                </label>
                                <input
                                    id="product-image"
                                    type="file"
                                    class="sr-only"
                                    accept="image/jpeg,image/png,image/webp"
                                    aria-describedby="product-photo-help"
                                    @change="chooseImage"
                                />
                                <button
                                    v-if="imagePreview"
                                    type="button"
                                    class="text-meta font-semibold text-stop-ink hover:underline"
                                    @click="clearImage"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>

                        <p v-if="productForm.errors.image" class="mt-1.5 text-meta text-stop-ink">
                            {{ productForm.errors.image }}
                        </p>
                        <p v-if="productForm.progress" role="status" class="mt-1.5 text-meta text-ink-3 tabular-nums">
                            Uploading&hellip; {{ productForm.progress.percentage }}%
                        </p>
                    </div>

                    <label class="flex items-center gap-2.5">
                        <input v-model="productForm.is_favorite" type="checkbox"
                            class="rounded border-line-strong text-accent-ink focus:ring-accent" />
                        <span class="flex items-center gap-1 text-ui text-ink-2">
                            <StarIcon aria-hidden="true" class="h-3.5 w-3.5 text-wait-mark" />
                            Mark as favorite (prioritized in POS)
                        </span>
                    </label>

                    <label class="flex items-center gap-2.5">
                        <input v-model="productForm.track_stock" type="checkbox"
                            class="rounded border-line-strong text-accent-ink focus:ring-accent" />
                        <span class="text-ui text-ink-2">
                            Count stock for this item
                            <span class="block text-meta text-ink-3">Turn off for made-to-order items like coffee</span>
                        </span>
                    </label>

                    <label class="flex items-center gap-2.5">
                        <input v-model="productForm.is_available" type="checkbox"
                            class="rounded border-line-strong text-accent-ink focus:ring-accent" />
                        <span class="text-ui text-ink-2">
                            Available today
                            <span class="block text-meta text-ink-3">Untick to hide it from the POS without deactivating it</span>
                        </span>
                    </label>
                </div>

                <!-- SIZES -->
                <div
                    v-show="activeTab === 'sizes'"
                    id="product-panel-sizes"
                    role="tabpanel"
                    aria-labelledby="product-tab-sizes"
                    class="space-y-3"
                >
                    <p class="text-meta text-ink-3">
                        Leave this empty for a single-price item. When sizes exist, the price on the
                        Details tab is what the POS shows as the &ldquo;from&rdquo; price.
                    </p>

                    <div v-if="productForm.variants.length === 0" class="rounded-control border border-dashed border-line py-8 text-center">
                        <p class="text-ui font-medium text-ink-3">No sizes yet</p>
                    </div>

                    <div v-for="(variant, index) in productForm.variants" :key="index" class="flex items-end gap-2">
                        <div class="flex-1">
                            <label :for="`variant-name-${index}`" class="block text-label font-semibold uppercase tracking-wider text-ink-3">Name</label>
                            <input :id="`variant-name-${index}`" v-model="variant.name" type="text" placeholder="16oz"
                                class="input-field mt-1 w-full" />
                        </div>
                        <div class="w-24">
                            <label :for="`variant-cost-${index}`" class="block text-label font-semibold uppercase tracking-wider text-ink-3">Cost</label>
                            <input :id="`variant-cost-${index}`" v-model="variant.cost_price" type="number" step="0.01" min="0"
                                class="input-field mt-1 w-full tabular-nums" />
                        </div>
                        <div class="w-24">
                            <label :for="`variant-price-${index}`" class="block text-label font-semibold uppercase tracking-wider text-ink-3">Price</label>
                            <input :id="`variant-price-${index}`" v-model="variant.selling_price" type="number" step="0.01" min="0"
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
                <div
                    v-show="activeTab === 'addons'"
                    id="product-panel-addons"
                    role="tabpanel"
                    aria-labelledby="product-tab-addons"
                    class="space-y-3"
                >
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
                            class="mt-0.5 rounded border-line-strong text-accent-ink focus:ring-accent"
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
                                <label for="group-name" class="block text-label font-semibold uppercase tracking-wider text-ink-3">Group name</label>
                                <input id="group-name" v-model="groupForm.name" type="text" placeholder="Milk"
                                    class="input-field mt-1 w-full" />
                                <p v-if="groupForm.errors.name" class="mt-1 text-meta text-stop-ink">{{ groupForm.errors.name }}</p>
                            </div>
                            <div>
                                <label for="group-min" class="block text-label font-semibold uppercase tracking-wider text-ink-3">Least</label>
                                <input id="group-min" v-model="groupForm.min_select" type="number" min="0" max="20"
                                    class="input-field mt-1 w-full tabular-nums" />
                            </div>
                            <div>
                                <label for="group-max" class="block text-label font-semibold uppercase tracking-wider text-ink-3">Most</label>
                                <input id="group-max" v-model="groupForm.max_select" type="number" min="1" max="20"
                                    class="input-field mt-1 w-full tabular-nums" />
                                <p v-if="groupForm.errors.max_select" class="mt-1 text-meta text-stop-ink">{{ groupForm.errors.max_select }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <span class="block text-label font-semibold uppercase tracking-wider text-ink-3">Options</span>
                            <div v-for="(option, index) in groupForm.modifiers" :key="index" class="flex items-center gap-2">
                                <label :for="`option-name-${index}`" class="sr-only">Option {{ index + 1 }} name</label>
                                <input :id="`option-name-${index}`" v-model="option.name" type="text" placeholder="Oat milk"
                                    class="input-field flex-1" />
                                <label :for="`option-price-${index}`" class="sr-only">Option {{ index + 1 }} extra charge</label>
                                <input :id="`option-price-${index}`" v-model="option.price_delta" type="number" step="0.01" min="0" placeholder="0"
                                    class="input-field w-24 tabular-nums" />
                                <button type="button" :aria-label="`Remove option ${index + 1}`"
                                    class="rounded-control p-1.5 text-ink-3 hover:bg-stop-tint hover:text-stop-ink"
                                    style="transition: background-color var(--t-fast), color var(--t-fast);"
                                    @click="removeGroupOption(index)">
                                    <TrashIcon class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </div>
                            <p v-if="groupForm.errors.modifiers" class="text-meta text-stop-ink">{{ groupForm.errors.modifiers }}</p>
                            <button type="button" class="text-meta font-semibold text-accent-ink hover:underline" @click="addGroupOption">
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
            </form>

            <template #footer>
                <div class="flex gap-3">
                    <button type="button" @click="showProductModal = false"
                        class="flex-1 rounded-control border border-line py-2.5 text-ui font-semibold text-ink-2 hover:bg-surface-2" style="transition: background-color var(--t-fast);">
                        Cancel
                    </button>
                    <button type="submit" form="product-form" :disabled="productForm.processing"
                        class="btn-primary flex flex-1 items-center justify-center gap-2 py-2.5 disabled:opacity-50">
                        <svg v-if="productForm.processing" aria-hidden="true" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        {{ productForm.processing ? 'Saving&hellip;' : (editingProduct ? `Update ${words.item}` : `Add ${words.item}`) }}
                    </button>
                </div>
            </template>
        </Dialog>

        <!-- RESTOCK -->
        <Dialog
            :show="showRestockModal"
            title="Restock"
            :icon="ArrowPathIcon"
            icon-class="text-ready-ink"
            max-width="sm"
            @close="showRestockModal = false"
        >
            <p class="text-ui font-medium text-ink-2">{{ restockProduct?.name }}</p>
            <p class="text-meta text-ink-3">
                Current stock: <span class="font-semibold text-ink-2">{{ restockProduct?.stock_quantity }}</span>
            </p>

            <form id="restock-form" @submit.prevent="submitRestock" class="mt-4 space-y-4">
                <div>
                    <label for="restock-quantity" class="block text-ui font-semibold text-ink-2">
                        Quantity to Add <span class="text-stop-ink" aria-hidden="true">*</span>
                    </label>
                    <input id="restock-quantity" v-model="restockForm.quantity" data-autofocus type="number" min="1" required aria-required="true"
                        :aria-invalid="restockForm.errors.quantity ? 'true' : undefined"
                        :aria-describedby="restockForm.errors.quantity ? 'restock-quantity-error' : undefined"
                        class="input-field mt-1.5 w-full tabular-nums"
                        placeholder="e.g. 24" />
                    <p v-if="restockForm.errors.quantity" id="restock-quantity-error" class="mt-1 text-meta text-stop-ink">{{ restockForm.errors.quantity }}</p>
                </div>
                <div>
                    <label for="restock-reason" class="block text-ui font-semibold text-ink-2">Reason (optional)</label>
                    <input id="restock-reason" v-model="restockForm.reason" type="text"
                        class="input-field mt-1.5 w-full"
                        placeholder="e.g. Weekly delivery" />
                </div>
            </form>

            <template #footer>
                <div class="flex gap-3">
                    <button type="button" @click="showRestockModal = false"
                        class="flex-1 rounded-control border border-line py-2.5 text-ui font-semibold text-ink-2 hover:bg-surface-2" style="transition: background-color var(--t-fast);">
                        Cancel
                    </button>
                    <button type="submit" form="restock-form" :disabled="restockForm.processing"
                        class="flex-1 rounded-control bg-ready-solid py-2.5 text-ui font-bold text-on-solid hover:bg-ready-mark disabled:opacity-50" style="transition: background-color var(--t-fast);">
                        {{ restockForm.processing ? 'Restocking&hellip;' : 'Confirm Restock' }}
                    </button>
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
