<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="btn-danger disabled:opacity-50">
        <slot />
    </button>
</template>
