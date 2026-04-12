/**
 * useOfflineQueue — Offline action queue backed by IndexedDB
 *
 * Usage in any Vue page:
 *
 *   import { useOfflineQueue } from '@/composables/useOfflineQueue'
 *   const { isOnline, queueCount, offlinePost, offlinePut, offlineDelete, syncQueue } = useOfflineQueue()
 *
 * When online  → request goes straight to server via fetch (with CSRF + Inertia headers)
 * When offline → action is saved to IndexedDB; the caller should update local state optimistically
 */

import { ref, onMounted, onUnmounted } from 'vue'

// ─── IndexedDB helpers ────────────────────────────────────────────────────────

const DB_NAME = 'budget_tracker_offline'
const DB_VERSION = 1
const STORE_NAME = 'action_queue'

let _db = null

function openDB() {
  if (_db) return Promise.resolve(_db)
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION)
    req.onupgradeneeded = (e) => {
      const db = e.target.result
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        const store = db.createObjectStore(STORE_NAME, { keyPath: 'id' })
        store.createIndex('timestamp', 'timestamp', { unique: false })
      }
    }
    req.onsuccess = (e) => { _db = e.target.result; resolve(_db) }
    req.onerror = (e) => reject(e.target.error)
  })
}

function dbGetAll() {
  return openDB().then((db) => new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readonly')
    const req = tx.objectStore(STORE_NAME).index('timestamp').getAll()
    req.onsuccess = (e) => resolve(e.target.result)
    req.onerror = (e) => reject(e.target.error)
  }))
}

function dbPut(item) {
  return openDB().then((db) => new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite')
    const req = tx.objectStore(STORE_NAME).put(item)
    req.onsuccess = () => resolve()
    req.onerror = (e) => reject(e.target.error)
  }))
}

function dbDelete(id) {
  return openDB().then((db) => new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite')
    const req = tx.objectStore(STORE_NAME).delete(id)
    req.onsuccess = () => resolve()
    req.onerror = (e) => reject(e.target.error)
  }))
}

function dbClearAll() {
  return openDB().then((db) => new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite')
    const req = tx.objectStore(STORE_NAME).clear()
    req.onsuccess = () => resolve()
    req.onerror = (e) => reject(e.target.error)
  }))
}

// ─── UUID helper ──────────────────────────────────────────────────────────────
function uuid() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
  })
}

// ─── CSRF token helper ────────────────────────────────────────────────────────
function getCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

// ─── Shared reactive state (singleton across uses) ────────────────────────────
const isOnline = ref(navigator.onLine)
const queueCount = ref(0)
const isSyncing = ref(false)
const syncError = ref(null)
const lastSynced = ref(null)

async function refreshCount() {
  try {
    const items = await dbGetAll()
    queueCount.value = items.length
  } catch {
    queueCount.value = 0
  }
}

