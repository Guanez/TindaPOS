<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Dialog.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { UserPlusIcon, PencilSquareIcon, NoSymbolIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    users: { type: Array, default: () => [] },
    canManageOwners: { type: Boolean, default: false },
});

const roles = [
    { value: 'cashier', label: 'Cashier', hint: 'POS, order queue and sales history' },
    { value: 'admin', label: 'Admin', hint: 'Everything except managing owners' },
    { value: 'owner', label: 'Owner', hint: 'Full access, including other owners' },
];

const availableRoles = () =>
    props.canManageOwners ? roles : roles.filter((r) => r.value !== 'owner');

const showModal = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    username: '',
    email: '',
    role: 'cashier',
    is_active: true,
    password: '',
    password_confirmation: '',
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (user) => {
    editing.value = user;
    form.clearErrors();
    Object.assign(form, {
        name: user.name,
        username: user.username,
        email: user.email ?? '',
        role: user.role,
        is_active: user.is_active,
        password: '',
        password_confirmation: '',
    });
    showModal.value = true;
};

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => { showModal.value = false; } };

    if (editing.value) form.put(route('users.update', editing.value.id), options);
    else form.post(route('users.store'), options);
};

const confirmingDeactivate = ref(null);

const deactivate = (user) => {
    if (confirmingDeactivate.value === user.id) {
        router.delete(route('users.destroy', user.id), { preserveScroll: true });
        confirmingDeactivate.value = null;
        return;
    }

    confirmingDeactivate.value = user.id;
    setTimeout(() => { confirmingDeactivate.value = null; }, 3000);
};

const roleClass = (role) => ({
    owner: 'badge-info',
    admin: 'badge-warning',
    cashier: 'badge-neutral',
}[role] ?? 'badge-neutral');
</script>

