import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { queueOfflineAction } from '@/composables/useOfflineQueue';

const ONLINE_ONLY_PATHS = [
    '/login',
    '/logout',
    '/2fa',
    '/forgot-password',
    '/reset-password',
    '/tutorial',
]

function containsBinary(value) {
    if (!value || typeof value !== 'object') return false
    if (value instanceof Blob || value instanceof File || value instanceof FormData) return true
    return Object.values(value).some(containsBinary)
}

function offlineLabel(method, url) {
    const section = String(url).split('/').filter(Boolean)[0]?.replaceAll('-', ' ') || 'record'
    return `${method.toUpperCase()} ${section}`
}

async function queueMutation(method, url, data, options = {}) {
    if (ONLINE_ONLY_PATHS.some((path) => String(url).startsWith(path))) {
        const message = 'This action requires an internet connection.'
        options.onError?.({ offline: message })
        options.onFinish?.()
        window.alert(message)
        return
    }

    if (containsBinary(data)) {
        const message = 'File uploads cannot be queued offline. Reconnect before uploading this file.'
        options.onError?.({ offline: message })
        options.onFinish?.()
        window.alert(message)
        return
    }

    await queueOfflineAction(method, url, data || {}, offlineLabel(method, url))
    options.onSuccess?.({})
    options.onFinish?.()
}

function installOfflineMutationGuard() {
    const original = {
        post: router.post.bind(router),
        put: router.put.bind(router),
        patch: router.patch.bind(router),
        delete: router.delete.bind(router),
    }

    for (const method of ['post', 'put', 'patch']) {
        router[method] = (url, data = {}, options = {}) => {
            if (navigator.onLine) return original[method](url, data, options)
            queueMutation(method, url, data, options)
        }
    }

    router.delete = (url, options = {}) => {
        if (navigator.onLine) return original.delete(url, options)
        queueMutation('delete', url, {}, options)
    }
}

installOfflineMutationGuard()

createInertiaApp({
    title: (title) => title ? `${title} - Budget Fund Utilization & Tracking` : 'Budget Fund Utilization & Tracking',
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        window.__BUDGET_TRACKER_USER_ID__ = props.initialPage?.props?.auth?.user?.id || null
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
