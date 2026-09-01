<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message">
        <p class="text-ui text-stop-ink">
            {{ message }}
        </p>
    </div>
</template>
