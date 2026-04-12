import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createInertiaApp({
    title: (title) => title ? `${title} - Budget Fund Utilization & Tracking` : 'Budget Fund Utilization & Tracking',
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#d4a843',
    },
});

// ── Service Worker Registration ─────────────────────────────────────────────
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .then((registration) => {
                console.log('[SW] Registered, scope:', registration.scope)

                // Check for updates on every page load
                registration.update()

                // When a new SW is waiting, activate it immediately
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing
                    newWorker?.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            newWorker.postMessage('SKIP_WAITING')
                        }
                    })
                })
            })
            .catch((err) => console.warn('[SW] Registration failed:', err))
    })
}

