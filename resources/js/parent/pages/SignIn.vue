<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, ApiError } from '../api.js';
import { signIn } from '../session.js';
import ErrorNote from '../components/ErrorNote.vue';
import PrimaryButton from '../components/PrimaryButton.vue';
import TextField from '../components/TextField.vue';

const router = useRouter();
const phone = ref('');
const password = ref('');
const busy = ref(false);
const error = ref(null);

async function submit() {
    busy.value = true;
    error.value = null;
    try {
        const { token } = await api('auth/login', { method: 'POST', body: { phone: phone.value, password: password.value, device_name: navigator.userAgent.slice(0, 100) } });
        signIn(token);
        router.replace({ name: 'children' });
    } catch (e) {
        error.value = e instanceof ApiError ? e : new ApiError(0, 'Something went wrong. Please try again.');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <section class="space-y-6 pt-4">
        <div>
            <h2 class="text-2xl font-semibold">Sign in</h2>
            <p class="mt-1 text-zinc-600">Use the phone number the school has for you.</p>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="submit">
            <TextField v-model="phone" label="Phone number" type="tel" inputmode="tel" autocomplete="tel" placeholder="024 412 3456" :error="error?.field('phone')" required />
            <TextField v-model="password" label="Password" type="password" autocomplete="current-password" :error="error?.field('password')" required />
            <ErrorNote :message="error && !error.field('phone') && !error.field('password') ? error.message : null" />
            <PrimaryButton :busy="busy">{{ busy ? 'Signing in…' : 'Sign in' }}</PrimaryButton>
        </form>

        <div class="space-y-2 rounded-lg border border-zinc-200 bg-white p-4">
            <p class="font-medium">First time here?</p>
            <p class="text-sm text-zinc-600">We'll text a code to your phone so you can choose a password.</p>
            <RouterLink :to="{ name: 'set-password' }" class="inline-block font-semibold text-zinc-900 underline">Get started</RouterLink>
            <p class="pt-2 text-sm text-zinc-600">
                Forgot your password?
                <RouterLink :to="{ name: 'set-password', query: { forgot: 1 } }" class="font-semibold text-zinc-900 underline">Set a new one</RouterLink>
            </p>
        </div>
    </section>
</template>
