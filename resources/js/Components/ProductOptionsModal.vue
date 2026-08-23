<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/currency';

import { XMarkIcon, PlusIcon } from '@heroicons/vue/24/outline';

const { money } = useCurrency();


const props = defineProps({
    show: Boolean,
    product: { type: Object, default: null },
});

const emit = defineEmits(['close', 'confirm']);

// This component is shared by the till and the customer's phone, and the two
// want different things from a dialog. A centred box is right with a mouse;
// on a phone it lands mid-screen with its actions furthest from the thumb.
// The density prop already says which surface this is, so nothing here needs
// a new flag — and the POS is untouched by the change.
const page = usePage();
const isSheet = computed(() => page.props.density === 'touch');

const selectedVariantId = ref(null);
const selectedModifierIds = ref([]);

const variants = computed(() => (props.product?.variants ?? []).filter((v) => v.is_active !== false));
const groups = computed(() => props.product?.modifier_groups ?? []);

// Reset every time a different product is opened.
watch(
    () => [props.show, props.product?.id],
    ([show]) => {
        if (!show) return;
        const list = variants.value;
        selectedVariantId.value = (list.find((v) => v.is_default) ?? list[0])?.id ?? null;
        selectedModifierIds.value = [];
    },
    { immediate: true },
);

const selectedVariant = computed(
    () => variants.value.find((v) => v.id === selectedVariantId.value) ?? null,
);

const basePrice = computed(() =>
    parseFloat(selectedVariant.value?.selling_price ?? props.product?.selling_price ?? 0),
);

const selectedModifiers = computed(() =>
    groups.value
        .flatMap((g) => g.modifiers ?? [])
        .filter((m) => selectedModifierIds.value.includes(m.id)),
);

const unitPrice = computed(
    () => basePrice.value + selectedModifiers.value.reduce((sum, m) => sum + parseFloat(m.price_delta ?? 0), 0),
);

const chosenIn = (group) =>
    (group.modifiers ?? []).filter((m) => selectedModifierIds.value.includes(m.id)).length;

const toggleModifier = (group, modifier) => {
    const index = selectedModifierIds.value.indexOf(modifier.id);

    if (index !== -1) {
        selectedModifierIds.value.splice(index, 1);
        return;
    }

    // A single-choice group swaps rather than stacks.
    if (group.max_select === 1) {
        const others = (group.modifiers ?? []).map((m) => m.id);
        selectedModifierIds.value = selectedModifierIds.value.filter((id) => !others.includes(id));
        selectedModifierIds.value.push(modifier.id);
        return;
    }

    if (chosenIn(group) < group.max_select) {
        selectedModifierIds.value.push(modifier.id);
    }
};

const isChosen = (modifier) => selectedModifierIds.value.includes(modifier.id);

const unmetGroup = computed(() =>
    groups.value.find((g) => chosenIn(g) < (g.min_select ?? 0)) ?? null,
);

const canAdd = computed(() => unmetGroup.value === null);

