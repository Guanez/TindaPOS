<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, onUnmounted } from 'vue';
import { QrCodeIcon, PrinterIcon, ClipboardIcon, CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    store: { type: Object, required: true },
    orderUrl: { type: String, required: true },
    qrSvg: { type: String, required: true },
    qrUnreachable: { type: Boolean, default: false },
});

const form = useForm({
    name: props.store.name,
    address: props.store.address ?? '',
    phone: props.store.phone ?? '',
    receipt_footer: props.store.receipt_footer ?? '',
    currency_symbol: props.store.currency_symbol ?? 'P',
    online_ordering_enabled: props.store.online_ordering_enabled,
    accent: props.store.accent ?? '',
    logo: null,
    remove_logo: false,
});

// ── Branding ────────────────────────────────────────────────────────────
// Only ever applied to the customer menu. The till stays the same colour in
// every shop, because a cashier working two of them should not have to
// re-learn which button is which.
const logoPreview = ref(props.store.logo_url ?? null);

const revokeLogo = () => {
    if (logoPreview.value?.startsWith('blob:')) URL.revokeObjectURL(logoPreview.value);
};

const chooseLogo = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    revokeLogo();
    form.logo = file;
    form.remove_logo = false;
    logoPreview.value = URL.createObjectURL(file);
};

const clearLogo = () => {
    revokeLogo();
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
};

onUnmounted(revokeLogo);

