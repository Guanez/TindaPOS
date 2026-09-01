<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/Composables/currency';
import Dialog from '@/Components/Dialog.vue';
import ProductOptionsModal from '@/Components/ProductOptionsModal.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

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

const { money } = useCurrency();


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

// Whether the cart sheet is up. Only consulted below `lg`, where the cart is
// a sheet rather than a column — above it the panel is always on screen and
// this is ignored.
const cartOpen = ref(false);

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

/*
 * What the customer is actually likely to hand over.
 *
 * The old six buttons were fixed notes — 20, 50, 100, 200, 500, 1000 — which
 * on a ₱237 total are all either useless or wrong, so the cashier typed the
 * amount every time. These are derived from the total instead: the exact
 * money, the next round 50 and 100, and the notes above that. For ₱237 it
 * offers 237, 250, 300, 500, 1000, which is the real set.
 */
const PESO_NOTES = [20, 50, 100, 200, 500, 1000];

const quickCash = computed(() => {
    const due = total.value;
    if (due <= 0) return [];

    const amounts = new Set([
        due,
        Math.ceil(due / 50) * 50,
        Math.ceil(due / 100) * 100,
        Math.ceil(due / 500) * 500,
        ...PESO_NOTES.filter((note) => note >= due),
    ]);

    return [...amounts]
        .filter((amount) => amount >= due)
        .sort((a, b) => a - b)
        .slice(0, 5);
});

