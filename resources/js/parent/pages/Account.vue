<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api.js';
import { forget, session } from '../session.js';
import ErrorNote from '../components/ErrorNote.vue';

const router = useRouter();
const error = ref(null);
const busy = ref(false);

onMounted(async () => {
    try {
        session.me = (await api('me')).data;
    } catch (e) {
        error.value = e.message;
    }
});

async function signOut() {
    busy.value = true;
    try {
        await api('auth/logout', { method: 'POST' });
    } catch {
        // Offline: the token ends on its own after 90 days; forget it here anyway.
    }
    forget();
    router.replace({ name: 'sign-in' });
}

async function install() {
    const prompt = session.installPrompt;
    session.installPrompt = null;
    await prompt?.prompt();
}
</script>

<template>
    <section class="space-y-4">
        <h2 class="text-xl font-semibold">Account</h2>
        <ErrorNote :message="error" />

        <dl v-if="session.me" class="space-y-2 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div><dt class="text-sm text-zinc-500">Name</dt><dd class="font-medium">{{ session.me.name }}</dd></div>
            <div><dt class="text-sm text-zinc-500">Phone</dt><dd class="font-medium">{{ session.me.phone }}</dd></div>
            <div><dt class="text-sm text-zinc-500">School</dt><dd class="font-medium">{{ session.me.school.name }}</dd></div>
        </dl>
        <p class="text-sm text-zinc-600">To change your name or phone number, please contact the school.</p>

        <button v-if="session.installPrompt" class="w-full rounded-lg border border-zinc-300 bg-white py-3 font-medium" @click="install">Add to home screen</button>
        <button class="w-full rounded-lg border border-red-300 bg-white py-3 font-medium text-red-700 disabled:opacity-60" :disabled="busy" @click="signOut">Sign out</button>
    </section>
</template>
