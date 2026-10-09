<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div class="mb-6">
            <h1 class="font-display text-2xl font-semibold text-ori-blue-900">Sign in</h1>
            <p class="mt-1 text-sm text-ori-blue-600">
                Access the ORI research involvement workflow.
            </p>
        </div>

        <div v-if="status" class="mb-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="ori-input mt-1"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    type="password"
                    class="ori-input mt-1"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-ori-blue-600">Remember me</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-ori-blue-700 underline"
                >
                    Forgot password?
                </Link>
            </div>

            <button
                type="submit"
                class="ori-btn-accent w-full"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Enter ORI portal
            </button>

            <div class="rounded-xl bg-ori-blue-50 p-3 text-xs text-ori-blue-700">
                Demo accounts use password <strong>password</strong>:
                faculty@lnu.edu.ph · coordinator@lnu.edu.ph · admin@lnu.edu.ph
            </div>
        </form>
    </GuestLayout>
</template>
