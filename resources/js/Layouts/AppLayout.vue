<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import {
    Squares2X2Icon,
    ShoppingCartIcon,
    ClipboardDocumentListIcon,
    CubeIcon,
    ChartBarIcon,
    Bars3Icon,
    XMarkIcon,
    ArrowRightStartOnRectangleIcon,
    ClockIcon,
    QueueListIcon,
    UsersIcon,
    Cog6ToothIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const user = computed(() => page.props.auth.user)
const store = computed(() => page.props.store)

// Only ever set for a platform admin. When they have stepped into a client
// shop the rest of this layout is deliberately indistinguishable from that
// shop's own — the banner is the one thing that gives it away, which is
// exactly why it has to be loud.
const actingAs = computed(() => page.props.platform?.acting_as ?? null)
const leaveStore = () => router.post(route('platform.leave'))

const sidebarOpen = ref(false)

// Toast notification system
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

const triggerToast = (msg, type = 'success') => {
    toastMessage.value = msg
    toastType.value = type
    showToast.value = true
    setTimeout(() => showToast.value = false, 4000)
}

watch(
    () => page.props.flash,
    (f) => {
        if (f?.success) triggerToast(f.success, 'success')
        if (f?.error)   triggerToast(f.error, 'error')
    },
    { immediate: true }
)

const iconMap = {
    dashboard:         Squares2X2Icon,
    'pos.index':       ShoppingCartIcon,
    'orders.index':    QueueListIcon,
    'sales.index':     ClipboardDocumentListIcon,
    'inventory.index': CubeIcon,
    'reports.index':   ChartBarIcon,
    'users.index':     UsersIcon,
    'store.edit':      Cog6ToothIcon,
    'inventory.logs':  ClockIcon,
}

const navItems = computed(() => {
    const items = [
        { name: 'Dashboard',     href: 'dashboard',       roles: ['owner', 'admin', 'cashier'] },
        { name: 'POS Terminal',  href: 'pos.index',       roles: ['owner', 'admin', 'cashier'] },
        { name: 'Order Queue',   href: 'orders.index',    roles: ['owner', 'admin', 'cashier'] },
        { name: 'Sales History', href: 'sales.index',     roles: ['owner', 'admin', 'cashier'] },
        { name: 'divider',       roles: ['owner', 'admin'] },
        { name: 'Inventory',     href: 'inventory.index', roles: ['owner', 'admin'] },
        { name: 'Stock Logs',    href: 'inventory.logs',  roles: ['owner', 'admin'] },
        { name: 'Reports',       href: 'reports.index',   roles: ['owner', 'admin'] },
        { name: 'divider2',      roles: ['owner', 'admin'] },
        { name: 'Staff',          href: 'users.index',    roles: ['owner', 'admin'] },
        { name: 'Store Settings', href: 'store.edit',     roles: ['owner', 'admin'] },
    ]
    return items.filter(item => item.roles.includes(user.value?.role))
})

const logout = () => router.post(route('logout'))
const isActive = (routeName) => route().current(routeName)

const roleLabel = computed(() => {
    const roles = { owner: 'Store Owner', admin: 'Administrator', cashier: 'Cashier' }
    return roles[user.value?.role] ?? user.value?.role
})

// Live clock
const clock = ref('')
let clockInterval = null
const updateClock = () => {
    const now = new Date()
    clock.value = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
}

// Keyboard shortcut: Escape closes sidebar
const handleKeydown = (e) => {
    if (e.key === 'Escape' && sidebarOpen.value) {
        sidebarOpen.value = false
    }
}

onMounted(() => {
    updateClock()
    clockInterval = setInterval(updateClock, 30000)
    document.addEventListener('keydown', handleKeydown)
})
onUnmounted(() => {
    clearInterval(clockInterval)
    document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <div class="flex h-screen flex-col bg-surface-2/80">
        <!--
            Impersonation banner. Amber and full-bleed on purpose: everything
            below it is a real shop's real till, and the one mistake worth
            engineering against is forgetting whose.
        -->
        <div
            v-if="actingAs"
            role="alert"
            class="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-1.5 bg-wait-mark px-4 py-2 text-ui font-medium text-wait-ink"
        >
            <ExclamationTriangleIcon class="h-4 w-4 shrink-0" aria-hidden="true" />
            <span>
                You are inside <strong class="font-bold">{{ actingAs.name }}</strong> as platform admin.
                Anything you do here is real.
            </span>
            <button
                @click="leaveStore"
                class="ml-auto rounded-control bg-amber-950/10 px-3 py-1 text-meta font-semibold text-wait-ink hover:bg-amber-950/20"
                style="transition: background-color var(--t-fast);"
            >
                Leave store
            </button>
        </div>

    <div class="flex min-h-0 flex-1">
        <!-- Sidebar Overlay (mobile) -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-ink-1/40 backdrop-blur-sm lg:hidden"
                aria-hidden="true"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- Sidebar -->
        <aside
            :aria-label="'Main navigation'"
            class="fixed inset-y-0 left-0 z-40 flex w-[260px] flex-col border-r border-line/60 bg-surface-1/95 backdrop-blur-xl lg:static lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            style="transition: transform var(--t-base) var(--ease-out);"
        >
            <!-- Brand -->
            <div class="flex h-16 items-center gap-3 border-b border-line px-5">
                <div class="flex h-9 w-9 items-center justify-center rounded-control bg-gradient-to-br from-accent to-accent-hover shadow-rest shadow-rest/20">
                    <ShoppingCartIcon class="h-5 w-5 text-white" />
                </div>
                <div>
                    <!--
                        The shop's own name, not a hard-coded tagline: this
                        layout is used by cafes and sari-sari stores alike,
                        and while a platform admin is inside a client it needs
                        to say whose till this is. The store type is
                        deliberately not spelled out here — StoreVocabulary
                        owns every word that varies by type.
                    -->
                    <h1 class="truncate text-body font-bold tracking-tight text-ink-1">
                        {{ store?.name ?? 'TindaPOS' }}
                    </h1>
                    <p class="text-meta font-medium text-ink-3">TindaPOS</p>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto px-3 py-4" role="navigation" aria-label="Primary">
                <p class="mb-2 px-3 text-label font-bold uppercase tracking-widest text-ink-3">Menu</p>
                <template v-for="item in navItems" :key="item.name">
                    <div v-if="item.name.startsWith('divider')" class="my-3 border-t border-line" role="separator" />
                    <Link
                        v-else
                        :href="route(item.href)"
                        class="group mb-0.5 flex items-center gap-3 rounded-control px-3 py-2.5 text-ui font-medium"
                        :class="isActive(item.href)
                            ? 'bg-accent-tint text-accent shadow-rest shadow-rest/50'
                            : 'text-ink-3 hover:bg-surface-2 hover:text-ink-2'"
                        style="transition: background-color var(--t-fast), color var(--t-fast), box-shadow var(--t-fast);"
                        @click="sidebarOpen = false"
                    >
                        <component
                            :is="iconMap[item.href]"
                            class="h-[18px] w-[18px] shrink-0"
                            :class="isActive(item.href) ? 'text-accent' : 'text-ink-3 group-hover:text-ink-3'"
                            aria-hidden="true"
                            style="transition: color var(--t-fast);"
                        />
                        {{ item.name }}
                        <span
                            v-if="isActive(item.href)"
                            class="ml-auto h-1.5 w-1.5 rounded-full bg-accent"
                        />
                    </Link>
                </template>
            </nav>

            <!-- User Footer -->
            <div class="border-t border-line p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-accent to-accent-hover text-sm font-bold text-accent-fg shadow-rest">
                        {{ user?.name?.charAt(0)?.toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-ui font-semibold text-ink-1">{{ user?.name }}</p>
                        <p class="text-meta font-medium text-ink-3">{{ roleLabel }}</p>
                    </div>
                </div>
                <button
                    @click="logout"
                    aria-label="Sign out"
                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-control border border-line px-3 py-2 text-ui font-medium text-ink-3 hover:border-stop-tint hover:bg-stop-tint hover:text-stop-ink"
                    style="transition: background-color var(--t-fast), color var(--t-fast), border-color var(--t-fast);"
                >
                    <ArrowRightStartOnRectangleIcon class="h-4 w-4" aria-hidden="true" />
                    Sign Out
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="flex h-14 items-center gap-4 border-b border-line/60 bg-surface-1/80 px-4 backdrop-blur-xl lg:px-6">
                <button
                    class="rounded-control p-1.5 text-ink-3 hover:bg-surface-2 hover:text-ink-2 lg:hidden"
                    style="transition: background-color var(--t-fast), color var(--t-fast);"
                    aria-label="Open sidebar menu"
                    @click="sidebarOpen = true"
                >
                    <Bars3Icon class="h-5 w-5" aria-hidden="true" />
                </button>
                <slot name="header" />
                <div class="ml-auto flex items-center gap-3">
                    <span class="hidden text-ui font-medium tabular-nums text-ink-3 sm:inline">{{ clock }}</span>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-6" id="main-content">
                <slot />
            </main>
        </div>

        <!-- Toast Notification -->
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
                        class="flex items-center gap-2.5 rounded-control px-4 py-3 text-ui font-medium text-white shadow-overlay"
                        :class="toastType === 'success' ? 'bg-ready-solid' : 'bg-stop-solid'"
                    >
                        <svg v-if="toastType === 'success'" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                        </svg>
                        <svg v-else class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                        {{ toastMessage }}
                        <button
                            @click="showToast = false"
                            class="ml-1 rounded-control p-0.5 hover:bg-surface-1/20"
                            aria-label="Dismiss notification"
                        >
                            <XMarkIcon class="h-3.5 w-3.5" aria-hidden="true" />
                        </button>
                    </div>
                </Transition>
            </div>
        </Teleport>
    </div>
    </div>
</template>