const confirm = () => {
    if (!canAdd.value) return;

    emit('confirm', {
        variant: selectedVariant.value,
        modifiers: selectedModifiers.value,
        unitPrice: unitPrice.value,
    });
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show && product"
            :class="[
                'fixed inset-0 z-50 flex bg-ink-1/40 backdrop-blur-sm',
                isSheet ? 'items-end' : 'items-center justify-center p-4',
            ]"
            role="dialog"
            aria-modal="true"
            :aria-label="`Options for ${product.name}`"
            @click.self="emit('close')"
        >
            <div
                :class="[
                    'flex w-full max-w-md flex-col overflow-hidden bg-surface-1 shadow-overlay',
                    isSheet
                        ? 'mx-auto max-h-[88vh] rounded-t-sheet animate-sheet-up'
                        : 'max-h-[90vh] rounded-card animate-scale-in',
                ]"
            >
                <!-- Header -->
                <div class="flex items-start justify-between border-b border-line px-6 py-4">
                    <div>
                        <h3 class="text-lg font-bold text-ink-1">{{ product.name }}</h3>
                        <p class="mt-0.5 text-meta text-ink-3">Choose options to add this to the cart</p>
                    </div>
                    <button
                        class="rounded-control p-1 text-ink-3 hover:bg-surface-2 hover:text-ink-2"
                        style="transition: background-color var(--t-fast), color var(--t-fast);"
                        aria-label="Close"
                        @click="emit('close')"
                    >
                        <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5" style="overscroll-behavior: contain;">
                    <!-- Sizes -->
                    <fieldset v-if="variants.length > 0">
                        <legend class="text-label font-semibold uppercase tracking-wider text-ink-3">Size</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="variant in variants"
                                :key="variant.id"
                                type="button"
                                :aria-pressed="selectedVariantId === variant.id"
                                :class="[
                                    'rounded-control border px-3.5 py-2 text-left transition-all',
                                    selectedVariantId === variant.id
                                        ? 'border-accent bg-accent-tint text-accent-ink shadow-rest'
                                        : 'border-line text-ink-2 hover:border-line-strong hover:bg-surface-2',
                                ]"
                                @click="selectedVariantId = variant.id"
                            >
                                <span class="block text-ui font-semibold">{{ variant.name }}</span>
                                <span class="block text-meta tabular-nums opacity-70">{{ money(variant.selling_price) }}</span>
                            </button>
                        </div>
                    </fieldset>

                    <!-- Add-on groups -->
                    <fieldset v-for="group in groups" :key="group.id">
                        <legend class="flex items-baseline gap-2">
                            <span class="text-label font-semibold uppercase tracking-wider text-ink-3">{{ group.name }}</span>
                            <span v-if="group.min_select > 0" class="text-meta font-medium text-stop-ink">Required</span>
                            <span v-else-if="group.max_select > 1" class="text-meta text-ink-3">up to {{ group.max_select }}</span>
                        </legend>

                        <div class="mt-2 space-y-1.5">
                            <button
                                v-for="modifier in group.modifiers"
                                :key="modifier.id"
                                type="button"
                                :aria-pressed="isChosen(modifier)"
                                :class="[
                                    'flex w-full items-center gap-3 rounded-control border px-3.5 py-2.5 text-left transition-all',
                                    isChosen(modifier)
                                        ? 'border-accent bg-accent-tint'
                                        : 'border-line hover:border-line-strong hover:bg-surface-2',
                                ]"
                                @click="toggleModifier(group, modifier)"
                            >
                                <span
                                    :class="[
                                        'flex h-4 w-4 shrink-0 items-center justify-center border',
                                        group.max_select === 1 ? 'rounded-full' : 'rounded',
                                        isChosen(modifier) ? 'border-accent bg-accent' : 'border-line-strong',
                                    ]"
                                >
                                    <span v-if="isChosen(modifier)" class="block h-1.5 w-1.5 rounded-full bg-surface-1" />
                                </span>

                                <span class="flex-1 text-ui font-medium text-ink-2">{{ modifier.name }}</span>

                                <span
                                    v-if="parseFloat(modifier.price_delta) > 0"
                                    class="text-meta font-semibold tabular-nums text-ink-3"
                                >
                                    +{{ money(modifier.price_delta) }}
                                </span>
                            </button>
                        </div>
                    </fieldset>
                </div>

                <!-- Footer -->
                <div class="border-t border-line bg-surface-2/60 px-6 py-4">
                    <p v-if="unmetGroup" class="mb-2 text-meta font-medium text-stop-ink">
                        Choose at least {{ unmetGroup.min_select }} from {{ unmetGroup.name }}.
                    </p>

                    <button
                        type="button"
                        :disabled="!canAdd"
                        class="flex w-full items-center justify-center gap-2 rounded-control bg-accent py-3 text-sm font-bold text-accent-fg shadow-rest transition-colors hover:bg-accent-hover disabled:opacity-50"
                        @click="confirm"
                    >
                        <PlusIcon class="h-4 w-4" aria-hidden="true" />
                        Add to cart &mdash; {{ money(unitPrice) }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
