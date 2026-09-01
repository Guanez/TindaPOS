<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useId, useSlots, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

/*
 * The one dialog in the app.
 *
 * Every screen used to roll its own overlay: a div with `fixed inset-0`, a
 * panel, and nothing else. That reads fine with a mouse and fails everywhere
 * else — the page behind stayed tabbable, Escape did nothing, and closing a
 * dialog dropped focus back at the top of the document, so a keyboard user
 * landed in the sidebar every time they finished a sale.
 *
 * The behaviour that matters is all here: focus goes in, stays in, and comes
 * back out to the control that opened it.
 */

const props = defineProps({
    show: { type: Boolean, default: false },
    /*
     * The accessible name. Required rather than optional, because a dialog
     * without one is announced as just "dialog" — which tells a screen-reader
     * user that something opened but not what.
     */
    title: { type: String, required: true },
    /* Optional heroicon beside the title, matching the page it came from. */
    icon: { type: [Object, Function], default: null },
    iconClass: { type: String, default: 'text-accent-ink' },
    maxWidth: { type: String, default: 'md' },
    /* 'center' for a desktop dialog, 'bottom' for a phone sheet. */
    placement: { type: String, default: 'center' },
    /* False for a dialog that must be answered rather than dismissed. */
    closeable: { type: Boolean, default: true },
    /* A visible ✕. Escape does not exist on a touch till. */
    closeButton: { type: Boolean, default: false },
    /*
     * For a panel whose own content is the heading — the printed receipt,
     * which opens with the shop's name. `title` still supplies the accessible
     * name; it just is not drawn twice.
     */
    headerless: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const titleId = useId()

/*
 * Named by the heading when this dialog draws one, and by the prop when the
 * page supplies its own header. Both at once would be ambiguous: labelledby
 * silently wins, so a caller correcting the name via `title` would see no
 * effect.
 */
const slots = useSlots()
const usesOwnHeader = computed(() => props.headerless || !!slots.header)
const panel = ref(null)
const previouslyFocused = ref(null)

const maxWidthClass = computed(
    () =>
        ({
            xs: 'max-w-xs',
            sm: 'max-w-sm',
            md: 'max-w-md',
            lg: 'max-w-lg',
            xl: 'max-w-xl',
            '2xl': 'max-w-2xl',
            '3xl': 'max-w-3xl',
        })[props.maxWidth] ?? 'max-w-md',
)

const close = () => {
    if (props.closeable) emit('close')
}

const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',')

/*
 * `getClientRects()` rather than `offsetParent`, which is null for anything
 * positioned `fixed` and would have quietly dropped such a control out of the
 * trap. This also skips the panels a `v-show` has hidden, so tabbing inside
 * the product dialog stays on the tab you are actually looking at.
 */
const focusableInPanel = () =>
    panel.value
        ? Array.from(panel.value.querySelectorAll(FOCUSABLE)).filter(
              (el) => el.getClientRects().length > 0 || el === document.activeElement,
          )
        : []

const trapTab = (event) => {
    const items = focusableInPanel()

    if (items.length === 0) {
        // Nothing to tab to — hold focus on the panel rather than let it out.
        event.preventDefault()
        panel.value?.focus()
        return
    }

    const first = items[0]
    const last = items[items.length - 1]
    const active = document.activeElement

    if (event.shiftKey && (active === first || !panel.value?.contains(active))) {
        event.preventDefault()
        last.focus()
    } else if (!event.shiftKey && active === last) {
        event.preventDefault()
        first.focus()
    }
}

const instance = {
    onKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault()
            close()
        } else if (event.key === 'Tab') {
            trapTab(event)
        }
    },
}

/*
 * A shared stack, not a listener per instance. The POS opens a receipt over a
 * checkout and Sales opens a void confirmation over a detail view; Escape
 * should close the one on top, not all of them at once.
 */
const stack = dialogStack()

