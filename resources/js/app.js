import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { queueOfflineAction, savePageSnapshot } from '@/composables/useOfflineQueue';
import { findRecordVersion, offlinePolicy } from '@/offlinePolicy';
import RequestErrorAlert from '@/Components/RequestErrorAlert.vue';
import { requestError, requestErrorMessage, showRequestError } from '@/support/requestErrors';

router.on('invalid', (event) => {
    event.preventDefault()
    const response = event.detail.response
    const offline = !navigator.onLine || response.data?.message === 'This page has not been cached for offline use yet.'
    showRequestError(requestErrorMessage(response.status, offline))
})

router.on('exception', (event) => {
    event.preventDefault()
    console.error('Page request failed:', event.detail.exception)
    showRequestError(navigator.onLine
        ? 'The connection was interrupted or the page could not load. Check your connection. If you were saving, check the records before retrying.'
        : requestErrorMessage(503, true))
})

router.on('success', () => { requestError.value = '' })

let pendingVisits = 0
let loadingTimer = null

function setGlobalLoading(active) {
    window.dispatchEvent(new CustomEvent('app:loading', { detail: { active } }))
}

router.on('start', () => {
    pendingVisits += 1
    clearTimeout(loadingTimer)
    loadingTimer = setTimeout(() => setGlobalLoading(true), 120)
})

router.on('finish', () => {
    pendingVisits = Math.max(0, pendingVisits - 1)
    if (pendingVisits === 0) {
        clearTimeout(loadingTimer)
        setGlobalLoading(false)
    }
})

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
    const queuedData = { ...(data || {}) }
    const policy = offlinePolicy(method, url, queuedData)
    if (!policy.allowed) {
        const message = policy.reason
        options.onError?.({ offline: message })
        options.onFinish?.()
        if (!options.onError) showRequestError(message)
        return
    }

    if (containsBinary(data)) {
        const message = 'File uploads cannot be queued offline. Reconnect before uploading this file.'
        options.onError?.({ offline: message })
        options.onFinish?.()
        if (!options.onError) showRequestError(message)
        return
    }

    const tempId = String(method).toLowerCase() === 'post'
        ? `offline-${policy.resource}-${crypto.randomUUID()}`
        : null
    if (policy.resource === 'expense') {
        queuedData.status = 'pending'
        queuedData.date_approved = null
    }
    if (policy.resource === 'disbursement') {
        queuedData.status = 'draft'
    }

    const expenseId = String(queuedData.expense_id || '')
    const dependsOn = expenseId.startsWith('offline-expense-') ? expenseId : null
    const baseVersion = findRecordVersion(
        window.__BUDGET_TRACKER_PAGE_PROPS__ || {},
        policy.resource,
        policy.pathname,
    )

    try {
    await queueOfflineAction(method, url, queuedData, offlineLabel(method, url), {
        resource: policy.resource,
        rank: policy.rank,
        tempId,
        dependsOn,
        baseVersion,
    })
    options.onSuccess?.({})
    options.onFinish?.()
    } catch (error) {
        console.error('Offline save failed:', error)
        const message = 'This change could not be saved offline. Keep your entries and reconnect before trying again.'
        if (options.onError) options.onError({ offline: message })
        else showRequestError(message)
        options.onFinish?.()
    }
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

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href]')
    if (navigator.onLine || !link) return
    const pathname = new URL(link.href, window.location.origin).pathname
    if (!pathname.includes('/export-csv')) return
    event.preventDefault()
    showRequestError('Export requires an internet connection. Reconnect and try again.')
}, true)

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href]')
    if (navigator.onLine || !link) return

    const url = new URL(link.href, window.location.origin)
    if (url.origin !== window.location.origin) return
    if (link.hasAttribute('download')) return

    const pathname = url.pathname
    if (pathname.includes('/export-csv')) return
    if (
        pathname.startsWith('/login') ||
        pathname.startsWith('/logout') ||
        pathname.startsWith('/2fa') ||
        pathname.startsWith('/forgot-password') ||
        pathname.startsWith('/reset-password')
    ) {
        event.preventDefault()
        showRequestError('Authentication requires an internet connection. Reconnect and try again.')
        return
    }

    event.preventDefault()
    router.visit(link.href, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}, true)

createInertiaApp({
    title: (title) => title ? `${title} - Budget Fund Utilization & Tracking` : 'Budget Fund Utilization & Tracking',
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        window.__BUDGET_TRACKER_USER_ID__ = props.initialPage?.props?.auth?.user?.id || null
        window.__BUDGET_TRACKER_USER_NAME__ = props.initialPage?.props?.auth?.user?.name || 'Current user'
        window.__BUDGET_TRACKER_PAGE_PROPS__ = props.initialPage?.props || {}
        savePageSnapshot(props.initialPage).catch(() => null)
        const app = createApp({ render: () => [h(App, props), h(RequestErrorAlert)] })
        app.config.errorHandler = (error) => {
            console.error('Page error:', error)
            showRequestError('Part of this page could not load. Refresh the page. If the problem continues, contact your administrator.')
        }
        app.use(plugin).mount(el);
    },
    progress: {
        color: '#d4a843',
    },
});

router.on('navigate', (event) => {
    const page = event.detail?.page
    if (!page) return
    window.__BUDGET_TRACKER_PAGE_PROPS__ = page.props || {}
    window.__BUDGET_TRACKER_USER_ID__ = page.props?.auth?.user?.id || window.__BUDGET_TRACKER_USER_ID__ || null
    window.__BUDGET_TRACKER_USER_NAME__ = page.props?.auth?.user?.name || window.__BUDGET_TRACKER_USER_NAME__ || 'Current user'
    savePageSnapshot(page).catch(() => null)
})

// Service Worker Registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' })
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
        } catch (err) {
            console.warn('[SW] Registration failed:', err)
        }
    })
}