// Mirrors AccentPalette::isReadableAsText. Shown as a note rather than an
// error: a pale brand colour is still the shop's brand, it just gets
// darkened where it has to be read as words.
const accentReadable = computed(() => {
    const hex = form.accent;
    if (!/^#[0-9a-f]{6}$/i.test(hex)) return true;

    const channel = (i) => {
        const v = parseInt(hex.slice(1 + i * 2, 3 + i * 2), 16) / 255;
        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    };
    const l = 0.2126 * channel(0) + 0.7152 * channel(1) + 0.0722 * channel(2);

    return 1.05 / (l + 0.05) >= 4.5;
});

const save = () =>
    form
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(route('store.update'), { preserveScroll: true });

const copied = ref(false);

// ── Retiring the ordering address ───────────────────────────────────────
// Separate from the settings form on purpose: saving a phone number must
// never be able to invalidate a wall full of printed cards.
const changingAddress = ref(false);

const addressForm = useForm({
    slug: props.store.slug,
    confirm: false,
});

const openAddressChange = () => {
    addressForm.reset();
    addressForm.clearErrors();
    changingAddress.value = true;
};

const saveAddress = () => {
    addressForm.put(route('store.address'), {
        preserveScroll: true,
        onSuccess: () => { changingAddress.value = false; },
    });
};

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.orderUrl);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch {
        /* Clipboard blocked — the link is on screen to copy by hand. */
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Store Settings" />

        <div class="space-y-5">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-ink-1">Store Settings</h1>
                <p class="mt-0.5 text-ui text-ink-3">Your shop's details and its customer ordering code</p>
            </div>

            <div class="grid gap-5 lg:grid-cols-5">
                <!-- Details -->
                <form class="card space-y-4 p-6 lg:col-span-3" @submit.prevent="save">
                    <div>
                        <label class="block text-ui font-semibold text-ink-2">Store name <span class="text-stop-ink">*</span></label>
                        <input v-model="form.name" type="text" required class="input-field mt-1.5 w-full" />
                        <p v-if="form.errors.name" class="mt-1 text-meta text-stop-ink">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-ui font-semibold text-ink-2">Address</label>
                        <input v-model="form.address" type="text" class="input-field mt-1.5 w-full" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-ui font-semibold text-ink-2">Phone</label>
                            <input v-model="form.phone" type="text" class="input-field mt-1.5 w-full" />
                        </div>
                        <div>
                            <label class="block text-ui font-semibold text-ink-2">Currency symbol</label>
                            <input v-model="form.currency_symbol" type="text" maxlength="5" class="input-field mt-1.5 w-full" />
                        </div>
                    </div>

                    <!-- Branding: customer menu only -->
                    <div class="rounded-card border border-line bg-surface-2 p-4">
                        <h3 class="text-ui font-bold text-ink-1">Customer menu branding</h3>
                        <p class="mt-0.5 text-meta text-ink-3">
                            Applies to the page customers see after scanning your code. Your staff
                            screens are unaffected.
                        </p>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="accent" class="block text-ui font-semibold text-ink-2">Brand colour</label>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <input
                                        id="accent"
                                        v-model="form.accent"
                                        type="color"
                                        class="h-10 w-12 shrink-0 cursor-pointer rounded-control border border-line-strong bg-surface-1 p-1"
                                    />
                                    <input
                                        v-model="form.accent"
                                        type="text"
                                        maxlength="7"
                                        placeholder="#5B3FD9"
                                        class="input-field font-mono uppercase"
                                    />
                                </div>
                                <p v-if="form.errors.accent" class="mt-1 text-meta text-stop-ink">{{ form.errors.accent }}</p>
                                <p v-else-if="!accentReadable" class="mt-1 text-meta text-wait-ink">
                                    Pale colour — we'll darken it where it has to be read as text,
                                    so your buttons keep the exact shade you picked.
                                </p>
                            </div>

                            <div>
                                <span class="block text-ui font-semibold text-ink-2">Logo</span>
                                <div class="mt-1.5 flex items-center gap-3">
                                    <div class="flex h-10 w-24 shrink-0 items-center justify-center overflow-hidden rounded-control border border-line bg-surface-1">
                                        <img v-if="logoPreview" :src="logoPreview" alt="" class="max-h-full max-w-full object-contain" />
                                        <span v-else class="text-label uppercase tracking-wider text-ink-3">None</span>
                                    </div>
                                    <label class="btn-secondary cursor-pointer !py-2">
                                        {{ logoPreview ? 'Replace' : 'Upload' }}
                                        <input type="file" class="sr-only" accept="image/png,image/webp,image/jpeg" @change="chooseLogo" />
                                    </label>
                                    <button v-if="logoPreview" type="button"
                                        class="text-meta font-semibold text-stop-ink hover:underline" @click="clearLogo">
                                        Remove
                                    </button>
                                </div>
                                <p v-if="form.errors.logo" class="mt-1 text-meta text-stop-ink">{{ form.errors.logo }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-ui font-semibold text-ink-2">Receipt footer</label>
                        <input v-model="form.receipt_footer" type="text" class="input-field mt-1.5 w-full"
                            placeholder="Salamat po! Please come again." />
                    </div>

                    <label class="flex items-start gap-3 rounded-control border p-3"
                        :class="form.online_ordering_enabled ? 'border-emerald-300 bg-ready-tint/60' : 'border-line'">
                        <input v-model="form.online_ordering_enabled" type="checkbox"
                            class="mt-0.5 rounded border-line-strong text-ready-ink focus:ring-emerald-500" />
                        <span>
                            <span class="block text-ui font-semibold text-ink-1">Accept customer QR orders</span>
                            <span class="block text-meta text-ink-3">
                                When this is off, the menu link returns nothing and no new orders can arrive.
                            </span>
                        </span>
                    </label>

                    <div class="flex justify-end pt-1">
                        <button type="submit" :disabled="form.processing" class="btn-primary disabled:opacity-50">
                            {{ form.processing ? 'Saving…' : 'Save settings' }}
                        </button>
                    </div>
                </form>

                <!-- QR -->
                <section class="card flex flex-col items-center p-6 text-center lg:col-span-2">
                    <h2 class="flex items-center gap-2 self-start text-ui font-bold text-ink-1">
                        <QrCodeIcon class="h-4 w-4 text-accent-ink" aria-hidden="true" />
                        Customer QR code
                    </h2>
                    <p class="mt-1 self-start text-left text-meta text-ink-3">
                        Print this and put it on the counter. Scanning it opens your menu.
                    </p>

                    <!-- Generated server-side by the QR library from our own URL. -->
                    <div class="mt-4 w-full max-w-[240px] rounded-card border border-line bg-surface-1 p-3" v-html="qrSvg" />

                    <p class="mt-3 w-full break-all rounded-control bg-surface-2 px-3 py-2 font-mono text-meta text-ink-3">
                        {{ orderUrl }}
                    </p>

                    <!--
                        The code renders and scans fine here; it just resolves
                        to nothing on a phone. Stated on the screen that offers
                        the print button, not left to be found at a counter.
                    -->
                    <div
                        v-if="qrUnreachable"
                        role="alert"
                        class="mt-3 w-full rounded-control border border-wait-mark bg-wait-tint px-3 py-2"
                    >
                        <p class="text-meta font-bold text-wait-ink">Not ready to print</p>
                        <p class="mt-0.5 text-meta leading-relaxed text-wait-ink">
                            This address only works on the computer running the app. Set
                            <span class="font-mono">APP_URL</span> to the one customers will use.
                        </p>
                    </div>

                    <div class="mt-3 flex w-full gap-2">
                        <button type="button" class="btn-secondary flex-1 justify-center !py-2 text-meta" @click="copyLink">
                            <component :is="copied ? CheckIcon : ClipboardIcon" class="h-3.5 w-3.5" aria-hidden="true" />
                            {{ copied ? 'Copied' : 'Copy link' }}
                        </button>
                        <a :href="route('store.qr')" target="_blank"
                            class="btn-primary flex-1 justify-center !py-2 text-meta">
                            <PrinterIcon class="h-3.5 w-3.5" aria-hidden="true" />
                            Print card
                        </a>
                    </div>

                    <p v-if="!store.online_ordering_enabled"
                        class="mt-3 w-full rounded-control bg-wait-tint px-3 py-2 text-left text-meta font-medium text-wait-ink">
                        Ordering is currently off, so this code will not work for customers yet.
                    </p>

                    <!-- Recovery for a code that has been misused. -->
                    <div class="mt-4 w-full border-t border-line pt-4 text-left">
                        <button
                            v-if="!changingAddress"
                            type="button"
                            class="text-meta font-semibold text-ink-3 underline decoration-line-strong underline-offset-2 hover:text-ink-2"
                            @click="openAddressChange"
                        >
                            Change ordering address
                        </button>

                        <form v-else class="space-y-3" @submit.prevent="saveAddress">
                            <div>
                                <label for="slug" class="block text-meta font-semibold text-ink-2">
                                    New ordering address
                                </label>
                                <p class="mt-0.5 text-meta leading-relaxed text-ink-3">
                                    Use this if your code has been shared somewhere you did not intend. The old
                                    address stops working immediately and can never be reused by anyone.
                                </p>
                                <div class="mt-1.5 flex items-center gap-1 rounded-control border border-line bg-surface-1 px-2.5 py-1.5 focus-within:border-accent">
                                    <span class="shrink-0 font-mono text-meta text-ink-3">/s/</span>
                                    <input
                                        id="slug"
                                        v-model="addressForm.slug"
                                        type="text"
                                        class="w-full border-0 p-0 font-mono text-ui focus:ring-0"
                                    />
                                </div>
                                <p v-if="addressForm.errors.slug" class="mt-1 text-meta text-stop-ink">
                                    {{ addressForm.errors.slug }}
                                </p>
                            </div>

                            <label class="flex items-start gap-2.5 rounded-control border border-wait-mark bg-wait-tint p-2.5">
                                <input
                                    v-model="addressForm.confirm"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-amber-400 text-wait-ink focus:ring-amber-500"
                                />
                                <span class="text-meta leading-relaxed text-wait-ink">
                                    I understand every printed card showing
                                    <span class="font-mono font-semibold">/s/{{ store.slug }}</span>
                                    will stop working, and I will print new ones.
                                </span>
                            </label>
                            <p v-if="addressForm.errors.confirm" class="text-meta text-stop-ink">
                                {{ addressForm.errors.confirm }}
                            </p>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="btn-secondary flex-1 justify-center !py-2 text-meta"
                                    @click="changingAddress = false"
                                >Cancel</button>
                                <button
                                    type="submit"
                                    :disabled="addressForm.processing"
                                    class="btn-danger flex-1 justify-center !py-2 text-meta disabled:opacity-50"
                                >
                                    {{ addressForm.processing ? 'Changing…' : 'Change address' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
