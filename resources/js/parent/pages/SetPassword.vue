<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../api.js';
import { signIn } from '../session.js';
import ErrorNote from '../components/ErrorNote.vue';
import PrimaryButton from '../components/PrimaryButton.vue';
import TextField from '../components/TextField.vue';

// Two steps: the phone (we text a code), then the code and a new password. The same screen
// activates the app the first time and sets a new password later (POST auth/set-password).
const route = useRoute();
const router = useRouter();
const forgot = computed(() => route.query.forgot !== undefined);

const step = ref('phone');
const phone = ref('');
const code = ref('');
const password = ref('');
const confirmation = ref('');
const busy = ref(false);
const error = ref(null);
const sentMessage = ref('');
const wait = ref(0);
let countdown = null;

function startCountdown() {
    wait.value = 60;
    clearInterval(countdown);
    countdown = setInterval(() => {
        wait.value -= 1;
        if (wait.value <= 0) {
            clearInterval(countdown);
        }
    }, 1000);
}
onBeforeUnmount(() => clearInterval(countdown));

function asError(e) {
    return e instanceof ApiError ? e : new ApiError(0, 'Something went wrong. Please try again.');
}

async function sendCode() {
    busy.value = true;
    error.value = null;
    try {
        sentMessage.value = (await api('auth/otp', { method: 'POST', body: { phone: phone.value } })).message;
        step.value = 'code';
        startCountdown();
    } catch (e) {
        error.value = asError(e);
    } finally {
        busy.value = false;
    }
}

async function setPassword() {
    busy.value = true;
    error.value = null;
    try {
        const { token } = await api('auth/set-password', {
            method: 'POST',
            body: { phone: phone.value, code: code.value, password: password.value, password_confirmation: confirmation.value, device_name: navigator.userAgent.slice(0, 100) },
        });
        signIn(token);
        router.replace({ name: 'children' });
    } catch (e) {
        error.value = asError(e);
    } finally {
        busy.value = false;
    }
}

const otherError = computed(() => {
    const e = error.value;

    return e && !['phone', 'code', 'password'].some((field) => e.field(field)) ? e.message : null;
});
</script>

<template>
    <section class="space-y-6 pt-4">
        <div>
            <h2 class="text-2xl font-semibold">{{ forgot ? 'Set a new password' : 'Get started' }}</h2>
            <p class="mt-1 text-zinc-600">
                {{ step === 'phone' ? 'Enter the phone number the school has for you. We will text you a 6-digit code.' : sentMessage }}
            </p>
        </div>

        <form v-if="step === 'phone'" class="space-y-4" novalidate @submit.prevent="sendCode">
            <TextField v-model="phone" label="Phone number" type="tel" inputmode="tel" autocomplete="tel" placeholder="024 412 3456" :error="error?.field('phone')" required />
            <ErrorNote :message="otherError" />
            <PrimaryButton :busy="busy">{{ busy ? 'Sending…' : 'Text me a code' }}</PrimaryButton>
        </form>

        <form v-else class="space-y-4" novalidate @submit.prevent="setPassword">
            <TextField v-model="code" label="Code from the SMS" inputmode="numeric" autocomplete="one-time-code" maxlength="6" :error="error?.field('code') ?? error?.field('phone')" required />
            <TextField v-model="password" label="New password" type="password" autocomplete="new-password" hint="At least 8 characters." :error="error?.field('password')" required />
            <TextField v-model="confirmation" label="Type the password again" type="password" autocomplete="new-password" required />
            <ErrorNote :message="otherError" />
            <PrimaryButton :busy="busy">{{ busy ? 'Saving…' : 'Save password and sign in' }}</PrimaryButton>

            <p class="text-sm text-zinc-600">
                No code?
                <button v-if="wait <= 0" type="button" class="font-semibold text-zinc-900 underline" :disabled="busy" @click="sendCode">Send another</button>
                <span v-else>You can ask for another in {{ wait }} s.</span>
                <button type="button" class="ml-2 underline" @click="step = 'phone'">Change number</button>
            </p>
        </form>

        <RouterLink :to="{ name: 'sign-in' }" class="inline-block text-sm underline">Back to sign in</RouterLink>
    </section>
</template>
