<script setup>
defineProps({
    label: String,
    value: [String, Number],
    icon: { type: Object, default: null },   // Heroicon component
    color: { type: String, default: 'default' },
})

const bgClasses = {
    default: 'bg-slate-50 text-slate-600',
    success: 'bg-emerald-50 text-emerald-600',
    warning: 'bg-amber-50 text-amber-600',
    danger:  'bg-red-50 text-red-600',
    info:    'bg-brand-50 text-brand-600',
}

const valueClasses = {
    default: 'text-slate-900',
    success: 'text-emerald-700',
    warning: 'text-amber-700',
    danger:  'text-red-700',
    info:    'text-brand-700',
}
</script>

<template>
    <div class="card p-4">
        <div class="flex items-center gap-2.5">
            <div
                v-if="icon"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                :class="bgClasses[color]"
            >
                <component :is="icon" class="h-[18px] w-[18px]" aria-hidden="true" />
            </div>
            <div class="min-w-0">
                <p class="truncate text-[12px] font-medium text-slate-500">{{ label }}</p>
                <p class="mt-0.5 text-xl font-bold tracking-tight tabular-nums" :class="valueClasses[color]">
                    {{ value }}
                </p>
            </div>
        </div>
    </div>
</template>
