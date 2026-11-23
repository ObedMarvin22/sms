<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api.js';
import { forget, session } from '../session.js';
import { phone } from '../format.js';
import Crest from '../components/Crest.vue';
import ErrorState from '../components/ErrorState.vue';
import Icon from '../components/Icon.vue';

const router = useRouter();
const error = ref(null);
const busy = ref(false);

async function load() {
    error.value = null;
    try {
        session.me = (await api('me')).data;
    } catch (e) {
        error.value = e.message;
    }
}

/** Signs out; to change the password the parent then gets a code on the set-password screen. */
async function signOut(then = 'sign-in') {
    busy.value = true;
    try {
        await api('auth/logout', { method: 'POST' });
    } catch {
        // Offline: the token ends on its own after 90 days; forget it here anyway.
    }
    forget();
    router.replace(then === 'set-password' ? { name: 'set-password', query: { forgot: 1 } } : { name: 'sign-in' });
}

async function install() {
    const prompt = session.installPrompt;
    session.installPrompt = null;
    await prompt?.prompt();
}

onMounted(load);
</script>

<template>
    <section class="mx-auto max-w-3xl space-y-5">
        <h1 class="text-2xl font-bold tracking-tight">Account</h1>

        <ErrorState v-if="error" :message="error" @retry="load" />

        <div v-else-if="!session.me" class="flex items-center gap-4 rounded-2xl bg-surface p-5 ring-1 ring-line" aria-hidden="true">
            <div class="skeleton size-14 rounded-full"></div><div class="flex-1 space-y-2"><div class="skeleton h-4 w-1/2"></div><div class="skeleton h-3 w-1/3"></div></div>
        </div>

        <div v-else class="overflow-hidden rounded-2xl bg-surface ring-1 ring-line">
            <div class="flex items-center gap-4 p-5">
                <Crest :name="session.me.name" size="size-14 text-lg" />
                <div class="min-w-0">
                    <p class="text-lg font-bold">{{ session.me.name }}</p>
                    <p class="text-sm text-muted">Parent at {{ session.me.school.name }}</p>
                </div>
            </div>
            <dl class="divide-y divide-line border-t border-line text-sm">
                <div class="flex items-center gap-3 px-5 py-3.5">
                    <Icon name="phone" class="text-faint" />
                    <dt class="sr-only">Phone</dt>
                    <dd class="flex-1">{{ phone(session.me.phone) }}</dd>
                    <span class="text-xs text-faint">Used to sign in</span>
                </div>
            </dl>
        </div>

        <p class="px-1 text-sm text-muted">To change your name or phone number, ask the school office: your phone number is also how you sign in.</p>

        <div class="overflow-hidden rounded-2xl bg-surface ring-1 ring-line">
            <button v-if="session.installPrompt" class="press flex w-full items-center gap-3 border-b border-line px-5 py-4 text-left hover:bg-paper/60" @click="install">
                <Icon name="install" class="text-muted" />
                <span class="flex-1 font-bold">Add to home screen</span>
                <Icon name="chevron" size="size-4" class="text-faint" />
            </button>
            <button class="press flex w-full items-center gap-3 border-b border-line px-5 py-4 text-left hover:bg-paper/60 disabled:opacity-60" :disabled="busy" @click="signOut('set-password')">
                <Icon name="lock" class="text-muted" />
                <span class="flex-1">
                    <span class="block font-bold">Change password</span>
                    <span class="block text-sm text-muted">We text you a code, then you choose a new one.</span>
                </span>
                <Icon name="chevron" size="size-4" class="text-faint" />
            </button>
            <button class="press flex w-full items-center gap-3 px-5 py-4 text-left text-absent hover:bg-absent-soft/50 disabled:opacity-60" :disabled="busy" @click="signOut()">
                <Icon name="logout" />
                <span class="flex-1 font-bold">{{ busy ? 'Signing out…' : 'Sign out' }}</span>
            </button>
        </div>
    </section>
</template>