// ─── Send one action to server ────────────────────────────────────────────────
//
// KEY: We do NOT send X-Inertia headers here.
//   Sending X-Inertia:true without the matching X-Inertia-Version causes a
//   409 Conflict (Inertia version mismatch). Instead we use plain AJAX with
//   the CSRF token only. Laravel processes the request normally and redirects
//   (302) on success; fetch follows that redirect and we get a 200 back.
//
// KEY: We use real HTTP methods (PUT, DELETE) — method spoofing via _method
//   only works for form-encoded bodies, not JSON.
//
async function sendAction(item) {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json, text/plain, */*',
    'X-CSRF-TOKEN': getCsrf(),
    'X-Requested-With': 'XMLHttpRequest',
  }

  const opts = {
    method: item.method,   // Real method: POST | PUT | DELETE
    headers,
    credentials: 'same-origin',
    redirect: 'follow',    // Follow Laravel's post-action redirects
  }

  // Attach body for POST and PUT
  if (item.method !== 'DELETE' && item.data && Object.keys(item.data).length > 0) {
    opts.body = JSON.stringify(item.data)
  }

  const response = await fetch(item.url, opts)

  // Detect redirect to /login — means session expired
  if (response.url && response.url.includes('/login')) {
    throw new Error('Session expired — please log in again, then sync.')
  }

  // Inertia controllers redirect (302→200) on success, so the final response
  // will be 200. We also accept 201/204 for API-style controllers.
  if (!response.ok) {
    const text = await response.text().catch(() => '')
    // Try to parse a JSON error message
    let msg = `Server responded ${response.status}`
    try {
      const json = JSON.parse(text)
      if (json.message) msg += `: ${json.message}`
      else if (json.error) msg += `: ${json.error}`
    } catch {
      if (text) msg += `: ${text.slice(0, 150)}`
    }
    throw new Error(msg)
  }

  return response
}

// ─── Composable ───────────────────────────────────────────────────────────────
export function useOfflineQueue() {

  // Update isOnline reactively
  function handleOnline() { isOnline.value = true }
  function handleOffline() { isOnline.value = false }

  onMounted(async () => {
    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)
    isOnline.value = navigator.onLine
    await refreshCount()
  })

  onUnmounted(() => {
    window.removeEventListener('online', handleOnline)
    window.removeEventListener('offline', handleOffline)
  })

  /**
   * Queue an action for later sync.
   * @param {string} method - 'POST' | 'PUT' | 'DELETE'
   * @param {string} url    - e.g. '/expenses' or '/expenses/42'
   * @param {object} data   - request body
   * @param {string} label  - human-readable description for the queue UI
   */
  async function enqueue(method, url, data = {}, label = '') {
    const item = {
      id: uuid(),
      method,
      url,
      data,
      label: label || `${method} ${url}`,
      timestamp: Date.now(),
      status: 'pending',
    }
    await dbPut(item)
    await refreshCount()
    return item
  }

  /**
   * Offline-aware POST — if online, sends immediately; if offline, queues.
   * Returns { queued: bool, item }
   */
  async function offlinePost(url, data, label) {
    if (isOnline.value) {
      return { queued: false }
    }
    const item = await enqueue('POST', url, data, label)
    return { queued: true, item }
  }

  /**
   * Offline-aware PUT
   */
  async function offlinePut(url, data, label) {
    if (isOnline.value) {
      return { queued: false }
    }
    const item = await enqueue('PUT', url, data, label)
    return { queued: true, item }
  }

  /**
   * Offline-aware DELETE
   */
  async function offlineDelete(url, label) {
    if (isOnline.value) {
      return { queued: false }
    }
    const item = await enqueue('DELETE', url, {}, label)
    return { queued: true, item }
  }

  /**
   * Sync all queued actions to the server.
   * Returns { succeeded, failed }
   */
  async function syncQueue() {
    if (isSyncing.value) return { succeeded: 0, failed: 0 }
    isSyncing.value = true
    syncError.value = null

    let succeeded = 0
    let failed = 0
    const errors = []

    try {
      const items = await dbGetAll()
      for (const item of items) {
        try {
          item.status = 'syncing'
          await sendAction(item)
          await dbDelete(item.id)
          succeeded++
        } catch (err) {
          failed++
          errors.push({ label: item.label, error: err.message })
          // Keep it in the queue but mark error
          item.status = 'error'
          item.lastError = err.message
          await dbPut(item)
        }
      }
    } finally {
      await refreshCount()
      isSyncing.value = false
      if (errors.length) {
        syncError.value = errors
      }
      if (succeeded > 0) {
        lastSynced.value = new Date()
      }
    }

    return { succeeded, failed, errors }
  }

  /**
   * Get all queued items (for display)
   */
  async function getQueue() {
    return dbGetAll()
  }

  /**
   * Remove a specific queued item by id
   */
  async function removeFromQueue(id) {
    await dbDelete(id)
    await refreshCount()
  }

  /**
   * Clear entire queue
   */
  async function clearQueue() {
    await dbClearAll()
    await refreshCount()
  }

  return {
    isOnline,
    queueCount,
    isSyncing,
    syncError,
    lastSynced,
    enqueue,
    offlinePost,
    offlinePut,
    offlineDelete,
    syncQueue,
    getQueue,
    removeFromQueue,
    clearQueue,
    refreshCount,
  }
}
