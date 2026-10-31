import { reactive } from 'vue';

const TOKEN_KEY = 'parent-token';

function readToken() {
    try {
        return localStorage.getItem(TOKEN_KEY);
    } catch {
        return null;
    }
}

const mount = document.getElementById('app');

/**
 * App-wide state. Only the sign-in token is kept on the phone; everything else is fetched fresh.
 */
export const session = reactive({
    token: readToken(),
    me: null,
    unread: 0,
    online: navigator.onLine,
    installPrompt: null,
    school: {
        name: mount?.dataset.schoolName ?? '',
        logo: mount?.dataset.schoolLogo || null,
    },
});

export function signIn(token) {
    session.token = token;
    try {
        localStorage.setItem(TOKEN_KEY, token);
    } catch {
        // Private browsing: the token lasts until the tab closes.
    }
}

/**
 * Forgets the token and anything the app holds, including the service worker's cache.
 */
export function forget() {
    session.token = null;
    session.me = null;
    session.unread = 0;
    try {
        localStorage.removeItem(TOKEN_KEY);
    } catch {
        // nothing stored
    }
    navigator.serviceWorker?.controller?.postMessage('sign-out');
}

window.addEventListener('online', () => (session.online = true));
window.addEventListener('offline', () => (session.online = false));

// Android/Chrome offers installing; keep the event so the app can show its own button.
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    session.installPrompt = event;
});
window.addEventListener('appinstalled', () => (session.installPrompt = null));
