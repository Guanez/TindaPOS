<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { QrCodeIcon, PrinterIcon, ClipboardIcon, CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    store: { type: Object, required: true },
    orderUrl: { type: String, required: true },
    qrSvg: { type: String, required: true },
});

const form = useForm({
    name: props.store.name,
    address: props.store.address ?? '',
    phone: props.store.phone ?? '',
    receipt_footer: props.store.receipt_footer ?? '',
    currency_symbol: props.store.currency_symbol ?? 'P',
    online_ordering_enabled: props.store.online_ordering_enabled,
});

const save = () => form.put(route('store.update'), { preserveScroll: true });

const copied = ref(false);

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
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Store Settings</h1>
                <p class="mt-0.5 text-[13px] text-slate-500">Your shop's details and its customer ordering code</p>
            </div>

            <div class="grid gap-5 lg:grid-cols-5">
                <!-- Details -->
                <form class="card space-y-4 p-6 lg:col-span-3" @submit.prevent="save">
                    <div>
                        <label class="block text-[13px] font-semibold text-slate-700">Store name <span class="text-red-400">*</span></label>
                        <input v-model="form.name" type="text" required class="input-field mt-1.5 w-full" />
                        <p v-if="form.errors.name" class="mt-1 text-[12px] text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-[13px] font-semibold text-slate-700">Address</label>
                        <input v-model="form.address" type="text" class="input-field mt-1.5 w-full" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[13px] font-semibold text-slate-700">Phone</label>
                            <input v-model="form.phone" type="text" class="input-field mt-1.5 w-full" />
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-slate-700">Currency symbol</label>
                            <input v-model="form.currency_symbol" type="text" maxlength="5" class="input-field mt-1.5 w-full" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[13px] font-semibold text-slate-700">Receipt footer</label>
                        <input v-model="form.receipt_footer" type="text" class="input-field mt-1.5 w-full"
                            placeholder="Salamat po! Please come again." />
                    </div>

                    <label class="flex items-start gap-3 rounded-xl border p-3"
                        :class="form.online_ordering_enabled ? 'border-emerald-300 bg-emerald-50/60' : 'border-slate-200'">
                        <input v-model="form.online_ordering_enabled" type="checkbox"
                            class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                        <span>
                            <span class="block text-[13px] font-semibold text-slate-800">Accept customer QR orders</span>
                            <span class="block text-[12px] text-slate-500">
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
                    <h2 class="flex items-center gap-2 self-start text-[13px] font-bold text-slate-900">
                        <QrCodeIcon class="h-4 w-4 text-brand-600" aria-hidden="true" />
                        Customer QR code
                    </h2>
                    <p class="mt-1 self-start text-left text-[12px] text-slate-500">
                        Print this and put it on the counter. Scanning it opens your menu.
                    </p>

                    <!-- Generated server-side by the QR library from our own URL. -->
                    <div class="mt-4 w-full max-w-[240px] rounded-2xl border border-slate-200 bg-white p-3" v-html="qrSvg" />

                    <p class="mt-3 w-full break-all rounded-lg bg-slate-50 px-3 py-2 font-mono text-[11px] text-slate-500">
                        {{ orderUrl }}
                    </p>

                    <div class="mt-3 flex w-full gap-2">
                        <button type="button" class="btn-secondary flex-1 justify-center !py-2 text-[12px]" @click="copyLink">
                            <component :is="copied ? CheckIcon : ClipboardIcon" class="h-3.5 w-3.5" aria-hidden="true" />
                            {{ copied ? 'Copied' : 'Copy link' }}
                        </button>
                        <a :href="route('store.qr')" target="_blank"
                            class="btn-primary flex-1 justify-center !py-2 text-[12px]">
                            <PrinterIcon class="h-3.5 w-3.5" aria-hidden="true" />
                            Print card
                        </a>
                    </div>

                    <p v-if="!store.online_ordering_enabled"
                        class="mt-3 w-full rounded-lg bg-amber-50 px-3 py-2 text-left text-[11px] font-medium text-amber-800">
                        Ordering is currently off, so this code will not work for customers yet.
                    </p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
