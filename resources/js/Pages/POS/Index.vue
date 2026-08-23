<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ProductOptionsModal from '@/Components/ProductOptionsModal.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { formatPeso } from '@/Composables/helpers';
import {
    MagnifyingGlassIcon,
    ShoppingCartIcon,
    TrashIcon,
    MinusIcon,
    PlusIcon,
    XMarkIcon,
    CreditCardIcon,
    BanknotesIcon,
    DevicePhoneMobileIcon,
    ArrowPathIcon,
    CheckCircleIcon,
    CubeIcon,
    PrinterIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon } from '@heroicons/vue/16/solid';

const props = defineProps({
    products: Object,
    categories: Array,
});

const page = usePage();

// Shared by HandleInertiaRequests: the shop this till belongs to.
const store = computed(() => page.props.store ?? {});

const receiptPrintedAt = ref('');

const printReceipt = () => window.print();

// Resolve products array from Resource collection (handles {data: [...]} or [...])
const productList = computed(() => props.products?.data ?? props.products ?? []);

// State
const search = ref('');
const selectedCategory = ref(null);
const discount = ref(0);
const paymentMethod = ref('cash');
const cashReceived = ref('');
const showCheckout = ref(false);
const showReceipt = ref(false);
const lastSale = ref(null);
const processing = ref(false);

// Cart with localStorage persistence
const CART_KEY = 'tindapos_cart';

const loadCart = () => {
    try {
        const saved = localStorage.getItem(CART_KEY);
        return saved ? JSON.parse(saved) : [];
    } catch {
        return [];
    }
};

const cart = ref(loadCart());

watch(cart, (val) => {
    try {
        localStorage.setItem(CART_KEY, JSON.stringify(val));
    } catch { /* storage full — gracefully ignore */ }
}, { deep: true });

// Filtered Products
const filteredProducts = computed(() => {
    let items = productList.value;
    if (selectedCategory.value) {
        items = items.filter(p => p.category_id === selectedCategory.value);
    }
    if (search.value) {
        const q = search.value.toLowerCase();
        items = items.filter(p =>
            p.name.toLowerCase().includes(q) ||
            (p.sku && p.sku.toLowerCase().includes(q)) ||
            (p.barcode && p.barcode.includes(q))
        );
    }
    return items;
});

// Cart helpers
//
// The same drink in two sizes is two different lines, so a line is identified
// by product + variant + the add-ons chosen, not by product alone.
const lineKey = (productId, variantId, modifierIds) =>
    [productId, variantId ?? 0, [...modifierIds].sort((a, b) => a - b).join('.')].join(':');

// A product with no sizes and no add-ons still goes straight in, which keeps
// sari-sari ringing up as fast as it was.
const hasOptions = (product) =>
    (product.variants?.length ?? 0) > 0 || (product.modifier_groups?.length ?? 0) > 0;

// Stock only limits products that are counted; a cafe does not count lattes.
const stockLimit = (product) =>
    product.track_stock === false ? Infinity : product.stock_quantity;

const isSoldOut = (product) =>
    product.is_available === false || (product.track_stock !== false && product.stock_quantity <= 0);

const optionsProduct = ref(null);

const openProduct = (product) => {
    if (isSoldOut(product)) return;

    if (hasOptions(product)) {
        optionsProduct.value = product;
        return;
    }

    addToCart(product, { variant: null, modifiers: [], unitPrice: parseFloat(product.selling_price) });
};

const addToCart = (product, { variant, modifiers, unitPrice }) => {
    const modifierIds = modifiers.map(m => m.id);
    const key = lineKey(product.id, variant?.id, modifierIds);
    const limit = stockLimit(product);

    const existing = cart.value.find(i => i.key === key);

    if (existing) {
        if (existing.quantity < limit) existing.quantity++;
        return;
    }

    cart.value.push({
        key,
        product_id: product.id,
        variant_id: variant?.id ?? null,
        modifier_ids: modifierIds,
        name: product.name,
        variant_name: variant?.name ?? null,
        modifier_names: modifiers.map(m => m.name),
        selling_price: unitPrice,
        stock: limit === Infinity ? null : limit,
        quantity: 1,
    });
};

const confirmOptions = (selection) => {
    addToCart(optionsProduct.value, selection);
    optionsProduct.value = null;
};