const open = async () => {
    previouslyFocused.value =
        document.activeElement instanceof HTMLElement ? document.activeElement : null

    stack.push(instance)
    await nextTick()

    /*
     * Prefer whatever the page marked as the point of the dialog — the cash
     * field, the product name — over the first focusable thing, which is
     * usually a close button.
     */
    const target =
        panel.value?.querySelector('[data-autofocus]') ?? focusableInPanel()[0] ?? panel.value

    target?.focus()
}

const release = () => {
    if (!stack.has(instance)) return

    stack.remove(instance)

    /*
     * Restore focus only if the element is still in the document. After a
     * delete, the row that held the button is gone, and focusing a detached
     * node silently drops focus to <body>.
     */
    const target = previouslyFocused.value
    previouslyFocused.value = null

    if (target?.isConnected) target.focus()
}

watch(() => props.show, (isOpen) => (isOpen ? open() : release()), { immediate: true })

/* A dialog left open through an Inertia visit must not leave the page locked. */
onBeforeUnmount(release)

/*
 * Module scope, so every Dialog shares one stack and one keydown listener, and
 * so the scroll lock is counted rather than toggled — a nested dialog closing
 * should not unlock the page underneath the one still open.
 */
function dialogStack() {
    if (!dialogStack.state) {
        const dialogs = []
        let previousOverflow = ''

        const handler = (event) => dialogs[dialogs.length - 1]?.onKeydown(event)

        dialogStack.state = {
            has: (d) => dialogs.includes(d),
            push(d) {
                if (dialogs.length === 0) {
                    previousOverflow = document.body.style.overflow
                    document.body.style.overflow = 'hidden'
                    document.addEventListener('keydown', handler, true)
                }
                dialogs.push(d)
            },
            remove(d) {
                const at = dialogs.indexOf(d)
                if (at !== -1) dialogs.splice(at, 1)

                if (dialogs.length === 0) {
                    document.body.style.overflow = previousOverflow
                    document.removeEventListener('keydown', handler, true)
                }
            },
        }
    }

    return dialogStack.state
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-fast"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-fast"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex bg-scrim/50 backdrop-blur-sm"
                :class="
                    placement === 'bottom'
                        ? 'items-end justify-center'
                        : 'items-center justify-center p-4'
                "
                @click.self="close"
            >
                <div
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="usesOwnHeader ? title : undefined"
                    :aria-labelledby="usesOwnHeader ? undefined : titleId"
                    tabindex="-1"
                    class="flex w-full flex-col bg-surface-1 shadow-overlay focus:outline-none"
                    :class="[
                        maxWidthClass,
                        placement === 'bottom'
                            ? 'max-h-[85vh] rounded-t-sheet animate-sheet-up'
                            : 'max-h-[90vh] rounded-card animate-scale-in',
                    ]"
                >
                    <slot name="header" :close="close">
                        <div
                            v-if="!headerless"
                            class="flex shrink-0 items-center gap-2 px-6 pt-6"
                            :class="{ 'justify-between': closeButton }"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <component
                                    :is="icon"
                                    v-if="icon"
                                    class="h-5 w-5 shrink-0"
                                    :class="iconClass"
                                    aria-hidden="true"
                                />
                                <h2 :id="titleId" class="truncate text-title font-bold text-ink-1">
                                    {{ title }}
                                </h2>
                            </div>

                            <button
                                v-if="closeButton"
                                type="button"
                                class="shrink-0 rounded-control p-1 text-ink-3 transition-colors hover:bg-surface-2 hover:text-ink-1"
                                aria-label="Close dialog"
                                @click="close"
                            >
                                <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                            </button>
                        </div>
                    </slot>

                    <div
                        class="min-h-0 flex-1 overflow-y-auto px-6 pb-6"
                        :class="usesOwnHeader ? 'pt-6' : 'pt-4'"
                    >
                        <slot />
                    </div>

                    <div v-if="$slots.footer" class="shrink-0 border-t border-line px-6 py-4">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
