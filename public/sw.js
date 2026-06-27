/**
 * Service Worker — Budget Fund Utilization & Tracking
 *
 * Strategy:
 *  - Cache-first for static assets (JS, CSS, fonts, images)
 *  - Network-first for HTML/Inertia navigation pages
 *  - Never intercept POST/PUT/DELETE (those go through the offline queue)
 */

const CACHE_NAME = 'budget-tracker-v1'

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

  // Never intercept Inertia XHR data calls (they have X-Inertia header)
  if (request.headers.get('X-Inertia')) return

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
        .then((response) => {
          // Cache a fresh copy of HTML pages
          if (response.ok) {
            const clone = response.clone()
            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone))
          }
          return response
        })
        .catch(() =>
          caches.match(request).then(
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
})
