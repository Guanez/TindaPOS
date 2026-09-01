<script setup>
import { computed } from 'vue'
import { ExclamationTriangleIcon, XCircleIcon, CheckCircleIcon } from '@heroicons/vue/16/solid'

const props = defineProps({
    stock: Number,
    threshold: { type: Number, default: 10 },
})

/*
 * Computed, not read once at setup.
 *
 * The inventory table keys its rows by product id, so a restock re-renders
 * the same component instance with new props rather than mounting a fresh
 * one. Resolved eagerly, the badge kept reporting "Out of Stock" on a product
 * that had just been restocked — the one column a manager is there to trust.
 */
const status = computed(() =>
    props.stock <= 0 ? 'out' : props.stock <= props.threshold ? 'low' : 'ok',
)

const config = {
    out: { class: 'badge-danger', label: 'Out of Stock', icon: XCircleIcon },
    low: { class: 'badge-warning', label: 'Low Stock',   icon: ExclamationTriangleIcon },
    ok:  { class: 'badge-success', label: 'In Stock',    icon: CheckCircleIcon },
}

const current = computed(() => config[status.value])
</script>

<template>
    <span class="badge" :class="current.class">
        <component :is="current.icon" aria-hidden="true" class="h-3.5 w-3.5" />
        {{ current.label }}
    </span>
</template>
