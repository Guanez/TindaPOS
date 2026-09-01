<script setup>
import { ShoppingCartIcon } from '@heroicons/vue/24/outline'
import ThemeToggle from '@/Components/ThemeToggle.vue'
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-surface-2 px-4 py-8">
        <!-- Subtle background decoration -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -right-32 -top-32 h-[500px] w-[500px] rounded-full bg-accent/[0.03] blur-3xl" />
            <div class="absolute -bottom-32 -left-32 h-[400px] w-[400px] rounded-full bg-accent/[0.04] blur-3xl" />
        </div>

        <div class="relative w-full max-w-[400px]">
            <!-- Brand Header -->
            <div class="mb-8 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-card bg-gradient-to-br from-accent to-accent-hover shadow-overlay shadow-rest/25">
                    <ShoppingCartIcon class="h-6 w-6 text-accent-fg" aria-hidden="true" />
                </div>
                <h1 class="mt-4 text-heading font-bold tracking-tight text-ink-1">TindaPOS</h1>
                <p class="mt-1 text-ui text-ink-3">Point of Sale</p>
            </div>

            <!-- Content Card -->
            <div class="animate-fade-in-up rounded-card border border-line/80 bg-surface-1/80 p-6 shadow-overlay backdrop-blur-xl sm:p-8">
                <slot />
            </div>

            <!--
                Reachable before sign-in on purpose. The theme is stored per
                device, not per account, and a shared till in a dim shop is
                exactly where somebody wants to set it — which is a screen
                they are looking at before they have a session.
            -->
            <div class="mt-6 flex justify-center">
                <ThemeToggle />
            </div>
        </div>
    </div>
</template>
