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
const DB_VERSION = 2
const STORE_NAME = 'action_queue'
const SNAPSHOT_STORE = 'page_snapshots'

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
      if (!db.objectStoreNames.contains(SNAPSHOT_STORE)) {
        db.createObjectStore(SNAPSHOT_STORE, { keyPath: 'url' })
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

function snapshotPut(snapshot) {
  return openDB().then((db) => new Promise((resolve, reject) => {
    const tx = db.transaction(SNAPSHOT_STORE, 'readwrite')
    const req = tx.objectStore(SNAPSHOT_STORE).put(snapshot)
    req.onsuccess = () => resolve(snapshot)
    req.onerror = (e) => reject(e.target.error)
  }))
}

function snapshotGet(url) {
  return openDB().then((db) => new Promise((resolve, reject) => {
    const tx = db.transaction(SNAPSHOT_STORE, 'readonly')
    const req = tx.objectStore(SNAPSHOT_STORE).get(url)
    req.onsuccess = (e) => resolve(e.target.result || null)
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
const lastSnapshotAt = ref(null)

async function refreshCount() {
  try {
    const items = await dbGetAll()
    queueCount.value = items.length
  } catch {
    queueCount.value = 0
  }
}

export async function queueOfflineAction(method, url, data = {}, label = '', metadata = {}) {
  const item = {
    id: uuid(),
    method: method.toUpperCase(),
    url,
    data,
    label: label || `${method.toUpperCase()} ${url}`,
    timestamp: Date.now(),
    status: 'pending',
    ownerId: window.__BUDGET_TRACKER_USER_ID__ || null,
    ownerName: window.__BUDGET_TRACKER_USER_NAME__ || 'Current user',
    resource: metadata.resource || 'record',
    rank: Number(metadata.rank || 99),
    tempId: metadata.tempId || null,
    dependsOn: metadata.dependsOn || null,
    baseVersion: metadata.baseVersion || null,
    serverRecord: null,
  }

  await dbPut(item)
  await refreshCount()
  window.dispatchEvent(new CustomEvent('offline:queued', { detail: item }))
  return item
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
  const activeUserId = window.__BUDGET_TRACKER_USER_ID__ || null
  if (item.ownerId && String(item.ownerId) !== String(activeUserId)) {
    throw new Error('Queued by another account. Log in with the original account to sync this action.')
  }

  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json, text/plain, */*',
    'X-CSRF-TOKEN': getCsrf(),
    'X-Requested-With': 'XMLHttpRequest',
    'X-Offline-Sync': 'true',
  }
  if (item.baseVersion) headers['X-Offline-Base-Version'] = item.baseVersion

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
      if (response.status === 409) item.serverRecord = json.current || null
      const validation = json.errors ? Object.values(json.errors).flat().join(' ') : ''
      if (validation) msg += `: ${validation}`
      else if (json.message) msg += `: ${json.message}`
      else if (json.error) msg += `: ${json.error}`
    } catch {
      if (text) msg += `: ${text.slice(0, 150)}`
    }
    throw new Error(msg)
  }

  const contentType = response.headers.get('content-type') || ''
  const result = contentType.includes('application/json') ? await response.clone().json().catch(() => ({})) : {}
  return { response, result }
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
  async function enqueue(method, url, data = {}, label = '', metadata = {}) {
    return queueOfflineAction(method, url, data, label, metadata)
  }

  /**
   * Offline-aware POST — if online, sends immediately; if offline, queues.
   * Returns { queued: bool, item }
   */
  async function offlinePost(url, data, label, metadata = {}) {
    if (isOnline.value) {
      return { queued: false }
    }
    const item = await enqueue('POST', url, data, label, metadata)
    return { queued: true, item }
  }

  /**
   * Offline-aware PUT
   */
  async function offlinePut(url, data, label, metadata = {}) {
    if (isOnline.value) {
      return { queued: false }
    }
    const item = await enqueue('PUT', url, data, label, metadata)
    return { queued: true, item }
  }

  /**
   * Offline-aware DELETE
   */
  async function offlineDelete(url, label) {
    if (isOnline.value) {
      return { queued: false }
    }
    return { queued: false, blocked: true, reason: 'Financial record deletion requires an internet connection.' }
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
      const items = (await dbGetAll()).sort((a, b) => (Number(a.rank || 99) - Number(b.rank || 99)) || (a.timestamp - b.timestamp))
      const completed = new Set()
      const idMap = new Map()
      for (const item of items) {
        try {
          if (item.dependsOn && !completed.has(item.dependsOn)) {
            throw new Error(`Waiting for prerequisite ${item.dependsOn} to sync successfully.`)
          }
          if (item.data?.expense_id && idMap.has(item.data.expense_id)) {
            item.data.expense_id = idMap.get(item.data.expense_id)
          }
          item.status = 'syncing'
          const { result } = await sendAction(item)
          if (item.tempId && result?.id) {
            idMap.set(item.tempId, result.id)
            const dependents = await dbGetAll()
            for (const dependent of dependents.filter((entry) => entry.dependsOn === item.tempId)) {
              if (dependent.data?.expense_id === item.tempId) dependent.data.expense_id = result.id
              dependent.dependsOn = null
              dependent.status = dependent.status === 'error' ? 'pending' : dependent.status
              dependent.lastError = null
              await dbPut(dependent)
            }
          }
          await dbDelete(item.id)
          completed.add(item.id)
          if (item.tempId) completed.add(item.tempId)
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

  async function retryQueueItem(id) {
    const items = await dbGetAll()
    const item = items.find((entry) => entry.id === id)
    if (!item) return
    item.status = 'pending'
    item.lastError = null
    item.serverRecord = null
    await dbPut(item)
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
    retryQueueItem,
    clearQueue,
    refreshCount,
    lastSnapshotAt,
  }
}

export async function savePageSnapshot(page) {
  if (!page?.url || !page?.component) return null
  const parsed = new URL(page.url, window.location.origin)
  const snapshotUrl = `${parsed.pathname}${parsed.search}`
  const pathname = parsed.pathname
  const cacheable = [
    /^\/$/,
    /^\/income(?:\/|$)/,
    /^\/annual-budgets(?:\/|$)/,
    /^\/expenses(?:\/|$)/,
    /^\/disbursements(?:\/|$)/,
    /^\/iaeo(?:\/|$)/,
    /^\/reports(?:\/|$)/,
  ].some((pattern) => pattern.test(pathname))
  if (!cacheable) return null
  const snapshot = {
    url: snapshotUrl,
    component: page.component,
    props: page.props || {},
    cachedAt: new Date().toISOString(),
    ownerId: window.__BUDGET_TRACKER_USER_ID__ || null,
  }
  await snapshotPut(snapshot)
  lastSnapshotAt.value = snapshot.cachedAt
  return snapshot
}

export async function getPageSnapshot(url) {
  const parsed = new URL(url, window.location.origin)
  const exactKey = `${parsed.pathname}${parsed.search}`
  const pathKey = parsed.pathname
  const snapshot = (await snapshotGet(exactKey)) || (await snapshotGet(pathKey))
  const ownerId = window.__BUDGET_TRACKER_USER_ID__ || null
  if (snapshot && String(snapshot.ownerId || '') !== String(ownerId || '')) return null
  if (snapshot?.cachedAt) lastSnapshotAt.value = snapshot.cachedAt
  return snapshot
}
