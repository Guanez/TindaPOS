<script setup>
import { Head, router } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/currency';
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';

import Dialog from '@/Components/Dialog.vue';
import ProductOptionsModal from '@/Components/ProductOptionsModal.vue';
import {
    ShoppingBagIcon,
    PlusIcon,
    MinusIcon,
    ArrowRightIcon,
    ClockIcon,
    MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline';

const { money } = useCurrency();

const props = defineProps({
    store: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    products: { type: Object, default: () => ({ data: [] }) },
});

const menu = computed(() => props.products?.data ?? []);

// `is_open` is absent on a store that has never set hours, which is a store
// that never closes — so only an explicit false shuts the ordering path.
const isClosed = computed(() => props.store.is_open === false);

// ── Sections ────────────────────────────────────────────────────────────
// The rail used to filter the list, which meant reading the menu was a
// series of decisions before you could see anything. Sections show the whole
// menu and the rail moves you around it, which is how a paper menu works.
// ── Search ──────────────────────────────────────────────────────────────
// Browsing and looking for one thing are different jobs. The rail is for
// browsing and deliberately does not filter; search is the other job, and a
// forty-item menu on a phone is where it earns its place — a customer who
// already knows they want the spanish latte should not have to scroll past
// the pastries to order it.
const query = ref('');
const searching = computed(() => query.value.trim().length > 0);

const matches = (product, needle) =>
    product.name.toLowerCase().includes(needle) ||
    (product.description ?? '').toLowerCase().includes(needle);

const results = computed(() => {
    if (!searching.value) return [];
    const needle = query.value.trim().toLowerCase();
    return menu.value.filter((p) => matches(p, needle));
});

const sections = computed(() =>
    props.categories
        .map((category) => ({
            ...category,
            products: menu.value.filter((p) => p.category_id === category.id),
        }))
        .filter((section) => section.products.length > 0),
);

// What the list actually renders. Search results come through as a single
// pseudo-section so the tile markup has exactly one home — a second copy of
// it is a second place for a sold-out state to be forgotten.
const visibleSections = computed(() => {
    if (!searching.value) return sections.value;
    if (results.value.length === 0) return [];

    return [{
        id: '__results',
        name: `${results.value.length} ${results.value.length === 1 ? 'match' : 'matches'}`,
        products: results.value,
    }];
});

const activeCategory = ref(null);
const sectionEls = ref({});

const setSectionEl = (id) => (el) => {
    if (el) sectionEls.value[id] = el;
    else delete sectionEls.value[id];
};

const goToSection = (id) => {
    sectionEls.value[id]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

// Which section the customer is currently inside.
//
// The answer is the last heading to have passed under the rail, not the
// topmost one still on screen — two sections overlap the top of the viewport
// for most of a scroll, and picking the higher of them leaves the rail a
// whole category behind what is being read.
//
// The observer is only a signal that a boundary was crossed; the answer is
// read off the rects, which is exact and costs nothing at this size.
const RAIL = 72;

let spy = null;

const syncActive = () => {
    let current = visibleSections.value[0]?.id ?? null;

    for (const section of visibleSections.value) {
        const el = sectionEls.value[section.id];
        if (el && el.getBoundingClientRect().top <= RAIL) current = section.id;
    }

    activeCategory.value = current;
};

const watchSections = () => {
    spy?.disconnect();

    spy = new IntersectionObserver(syncActive, {
        rootMargin: `-${RAIL}px 0px 0px 0px`,
        threshold: 0,
    });

    Object.values(sectionEls.value).forEach((el) => spy.observe(el));
};

// Searching swaps the whole list, which leaves the observer holding elements
// that are no longer in the document. Rebind after the DOM catches up.
watch(visibleSections, () => nextTick(() => {
    watchSections();
    syncActive();
}));

// ── An order already in progress on this phone ──────────────────────────
const TOKEN_KEY = 'tindapos_order_token';
const activeToken = ref(null);

onMounted(() => {
    try {
        activeToken.value = localStorage.getItem(TOKEN_KEY);
    } catch { /* private browsing */ }

    restoreBasket();

    nextTick(() => {
        watchSections();
        syncActive();
    });
});

onUnmounted(() => spy?.disconnect());

const openActiveOrder = () => router.visit(`/o/${activeToken.value}`);

// ── Basket ──────────────────────────────────────────────────────────────
// Kept on the phone, because the trip between choosing and paying involves
// putting the phone away, and a basket that empties itself in a pocket is
// worse than no basket at all. Keyed per shop: two cafes on one phone are
// two different baskets.
const basketKey = `tindapos_basket_${props.store.slug}`;

const basket = ref([]);
const chooser = ref(null);

const restoreBasket = () => {
    try {
        const saved = JSON.parse(localStorage.getItem(basketKey) ?? '[]');
        if (!Array.isArray(saved)) return;

        // The menu may have moved on since this was saved — an item pulled,
        // a shop closed for the night, the last one sold. Anything no longer
        // orderable is dropped rather than carried to a checkout that would
        // reject it. Note this is the orderable set, not the visible one:
        // sold-out items are on the menu now, and must not survive in a
        // basket.
        const sellable = new Set(menu.value.filter((p) => p.is_available).map((p) => p.id));
        basket.value = saved.filter((line) => sellable.has(line.product_id));
    } catch { /* private browsing, or nothing saved */ }
};

watch(
    basket,
    (lines) => {
        try {
            localStorage.setItem(basketKey, JSON.stringify(lines));
        } catch { /* storage full or unavailable */ }
    },
    { deep: true },
);

const lineKey = (productId, variantId, modifierIds) =>
    [productId, variantId ?? 0, [...modifierIds].sort((a, b) => a - b).join('.')].join(':');

const hasOptions = (product) =>
    (product.variants?.length ?? 0) > 0 || (product.modifier_groups?.length ?? 0) > 0;

// The chooser expects a product priced by `selling_price`; the public menu
// calls that `price_from` so nothing implies a single fixed price.
const forChooser = (product) => ({ ...product, selling_price: product.price_from });

const choose = (product) => {
    // Nothing goes in a basket that cannot be sent. The tile stays readable
    // rather than disabled — the menu is still worth reading when shut.
    if (isClosed.value || !product.is_available) return;

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
    if (basket.value.length === 0 || placing.value || isClosed.value) return;
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
                basket.value = [];
                try {
                    localStorage.setItem(TOKEN_KEY, page.props.order?.token ?? '');
                    localStorage.removeItem(basketKey);
                } catch { /* ignore */ }
            },
            onError: (e) => { errors.value = e; placing.value = false; },
            onFinish: () => { placing.value = false; },
        },
    );
};
</script>

