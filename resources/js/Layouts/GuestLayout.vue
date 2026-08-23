<script setup>
import { ShoppingCartIcon } from '@heroicons/vue/24/outline'
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-50 px-4 py-8">
        <!-- Subtle background decoration -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -right-32 -top-32 h-[500px] w-[500px] rounded-full bg-brand-500/[0.03] blur-3xl" />
            <div class="absolute -bottom-32 -left-32 h-[400px] w-[400px] rounded-full bg-brand-500/[0.04] blur-3xl" />
        </div>

        <div class="relative w-full max-w-[400px]">
            <!-- Brand Header -->
            <div class="mb-8 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 shadow-lg shadow-brand-500/25">
                    <ShoppingCartIcon class="h-6 w-6 text-white" aria-hidden="true" />
                </div>
                <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900">TindaPOS</h1>
                <p class="mt-1 text-sm text-slate-500">Point of Sale</p>
            </div>

            <!-- Content Card -->
            <div class="animate-fade-in-up rounded-2xl border border-slate-200/80 bg-white/80 p-6 shadow-elevated backdrop-blur-xl sm:p-8">
                <slot />
            </div>
        </div>
    </div>
</template>
