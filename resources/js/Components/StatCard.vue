<script setup>
defineProps({
    label: String,
    value: [String, Number],
    icon: { type: Object, default: null },   // Heroicon component
    color: { type: String, default: 'default' },
})

const bgClasses = {
    default: 'bg-surface-2 text-ink-2',
    success: 'bg-ready-tint text-ready-ink',
    warning: 'bg-wait-tint text-wait-ink',
    danger:  'bg-stop-tint text-stop-ink',
    info:    'bg-accent-tint text-accent-ink',
}

const valueClasses = {
    default: 'text-ink-1',
    success: 'text-ready-ink',
    warning: 'text-wait-ink',
    danger:  'text-stop-ink',
    info:    'text-accent-ink',
}
</script>

<template>
    <div class="card p-4">
        <div class="flex items-center gap-2.5">
            <div
                v-if="icon"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-control"
                :class="bgClasses[color]"
            >
                <component :is="icon" class="h-[18px] w-[18px]" aria-hidden="true" />
            </div>
            <div class="min-w-0">
                <p class="truncate text-meta font-medium text-ink-3">{{ label }}</p>
                <p class="mt-0.5 text-heading font-bold tracking-tight tabular-nums" :class="valueClasses[color]">
                    {{ value }}
                </p>
            </div>
        </div>
    </div>
</template>
