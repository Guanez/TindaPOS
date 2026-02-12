<script setup>
import { ExclamationTriangleIcon, XCircleIcon, CheckCircleIcon } from '@heroicons/vue/16/solid'

const props = defineProps({
    stock: Number,
    threshold: { type: Number, default: 10 },
})

const status = props.stock <= 0 ? 'out' : props.stock <= props.threshold ? 'low' : 'ok'

const config = {
    out: { class: 'badge-danger', label: 'Out of Stock', icon: XCircleIcon },
    low: { class: 'badge-warning', label: 'Low Stock',   icon: ExclamationTriangleIcon },
    ok:  { class: 'badge-success', label: 'In Stock',    icon: CheckCircleIcon },
}
</script>

<template>
    <span class="badge" :class="config[status].class">
        <component :is="config[status].icon" aria-hidden="true" class="h-3.5 w-3.5" />
        {{ config[status].label }}
    </span>
</template>
