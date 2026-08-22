<script setup>
import { computed, ref, watch } from 'vue';
import { formatPeso } from '@/Composables/helpers';
import { XMarkIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    show: Boolean,
    product: { type: Object, default: null },
});

const emit = defineEmits(['close', 'confirm']);

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
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            :aria-label="`Options for ${product.name}`"
            @click.self="emit('close')"
        >
            <div class="flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-elevated animate-scale-in">
                <!-- Header -->
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">{{ product.name }}</h3>
                        <p class="mt-0.5 text-[12px] text-slate-400">Choose options to add this to the cart</p>
                    </div>
                    <button
                        class="rounded-lg p-1 text-slate-400 hover:bg-slate-50 hover:text-slate-600"
                        style="transition: background-color 0.15s, color 0.15s;"
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
                        <legend class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Size</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="variant in variants"
                                :key="variant.id"
                                type="button"
                                :aria-pressed="selectedVariantId === variant.id"
                                :class="[
                                    'rounded-xl border px-3.5 py-2 text-left transition-all',
                                    selectedVariantId === variant.id
                                        ? 'border-brand-500 bg-brand-50 text-brand-700 shadow-sm'
                                        : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50',
                                ]"
                                @click="selectedVariantId = variant.id"
                            >
                                <span class="block text-[13px] font-semibold">{{ variant.name }}</span>
                                <span class="block text-[12px] tabular-nums opacity-70">{{ formatPeso(variant.selling_price) }}</span>
                            </button>
                        </div>
                    </fieldset>

                    <!-- Add-on groups -->
                    <fieldset v-for="group in groups" :key="group.id">
                        <legend class="flex items-baseline gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ group.name }}</span>
                            <span v-if="group.min_select > 0" class="text-[11px] font-medium text-red-500">Required</span>
                            <span v-else-if="group.max_select > 1" class="text-[11px] text-slate-400">up to {{ group.max_select }}</span>
                        </legend>

                        <div class="mt-2 space-y-1.5">
                            <button
                                v-for="modifier in group.modifiers"
                                :key="modifier.id"
                                type="button"
                                :aria-pressed="isChosen(modifier)"
                                :class="[
                                    'flex w-full items-center gap-3 rounded-xl border px-3.5 py-2.5 text-left transition-all',
                                    isChosen(modifier)
                                        ? 'border-brand-500 bg-brand-50'
                                        : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50',
                                ]"
                                @click="toggleModifier(group, modifier)"
                            >
                                <span
                                    :class="[
                                        'flex h-4 w-4 shrink-0 items-center justify-center border',
                                        group.max_select === 1 ? 'rounded-full' : 'rounded',
                                        isChosen(modifier) ? 'border-brand-600 bg-brand-600' : 'border-slate-300',
                                    ]"
                                >
                                    <span v-if="isChosen(modifier)" class="block h-1.5 w-1.5 rounded-full bg-white" />
                                </span>

                                <span class="flex-1 text-[13px] font-medium text-slate-700">{{ modifier.name }}</span>

                                <span
                                    v-if="parseFloat(modifier.price_delta) > 0"
                                    class="text-[12px] font-semibold tabular-nums text-slate-500"
                                >
                                    +{{ formatPeso(modifier.price_delta) }}
                                </span>
                            </button>
                        </div>
                    </fieldset>
                </div>

                <!-- Footer -->
                <div class="border-t border-slate-100 bg-slate-50/60 px-6 py-4">
                    <p v-if="unmetGroup" class="mb-2 text-[12px] font-medium text-red-600">
                        Choose at least {{ unmetGroup.min_select }} from {{ unmetGroup.name }}.
                    </p>

                    <button
                        type="button"
                        :disabled="!canAdd"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 py-3 text-sm font-bold text-white shadow-sm transition-colors hover:bg-brand-700 disabled:opacity-50"
                        @click="confirm"
                    >
                        <PlusIcon class="h-4 w-4" aria-hidden="true" />
                        Add to cart &mdash; {{ formatPeso(unitPrice) }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