const decreaseFromCart = (product) => {
    // Right-click decrements the most recent line for this product.
    const index = cart.value.map(i => i.product_id).lastIndexOf(product.id);
    if (index === -1) return;
    if (cart.value[index].quantity > 1) cart.value[index].quantity--;
    else cart.value.splice(index, 1);
};

// How many of this product are in the cart, across all sizes and add-ons.
const inCartCount = (product) =>
    cart.value.filter(i => i.product_id === product.id).reduce((sum, i) => sum + i.quantity, 0);

const removeFromCart = (index) => cart.value.splice(index, 1);

const limitOf = (item) => item.stock ?? Infinity;

const updateQuantity = (item, qty) => {
    const val = parseInt(qty);
    if (isNaN(val) || val < 1) item.quantity = 1;
    else if (val > limitOf(item)) item.quantity = limitOf(item);
    else item.quantity = val;
};

const incrementQty = (item) => { if (item.quantity < limitOf(item)) item.quantity++; };
const decrementQty = (item) => { if (item.quantity > 1) item.quantity--; else removeFromCart(cart.value.indexOf(item)); };
const clearCart = () => { cart.value = []; discount.value = 0; localStorage.removeItem(CART_KEY); };

// Cart Computed
const cartItemCount = computed(() => cart.value.reduce((sum, i) => sum + i.quantity, 0));
const subtotal = computed(() => cart.value.reduce((sum, i) => sum + (i.selling_price * i.quantity), 0));
const discountAmount = computed(() => parseFloat(discount.value) || 0);
const total = computed(() => Math.max(0, subtotal.value - discountAmount.value));
const cashReceivedNum = computed(() => parseFloat(cashReceived.value) || 0);
const change = computed(() => Math.max(0, cashReceivedNum.value - total.value));

const canCheckout = computed(() => {
    if (cart.value.length === 0) return false;
    if (paymentMethod.value === 'cash' && cashReceivedNum.value < total.value) return false;
    return true;
});

// Checkout
const openCheckout = () => {
    if (cart.value.length === 0) return;
    cashReceived.value = '';
    showCheckout.value = true;
};

const processCheckout = () => {
    if (!canCheckout.value || processing.value) return;
    processing.value = true;

    router.post(route('pos.checkout'), {
        items: cart.value.map(i => ({
            product_id: i.product_id,
            quantity: i.quantity,
            variant_id: i.variant_id ?? null,
            modifier_ids: i.modifier_ids ?? [],
        })),
        discount: discountAmount.value,
        payment_method: paymentMethod.value,
        cash_received: paymentMethod.value === 'cash' ? cashReceivedNum.value : null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            const flash = page.props.flash;
            if (flash?.sale) {
                lastSale.value = flash.sale;
                receiptPrintedAt.value = new Date().toLocaleString('en-PH', {
                    year: 'numeric', month: 'short', day: 'numeric',
                    hour: '2-digit', minute: '2-digit',
                });
                showReceipt.value = true;
            }
            showCheckout.value = false;
            cart.value = []; discount.value = 0; cashReceived.value = '';
            localStorage.removeItem(CART_KEY);
            processing.value = false;
        },
        onError: () => { processing.value = false; },
    });
};

const closeReceipt = () => { showReceipt.value = false; lastSale.value = null; };

// Payment method config
const paymentMethods = [
    { value: 'cash',  label: 'Cash',  icon: BanknotesIcon },
    { value: 'gcash', label: 'GCash', icon: DevicePhoneMobileIcon },
    { value: 'maya',  label: 'Maya',  icon: DevicePhoneMobileIcon },
    { value: 'card',  label: 'Card',  icon: CreditCardIcon },
    { value: 'other', label: 'Other', icon: ArrowPathIcon },
];

watch(paymentMethod, (val) => { if (val !== 'cash') cashReceived.value = ''; });

const searchInput = ref(null);

