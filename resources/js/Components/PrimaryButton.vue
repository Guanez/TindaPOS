<script setup>
defineProps({
    // Submits by default: this is the button that ends a form.
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="btn-primary disabled:opacity-50">
        <slot />
    </button>
</template>
