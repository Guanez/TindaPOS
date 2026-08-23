<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { UserPlusIcon, PencilSquareIcon, NoSymbolIcon, XMarkIcon } from '@heroicons/vue/24/outline';

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
                    <h1 class="text-xl font-bold tracking-tight text-slate-900">Staff</h1>
                    <p class="mt-0.5 text-[13px] text-slate-500">Who can sign in to this store</p>
                </div>

                <button class="btn-primary" @click="openCreate">
                    <UserPlusIcon class="h-4 w-4" aria-hidden="true" />
                    Add account
                </button>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Name</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Username</th>
                                <th class="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">Role</th>
                                <th class="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">Status</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="user in users" :key="user.id" :class="user.is_active ? '' : 'bg-slate-50/60'">
                                <td class="px-4 py-3">
                                    <span class="text-[13px] font-semibold text-slate-800">{{ user.name }}</span>
                                    <span v-if="user.is_self" class="ml-2 text-[11px] font-medium text-slate-400">you</span>
                                    <span v-if="user.email" class="block text-[11px] text-slate-400">{{ user.email }}</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-[13px] text-slate-600">{{ user.username }}</td>
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
                                        <button class="flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-50"
                                            style="transition: background-color 0.15s;" @click="openEdit(user)">
                                            <PencilSquareIcon class="h-3.5 w-3.5" aria-hidden="true" /> Edit
                                        </button>
                                        <button v-if="user.is_active && !user.is_self"
                                            class="flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-[11px] font-semibold"
                                            :class="confirmingDeactivate === user.id
                                                ? 'border-red-500 bg-red-500 text-white'
                                                : 'border-red-200 text-red-600 hover:bg-red-50'"
                                            style="transition: background-color 0.15s;"
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

            <p class="text-[12px] text-slate-400">
                Accounts are deactivated rather than deleted, so past sales keep showing who rang them up.
                A deactivated person is signed out on their next action.
            </p>
        </div>

        <!-- ADD / EDIT -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
                role="dialog" aria-modal="true" :aria-label="editing ? 'Edit account' : 'Add account'">
                <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-6 shadow-elevated animate-scale-in">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-900">{{ editing ? 'Edit account' : 'Add account' }}</h3>
                        <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-50" aria-label="Close" @click="showModal = false">
                            <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="save">
                        <div>
                            <label class="block text-[13px] font-semibold text-slate-700">Full name <span class="text-red-400">*</span></label>
                            <input v-model="form.name" type="text" required class="input-field mt-1.5 w-full" />
                            <p v-if="form.errors.name" class="mt-1 text-[12px] text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-[13px] font-semibold text-slate-700">Username <span class="text-red-400">*</span></label>
                            <input v-model="form.username" type="text" required autocomplete="off" class="input-field mt-1.5 w-full font-mono" />
                            <p v-if="form.errors.username" class="mt-1 text-[12px] text-red-500">{{ form.errors.username }}</p>
                        </div>

                        <div>
                            <label class="block text-[13px] font-semibold text-slate-700">Email</label>
                            <input v-model="form.email" type="email" class="input-field mt-1.5 w-full" placeholder="Optional" />
                            <p v-if="form.errors.email" class="mt-1 text-[12px] text-red-500">{{ form.errors.email }}</p>
                        </div>

                        <fieldset>
                            <legend class="text-[13px] font-semibold text-slate-700">Role</legend>
                            <div class="mt-2 space-y-1.5">
                                <label v-for="role in availableRoles()" :key="role.value"
                                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-3"
                                    :class="form.role === role.value ? 'border-brand-500 bg-brand-50/60' : 'border-slate-200 hover:bg-slate-50'"
                                    style="transition: background-color 0.15s, border-color 0.15s;">
                                    <input v-model="form.role" :value="role.value" type="radio" name="role"
                                        class="mt-0.5 border-slate-300 text-brand-600 focus:ring-brand-500" />
                                    <span>
                                        <span class="block text-[13px] font-semibold text-slate-800">{{ role.label }}</span>
                                        <span class="block text-[11px] text-slate-500">{{ role.hint }}</span>
                                    </span>
                                </label>
                            </div>
                            <p v-if="form.errors.role" class="mt-1 text-[12px] text-red-500">{{ form.errors.role }}</p>
                        </fieldset>

                        <div>
                            <label class="block text-[13px] font-semibold text-slate-700">
                                {{ editing ? 'New password' : 'Password' }}
                                <span v-if="!editing" class="text-red-400">*</span>
                            </label>
                            <input v-model="form.password" type="password" autocomplete="new-password"
                                :required="!editing" class="input-field mt-1.5 w-full"
                                :placeholder="editing ? 'Leave blank to keep the current one' : 'At least 8 characters'" />
                            <p v-if="form.errors.password" class="mt-1 text-[12px] text-red-500">{{ form.errors.password }}</p>
                        </div>

                        <div v-if="form.password">
                            <label class="block text-[13px] font-semibold text-slate-700">Confirm password</label>
                            <input v-model="form.password_confirmation" type="password" autocomplete="new-password"
                                class="input-field mt-1.5 w-full" />
                        </div>

                        <label v-if="editing && !editing.is_self" class="flex items-center gap-2.5">
                            <input v-model="form.is_active" type="checkbox"
                                class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                            <span class="text-[13px] text-slate-600">Account is active</span>
                        </label>

                        <div class="flex gap-3 pt-1">
                            <button type="button" class="btn-secondary flex-1 justify-center" @click="showModal = false">Cancel</button>
                            <button type="submit" :disabled="form.processing" class="btn-primary flex-1 justify-center disabled:opacity-50">
                                {{ form.processing ? 'Saving…' : (editing ? 'Save changes' : 'Create account') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