// Keyboard shortcuts for POS efficiency
const handleKeydown = (e) => {
    // F2: Focus search
    if (e.key === 'F2') { e.preventDefault(); searchInput.value?.focus(); }
    // F9: Open checkout
    if (e.key === 'F9' && cart.value.length > 0 && !showCheckout.value) { e.preventDefault(); openCheckout(); }
    // Escape: Close modals
    if (e.key === 'Escape') {
        if (showReceipt.value) closeReceipt();
        else if (optionsProduct.value) optionsProduct.value = null;
        else if (showCheckout.value) showCheckout.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', handleKeydown));
onUnmounted(() => document.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <AppLayout>
        <Head title="POS Terminal" />

        <div class="flex h-[calc(100vh-5.5rem)] gap-4 lg:h-[calc(100vh-6.5rem)]">
            <!-- LEFT: Product Grid -->
            <div class="flex flex-1 flex-col overflow-hidden card">
                <!-- Search & Category Filter -->
                <div class="border-b border-slate-100 p-4">
                    <div class="relative">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input
                            ref="searchInput"
                            v-model="search"
                            type="text"
                            placeholder="Search products, SKU, or barcode… (F2)"
                            class="input-field pl-10 pr-4"
                        />
                    </div>

                    <!-- Category Pills -->
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <button
                            @click="selectedCategory = null"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-[12px] font-semibold transition-all',
                                !selectedCategory
                                    ? 'bg-brand-600 text-white shadow-sm'
                                    : 'bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700'
                            ]"
                        >
                            All
                        </button>
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            @click="selectedCategory = cat.id"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-[12px] font-semibold transition-all',
                                selectedCategory === cat.id
                                    ? 'bg-brand-600 text-white shadow-sm'
                                    : 'bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700'
                            ]"
                        >
                            {{ cat.name }}
                        </button>
                    </div>
                </div>

                <!-- Product Grid -->
                <div class="flex-1 overflow-y-auto p-4">
                    <div v-if="filteredProducts.length === 0" class="flex h-full flex-col items-center justify-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50">
                            <MagnifyingGlassIcon class="h-7 w-7 text-slate-300" />
                        </div>
                        <p class="mt-3 text-sm font-medium text-slate-400">No products found</p>
                    </div>

                    <div v-else class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                        <button
                            v-for="product in filteredProducts"
                            :key="product.id"
                            @click="openProduct(product)"
                            @contextmenu.prevent="decreaseFromCart(product)"
                            :disabled="isSoldOut(product)"
                            :class="[
                                'pos-grid-item relative',
                                isSoldOut(product)
                                    ? 'cursor-not-allowed opacity-50'
                                    : 'hover:border-brand-200'
                            ]"
                        >
                            <StarIcon v-if="product.is_favorite" class="absolute right-2 top-2 h-3.5 w-3.5 text-amber-400" aria-hidden="true" />

                            <p class="text-[13px] font-semibold leading-tight text-slate-800">{{ product.name }}</p>
                            <p class="mt-1.5 text-lg font-bold tabular-nums tracking-tight text-brand-600">
                                <span v-if="product.variants?.length" class="text-[11px] font-semibold text-slate-400">from </span>{{ formatPeso(product.selling_price) }}
                            </p>

                            <p
                                v-if="product.is_available === false"
                                class="mt-1.5 text-[11px] font-medium text-red-500"
                            >
                                Sold out
                            </p>
                            <p
                                v-else-if="product.track_stock === false"
                                class="mt-1.5 text-[11px] font-medium text-slate-400"
                            >
                                {{ product.modifier_groups?.length ? 'Made to order' : 'Available' }}
                            </p>
                            <p
                                v-else
                                class="mt-1.5 text-[11px] font-medium"
                                :class="product.stock_quantity <= 0 ? 'text-red-500' : product.stock_quantity <= product.low_stock_threshold ? 'text-amber-500' : 'text-slate-400'"
                            >
                                {{ product.stock_quantity <= 0 ? 'Out of stock' : `${product.stock_quantity} in stock` }}
                            </p>

                            <!-- Cart quantity indicator -->
                            <span
                                v-if="inCartCount(product) > 0"
                                class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-brand-600 text-[10px] font-bold text-white shadow-sm"
                            >
                                {{ inCartCount(product) }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Cart Panel -->
            <div class="flex w-80 flex-col overflow-hidden card xl:w-96">
                <!-- Cart Header -->
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <ShoppingCartIcon class="h-5 w-5 text-slate-400" aria-hidden="true" />
                        <h2 class="text-[15px] font-bold text-slate-900">Cart</h2>
                        <span v-if="cartItemCount > 0" class="badge badge-info">
                            {{ cartItemCount }}
                        </span>
                    </div>
                    <button
                        v-if="cart.length > 0"
                        @click="clearCart"
                        aria-label="Clear cart"
                        class="flex items-center gap-1 text-[12px] font-medium text-red-500 hover:text-red-700" style="transition: color 0.15s;"
                    >
                        <TrashIcon class="h-3.5 w-3.5" aria-hidden="true" />
                        Clear
                    </button>
                </div>

                <!-- Cart Items -->
                <div class="flex-1 overflow-y-auto" style="overscroll-behavior: contain;">
                    <div v-if="cart.length === 0" class="flex h-full flex-col items-center justify-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50">
                            <ShoppingCartIcon class="h-7 w-7 text-slate-300" />
                        </div>
                        <p class="mt-3 text-[13px] font-medium text-slate-400">Select products to add</p>
                    </div>

                    <div v-else class="divide-y divide-slate-100 px-4">
                        <div v-for="(item, index) in cart" :key="item.key ?? item.product_id" class="flex items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] font-semibold text-slate-800">
                                    {{ item.name }}<span v-if="item.variant_name" class="text-slate-500"> ({{ item.variant_name }})</span>
                                </p>
                                <p v-if="item.modifier_names?.length" class="truncate text-[11px] text-brand-600">
                                    + {{ item.modifier_names.join(', ') }}
                                </p>
                                <p class="text-[11px] text-slate-400">{{ formatPeso(item.selling_price) }} each</p>
                            </div>

                            <!-- Quantity Controls -->
                            <div class="flex items-center gap-0.5">
                                <button @click="decrementQty(item)" :aria-label="`Decrease ${item.name} quantity`" class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" style="transition: background-color 0.15s;">
                                    <MinusIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                </button>
                                <input
                                    :value="item.quantity"
                                    @change="updateQuantity(item, $event.target.value)"
                                    type="number" min="1" :max="item.stock ?? undefined"
                                    class="h-7 w-9 rounded-lg border-slate-200 text-center text-[12px] font-semibold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                />
                                <button @click="incrementQty(item)" :disabled="item.stock !== null && item.quantity >= item.stock" :aria-label="`Increase ${item.name} quantity`" class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-40" style="transition: background-color 0.15s;">
                                    <PlusIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                </button>
                            </div>

                            <!-- Line Total -->
                            <p class="w-[72px] text-right text-[13px] font-bold tabular-nums text-slate-900">
                                {{ formatPeso(item.selling_price * item.quantity) }}
                            </p>

                            <!-- Remove -->
                            <button @click="removeFromCart(index)" :aria-label="`Remove ${item.name} from cart`" class="text-slate-300 hover:text-red-500" style="transition: color 0.15s;">
                                <XMarkIcon class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cart Footer / Totals -->
                <div v-if="cart.length > 0" class="border-t border-slate-100 bg-slate-50/50 p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <label class="text-[12px] font-semibold text-slate-500">Discount</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-[12px] text-slate-400">&#8369;</span>
                            <input
                                v-model="discount" type="number" min="0" step="0.01"
                                class="h-8 w-24 rounded-lg border-slate-200 pl-6 pr-2 text-right text-[12px] font-semibold focus:border-brand-500 focus:ring-brand-500/20"
                            />
                        </div>
                    </div>

                    <div class="space-y-1 text-[13px]">
                        <div class="flex justify-between text-slate-500">
                            <span>Subtotal</span>
                            <span>{{ formatPeso(subtotal) }}</span>
                        </div>
                        <div v-if="discountAmount > 0" class="flex justify-between text-emerald-600">
                            <span>Discount</span>
                            <span>-{{ formatPeso(discountAmount) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 text-lg font-bold text-slate-900">
                            <span>Total</span>
                            <span>{{ formatPeso(total) }}</span>
                        </div>
                    </div>

                    <button
                        @click="openCheckout"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-700 active:scale-[0.98]" style="transition: background-color 0.15s, transform 0.1s;"
                    >
                        <CreditCardIcon class="h-4 w-4" aria-hidden="true" />
                        Checkout &mdash; {{ formatPeso(total) }}
                        <kbd class="ml-1 hidden rounded bg-white/20 px-1.5 py-0.5 text-[10px] font-medium sm:inline">F9</kbd>
                    </button>
                </div>
            </div>
        </div>

        <!-- PRODUCT OPTIONS -->
        <ProductOptionsModal
            :show="optionsProduct !== null"
            :product="optionsProduct"
            @close="optionsProduct = null"
            @confirm="confirmOptions"
        />

        <!-- CHECKOUT MODAL -->
        <Teleport to="body">
            <div v-if="showCheckout" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-label="Checkout">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-elevated" style="overscroll-behavior: contain;">
                    <div class="flex items-center gap-2">
                        <CreditCardIcon class="h-5 w-5 text-brand-600" aria-hidden="true" />
                        <h3 class="text-lg font-bold text-slate-900">Checkout</h3>
                    </div>

                    <!-- Order Summary -->
                    <div class="mt-4 rounded-xl bg-slate-50 p-3 space-y-1 text-[13px]">
                        <div class="flex justify-between text-slate-500">
                            <span>{{ cartItemCount }} item{{ cartItemCount !== 1 ? 's' : '' }}</span>
                            <span>{{ formatPeso(subtotal) }}</span>
                        </div>
                        <div v-if="discountAmount > 0" class="flex justify-between text-emerald-600">
                            <span>Discount</span>
                            <span>-{{ formatPeso(discountAmount) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-1.5 text-lg font-bold tabular-nums text-slate-900">
                            <span>Total</span>
                            <span>{{ formatPeso(total) }}</span>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="mt-5">
                        <label class="text-[13px] font-semibold text-slate-700">Payment Method</label>
                        <div class="mt-2 grid grid-cols-5 gap-1.5">
                            <button
                                v-for="pm in paymentMethods"
                                :key="pm.value"
                                @click="paymentMethod = pm.value"
                                :class="[
                                    'flex flex-col items-center gap-1 rounded-xl border py-2.5 text-[11px] font-semibold transition-all',
                                    paymentMethod === pm.value
                                        ? 'border-brand-500 bg-brand-50 text-brand-700 shadow-sm'
                                        : 'border-slate-200 text-slate-500 hover:border-slate-300 hover:bg-slate-50'
                                ]"
                            >
                                <component :is="pm.icon" class="h-4 w-4" />
                                {{ pm.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Cash Received -->
                    <div v-if="paymentMethod === 'cash'" class="mt-5">
                        <label class="text-[13px] font-semibold text-slate-700">Cash Received</label>
                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">&#8369;</span>
                            <input
                                v-model="cashReceived"
                                type="number" min="0" step="0.01"
                                :placeholder="`Min: ${formatPeso(total)}`"
                                class="w-full rounded-xl border-slate-200 py-2.5 pl-8 pr-4 text-right text-lg font-bold text-slate-800 focus:border-brand-500 focus:ring-brand-500/20"
                            />
                        </div>

                        <!-- Quick cash buttons -->
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="amount in [20, 50, 100, 200, 500, 1000]"
                                :key="amount"
                                @click="cashReceived = amount"
                                :class="[
                                    'rounded-lg border px-3 py-1.5 text-[12px] font-semibold transition-all',
                                    cashReceivedNum === amount
                                        ? 'border-brand-500 bg-brand-50 text-brand-700'
                                        : 'border-slate-200 text-slate-500 hover:bg-slate-50'
                                ]"
                            >
                                &#8369;{{ amount }}
                            </button>
                        </div>

                        <!-- Change display -->
                        <div v-if="cashReceivedNum >= total" class="mt-3 rounded-xl bg-emerald-50 p-3 text-center">
                            <p class="text-[12px] font-medium text-emerald-600">Change</p>
                            <p class="text-2xl font-bold tabular-nums text-emerald-700">{{ formatPeso(change) }}</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex gap-3">
                        <button
                            @click="showCheckout = false"
                            class="flex-1 rounded-xl border border-slate-200 py-2.5 text-[13px] font-semibold text-slate-600 transition-all hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            @click="processCheckout"
                            :disabled="!canCheckout || processing"
                            class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand-600 py-2.5 text-[13px] font-bold text-white transition-all hover:bg-brand-700 disabled:opacity-50"
                        >
                            <svg v-if="processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ processing ? 'Processing...' : `Pay ${formatPeso(total)}` }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- RECEIPT MODAL -->
        <Teleport to="body">
            <div v-if="showReceipt && lastSale" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-label="Sale complete">
                <div id="receipt" class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-elevated animate-scale-in">
                    <div class="text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 print:hidden">
                            <CheckCircleIcon class="h-7 w-7 text-emerald-600" aria-hidden="true" />
                        </div>
                        <h3 class="mt-3 text-lg font-bold text-slate-900 print:mt-0 print:text-xl">
                            {{ store.name || 'Sale Complete' }}
                        </h3>
                        <p v-if="store.address" class="text-[11px] leading-snug text-slate-500">{{ store.address }}</p>
                        <p v-if="store.phone" class="text-[11px] text-slate-500">{{ store.phone }}</p>

                        <p class="mt-2 font-mono text-[13px] text-slate-500">{{ lastSale.receipt_number }}</p>
                        <p class="text-[11px] text-slate-400">
                            {{ receiptPrintedAt }}<span v-if="page.props.auth?.user"> &middot; {{ page.props.auth.user.name }}</span>
                        </p>
                    </div>

                    <!-- What was actually sold -->
                    <ul v-if="lastSale.items?.length" class="mt-4 space-y-1.5 border-t border-slate-100 pt-3">
                        <li v-for="item in lastSale.items" :key="item.id" class="flex items-start gap-2 text-[12px]">
                            <span class="font-semibold tabular-nums text-slate-400">{{ item.quantity }}&times;</span>
                            <span class="min-w-0 flex-1 text-slate-700">
                                {{ item.product_name }}<span v-if="item.variant_name" class="text-slate-500"> ({{ item.variant_name }})</span>
                                <span v-if="item.modifiers?.length" class="block text-[11px] text-slate-400">
                                    + {{ item.modifiers.map(m => m.name).join(', ') }}
                                </span>
                            </span>
                            <span class="tabular-nums text-slate-600">{{ formatPeso(item.line_total) }}</span>
                        </li>
                    </ul>

                    <div class="mt-5 space-y-2 rounded-xl bg-slate-50 p-4 text-[13px]">
                        <div class="flex justify-between text-slate-500">
                            <span>Items</span>
                            <span class="font-medium text-slate-700">{{ lastSale.item_count }}</span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Subtotal</span>
                            <span class="text-slate-700">{{ formatPeso(lastSale.subtotal) }}</span>
                        </div>
                        <div v-if="parseFloat(lastSale.discount) > 0" class="flex justify-between text-emerald-600">
                            <span>Discount</span>
                            <span>-{{ formatPeso(lastSale.discount) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 text-lg font-bold text-slate-900">
                            <span>Total</span>
                            <span>{{ formatPeso(lastSale.total) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Payment</span>
                            <span class="font-medium text-slate-700">{{ lastSale.payment_method?.toUpperCase() }}</span>
                        </div>
                        <div v-if="lastSale.cash_received" class="flex justify-between text-slate-500">
                            <span>Cash Received</span>
                            <span class="text-slate-700">{{ formatPeso(lastSale.cash_received) }}</span>
                        </div>
                        <div v-if="lastSale.change_amount" class="flex justify-between font-bold text-emerald-700">
                            <span>Change</span>
                            <span>{{ formatPeso(lastSale.change_amount) }}</span>
                        </div>
                    </div>

                    <p v-if="store.receipt_footer" class="mt-4 text-center text-[12px] italic text-slate-500">
                        {{ store.receipt_footer }}
                    </p>

                    <div class="mt-5 flex gap-2 print:hidden">
                        <button
                            @click="printReceipt"
                            class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                            style="transition: background-color 0.15s;"
                        >
                            <PrinterIcon class="h-4 w-4" aria-hidden="true" />
                            Print
                        </button>
                        <button
                            @click="closeReceipt"
                            class="flex flex-[2] items-center justify-center gap-2 rounded-xl bg-brand-600 py-3 text-sm font-bold text-white transition-all hover:bg-brand-700"
                        >
                            <PlusIcon class="h-4 w-4" />
                            New Transaction
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style>
/* Printing from the till should produce the receipt, nothing else. */
@media print {
    body * { visibility: hidden; }
    #receipt, #receipt * { visibility: visible; }
    #receipt {
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        max-width: none;
        box-shadow: none;
        padding: 0;
    }
    @page { margin: 8mm; }
}
</style>
