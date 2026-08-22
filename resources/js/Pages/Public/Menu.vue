<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { formatPeso } from '@/Composables/helpers';
import ProductOptionsModal from '@/Components/ProductOptionsModal.vue';
import {
    ShoppingBagIcon,
    PlusIcon,
    MinusIcon,
    XMarkIcon,
    ArrowRightIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    store: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    products: { type: Object, default: () => ({ data: [] }) },
});

const menu = computed(() => props.products?.data ?? []);

const selectedCategory = ref(null);

const visible = computed(() =>
    selectedCategory.value === null
        ? menu.value
        : menu.value.filter((p) => p.category_id === selectedCategory.value),
);

// ── An order already in progress on this phone ──────────────────────────
const TOKEN_KEY = 'tindapos_order_token';
const activeToken = ref(null);

onMounted(() => {
    try {
        activeToken.value = localStorage.getItem(TOKEN_KEY);
    } catch { /* private browsing */ }
});

const openActiveOrder = () => router.visit(`/o/${activeToken.value}`);

// ── Basket ──────────────────────────────────────────────────────────────
const basket = ref([]);
const chooser = ref(null);

const lineKey = (productId, variantId, modifierIds) =>
    [productId, variantId ?? 0, [...modifierIds].sort((a, b) => a - b).join('.')].join(':');

const hasOptions = (product) =>
    (product.variants?.length ?? 0) > 0 || (product.modifier_groups?.length ?? 0) > 0;

// The chooser expects a product priced by `selling_price`; the public menu
// calls that `price_from` so nothing implies a single fixed price.
const forChooser = (product) => ({ ...product, selling_price: product.price_from });

const choose = (product) => {
    if (hasOptions(product)) {
        chooser.value = forChooser(product);
        return;
    }

    addLine(product, { variant: null, modifiers: [], unitPrice: parseFloat(product.price_from) });
};

const addLine = (product, { variant, modifiers, unitPrice }) => {
    const modifierIds = modifiers.map((m) => m.id);
    const key = lineKey(product.id, variant?.id, modifierIds);
    const existing = basket.value.find((l) => l.key === key);

    if (existing) {
        existing.quantity++;
        return;
    }

    basket.value.push({
        key,
        product_id: product.id,
        variant_id: variant?.id ?? null,
        modifier_ids: modifierIds,
        name: product.name,
        variant_name: variant?.name ?? null,
        modifier_names: modifiers.map((m) => m.name),
        unit_price: unitPrice,
        quantity: 1,
    });
};

const confirmChoice = (selection) => {
    addLine(chooser.value, selection);
    chooser.value = null;
};

const increment = (line) => line.quantity++;
const decrement = (line) => {
    if (line.quantity > 1) line.quantity--;
    else basket.value.splice(basket.value.indexOf(line), 1);
};

const basketCount = computed(() => basket.value.reduce((sum, l) => sum + l.quantity, 0));
const basketTotal = computed(() => basket.value.reduce((sum, l) => sum + l.unit_price * l.quantity, 0));

// ── Placing ─────────────────────────────────────────────────────────────
const showBasket = ref(false);
const customerName = ref('');
const note = ref('');
const placing = ref(false);
const errors = ref({});

const place = () => {
    if (basket.value.length === 0 || placing.value) return;
    placing.value = true;

    router.post(
        `/s/${props.store.slug}/orders`,
        {
            items: basket.value.map((l) => ({
                product_id: l.product_id,
                quantity: l.quantity,
                variant_id: l.variant_id,
                modifier_ids: l.modifier_ids,
            })),
            customer_name: customerName.value || null,
            note: note.value || null,
        },
        {
            onSuccess: (page) => {
                try {
                    localStorage.setItem(TOKEN_KEY, page.props.order?.token ?? '');
                } catch { /* ignore */ }
            },
            onError: (e) => { errors.value = e; placing.value = false; },
            onFinish: () => { placing.value = false; },
        },
    );
};
</script>