// Checkout
const openCheckout = () => {
    if (cart.value.length === 0) return;
    cashReceived.value = '';
    cartOpen.value = false;
    showCheckout.value = true;

    // The cash field carries `data-autofocus`, so Dialog puts focus there on
    // open and returns it to the Charge button on close: the common path stays
    // F9, type, Enter, with no trip to the mouse in the middle of it.
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
    // Escape only has the cart sheet left to close. Every dialog on this page
    // now handles its own, topmost first, from the shared stack in Dialog.
    if (e.key === 'Escape' && cartOpen.value && !showCheckout.value && !showReceipt.value && !optionsProduct.value) {
        cartOpen.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', handleKeydown));
onUnmounted(() => document.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <AppLayout>
        <Head title="POS Terminal" />

        <!--
            Two panes side by side at the counter; one pane and a sheet on a
            tablet held in one hand. The cart markup is the same in both — only
            its box changes — because a second copy of it would be a second
            place for the quantity controls to drift.
        -->
        <div class="flex h-[calc(100vh-5.5rem)] gap-4 lg:h-[calc(100vh-6.5rem)]">
            <!-- LEFT: Product Grid -->
            <div class="flex flex-1 flex-col overflow-hidden card">
                <!-- Search & Category Filter -->
                <div class="border-b border-line p-4">
                    <div class="relative">
                        <label for="pos-search" class="sr-only">Search products, SKU, or barcode</label>
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-3" aria-hidden="true" />
                        <input
                            id="pos-search"
                            ref="searchInput"
                            v-model="search"
                            type="search"
                            placeholder="Search products, SKU, or barcode… (F2)"
                            class="input-field pl-10 pr-4"
                        />
                    </div>

                    <!-- Category Pills -->
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <button
                            @click="selectedCategory = null"
                            :class="[
                                'rounded-control px-3 py-1.5 text-meta font-semibold transition-all',
                                !selectedCategory
                                    ? 'bg-accent text-accent-fg shadow-rest'
                                    : 'bg-surface-3 text-ink-3 hover:bg-line-strong hover:text-ink-2'
                            ]"
                        >
                            All
                        </button>
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            @click="selectedCategory = cat.id"
                            :class="[
                                'rounded-control px-3 py-1.5 text-meta font-semibold transition-all',
                                selectedCategory === cat.id
                                    ? 'bg-accent text-accent-fg shadow-rest'
                                    : 'bg-surface-3 text-ink-3 hover:bg-line-strong hover:text-ink-2'
                            ]"
                        >
                            {{ cat.name }}
                        </button>
                    </div>
                </div>

                <!-- Product Grid -->
                <div class="flex-1 overflow-y-auto p-4">
                    <div v-if="filteredProducts.length === 0" class="flex h-full flex-col items-center justify-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-card bg-surface-2">
                            <MagnifyingGlassIcon class="h-7 w-7 text-ink-3" />
                        </div>
                        <p class="mt-3 text-ui font-medium text-ink-3">No products found</p>
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
                                    : 'hover:border-accent-line'
                            ]"
                        >
                            <StarIcon v-if="product.is_favorite" class="absolute right-2 top-2 z-10 h-3.5 w-3.5 text-wait-mark" aria-hidden="true" />

                            <!--
                                Only rendered when there is a photo. An empty
                                grey placeholder on every tile would cost the
                                cashier a third of the screen to say nothing.
                            -->
                            <img
                                v-if="product.image_thumb_url"
                                :src="product.image_thumb_url"
                                alt=""
                                loading="lazy"
                                class="mb-2 aspect-square w-full rounded-control bg-surface-2 object-cover"
                            />

                            <p class="text-ui font-semibold leading-tight text-ink-1">{{ product.name }}</p>
                            <p class="mt-1.5 text-title font-bold tabular-nums tracking-tight text-ink-1">
                                <span v-if="product.variants?.length" class="text-meta font-semibold text-ink-3">from </span>{{ money(product.selling_price) }}
                            </p>

                            <p
                                v-if="product.is_available === false"
                                class="mt-1.5 text-meta font-medium text-stop-ink"
                            >
                                Sold out
                            </p>
                            <p
                                v-else-if="product.track_stock === false"
                                class="mt-1.5 text-meta font-medium text-ink-3"
                            >
                                {{ product.modifier_groups?.length ? 'Made to order' : 'Available' }}
                            </p>
                            <p
                                v-else
                                class="mt-1.5 text-meta font-medium"
                                :class="product.stock_quantity <= 0 ? 'text-stop-ink' : product.stock_quantity <= product.low_stock_threshold ? 'text-wait-ink' : 'text-ink-3'"
                            >
                                {{ product.stock_quantity <= 0 ? 'Out of stock' : `${product.stock_quantity} in stock` }}
                            </p>

                            <!-- Cart quantity indicator -->
                            <span
                                v-if="inCartCount(product) > 0"
                                class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-accent text-label font-bold text-accent-fg shadow-rest"
                            >
                                {{ inCartCount(product) }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Scrim, phone and tablet only -->
            <div
                v-if="cartOpen"
                class="fixed inset-0 z-30 bg-scrim/50 backdrop-blur-sm lg:hidden"
                aria-hidden="true"
                @click="cartOpen = false"
            />

            <!-- RIGHT: Cart Panel — a sheet below lg, a column above it -->
            <div
                class="z-40 flex-col overflow-hidden card
                       fixed inset-x-0 bottom-0 max-h-[85vh] rounded-b-none rounded-t-sheet
                       lg:static lg:z-auto lg:max-h-none lg:w-80 lg:rounded-card xl:w-96"
                :class="cartOpen ? 'flex animate-sheet-up' : 'hidden lg:flex'"
            >
                <!-- Sheet grabber, phone and tablet only -->
                <button
                    class="flex w-full justify-center py-2 lg:hidden"
                    aria-label="Close cart"
                    @click="cartOpen = false"
                >
                    <span class="h-1 w-9 rounded-full bg-line-strong" />
                </button>

                <!-- Cart Header -->
                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                    <div class="flex items-center gap-2">
                        <ShoppingCartIcon class="h-5 w-5 text-ink-3" aria-hidden="true" />
                        <h2 class="text-body font-bold text-ink-1">Cart</h2>
                        <span v-if="cartItemCount > 0" class="badge badge-info">
                            {{ cartItemCount }}
                        </span>
                    </div>
                    <button
                        v-if="cart.length > 0"
                        @click="clearCart"
                        aria-label="Clear cart"
                        class="flex items-center gap-1 text-meta font-medium text-stop-ink hover:text-stop-mark" style="transition: color var(--t-fast);"
                    >
                        <TrashIcon class="h-3.5 w-3.5" aria-hidden="true" />
                        Clear
                    </button>
                </div>

                <!-- Cart Items -->
                <div class="flex-1 overflow-y-auto" style="overscroll-behavior: contain;">
                    <div v-if="cart.length === 0" class="flex h-full flex-col items-center justify-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-card bg-surface-2">
                            <ShoppingCartIcon class="h-7 w-7 text-ink-3" />
                        </div>
                        <p class="mt-3 text-ui font-medium text-ink-3">Select products to add</p>
                    </div>

                    <div v-else class="divide-y divide-line px-4">
                        <div v-for="(item, index) in cart" :key="item.key ?? item.product_id" class="flex items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-ui font-semibold text-ink-1">
                                    {{ item.name }}<span v-if="item.variant_name" class="text-ink-3"> ({{ item.variant_name }})</span>
                                </p>
                                <p v-if="item.modifier_names?.length" class="truncate text-meta text-accent-ink">
                                    + {{ item.modifier_names.join(', ') }}
                                </p>
                                <p class="text-meta text-ink-3">{{ money(item.selling_price) }} each</p>
                            </div>

                            <!-- Quantity Controls -->
                            <div class="flex items-center gap-0.5">
                                <button @click="decrementQty(item)" :aria-label="`Decrease ${item.name} quantity`" class="flex h-7 w-7 items-center justify-center rounded-control border border-line text-ink-3 hover:bg-surface-2" style="transition: background-color var(--t-fast);">
                                    <MinusIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                </button>
                                <input
                                    :value="item.quantity"
                                    @change="updateQuantity(item, $event.target.value)"
                                    type="number" min="1" :max="item.stock ?? undefined"
                                    :aria-label="`${item.name} quantity`"
                                    class="h-7 w-9 rounded-control border-line text-center text-meta font-semibold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                />
                                <button @click="incrementQty(item)" :disabled="item.stock !== null && item.quantity >= item.stock" :aria-label="`Increase ${item.name} quantity`" class="flex h-7 w-7 items-center justify-center rounded-control border border-line text-ink-3 hover:bg-surface-2 disabled:opacity-40" style="transition: background-color var(--t-fast);">
                                    <PlusIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                </button>
                            </div>

                            <!-- Line Total -->
                            <p class="w-[72px] text-right text-ui font-bold tabular-nums text-ink-1">
                                {{ money(item.selling_price * item.quantity) }}
                            </p>

                            <!-- Remove -->
                            <button @click="removeFromCart(index)" :aria-label="`Remove ${item.name} from cart`" class="text-ink-3 hover:text-stop-ink" style="transition: color var(--t-fast);">
                                <XMarkIcon class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cart Footer / Totals -->
                <div v-if="cart.length > 0" class="border-t border-line bg-surface-2/50 p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <label for="pos-discount" class="text-meta font-semibold text-ink-3">Discount</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-meta text-ink-3" aria-hidden="true">&#8369;</span>
                            <input
                                id="pos-discount"
                                v-model="discount" type="number" min="0" step="0.01"
                                class="h-8 w-24 rounded-control border-line pl-6 pr-2 text-right text-meta font-semibold focus:border-accent focus:ring-accent/20"
                            />
                        </div>
                    </div>

                    <div class="space-y-1 text-ui">
                        <div class="flex justify-between text-ink-3">
                            <span>Subtotal</span>
                            <span>{{ money(subtotal) }}</span>
                        </div>
                        <div v-if="discountAmount > 0" class="flex justify-between text-ready-ink">
                            <span>Discount</span>
                            <span>-{{ money(discountAmount) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-line pt-2 text-title font-bold text-ink-1">
                            <span>Total</span>
                            <span>{{ money(total) }}</span>
                        </div>
                    </div>

                    <button
                        @click="openCheckout"
                        class="flex w-full items-center justify-center gap-2 rounded-control bg-accent py-3 text-ui font-bold text-accent-fg shadow-rest hover:bg-accent-hover active:scale-[0.98]" style="transition: background-color var(--t-fast), transform var(--t-fast);"
                    >
                        <CreditCardIcon class="h-4 w-4" aria-hidden="true" />
                        Checkout &mdash; {{ money(total) }}
                        <kbd class="ml-1 hidden rounded bg-surface-1/20 px-1.5 py-0.5 text-label font-medium sm:inline">F9</kbd>
                    </button>
                </div>
            </div>
        </div>

        <!--
            The cart's stand-in while it is a sheet. Mirrors the customer
            menu's basket bar deliberately: it is the same gesture on the same
            size of screen, and staff who have used the customer side already
            know what it does.
        -->
        <div
            v-if="cart.length > 0 && !cartOpen"
            class="fixed inset-x-0 bottom-0 z-30 p-3 lg:hidden"
        >
            <button
                class="flex w-full items-center gap-3 rounded-card bg-accent px-4 py-3.5 text-accent-fg shadow-overlay active:scale-[0.99]"
                style="transition: transform var(--t-fast);"
                @click="cartOpen = true"
            >
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-surface-1/20 text-ui font-bold tabular-nums">
                    {{ cartItemCount }}
                </span>
                <span class="flex-1 text-left text-body font-bold">View cart</span>
                <span class="text-body font-bold tabular-nums">{{ money(total) }}</span>
            </button>
        </div>

        <!-- PRODUCT OPTIONS -->
        <ProductOptionsModal
            :show="optionsProduct !== null"
            :product="optionsProduct"
            @close="optionsProduct = null"
            @confirm="confirmOptions"
        />

        <!-- CHECKOUT -->
        <Dialog
            :show="showCheckout"
            title="Checkout"
            :icon="CreditCardIcon"
            max-width="md"
            @close="showCheckout = false"
        >
            <!-- Order Summary -->
            <div class="rounded-control bg-surface-2 p-3 space-y-1 text-ui">
                <div class="flex justify-between text-ink-3">
                    <span>{{ cartItemCount }} item{{ cartItemCount !== 1 ? 's' : '' }}</span>
                    <span>{{ money(subtotal) }}</span>
                </div>
                <div v-if="discountAmount > 0" class="flex justify-between text-ready-ink">
                    <span>Discount</span>
                    <span>-{{ money(discountAmount) }}</span>
                </div>
                <div class="flex justify-between border-t border-line pt-1.5 text-title font-bold tabular-nums text-ink-1">
                    <span>Total</span>
                    <span>{{ money(total) }}</span>
                </div>
            </div>

            <!--
                A group, not five loose buttons. Each one announces as a bare
                word otherwise, and "Maya" on its own does not say what is
                being asked. `aria-pressed` carries the selection, which the
                tinted background alone never did.
            -->
            <div class="mt-5" role="group" aria-labelledby="checkout-method-label">
                <span id="checkout-method-label" class="text-ui font-semibold text-ink-2">Payment Method</span>
                <div class="mt-2 grid grid-cols-5 gap-1.5">
                    <button
                        v-for="pm in paymentMethods"
                        :key="pm.value"
                        type="button"
                        :aria-pressed="paymentMethod === pm.value"
                        @click="paymentMethod = pm.value"
                        :class="[
                            'flex flex-col items-center gap-1 rounded-control border py-2.5 text-meta font-semibold transition-all',
                            paymentMethod === pm.value
                                ? 'border-accent bg-accent-tint text-accent-ink shadow-rest'
                                : 'border-line text-ink-3 hover:border-line-strong hover:bg-surface-2'
                        ]"
                    >
                        <component :is="pm.icon" class="h-4 w-4" aria-hidden="true" />
                        {{ pm.label }}
                    </button>
                </div>
            </div>

            <!-- Cash Received -->
            <div v-if="paymentMethod === 'cash'" class="mt-5">
                <label for="checkout-cash" class="text-ui font-semibold text-ink-2">Cash Received</label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-ink-3" aria-hidden="true">&#8369;</span>
                    <input
                        id="checkout-cash"
                        v-model="cashReceived"
                        data-autofocus
                        type="number" min="0" step="0.01"
                        :placeholder="`Min: ${money(total)}`"
                        class="w-full rounded-control border-line py-2.5 pl-8 pr-4 text-right text-title font-bold text-ink-1 focus:border-accent focus:ring-accent/20"
                        @keyup.enter="processCheckout"
                    />
                </div>

                <!-- Quick cash, derived from the total rather than fixed notes -->
                <div class="mt-2 flex flex-wrap gap-1.5" role="group" aria-label="Quick cash amounts">
                    <button
                        v-for="(amount, index) in quickCash"
                        :key="amount"
                        type="button"
                        :aria-pressed="cashReceivedNum === amount"
                        @click="cashReceived = amount"
                        :class="[
                            'rounded-control border px-3 py-1.5 text-meta font-semibold transition-all',
                            cashReceivedNum === amount
                                ? 'border-accent bg-accent-tint text-accent-ink'
                                : 'border-line text-ink-3 hover:bg-surface-2'
                        ]"
                    >
                        {{ index === 0 ? 'Exact' : `&#8369;${amount.toLocaleString('en-PH')}` }}
                    </button>
                </div>

                <!--
                    Announced, not merely shown. The cashier is looking at the
                    drawer and the customer, not at the screen, and the change
                    due is the one number that has to arrive.
                -->
                <div
                    v-if="cashReceivedNum >= total"
                    role="status"
                    aria-live="polite"
                    class="mt-3 rounded-control bg-ready-tint p-3 text-center"
                >
                    <p class="text-meta font-medium text-ready-ink">Change</p>
                    <p class="text-figure font-bold tabular-nums text-ready-ink">{{ money(change) }}</p>
                </div>
            </div>

            <template #footer>
                <div class="flex gap-3">
                    <button
                        type="button"
                        @click="showCheckout = false"
                        class="flex-1 rounded-control border border-line py-2.5 text-ui font-semibold text-ink-2 transition-all hover:bg-surface-2"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="processCheckout"
                        :disabled="!canCheckout || processing"
                        class="flex flex-1 items-center justify-center gap-2 rounded-control bg-accent py-2.5 text-ui font-bold text-accent-fg transition-all hover:bg-accent-hover disabled:opacity-50"
                    >
                        <svg v-if="processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ processing ? 'Processing...' : `Pay ${money(total)}` }}
                    </button>
                </div>
            </template>
        </Dialog>

        <!--
            RECEIPT

            Headerless because the receipt opens with the shop name, and a
            dialog title stacked above that would be a receipt from a dialog.
            The accessible name still comes from `title`.
        -->
        <Dialog
            :show="showReceipt && !!lastSale"
            title="Sale complete"
            max-width="sm"
            headerless
            @close="closeReceipt"
        >
            <div v-if="lastSale" id="receipt">
                <div class="text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-ready-tint print:hidden">
                        <CheckCircleIcon class="h-7 w-7 text-ready-ink" aria-hidden="true" />
                    </div>
                    <h2 class="mt-3 text-title font-bold text-ink-1 print:mt-0">
                        {{ store.name || 'Sale Complete' }}
                    </h2>
                    <p v-if="store.address" class="text-meta leading-snug text-ink-3">{{ store.address }}</p>
                    <p v-if="store.phone" class="text-meta text-ink-3">{{ store.phone }}</p>

                    <p class="mt-2 font-mono text-ui text-ink-3">{{ lastSale.receipt_number }}</p>
                    <p class="text-meta text-ink-3">
                        {{ receiptPrintedAt }}<span v-if="page.props.auth?.user"> &middot; {{ page.props.auth.user.name }}</span>
                    </p>
                </div>

                <!-- What was actually sold -->
                <ul v-if="lastSale.items?.length" class="mt-4 space-y-1.5 border-t border-line pt-3">
                    <li v-for="item in lastSale.items" :key="item.id" class="flex items-start gap-2 text-meta">
                        <span class="font-semibold tabular-nums text-ink-3">{{ item.quantity }}&times;</span>
                        <span class="min-w-0 flex-1 text-ink-2">
                            {{ item.product_name }}<span v-if="item.variant_name" class="text-ink-3"> ({{ item.variant_name }})</span>
                            <span v-if="item.modifiers?.length" class="block text-meta text-ink-3">
                                + {{ item.modifiers.map(m => m.name).join(', ') }}
                            </span>
                        </span>
                        <span class="tabular-nums text-ink-2">{{ money(item.line_total) }}</span>
                    </li>
                </ul>

                <div class="mt-5 space-y-2 rounded-control bg-surface-2 p-4 text-ui">
                    <div class="flex justify-between text-ink-3">
                        <span>Items</span>
                        <span class="font-medium text-ink-2">{{ lastSale.item_count }}</span>
                    </div>
                    <div class="flex justify-between text-ink-3">
                        <span>Subtotal</span>
                        <span class="text-ink-2">{{ money(lastSale.subtotal) }}</span>
                    </div>
                    <div v-if="parseFloat(lastSale.discount) > 0" class="flex justify-between text-ready-ink">
                        <span>Discount</span>
                        <span>-{{ money(lastSale.discount) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-line pt-2 text-title font-bold text-ink-1">
                        <span>Total</span>
                        <span>{{ money(lastSale.total) }}</span>
                    </div>
                    <div class="flex justify-between text-ink-3">
                        <span>Payment</span>
                        <span class="font-medium text-ink-2">{{ lastSale.payment_method?.toUpperCase() }}</span>
                    </div>
                    <div v-if="lastSale.cash_received" class="flex justify-between text-ink-3">
                        <span>Cash Received</span>
                        <span class="text-ink-2">{{ money(lastSale.cash_received) }}</span>
                    </div>
                    <div v-if="lastSale.change_amount" class="flex justify-between font-bold text-ready-ink">
                        <span>Change</span>
                        <span>{{ money(lastSale.change_amount) }}</span>
                    </div>
                </div>

                <p v-if="store.receipt_footer" class="mt-4 text-center text-meta italic text-ink-3">
                    {{ store.receipt_footer }}
                </p>
            </div>

            <template #footer>
                <div class="flex gap-2 print:hidden">
                    <button
                        type="button"
                        @click="printReceipt"
                        class="flex flex-1 items-center justify-center gap-2 rounded-control border border-line py-3 text-ui font-semibold text-ink-2 hover:bg-surface-2"
                        style="transition: background-color var(--t-fast);"
                    >
                        <PrinterIcon class="h-4 w-4" aria-hidden="true" />
                        Print
                    </button>
                    <button
                        type="button"
                        data-autofocus
                        @click="closeReceipt"
                        class="flex flex-[2] items-center justify-center gap-2 rounded-control bg-accent py-3 text-ui font-bold text-accent-fg transition-all hover:bg-accent-hover"
                    >
                        <PlusIcon class="h-4 w-4" aria-hidden="true" />
                        New Transaction
                    </button>
                </div>
            </template>
        </Dialog>
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
