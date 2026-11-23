<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, ApiError } from '../api.js';
import { session, signIn } from '../session.js';
import AuthHeader from '../components/AuthHeader.vue';
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

const other = computed(() => (error.value && !error.value.field('phone') && !error.value.field('password') ? error.value.message : null));
</script>

<template>
    <section class="mx-auto max-w-sm">
        <AuthHeader :title="`Welcome to ${session.school.name}`" text="Follow your children's attendance, fees and school news." />

        <form class="space-y-4 rounded-2xl bg-surface p-5 ring-1 ring-line" novalidate @submit.prevent="submit">
            <TextField v-model="phone" label="Phone number" type="tel" inputmode="tel" autocomplete="tel" placeholder="024 412 3456" :error="error?.field('phone')" required />
            <TextField v-model="password" label="Password" type="password" autocomplete="current-password" :error="error?.field('password')" required />
            <ErrorNote :message="other" />
            <PrimaryButton :busy="busy">{{ busy ? 'Signing in…' : 'Sign in' }}</PrimaryButton>
            <RouterLink :to="{ name: 'set-password', query: { forgot: 1 } }" class="block text-center text-sm font-bold text-muted hover:text-ink">Forgot your password?</RouterLink>
        </form>

        <div class="mt-5 rounded-2xl p-5 text-center ring-1 ring-line">
            <p class="font-bold">First time here?</p>
            <p class="mt-1 text-sm text-muted">Use the phone number the school has for you. We text you a code so you can choose a password.</p>
            <RouterLink :to="{ name: 'set-password' }" class="press mt-3 inline-flex rounded-xl bg-surface px-4 py-2.5 text-sm font-bold ring-1 ring-line hover:bg-paper">Get started</RouterLink>
        </div>
    </section>
</template>
