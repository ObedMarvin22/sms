import { createRouter, createWebHashHistory } from 'vue-router';
import { whenSignedOut } from './api.js';
import { session } from './session.js';
import Account from './pages/Account.vue';
import Announcement from './pages/Announcement.vue';
import Child from './pages/Child.vue';
import Children from './pages/Children.vue';
import News from './pages/News.vue';
import Notifications from './pages/Notifications.vue';
import SetPassword from './pages/SetPassword.vue';
import SignIn from './pages/SignIn.vue';

// Hash URLs (/parent#/children/5): one Laravel page serves every screen, also offline.
export const router = createRouter({
    history: createWebHashHistory(),
    routes: [
        { path: '/sign-in', name: 'sign-in', component: SignIn, meta: { guest: true } },
        { path: '/set-password', name: 'set-password', component: SetPassword, meta: { guest: true } },
        { path: '/', name: 'children', component: Children },
        { path: '/children/:id(\\d+)', name: 'child', component: Child, props: (route) => ({ id: Number(route.params.id) }) },
        { path: '/news', name: 'news', component: News },
        { path: '/news/:id(\\d+)', name: 'announcement', component: Announcement, props: (route) => ({ id: Number(route.params.id) }) },
        { path: '/notifications', name: 'notifications', component: Notifications },
        { path: '/account', name: 'account', component: Account },
        { path: '/:rest(.*)*', redirect: '/' },
    ],
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach((to) => {
    if (!to.meta.guest && !session.token) {
        return { name: 'sign-in' };
    }
    if (to.meta.guest && session.token) {
        return { name: 'children' };
    }
});

whenSignedOut(() => router.push({ name: 'sign-in' }));
