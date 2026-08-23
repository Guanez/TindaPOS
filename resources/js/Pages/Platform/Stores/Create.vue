<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const form = useForm({
    name: '',
    slug: '',
    type: 'cafe',
    address: '',
    phone: '',
    currency_symbol: '₱',
    owner_name: '',
    owner_username: '',
    owner_email: '',
    owner_password: '',
    owner_password_confirmation: '',
});

// Suggest a slug from the name, but stop once it has been typed in by hand —
// it is printed onto the shop's QR code and can never be changed afterwards.
let slugTouched = false;
const slugify = (v) =>
    v.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60);

watch(
    () => form.name,
    (name) => {
        if (!slugTouched) form.slug = slugify(name);
    }
);

const submit = () => form.post(route('platform.stores.store'));
</script>

<template>
    <Head title="New store" />

    <PlatformLayout>
        <div class="mx-auto max-w-2xl animate-fade-in-up">
            <div class="mb-6">
                <Link
                    :href="route('platform.stores.index')"
                    class="text-[13px] font-medium text-slate-500 hover:text-slate-700"
                >
                    &larr; Back to stores
                </Link>
                <h2 class="mt-2 text-xl font-bold tracking-tight text-slate-900">New client store</h2>
                <p class="mt-0.5 text-[13px] text-slate-500">
                    The shop and the person who runs it are created together.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- The shop -->
                <section class="rounded-2xl bg-white p-5 shadow-card">
                    <h3 class="mb-4 text-[11px] font-bold uppercase tracking-widest text-slate-400">The shop</h3>

                    <div class="space-y-4">
                        <div>
                            <label for="name" class="mb-1 block text-[13px] font-semibold text-slate-700">Store name</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-xl border-slate-200 text-[14px] focus:border-brand-500 focus:ring-brand-500"
                                placeholder="Kape Lokal"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-[12px] text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label for="slug" class="mb-1 block text-[13px] font-semibold text-slate-700">
                                Ordering URL
                            </label>
                            <div class="flex items-center rounded-xl border border-slate-200 focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500">
                                <span class="pl-3 font-mono text-[13px] text-slate-400">/s/</span>
                                <input
                                    id="slug"
                                    v-model="form.slug"
                                    @input="slugTouched = true"
                                    type="text"
                                    class="w-full border-0 bg-transparent font-mono text-[14px] focus:ring-0"
                                    placeholder="kape-lokal"
                                />
                            </div>
                            <p class="mt-1 text-[12px] text-slate-500">
                                Printed on their QR code — this cannot be changed later.
                            </p>
                            <p v-if="form.errors.slug" class="mt-1 text-[12px] text-red-600">{{ form.errors.slug }}</p>
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
                                <p v-if="form.errors.type" class="mt-1 text-[12px] text-red-600">{{ form.errors.type }}</p>
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
                                <p class="mt-1 text-[12px] text-slate-500">
                                    Use a plain P if their receipt printer cannot render &#8369;.
                                </p>
                                <p v-if="form.errors.currency_symbol" class="mt-1 text-[12px] text-red-600">
                                    {{ form.errors.currency_symbol }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label for="address" class="mb-1 block text-[13px] font-semibold text-slate-700">
                                Address <span class="font-normal text-slate-400">(optional)</span>
                            </label>
                            <input
                                id="address"
                                v-model="form.address"
                                type="text"
                                class="w-full rounded-xl border-slate-200 text-[14px] focus:border-brand-500 focus:ring-brand-500"
                            />
                            <p v-if="form.errors.address" class="mt-1 text-[12px] text-red-600">{{ form.errors.address }}</p>
                        </div>

                        <div>
                            <label for="phone" class="mb-1 block text-[13px] font-semibold text-slate-700">
                                Phone <span class="font-normal text-slate-400">(optional)</span>
                            </label>
                            <input
                                id="phone"
                                v-model="form.phone"
                                type="text"
                                class="w-full rounded-xl border-slate-200 text-[14px] focus:border-brand-500 focus:ring-brand-500"
                                placeholder="0917-555-0123"
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-[12px] text-red-600">{{ form.errors.phone }}</p>
                        </div>
                    </div>
                </section>

                <!-- The owner -->
                <section class="rounded-2xl bg-white p-5 shadow-card">
                    <h3 class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-400">Owner account</h3>
                    <p class="mb-4 text-[12px] text-slate-500">
                        They sign in with this username and can create the rest of their staff themselves.
                    </p>

                    <div class="space-y-4">
                        <div>
                            <label for="owner_name" class="mb-1 block text-[13px] font-semibold text-slate-700">Full name</label>
                            <input
                                id="owner_name"
                                v-model="form.owner_name"
                                type="text"
                                class="w-full rounded-xl border-slate-200 text-[14px] focus:border-brand-500 focus:ring-brand-500"
                            />
                            <p v-if="form.errors.owner_name" class="mt-1 text-[12px] text-red-600">
                                {{ form.errors.owner_name }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="owner_username" class="mb-1 block text-[13px] font-semibold text-slate-700">
                                    Username
                                </label>
                                <input
                                    id="owner_username"
                                    v-model="form.owner_username"
                                    type="text"
                                    autocomplete="off"
                                    class="w-full rounded-xl border-slate-200 font-mono text-[14px] focus:border-brand-500 focus:ring-brand-500"
                                />
                                <p v-if="form.errors.owner_username" class="mt-1 text-[12px] text-red-600">
                                    {{ form.errors.owner_username }}
                                </p>
                            </div>

                            <div>
                                <label for="owner_email" class="mb-1 block text-[13px] font-semibold text-slate-700">
                                    Email <span class="font-normal text-slate-400">(optional)</span>
                                </label>
                                <input
                                    id="owner_email"
                                    v-model="form.owner_email"
                                    type="email"
                                    class="w-full rounded-xl border-slate-200 text-[14px] focus:border-brand-500 focus:ring-brand-500"
                                />
                                <p v-if="form.errors.owner_email" class="mt-1 text-[12px] text-red-600">
                                    {{ form.errors.owner_email }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="owner_password" class="mb-1 block text-[13px] font-semibold text-slate-700">
                                    Password
                                </label>
                                <input
                                    id="owner_password"
                                    v-model="form.owner_password"
                                    type="password"
                                    autocomplete="new-password"
                                    class="w-full rounded-xl border-slate-200 text-[14px] focus:border-brand-500 focus:ring-brand-500"
                                />
                                <p v-if="form.errors.owner_password" class="mt-1 text-[12px] text-red-600">
                                    {{ form.errors.owner_password }}
                                </p>
                            </div>

                            <div>
                                <label for="owner_password_confirmation" class="mb-1 block text-[13px] font-semibold text-slate-700">
                                    Confirm password
                                </label>
                                <input
                                    id="owner_password_confirmation"
                                    v-model="form.owner_password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    class="w-full rounded-xl border-slate-200 text-[14px] focus:border-brand-500 focus:ring-brand-500"
                                />
                            </div>
                        </div>
                    </div>
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
                        {{ form.processing ? 'Creating…' : 'Create store' }}
                    </button>
                </div>
            </form>
        </div>
    </PlatformLayout>
</template>
