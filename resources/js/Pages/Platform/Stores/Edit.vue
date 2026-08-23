<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    store: { type: Object, required: true },
    staff: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.store.name,
    type: props.store.type,
    address: props.store.address ?? '',
    phone: props.store.phone ?? '',
    currency_symbol: props.store.currency_symbol,
    online_ordering_enabled: props.store.online_ordering_enabled,
});

const submit = () => form.put(route('platform.stores.update', props.store.id));
</script>

<template>
    <Head :title="store.name" />

    <PlatformLayout>
        <div class="mx-auto max-w-2xl animate-fade-in-up">
            <div class="mb-6">
                <Link
                    :href="route('platform.stores.index')"
                    class="text-[13px] font-medium text-slate-500 hover:text-slate-700"
                >
                    &larr; Back to stores
                </Link>
                <h2 class="mt-2 text-xl font-bold tracking-tight text-slate-900">{{ store.name }}</h2>
                <p class="mt-0.5 font-mono text-[13px] text-slate-500">/s/{{ store.slug }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <section class="rounded-2xl bg-white p-5 shadow-card">
                    <h3 class="mb-4 text-[11px] font-bold uppercase tracking-widest text-slate-400">Details</h3>

                    <div class="space-y-4">
                        <div>
                            <label for="name" class="mb-1 block text-[13px] font-semibold text-slate-700">Store name</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-xl border-slate-200 text-[14px] focus:border-brand-500 focus:ring-brand-500"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-[12px] text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="type" class="mb-1 block text-[13px] font-semibold text-slate-700">Type</label>
                                <select
                                    id="type"
                                    v-model="form.type"
                                    class="w-full rounded-xl border-slate-200 text-[14px] focus:border-brand-500 focus:ring-brand-500"
                                >
                                    <option value="cafe">Cafe</option>
                                    <option value="sari_sari">Sari-Sari Store</option>
                                </select>
                            </div>

                            <div>
                                <label for="currency_symbol" class="mb-1 block text-[13px] font-semibold text-slate-700">
                                    Currency symbol
                                </label>
                                <input
                                    id="currency_symbol"
                                    v-model="form.currency_symbol"
                                    type="text"
                                    class="w-full rounded-xl border-slate-200 text-[14px] focus:border-brand-500 focus:ring-brand-500"
                                />
                            </div>
                        </div>

                        <div>
                            <label for="address" class="mb-1 block text-[13px] font-semibold text-slate-700">Address</label>
                            <input
                                id="address"
                                v-model="form.address"
                                type="text"
                                class="w-full rounded-xl border-slate-200 text-[14px] focus:border-brand-500 focus:ring-brand-500"
                            />
                        </div>

                        <div>
                            <label for="phone" class="mb-1 block text-[13px] font-semibold text-slate-700">Phone</label>
                            <input
                                id="phone"
                                v-model="form.phone"
                                type="text"
                                class="w-full rounded-xl border-slate-200 text-[14px] focus:border-brand-500 focus:ring-brand-500"
                            />
                        </div>

                        <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-3">
                            <input
                                v-model="form.online_ordering_enabled"
                                type="checkbox"
                                class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            />
                            <span>
                                <span class="block text-[13px] font-semibold text-slate-700">QR ordering</span>
                                <span class="block text-[12px] text-slate-500">
                                    Lets customers order from their phone at /s/{{ store.slug }}.
                                </span>
                            </span>
                        </label>
                    </div>
                </section>

                <!-- Staff, read-only: the shop manages its own people. -->
                <section class="rounded-2xl bg-white p-5 shadow-card">
                    <h3 class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-400">Staff</h3>
                    <p class="mb-4 text-[12px] text-slate-500">
                        Managed by the shop. Enter the store to change these.
                    </p>

                    <ul v-if="staff.length" class="divide-y divide-slate-100">
                        <li v-for="person in staff" :key="person.id" class="flex items-center gap-3 py-2.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] font-semibold text-slate-800">{{ person.name }}</p>
                                <p class="font-mono text-[12px] text-slate-400">{{ person.username }}</p>
                            </div>
                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600">
                                {{ person.role_label }}
                            </span>
                            <span
                                v-if="!person.is_active"
                                class="rounded-md bg-red-50 px-1.5 py-0.5 text-[11px] font-semibold text-red-600"
                            >
                                Disabled
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-[13px] text-slate-500">No staff accounts.</p>
                </section>

                <div class="flex justify-end gap-2">
                    <Link
                        :href="route('platform.stores.index')"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-[13px] font-medium text-slate-600 hover:bg-slate-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-xl bg-slate-900 px-5 py-2.5 text-[13px] font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                        style="transition: background-color 0.15s;"
                    >
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </PlatformLayout>
</template>
