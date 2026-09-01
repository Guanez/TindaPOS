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
                    class="text-ui font-medium text-ink-3 hover:text-ink-2"
                >
                    &larr; Back to stores
                </Link>
                <h2 class="mt-2 text-heading font-bold tracking-tight text-ink-1">{{ store.name }}</h2>
                <p class="mt-0.5 font-mono text-ui text-ink-3">/s/{{ store.slug }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <section class="rounded-card bg-surface-1 p-5 shadow-rest">
                    <h3 class="mb-4 text-label font-bold uppercase tracking-widest text-ink-3">Details</h3>

                    <div class="space-y-4">
                        <div>
                            <label for="name" class="mb-1 block text-ui font-semibold text-ink-2">Store name</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-control border-line text-body focus:border-accent focus:ring-accent"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-meta text-stop-ink">{{ form.errors.name }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="type" class="mb-1 block text-ui font-semibold text-ink-2">Type</label>
                                <select
                                    id="type"
                                    v-model="form.type"
                                    class="w-full rounded-control border-line text-body focus:border-accent focus:ring-accent"
                                >
                                    <option value="cafe">Cafe</option>
                                    <option value="sari_sari">Sari-Sari Store</option>
                                </select>
                            </div>

                            <div>
                                <label for="currency_symbol" class="mb-1 block text-ui font-semibold text-ink-2">
                                    Currency symbol
                                </label>
                                <input
                                    id="currency_symbol"
                                    v-model="form.currency_symbol"
                                    type="text"
                                    class="w-full rounded-control border-line text-body focus:border-accent focus:ring-accent"
                                />
                            </div>
                        </div>

                        <div>
                            <label for="address" class="mb-1 block text-ui font-semibold text-ink-2">Address</label>
                            <input
                                id="address"
                                v-model="form.address"
                                type="text"
                                class="w-full rounded-control border-line text-body focus:border-accent focus:ring-accent"
                            />
                        </div>

                        <div>
                            <label for="phone" class="mb-1 block text-ui font-semibold text-ink-2">Phone</label>
                            <input
                                id="phone"
                                v-model="form.phone"
                                type="text"
                                class="w-full rounded-control border-line text-body focus:border-accent focus:ring-accent"
                            />
                        </div>

                        <label class="flex items-start gap-3 rounded-control bg-surface-2 p-3">
                            <input
                                v-model="form.online_ordering_enabled"
                                type="checkbox"
                                class="mt-0.5 rounded border-line-strong text-accent-ink focus:ring-accent"
                            />
                            <span>
                                <span class="block text-ui font-semibold text-ink-2">QR ordering</span>
                                <span class="block text-meta text-ink-3">
                                    Lets customers order from their phone at /s/{{ store.slug }}.
                                </span>
                            </span>
                        </label>
                    </div>
                </section>

                <!-- Staff, read-only: the shop manages its own people. -->
                <section class="rounded-card bg-surface-1 p-5 shadow-rest">
                    <h3 class="mb-1 text-label font-bold uppercase tracking-widest text-ink-3">Staff</h3>
                    <p class="mb-4 text-meta text-ink-3">
                        Managed by the shop. Enter the store to change these.
                    </p>

                    <ul v-if="staff.length" class="divide-y divide-line">
                        <li v-for="person in staff" :key="person.id" class="flex items-center gap-3 py-2.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-ui font-semibold text-ink-1">{{ person.name }}</p>
                                <p class="font-mono text-meta text-ink-3">{{ person.username }}</p>
                            </div>
                            <span class="rounded-control bg-surface-3 px-1.5 py-0.5 text-meta font-semibold text-ink-2">
                                {{ person.role_label }}
                            </span>
                            <span
                                v-if="!person.is_active"
                                class="rounded-control bg-stop-tint px-1.5 py-0.5 text-meta font-semibold text-stop-ink"
                            >
                                Disabled
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-ui text-ink-3">No staff accounts.</p>
                </section>

                <div class="flex justify-end gap-2">
                    <Link
                        :href="route('platform.stores.index')"
                        class="rounded-control border border-line bg-surface-1 px-4 py-2.5 text-ui font-medium text-ink-2 hover:bg-surface-2"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-control bg-action px-5 py-2.5 text-ui font-semibold text-action-fg hover:bg-action-hover disabled:opacity-50"
                        style="transition: background-color var(--t-fast);"
                    >
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </PlatformLayout>
</template>