<template>
    <div class="mx-auto min-h-screen max-w-md bg-slate-50 pb-32 shadow-sm">
        <Head :title="`Order from ${store.name}`" />

        <!-- Store header -->
        <header class="bg-white px-5 pb-5 pt-8 shadow-card">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-600">Order ahead</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ store.name }}</h1>
            <p v-if="store.address" class="mt-0.5 text-[13px] text-slate-500">{{ store.address }}</p>
            <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-[12px] font-medium text-amber-800">
                Order here, then pay at the counter. We start making it once you&rsquo;ve paid.
            </p>
        </header>

        <!-- Resume an order in progress -->
        <button
            v-if="activeToken"
            class="mx-5 mt-4 flex w-[calc(100%-2.5rem)] items-center gap-2 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-left"
            @click="openActiveOrder"
        >
            <ClockIcon class="h-4 w-4 shrink-0 text-brand-600" aria-hidden="true" />
            <span class="flex-1 text-[13px] font-semibold text-brand-800">You have an order in progress</span>
            <ArrowRightIcon class="h-4 w-4 text-brand-600" aria-hidden="true" />
        </button>

        <!-- Categories -->
        <div v-if="categories.length" class="sticky top-0 z-10 border-b border-slate-200/70 bg-slate-50/95 px-5 py-3 backdrop-blur">
            <div class="flex gap-1.5 overflow-x-auto pb-0.5">
                <button
                    :class="[
                        'shrink-0 rounded-lg px-3 py-1.5 text-[12px] font-semibold',
                        selectedCategory === null ? 'bg-brand-600 text-white' : 'bg-white text-slate-500',
                    ]"
                    @click="selectedCategory = null"
                >All</button>
                <button
                    v-for="category in categories"
                    :key="category.id"
                    :class="[
                        'shrink-0 rounded-lg px-3 py-1.5 text-[12px] font-semibold',
                        selectedCategory === category.id ? 'bg-brand-600 text-white' : 'bg-white text-slate-500',
                    ]"
                    @click="selectedCategory = category.id"
                >{{ category.name }}</button>
            </div>
        </div>

        <!-- Menu -->
        <main class="space-y-2 px-5 py-4">
            <p v-if="visible.length === 0" class="py-16 text-center text-[13px] text-slate-400">
                Nothing on the menu right now.
            </p>

            <button
                v-for="product in visible"
                :key="product.id"
                class="flex w-full items-start gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 text-left shadow-card active:scale-[0.99]"
                style="transition: transform 0.1s;"
                @click="choose(product)"
            >
                <span class="min-w-0 flex-1">
                    <span class="block text-[15px] font-semibold text-slate-900">{{ product.name }}</span>
                    <span v-if="product.description" class="mt-0.5 block text-[12px] leading-snug text-slate-500">
                        {{ product.description }}
                    </span>
                    <span class="mt-1.5 block text-[15px] font-bold tabular-nums text-brand-600">
                        <span v-if="product.variants?.length" class="text-[11px] font-semibold text-slate-400">from </span>{{ formatPeso(product.price_from) }}
                    </span>
                </span>
                <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50">
                    <PlusIcon class="h-4 w-4 text-brand-600" aria-hidden="true" />
                </span>
            </button>
        </main>

        <!-- Basket bar -->
        <div v-if="basketCount > 0 && !showBasket" class="fixed inset-x-0 bottom-0 z-20 mx-auto max-w-md p-4">
            <button
                class="flex w-full items-center gap-3 rounded-2xl bg-brand-600 px-5 py-4 text-white shadow-elevated"
                @click="showBasket = true"
            >
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/20 text-[13px] font-bold tabular-nums">
                    {{ basketCount }}
                </span>
                <span class="flex-1 text-left text-[15px] font-bold">View basket</span>
                <span class="text-[15px] font-bold tabular-nums">{{ formatPeso(basketTotal) }}</span>
            </button>
        </div>

        <!-- Basket sheet -->
        <Teleport to="body">
            <div
                v-if="showBasket"
                class="fixed inset-0 z-50 flex items-end bg-slate-900/40 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-label="Your basket"
                @click.self="showBasket = false"
            >
                <div class="mx-auto max-h-[88vh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-5" style="overscroll-behavior: contain;">
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900">
                            <ShoppingBagIcon class="h-5 w-5 text-brand-600" aria-hidden="true" />
                            Your basket
                        </h2>
                        <button class="rounded-lg p-1 text-slate-400" aria-label="Close" @click="showBasket = false">
                            <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </div>

                    <ul class="mt-4 divide-y divide-slate-100">
                        <li v-for="line in basket" :key="line.key" class="flex items-start gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-[14px] font-semibold text-slate-800">
                                    {{ line.name }}<span v-if="line.variant_name" class="text-slate-500"> ({{ line.variant_name }})</span>
                                </p>
                                <p v-if="line.modifier_names.length" class="text-[12px] text-brand-600">
                                    + {{ line.modifier_names.join(', ') }}
                                </p>
                                <p class="text-[12px] tabular-nums text-slate-400">{{ formatPeso(line.unit_price) }} each</p>
                            </div>

                            <div class="flex items-center gap-1">
                                <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500"
                                    :aria-label="`One fewer ${line.name}`" @click="decrement(line)">
                                    <MinusIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                </button>
                                <span class="w-6 text-center text-[13px] font-bold tabular-nums">{{ line.quantity }}</span>
                                <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500"
                                    :aria-label="`One more ${line.name}`" @click="increment(line)">
                                    <PlusIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                </button>
                            </div>
                        </li>
                    </ul>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="block text-[13px] font-semibold text-slate-700" for="name">Your name</label>
                            <input id="name" v-model="customerName" type="text" maxlength="60"
                                class="input-field mt-1.5 w-full" placeholder="So we can call you" />
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-slate-700" for="note">Anything else?</label>
                            <input id="note" v-model="note" type="text" maxlength="255"
                                class="input-field mt-1.5 w-full" placeholder="e.g. less ice" />
                        </div>
                    </div>

                    <p v-if="errors.items" class="mt-3 text-[12px] text-red-500">{{ errors.items }}</p>

                    <div class="mt-5 flex items-baseline justify-between border-t border-slate-100 pt-4">
                        <span class="text-[15px] font-semibold text-slate-500">Total</span>
                        <span class="text-2xl font-bold tabular-nums text-slate-900">{{ formatPeso(basketTotal) }}</span>
                    </div>

                    <button
                        :disabled="placing"
                        class="mt-4 w-full rounded-2xl bg-brand-600 py-4 text-[15px] font-bold text-white shadow-sm disabled:opacity-50"
                        @click="place"
                    >
                        {{ placing ? 'Sending…' : 'Place order' }}
                    </button>
                    <p class="mt-2 text-center text-[11px] text-slate-400">
                        You&rsquo;ll pay at the counter. Nothing is charged now.
                    </p>
                </div>
            </div>
        </Teleport>

        <ProductOptionsModal
            :show="chooser !== null"
            :product="chooser"
            @close="chooser = null"
            @confirm="confirmChoice"
        />
    </div>
</template>
