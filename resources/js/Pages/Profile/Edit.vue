<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />

    <AppLayout>
        <div class="space-y-5">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-ink-1">Profile</h1>
                <p class="mt-0.5 text-ui text-ink-3">Manage your account settings</p>
            </div>

            <div class="space-y-5">
                <div class="card p-6">
                    <UpdateProfileInformationForm
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div class="card p-6">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="card p-6">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
