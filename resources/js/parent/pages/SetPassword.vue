<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../api.js';
import { signIn } from '../session.js';
import AuthHeader from '../components/AuthHeader.vue';
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
    <section class="mx-auto max-w-sm">
        <AuthHeader
            :title="forgot ? 'Set a new password' : 'Get started'"
            :text="step === 'phone' ? 'Enter the phone number the school has for you. We will text you a 6-digit code.' : sentMessage"
        />

        <!-- Where they are: phone first, then code and password. -->
        <ol class="mb-4 flex items-center justify-center gap-2 text-xs text-muted" aria-label="Steps">
            <li class="flex items-center gap-1.5" :class="step === 'phone' ? 'font-bold text-ink' : ''">
                <span class="grid size-5 place-items-center rounded-full text-[11px]" :class="step === 'phone' ? 'bg-school text-school-text' : 'bg-present text-white'">1</span> Phone
            </li>
            <li class="h-px w-6 bg-line" aria-hidden="true"></li>
            <li class="flex items-center gap-1.5" :class="step === 'code' ? 'font-bold text-ink' : ''">
                <span class="grid size-5 place-items-center rounded-full text-[11px]" :class="step === 'code' ? 'bg-school text-school-text' : 'bg-line text-muted'">2</span> Code and password
            </li>
        </ol>

        <form v-if="step === 'phone'" class="space-y-4 rounded-2xl bg-surface p-5 ring-1 ring-line" novalidate @submit.prevent="sendCode">
            <TextField v-model="phone" label="Phone number" type="tel" inputmode="tel" autocomplete="tel" placeholder="024 412 3456" :error="error?.field('phone')" required />
            <ErrorNote :message="otherError" />
            <PrimaryButton :busy="busy">{{ busy ? 'Sending…' : 'Text me a code' }}</PrimaryButton>
        </form>

        <form v-else class="space-y-4 rounded-2xl bg-surface p-5 ring-1 ring-line" novalidate @submit.prevent="setPassword">
            <TextField v-model="code" label="Code from the SMS" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6 digits" :error="error?.field('code') ?? error?.field('phone')" required />
            <TextField v-model="password" label="New password" type="password" autocomplete="new-password" hint="At least 8 characters." :error="error?.field('password')" required />
            <TextField v-model="confirmation" label="Type the password again" type="password" autocomplete="new-password" required />
            <ErrorNote :message="otherError" />
            <PrimaryButton :busy="busy">{{ busy ? 'Saving…' : 'Save password and sign in' }}</PrimaryButton>

            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <button v-if="wait <= 0" type="button" class="font-bold text-ink underline decoration-line underline-offset-4" :disabled="busy" @click="sendCode">Send another code</button>
                <span v-else class="text-muted">Another code in {{ wait }} s</span>
                <button type="button" class="font-bold text-muted hover:text-ink" @click="step = 'phone'">Change number</button>
            </div>
        </form>

        <RouterLink :to="{ name: 'sign-in' }" class="mt-5 block text-center text-sm font-bold text-muted hover:text-ink">Back to sign in</RouterLink>
    </section>
</template>
