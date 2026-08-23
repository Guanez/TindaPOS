<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import {
    BuildingStorefrontIcon,
    ArrowRightStartOnRectangleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const user = computed(() => page.props.auth.user)

/*
 * Deliberately a different shell from AppLayout — slate rather than the brand
 * blue, no store nav, no till. A platform admin flips between this and a real
 * shop all day, and the two should never be mistakable at a glance.
 */

const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

const triggerToast = (msg, type = 'success') => {
    toastMessage.value = msg
    toastType.value = type
    showToast.value = true
    setTimeout(() => (showToast.value = false), 4000)
}

watch(
    () => page.props.flash,
    (f) => {
        if (f?.success) triggerToast(f.success, 'success')
        if (f?.error) triggerToast(f.error, 'error')
    },
    { immediate: true }
)

const logout = () => router.post(route('logout'))

const clock = ref('')
let clockInterval = null
const updateClock = () => {
    clock.value = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
}

onMounted(() => {
    updateClock()
    clockInterval = setInterval(updateClock, 30000)
})
onUnmounted(() => clearInterval(clockInterval))
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-100">
        <header class="border-b border-slate-800 bg-slate-900">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 lg:px-6">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10">
                    <BuildingStorefrontIcon class="h-5 w-5 text-white" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-[15px] font-bold tracking-tight text-white">TindaPOS</h1>
                    <p class="text-[11px] font-medium text-slate-400">Platform Console</p>
                </div>

                <div class="ml-auto flex items-center gap-4">
                    <span class="hidden text-[13px] font-medium tabular-nums text-slate-500 sm:inline">
                        {{ clock }}
                    </span>
                    <div class="hidden text-right sm:block">
                        <p class="text-[13px] font-semibold text-white">{{ user?.name }}</p>
                        <p class="text-[11px] font-medium text-slate-400">Platform Admin</p>
                    </div>
                    <button
                        @click="logout"
                        aria-label="Sign out"
                        class="rounded-xl border border-slate-700 px-3 py-2 text-[13px] font-medium text-slate-300 hover:border-red-500/40 hover:bg-red-500/10 hover:text-red-300"
                        style="transition: background-color 0.15s, color 0.15s, border-color 0.15s;"
                    >
                        <ArrowRightStartOnRectangleIcon class="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 p-4 lg:p-6" id="main-content">
            <slot />
        </main>

        <Teleport to="body">
            <div aria-live="polite" aria-atomic="true" class="fixed bottom-5 right-5 z-50">
                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="translate-y-3 opacity-0 scale-95"
                    enter-to-class="translate-y-0 opacity-100 scale-100"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="translate-y-0 opacity-100 scale-100"
                    leave-to-class="translate-y-3 opacity-0 scale-95"
                >
                    <div
                        v-if="showToast"
                        role="status"
                        class="flex items-center gap-2.5 rounded-xl px-4 py-3 text-[13px] font-medium text-white shadow-elevated"
                        :class="toastType === 'success' ? 'bg-emerald-600' : 'bg-red-600'"
                    >
                        {{ toastMessage }}
                        <button
                            @click="showToast = false"
                            class="ml-1 rounded-md p-0.5 hover:bg-white/20"
                            aria-label="Dismiss notification"
                        >
                            <XMarkIcon class="h-3.5 w-3.5" aria-hidden="true" />
                        </button>
                    </div>
                </Transition>
            </div>
        </Teleport>
    </div>
</template>
