/**
 * Service Worker — Budget Fund Utilization & Tracking
 *
 * Strategy:
 *  - Cache-first for static assets (JS, CSS, fonts, images)
 *  - Network-first for HTML/Inertia navigation pages
 *  - Never intercept POST/PUT/DELETE (those go through the offline queue)
 */

const CACHE_NAME = 'budget-tracker-v5'

function normalizePageUrl(url) {
  const parsed = new URL(url, self.location.origin)
  return `${parsed.origin}${parsed.pathname}${parsed.search}`
}

async function cachePageResponse(url, response) {
  if (!response || !response.ok) return response
  const cache = await caches.open(CACHE_NAME)
  await cache.put(normalizePageUrl(url), response.clone())
  return response
}

async function matchPageResponse(url) {
  const cache = await caches.open(CACHE_NAME)
  const normalized = normalizePageUrl(url)
  const parsed = new URL(url, self.location.origin)
  const pathnameOnly = `${parsed.origin}${parsed.pathname}`

  return cache.match(normalized).then((cached) => cached || cache.match(pathnameOnly))
}

// Assets to pre-cache on install
const PRECACHE_URLS = [
  '/',
  '/offline.html',
]

// ─── Install ──────────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Silently cache what we can; ignore failures for individual items
      return Promise.allSettled(
        PRECACHE_URLS.map((url) => cache.add(url).catch(() => null))
      )
    }).then(() => self.skipWaiting())
  )
})

// ─── Activate ─────────────────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  )
})

// ─── Fetch ────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
  const { request } = event
  const url = new URL(request.url)

  // Only intercept HTTP/HTTPS requests (ignores chrome-extension://, data:, etc.)
  if (!url.protocol.startsWith('http')) return

  // Never intercept non-GET requests (POST, PUT, DELETE go through fetch normally)
  if (request.method !== 'GET') return

  // Never cache auth/onboarding routes so session changes are always fresh
  if (
    url.pathname.startsWith('/login') ||
    url.pathname.startsWith('/logout') ||
    url.pathname.startsWith('/2fa') ||
    url.pathname.startsWith('/forgot-password') ||
    url.pathname.startsWith('/reset-password')
  ) {
    event.respondWith(fetch(request))
    return
  }

  // Financial file transfer is intentionally network-only.
  if (url.pathname.includes('/import-csv') || url.pathname.includes('/export-csv')) {
    event.respondWith(fetch(request).catch(() => new Response('CSV import and export require an internet connection.', {
      status: 503,
      statusText: 'Service Unavailable',
      headers: { 'Content-Type': 'text/plain' }
    })))
    return
  }

  // Inertia page data -> network-first, then the last successful page snapshot.
  if (request.headers.get('X-Inertia')) {
    event.respondWith(
      fetch(request)
        .then((response) => cachePageResponse(request.url, response))
        .catch(() => matchPageResponse(request.url).then((cached) => cached || new Response(JSON.stringify({
          message: 'This page has not been cached for offline use yet.'
        }), {
          status: 503,
          headers: { 'Content-Type': 'application/json' }
        })))
    )
    return
  }

  // Static assets (JS, CSS, fonts, images) → Cache-first
  if (
    url.pathname.startsWith('/build/') ||
    url.pathname.startsWith('/images/') ||
    url.pathname.match(/\.(js|css|woff2?|ttf|eot|ico|png|jpg|jpeg|svg|gif)$/)
  ) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached
        return fetch(request).then((response) => {
          if (response.ok) {
            const clone = response.clone()
            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone))
          }
          return response
        }).catch(() => cached || new Response('Asset not available offline', { status: 503 }))
      })
    )
    return
  }

  // HTML navigation → Network-first, fallback to cache, then /offline.html
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => cachePageResponse(request.url, response))
        .catch(() =>
          matchPageResponse(request.url).then(
            (cached) => cached || caches.match('/offline.html').then((offline) => offline || new Response('Offline', {
              status: 503,
              statusText: 'Service Unavailable',
              headers: { 'Content-Type': 'text/html' }
            }))
          )
        )
    )
    return
  }

  // Default: network-first
  event.respondWith(
    fetch(request).catch(() => caches.match(request).then((cached) => cached || new Response('Network error', {
      status: 503,
      statusText: 'Service Unavailable',
      headers: { 'Content-Type': 'text/plain' }
    })))
  )
})

// ─── Background sync message from app ─────────────────────────────────────────
self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') {
    self.skipWaiting()
  }

  if (event.data === 'CLEAR_CACHES') {
    event.waitUntil(
      caches.keys().then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
    )
  }
})
