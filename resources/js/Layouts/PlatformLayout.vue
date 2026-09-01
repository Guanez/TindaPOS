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
 * Deliberately a different shell from AppLayout — a filled action bar rather
 * than a pale one, no store nav, no till. A platform admin flips between this
 * and a real shop all day, and the two should never be mistakable at a glance.
 */

/* Success dismisses itself, an error waits to be read. See AppLayout. */
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

let toastTimer = null

const dismissToast = () => {
    clearTimeout(toastTimer)
    showToast.value = false
}

const triggerToast = (msg, type = 'success') => {
    clearTimeout(toastTimer)
    toastMessage.value = msg
    toastType.value = type
    showToast.value = true

    if (type === 'success') {
        toastTimer = setTimeout(() => { showToast.value = false }, 4000)
    }
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
    <div class="flex min-h-screen flex-col bg-surface-3">
        <!--
            The console wears the action surface rather than raw ink, because
            `ink-1` is the *text* colour and inverts with the theme: filled
            with it, this whole bar turned near-white in dark while the text on
            it stayed white. `action` is the token for a filled surface — dark
            chrome on a light page, light chrome on a dark one — and
            `action-fg` is guaranteed to be readable on it in both.

            The secondary lines use action-fg at 70% instead of `ink-3`, which
            was never a colour for sitting on ink: it measured 3.31:1 here even
            in light, where this bar was supposed to work.
        -->
        <header class="border-b border-line bg-action">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 lg:px-6">
                <div class="flex h-9 w-9 items-center justify-center rounded-control bg-action-fg/10">
                    <BuildingStorefrontIcon class="h-5 w-5 text-action-fg" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-body font-bold tracking-tight text-action-fg">TindaPOS</h1>
                    <p class="text-meta font-medium text-action-fg/70">Platform Console</p>
                </div>

                <div class="ml-auto flex items-center gap-4">
                    <span class="hidden text-ui font-medium tabular-nums text-action-fg/70 sm:inline">
                        {{ clock }}
                    </span>
                    <div class="hidden text-right sm:block">
                        <p class="text-ui font-semibold text-action-fg">{{ user?.name }}</p>
                        <p class="text-meta font-medium text-action-fg/70">Platform Admin</p>
                    </div>
                    <button
                        @click="logout"
                        aria-label="Sign out"
                        class="rounded-control border border-action-fg/25 px-3 py-2 text-ui font-medium text-action-fg/80 hover:border-stop-mark/50 hover:bg-stop-mark/15 hover:text-action-fg"
                        style="transition: background-color var(--t-fast), color var(--t-fast), border-color var(--t-fast);"
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
            <div
                :aria-live="toastType === 'error' ? 'assertive' : 'polite'"
                aria-atomic="true"
                class="fixed bottom-5 right-5 z-50"
            >
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
                        :role="toastType === 'error' ? 'alert' : 'status'"
                        class="flex items-center gap-2.5 rounded-control px-4 py-3 text-ui font-medium text-on-solid shadow-overlay"
                        :class="toastType === 'success' ? 'bg-ready-solid' : 'bg-stop-solid'"
                    >
                        {{ toastMessage }}
                        <button
                            @click="dismissToast"
                            class="ml-1 rounded-control p-0.5 hover:bg-on-solid/20"
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