<template>
    <div class="mx-auto min-h-screen max-w-md bg-surface-2 pb-32 shadow-rest">
        <Head :title="`Order from ${store.name}`" />

        <!-- Store header -->
        <header class="bg-surface-1 px-5 pb-5 pt-8 shadow-rest">
            <!--
                The logo replaces the eyebrow rather than sitting above it —
                a shop that has uploaded one has already said who it is, and
                repeating it in small caps underneath is clutter.
            -->
            <img
                v-if="store.logo_url"
                :src="store.logo_url"
                :alt="store.name"
                class="mb-3 max-h-12 w-auto max-w-[200px] object-contain"
            />
            <p v-else class="text-label font-semibold uppercase tracking-widest text-accent-ink">Order ahead</p>

            <h1 class="mt-1 text-heading font-bold tracking-tight text-ink-1">{{ store.name }}</h1>
            <p v-if="store.address" class="mt-0.5 text-ui text-ink-3">{{ store.address }}</p>

            <!--
                A shut shop says so and says when it opens. The menu below
                stays readable on purpose — someone deciding what to get in
                the morning is worth keeping — but nothing can be added to a
                basket, so there is no way to reach a checkout that would only
                reject them.
            -->
            <p
                v-if="isClosed"
                class="mt-3 rounded-control bg-surface-3 px-3 py-2.5 text-meta font-medium text-ink-2"
            >
                <span class="block font-bold text-ink-1">We&rsquo;re closed right now</span>
                {{ store.next_opening ?? 'Come back during opening hours.' }} You can still look at the menu.
            </p>
            <p v-else class="mt-3 rounded-control bg-wait-tint px-3 py-2 text-meta font-medium text-wait-ink">
                Order here, then pay at the counter. We start making it once you&rsquo;ve paid.<!--
                    The estimate belongs here as well as on the status page:
                    "is this worth the wait" is a question people answer
                    before they order, not after.
                --><span v-if="store.prep_minutes"> Usually ready about {{ store.prep_minutes }} minutes after that.</span>
            </p>
        </header>

        <!-- Resume an order in progress -->
        <button
            v-if="activeToken"
            class="mx-5 mt-4 flex w-[calc(100%-2.5rem)] items-center gap-2 rounded-control border border-accent-line bg-accent-tint px-4 py-3 text-left"
            @click="openActiveOrder"
        >
            <ClockIcon class="h-4 w-4 shrink-0 text-accent-ink" aria-hidden="true" />
            <span class="flex-1 text-ui font-semibold text-accent-ink">You have an order in progress</span>
            <ArrowRightIcon class="h-4 w-4 text-accent-ink" aria-hidden="true" />
        </button>

        <!--
            The rail moves you through the menu rather than filtering it. A
            filter makes reading the menu a series of decisions before you can
            see anything; a paper menu just has headings.
        -->
        <div v-if="sections.length" class="sticky top-0 z-20 border-b border-line/70 bg-surface-2/95 px-5 py-3 backdrop-blur">
            <div class="relative">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-3" aria-hidden="true" />
                <label class="sr-only" for="menu-search">Search the menu</label>
                <input
                    id="menu-search"
                    v-model="query"
                    type="search"
                    enterkeyhint="search"
                    autocomplete="off"
                    placeholder="Search the menu"
                    class="input-field w-full !pl-9"
                />
            </div>

            <!--
                The rail is for browsing, and browsing is not what is
                happening once someone has typed. It goes rather than sitting
                there highlighting a category the results are not grouped by.
            -->
            <div v-if="!searching" class="mt-2 flex gap-1.5 overflow-x-auto pb-0.5">
                <button
                    v-for="section in sections"
                    :key="section.id"
                    :class="[
                        'shrink-0 rounded-control px-3 py-1.5 text-meta font-semibold',
                        activeCategory === section.id ? 'bg-accent text-accent-fg' : 'bg-surface-1 text-ink-3',
                    ]"
                    @click="goToSection(section.id)"
                >{{ section.name }}</button>
            </div>
        </div>

        <!-- Menu -->
        <main class="px-5 py-4">
            <p v-if="sections.length === 0" class="py-16 text-center text-ui text-ink-3">
                Nothing on the menu right now.
            </p>

            <div v-else-if="searching && results.length === 0" class="py-16 text-center">
                <p class="text-body font-semibold text-ink-1">No matches for &ldquo;{{ query.trim() }}&rdquo;</p>
                <button type="button" class="mt-2 text-ui font-semibold text-accent-ink underline" @click="query = ''">
                    Show the whole menu
                </button>
            </div>

            <section
                v-for="section in visibleSections"
                :key="section.id"
                :ref="setSectionEl(section.id)"
                :data-category="section.id"
                class="scroll-mt-14"
            >
                <!--
                    Sticks directly beneath the rail, so the heading you are
                    reading under is always on screen. z-10 keeps it under the
                    rail rather than sliding over it.
                -->
                <h2 class="sticky top-14 z-10 -mx-5 bg-surface-2/95 px-5 py-2 text-label font-bold uppercase tracking-widest text-ink-3 backdrop-blur">
                    {{ section.name }}
                </h2>

                <div class="space-y-2 pb-4 pt-1">
                <button
                    v-for="product in section.products"
                    :key="product.id"
                    :disabled="!product.is_available"
                    :aria-label="product.is_available ? null : `${product.name}, sold out`"
                    class="flex w-full items-start gap-3 rounded-card border border-line/80 bg-surface-1 p-4 text-left shadow-rest enabled:active:scale-[0.99] disabled:cursor-default"
                    style="transition: transform var(--t-fast);"
                    @click="choose(product)"
                >
                    <!--
                        Lazy, because a menu can be forty items long and the
                        customer is on mobile data seconds after scanning a code
                        at the counter. Decorative: the name is right beside it,
                        so a screen reader announcing the filename would only
                        repeat what it is about to read.
                    -->
                    <!--
                        Sold out dims the picture and the price but NOT the
                        name — the point of showing the row at all is that the
                        customer can find the thing and see it is gone for
                        today, which needs the name at full strength.
                    -->
                    <img
                        v-if="product.image_url"
                        :src="product.image_url"
                        alt=""
                        loading="lazy"
                        :class="['h-[76px] w-[76px] shrink-0 rounded-control bg-surface-2 object-cover', product.is_available || 'opacity-40 grayscale']"
                    />

                    <span class="min-w-0 flex-1">
                        <span class="block text-body font-semibold text-ink-1">{{ product.name }}</span>
                        <span v-if="product.description" class="mt-0.5 block text-meta leading-snug text-ink-3">
                            {{ product.description }}
                        </span>

                        <span v-if="!product.is_available" class="mt-1.5 inline-block rounded-control bg-surface-3 px-2 py-0.5 text-label font-bold uppercase tracking-widest text-ink-3">
                            Sold out
                        </span>
                        <span v-else class="mt-1.5 block text-body font-bold tabular-nums text-ink-1">
                            <span v-if="product.variants?.length" class="text-meta font-semibold text-ink-3">from </span>{{ money(product.price_from) }}
                        </span>
                    </span>
                    <!-- The add affordance goes when there is nothing to add to. -->
                    <span v-if="!isClosed && product.is_available" class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-tint">
                        <PlusIcon class="h-4 w-4 text-accent-ink" aria-hidden="true" />
                    </span>
                </button>
                </div>
            </section>
        </main>

        <!-- Basket bar -->
        <div v-if="basketCount > 0 && !showBasket && !isClosed" class="fixed inset-x-0 bottom-0 z-20 mx-auto max-w-md p-4">
            <button
                class="flex w-full items-center gap-3 rounded-card bg-accent px-5 py-4 text-accent-fg shadow-overlay"
                @click="showBasket = true"
            >
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-surface-1/20 text-ui font-bold tabular-nums">
                    {{ basketCount }}
                </span>
                <span class="flex-1 text-left text-body font-bold">View basket</span>
                <span class="text-body font-bold tabular-nums">{{ money(basketTotal) }}</span>
            </button>
        </div>

        <!-- Basket sheet -->
        <Dialog
            :show="showBasket"
            title="Your basket"
            :icon="ShoppingBagIcon"
            placement="bottom"
            max-width="md"
            close-button
            @close="showBasket = false"
        >
            <ul class="divide-y divide-line">
                <li v-for="line in basket" :key="line.key" class="flex items-start gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-body font-semibold text-ink-1">
                            {{ line.name }}<span v-if="line.variant_name" class="text-ink-3"> ({{ line.variant_name }})</span>
                        </p>
                        <p v-if="line.modifier_names.length" class="text-meta text-accent-ink">
                            + {{ line.modifier_names.join(', ') }}
                        </p>
                        <p class="text-meta tabular-nums text-ink-3">{{ money(line.unit_price) }} each</p>
                    </div>

                    <div class="flex items-center gap-1">
                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-control border border-line text-ink-3"
                            :aria-label="`One fewer ${line.name}`" @click="decrement(line)">
                            <MinusIcon class="h-3.5 w-3.5" aria-hidden="true" />
                        </button>
                        <span class="w-6 text-center text-ui font-bold tabular-nums">{{ line.quantity }}</span>
                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-control border border-line text-ink-3"
                            :aria-label="`One more ${line.name}`" @click="increment(line)">
                            <PlusIcon class="h-3.5 w-3.5" aria-hidden="true" />
                        </button>
                    </div>
                </li>
            </ul>

            <div class="mt-4 space-y-3">
                <div>
                    <label class="block text-ui font-semibold text-ink-2" for="basket-name">Your name</label>
                    <input id="basket-name" v-model="customerName" type="text" maxlength="60" autocomplete="name"
                        class="input-field mt-1.5 w-full" placeholder="So we can call you" />
                </div>
                <div>
                    <label class="block text-ui font-semibold text-ink-2" for="basket-note">Anything else?</label>
                    <input id="basket-note" v-model="note" type="text" maxlength="255"
                        class="input-field mt-1.5 w-full" placeholder="e.g. less ice" />
                </div>
            </div>

            <!--
                Two keys, because two different things reject an order: the
                request validator says `items` (empty basket, shop shut) and
                the pricer says `checkout` (sold out between opening the menu
                and pressing the button, which is now possible in a way it was
                not when sold-out items were hidden).
            -->
            <p v-if="errors.items || errors.checkout" role="alert" class="mt-3 text-meta text-stop-ink">
                {{ errors.items || errors.checkout }}
            </p>

            <template #footer>
                <div class="flex items-baseline justify-between">
                    <span class="text-body font-semibold text-ink-3">Total</span>
                    <span class="text-figure font-bold tabular-nums text-ink-1">{{ money(basketTotal) }}</span>
                </div>

                <button
                    type="button"
                    :disabled="placing"
                    class="mt-4 w-full rounded-card bg-accent py-4 text-body font-bold text-accent-fg shadow-rest disabled:opacity-50"
                    @click="place"
                >
                    {{ placing ? 'Sending&hellip;' : 'Place order' }}
                </button>
                <p class="mt-2 text-center text-meta text-ink-3">
                    You&rsquo;ll pay at the counter. Nothing is charged now.
                </p>
            </template>
        </Dialog>

        <ProductOptionsModal
            :show="chooser !== null"
            :product="chooser"
            @close="chooser = null"
            @confirm="confirmChoice"
        />
    </div>
</template>