<template>
    <AppLayout>
        <Head title="Staff" />

        <div class="space-y-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-ink-1">Staff</h1>
                    <p class="mt-0.5 text-ui text-ink-3">Who can sign in to this store</p>
                </div>

                <button class="btn-primary" @click="openCreate">
                    <UserPlusIcon class="h-4 w-4" aria-hidden="true" />
                    Add account
                </button>
            </div>

            <div class="card overflow-hidden">
                <!-- Phone: one card per account -->
                <ul class="divide-y divide-line md:hidden">
                    <li v-for="user in users" :key="user.id" :class="['p-4', user.is_active ? '' : 'bg-surface-2/60']">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-ui font-semibold text-ink-1">
                                    {{ user.name }}
                                    <span v-if="user.is_self" class="ml-1 text-meta font-medium text-ink-3">you</span>
                                </p>
                                <p class="font-mono text-meta text-ink-3">{{ user.username }}</p>
                                <p v-if="user.email" class="text-meta text-ink-3">{{ user.email }}</p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <span class="badge" :class="roleClass(user.role)">{{ user.role_label }}</span>
                                <span class="badge" :class="user.is_active ? 'badge-success' : 'badge-danger'">
                                    {{ user.is_active ? 'Active' : 'Deactivated' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-3 flex gap-1.5">
                            <button
                                type="button"
                                class="flex flex-1 items-center justify-center gap-1 rounded-control border border-line px-2.5 py-2 text-meta font-semibold text-ink-2 hover:bg-surface-2"
                                style="transition: background-color var(--t-fast);"
                                :aria-label="`Edit ${user.name}`"
                                @click="openEdit(user)"
                            >
                                <PencilSquareIcon class="h-3.5 w-3.5" aria-hidden="true" /> Edit
                            </button>
                            <button
                                v-if="user.is_active && !user.is_self"
                                type="button"
                                class="flex flex-1 items-center justify-center gap-1 rounded-control border px-2.5 py-2 text-meta font-semibold"
                                :class="confirmingDeactivate === user.id
                                    ? 'border-stop-mark bg-stop-solid text-on-solid'
                                    : 'border-stop-tint text-stop-ink hover:bg-stop-tint'"
                                style="transition: background-color var(--t-fast);"
                                :aria-label="confirmingDeactivate === user.id
                                    ? `Confirm deactivating ${user.name}`
                                    : `Deactivate ${user.name}`"
                                @click="deactivate(user)"
                            >
                                <NoSymbolIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                {{ confirmingDeactivate === user.id ? 'Confirm' : 'Deactivate' }}
                            </button>
                        </div>
                    </li>
                </ul>

                <!-- Counter: the full roster -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-line">
                        <caption class="sr-only">Everyone who can sign in to this store</caption>
                        <thead class="bg-surface-2/80">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Name</th>
                                <th scope="col" class="px-4 py-3 text-left text-label font-semibold uppercase tracking-wider text-ink-3">Username</th>
                                <th scope="col" class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Role</th>
                                <th scope="col" class="px-4 py-3 text-center text-label font-semibold uppercase tracking-wider text-ink-3">Status</th>
                                <th scope="col" class="px-4 py-3 text-right text-label font-semibold uppercase tracking-wider text-ink-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="user in users" :key="user.id" :class="user.is_active ? '' : 'bg-surface-2/60'">
                                <td class="px-4 py-3">
                                    <span class="text-ui font-semibold text-ink-1">{{ user.name }}</span>
                                    <span v-if="user.is_self" class="ml-2 text-meta font-medium text-ink-3">you</span>
                                    <span v-if="user.email" class="block text-meta text-ink-3">{{ user.email }}</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-ui text-ink-2">{{ user.username }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge" :class="roleClass(user.role)">{{ user.role_label }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge" :class="user.is_active ? 'badge-success' : 'badge-danger'">
                                        {{ user.is_active ? 'Active' : 'Deactivated' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" class="flex items-center gap-1 rounded-control border border-line px-2.5 py-1.5 text-meta font-semibold text-ink-2 hover:bg-surface-2"
                                            style="transition: background-color var(--t-fast);" :aria-label="`Edit ${user.name}`" @click="openEdit(user)">
                                            <PencilSquareIcon class="h-3.5 w-3.5" aria-hidden="true" /> Edit
                                        </button>
                                        <!--
                                            Two-step, so the label changes under
                                            the pointer. The name is in the
                                            accessible label because "Confirm"
                                            on its own does not say confirm what.
                                        -->
                                        <button v-if="user.is_active && !user.is_self"
                                            type="button"
                                            class="flex items-center gap-1 rounded-control border px-2.5 py-1.5 text-meta font-semibold"
                                            :class="confirmingDeactivate === user.id
                                                ? 'border-stop-mark bg-stop-solid text-on-solid'
                                                : 'border-stop-tint text-stop-ink hover:bg-stop-tint'"
                                            style="transition: background-color var(--t-fast);"
                                            :aria-label="confirmingDeactivate === user.id
                                                ? `Confirm deactivating ${user.name}`
                                                : `Deactivate ${user.name}`"
                                            @click="deactivate(user)">
                                            <NoSymbolIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                            {{ confirmingDeactivate === user.id ? 'Confirm' : 'Deactivate' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-meta text-ink-3">
                Accounts are deactivated rather than deleted, so past sales keep showing who rang them up.
                A deactivated person is signed out on their next action.
            </p>
        </div>

        <!-- ADD / EDIT -->
        <Dialog
            :show="showModal"
            :title="editing ? 'Edit account' : 'Add account'"
            max-width="md"
            close-button
            @close="showModal = false"
        >
            <form id="account-form" class="space-y-4" @submit.prevent="save">
                <div>
                    <label for="account-name" class="block text-ui font-semibold text-ink-2">
                        Full name <span class="text-stop-ink" aria-hidden="true">*</span>
                    </label>
                    <input id="account-name" v-model="form.name" data-autofocus type="text" required aria-required="true"
                        :aria-invalid="form.errors.name ? 'true' : undefined"
                        :aria-describedby="form.errors.name ? 'account-name-error' : undefined"
                        class="input-field mt-1.5 w-full" />
                    <p v-if="form.errors.name" id="account-name-error" class="mt-1 text-meta text-stop-ink">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label for="account-username" class="block text-ui font-semibold text-ink-2">
                        Username <span class="text-stop-ink" aria-hidden="true">*</span>
                    </label>
                    <input id="account-username" v-model="form.username" type="text" required aria-required="true" autocomplete="off"
                        :aria-invalid="form.errors.username ? 'true' : undefined"
                        :aria-describedby="form.errors.username ? 'account-username-error' : undefined"
                        class="input-field mt-1.5 w-full font-mono" />
                    <p v-if="form.errors.username" id="account-username-error" class="mt-1 text-meta text-stop-ink">{{ form.errors.username }}</p>
                </div>

                <div>
                    <label for="account-email" class="block text-ui font-semibold text-ink-2">Email</label>
                    <input id="account-email" v-model="form.email" type="email"
                        :aria-invalid="form.errors.email ? 'true' : undefined"
                        :aria-describedby="form.errors.email ? 'account-email-error' : undefined"
                        class="input-field mt-1.5 w-full" placeholder="Optional" />
                    <p v-if="form.errors.email" id="account-email-error" class="mt-1 text-meta text-stop-ink">{{ form.errors.email }}</p>
                </div>

                <fieldset>
                    <legend class="text-ui font-semibold text-ink-2">Role</legend>
                    <div class="mt-2 space-y-1.5">
                        <label v-for="role in availableRoles()" :key="role.value"
                            class="flex cursor-pointer items-start gap-3 rounded-control border p-3"
                            :class="form.role === role.value ? 'border-accent bg-accent-tint/60' : 'border-line hover:bg-surface-2'"
                            style="transition: background-color var(--t-fast), border-color var(--t-fast);">
                            <input v-model="form.role" :value="role.value" type="radio" name="role"
                                class="mt-0.5 border-line-strong text-accent-ink focus:ring-accent" />
                            <span>
                                <span class="block text-ui font-semibold text-ink-1">{{ role.label }}</span>
                                <span class="block text-meta text-ink-3">{{ role.hint }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors.role" class="mt-1 text-meta text-stop-ink">{{ form.errors.role }}</p>
                </fieldset>

                <div>
                    <label for="account-password" class="block text-ui font-semibold text-ink-2">
                        {{ editing ? 'New password' : 'Password' }}
                        <span v-if="!editing" class="text-stop-ink" aria-hidden="true">*</span>
                    </label>
                    <input id="account-password" v-model="form.password" type="password" autocomplete="new-password"
                        :required="!editing" :aria-required="!editing ? 'true' : undefined"
                        :aria-invalid="form.errors.password ? 'true' : undefined"
                        :aria-describedby="form.errors.password ? 'account-password-error' : undefined"
                        class="input-field mt-1.5 w-full"
                        :placeholder="editing ? 'Leave blank to keep the current one' : 'At least 8 characters'" />
                    <p v-if="form.errors.password" id="account-password-error" class="mt-1 text-meta text-stop-ink">{{ form.errors.password }}</p>
                </div>

                <div v-if="form.password">
                    <label for="account-password-confirm" class="block text-ui font-semibold text-ink-2">Confirm password</label>
                    <input id="account-password-confirm" v-model="form.password_confirmation" type="password" autocomplete="new-password"
                        class="input-field mt-1.5 w-full" />
                </div>

                <label v-if="editing && !editing.is_self" class="flex items-center gap-2.5">
                    <input v-model="form.is_active" type="checkbox"
                        class="rounded border-line-strong text-accent-ink focus:ring-accent" />
                    <span class="text-ui text-ink-2">Account is active</span>
                </label>
            </form>

            <template #footer>
                <div class="flex gap-3">
                    <button type="button" class="btn-secondary flex-1 justify-center" @click="showModal = false">Cancel</button>
                    <button type="submit" form="account-form" :disabled="form.processing" class="btn-primary flex-1 justify-center disabled:opacity-50">
                        {{ form.processing ? 'Saving&hellip;' : (editing ? 'Save changes' : 'Create account') }}
                    </button>
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
