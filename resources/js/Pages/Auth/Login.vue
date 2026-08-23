<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { UserIcon, LockClosedIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

defineProps({
    status: { type: String },
});

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Sign In" />

        <div v-if="status" class="mb-4 rounded-control bg-ready-tint px-4 py-3 text-sm font-medium text-ready-ink">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <!-- Username -->
            <div>
                <label for="username" class="block text-ui font-semibold text-ink-2">Username</label>
                <div class="relative mt-1.5">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <UserIcon class="h-4 w-4 text-ink-3" aria-hidden="true" />
                    </div>
                    <input
                        id="username"
                        type="text"
                        v-model="form.username"
                        required
                        autofocus
                        autocomplete="username"
                        spellcheck="false"
                        placeholder="Enter your username…"
                        class="input-field pl-10 pr-4"
                    />
                </div>
                <InputError class="mt-1.5" :message="form.errors.username" />
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-ui font-semibold text-ink-2">Password</label>
                <div class="relative mt-1.5">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <LockClosedIcon class="h-4 w-4 text-ink-3" aria-hidden="true" />
                    </div>
                    <input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="Enter your password…"
                        class="input-field pl-10 pr-10"
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-ink-3 hover:text-ink-2"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                    >
                        <EyeSlashIcon v-if="showPassword" class="h-4 w-4" aria-hidden="true" />
                        <EyeIcon v-else class="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <!-- Sign In Button -->
            <button
                type="submit"
                :disabled="form.processing"
                class="btn-primary w-full py-2.5 disabled:opacity-50"
            >
                <svg v-if="form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ form.processing ? 'Signing in…' : 'Sign In' }}
            </button>

            <!-- Demo credentials -->
            <div class="rounded-control bg-surface-2 p-3 text-center">
                <p class="text-meta font-medium text-ink-3">Demo credentials</p>
                <p class="mt-0.5 font-mono text-xs text-ink-3">owner / owner123</p>
            </div>
        </form>
    </GuestLayout>
</template>
