<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api } from './api.js';
import { session } from './session.js';
import Icon from './components/Icon.vue';

const route = useRoute();
const signedIn = computed(() => Boolean(session.token));

// The unread badge: checked on opening, every 60 s while the app is on screen, and on return.
let timer = null;

async function refreshUnread() {
    if (!session.token || document.visibilityState !== 'visible') {
        return;
    }
    try {
        session.unread = (await api('notifications?per_page=1')).unread ?? 0;
    } catch {
        // offline or signed out: keep the last count
    }
}

function onVisible() {
    if (document.visibilityState === 'visible') {
        refreshUnread();
    }
}

// Pull down at the top of a list to refresh it (screens watch session.refreshes).
const pull = ref(0);
let startY = null;

function onTouchStart(event) {
    startY = window.scrollY <= 0 ? event.touches[0].clientY : null;
}
function onTouchMove(event) {
    if (startY !== null) {
        pull.value = Math.max(0, Math.min(80, (event.touches[0].clientY - startY) * 0.5));
    }
}
function onTouchEnd() {
    if (pull.value >= 60) {
        session.refreshes++;
        refreshUnread();
    }
    pull.value = 0;
    startY = null;
}

onMounted(() => {
    refreshUnread();
    timer = setInterval(refreshUnread, 60_000);
    document.addEventListener('visibilitychange', onVisible);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
});
watch(() => session.token, refreshUnread);

const tabs = [
    { name: 'children', label: 'Children', icon: 'children' },
    { name: 'news', label: 'News', icon: 'news' },
    { name: 'notifications', label: 'Notices', icon: 'bell' },
    { name: 'account', label: 'Account', icon: 'user' },
];

const activeTab = computed(() => ({ child: 'children', announcement: 'news' })[route.name] ?? route.name);

const schoolInitials = computed(() =>
    session.school.name
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word[0] ?? '')
        .join('')
        .toUpperCase(),
);
</script>

<template>
    <div class="min-h-dvh" @touchstart.passive="onTouchStart" @touchmove.passive="onTouchMove" @touchend="onTouchEnd">
        <header class="sticky top-0 z-20 border-b border-line bg-surface/90 backdrop-blur-md">
            <!-- The school's colour as a thin stripe, like a uniform trim. -->
            <div class="h-1 bg-school"></div>
            <!-- Signed out, the sign-in screen shows the school's mark itself. -->
            <div v-if="signedIn" class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3">
                <img v-if="session.school.logo" :src="session.school.logo" alt="" class="size-9 rounded-lg object-contain" />
                <span v-else class="grid size-9 place-items-center rounded-lg bg-school text-sm font-bold text-school-text" aria-hidden="true">{{ schoolInitials }}</span>
                <p class="min-w-0 flex-1 truncate font-bold">{{ session.school.name }}</p>

                <!-- Tabs in the header on wider screens; a bottom bar on phones. -->
                <nav v-if="signedIn" class="hidden md:block" aria-label="Main">
                    <ul class="flex gap-1">
                        <li v-for="tab in tabs" :key="tab.name">
                            <RouterLink
                                :to="{ name: tab.name }"
                                class="press relative flex items-center gap-2 rounded-lg px-3 py-2 text-sm"
                                :class="activeTab === tab.name ? 'bg-paper font-bold text-ink' : 'text-muted hover:text-ink'"
                            >
                                <Icon :name="tab.icon" size="size-4.5" />
                                {{ tab.label }}
                                <span v-if="tab.name === 'notifications' && session.unread > 0" class="rounded-full bg-absent px-1.5 text-[11px] leading-5 font-bold text-white">
                                    {{ session.unread > 99 ? '99+' : session.unread }}
                                </span>
                            </RouterLink>
                        </li>
                    </ul>
                </nav>
            </div>
        </header>

        <p v-if="!session.online" class="flex items-center justify-center gap-2 bg-ink px-4 py-2 text-sm text-white" role="status">
            <Icon name="offline" size="size-4" /> You're offline. What you see may be out of date.
        </p>

        <div class="flex justify-center overflow-hidden transition-[height]" :style="{ height: `${pull}px` }" aria-hidden="true">
            <Icon name="refresh" class="mt-3 text-muted transition-transform" :style="{ transform: `rotate(${pull * 4}deg)`, opacity: pull / 60 }" />
        </div>

        <main class="mx-auto max-w-5xl px-4 pt-5" :class="signedIn ? 'pb-28 md:pb-12' : 'pb-12'">
            <RouterView v-slot="{ Component }">
                <Transition name="screen" mode="out-in">
                    <component :is="Component" />
                </Transition>
            </RouterView>
        </main>

        <nav v-if="signedIn" class="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-surface/85 pb-[env(safe-area-inset-bottom)] backdrop-blur-md md:hidden" aria-label="Main">
            <ul class="mx-auto flex max-w-lg">
                <li v-for="tab in tabs" :key="tab.name" class="flex-1">
                    <RouterLink
                        :to="{ name: tab.name }"
                        class="press relative flex flex-col items-center gap-1 pt-2.5 pb-2 text-[12px]"
                        :class="activeTab === tab.name ? 'font-bold text-ink' : 'text-muted'"
                        :aria-current="activeTab === tab.name ? 'page' : undefined"
                    >
                        <span class="relative">
                            <Icon :name="tab.icon" size="size-6" />
                            <span
                                v-if="tab.name === 'notifications' && session.unread > 0"
                                class="absolute -top-1.5 -right-2.5 min-w-[18px] rounded-full bg-absent px-1 text-center text-[11px] leading-[18px] font-bold text-white ring-2 ring-surface"
                            >
                                {{ session.unread > 99 ? '99+' : session.unread }}
                                <span class="sr-only">unread</span>
                            </span>
                        </span>
                        {{ tab.label }}
                        <span v-if="activeTab === tab.name" class="absolute top-0 h-0.5 w-8 rounded-full bg-school"></span>
                    </RouterLink>
                </li>
            </ul>
        </nav>
    </div>
</template>
