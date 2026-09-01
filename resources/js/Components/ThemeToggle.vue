<script setup>
import { SunIcon, MoonIcon, ComputerDesktopIcon } from '@heroicons/vue/24/outline'
import { useTheme } from '@/Composables/theme'

/*
 * Three buttons rather than one that cycles. A cycling button cannot show
 * where it will land, and the state a shop most needs to see is "follow the
 * device" — which is invisible on a toggle, because it looks exactly like
 * whichever of the two it currently resolves to.
 */
const { preference, setTheme } = useTheme()

const options = [
    { value: 'light', label: 'Light', icon: SunIcon },
    { value: 'system', label: 'Match device', icon: ComputerDesktopIcon },
    { value: 'dark', label: 'Dark', icon: MoonIcon },
]
</script>

<template>
    <div
        role="radiogroup"
        aria-label="Colour theme"
        class="flex items-center gap-0.5 rounded-full border border-line bg-surface-2 p-0.5"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="preference === option.value"
            :aria-label="option.label"
            :title="option.label"
            class="rounded-full p-1.5"
            :class="preference === option.value
                ? 'bg-surface-1 text-ink-1 shadow-rest'
                : 'text-ink-3 hover:text-ink-2'"
            style="transition: background-color var(--t-fast), color var(--t-fast);"
            @click="setTheme(option.value)"
        >
            <component :is="option.icon" class="h-4 w-4" aria-hidden="true" />
        </button>
    </div>
</template>
