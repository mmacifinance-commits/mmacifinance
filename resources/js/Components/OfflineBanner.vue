<script setup>
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { getPageSnapshot, useOfflineQueue } from '@/composables/useOfflineQueue'

const {
  isOnline,
  queueCount,
  isSyncing,
  syncError,
  lastSynced,
  syncQueue,
  getQueue,
  clearQueue,
  removeFromQueue,
  retryQueueItem,
  lastSnapshotAt,
} = useOfflineQueue()

const showQueue = ref(false)
const queueItems = ref([])
const syncSuccess = ref(false)
const syncResultMsg = ref('')

// Expand queue detail panel
async function toggleQueue() {
  showQueue.value = !showQueue.value
  if (showQueue.value) {
    queueItems.value = await getQueue()
  }
}

async function refreshSnapshotTime() {
  const url = window.location.pathname + window.location.search
  await getPageSnapshot(url).catch(() => null)
}

async function retryItem(item) {
  await retryQueueItem(item.id)
  queueItems.value = await getQueue()
  if (isOnline.value) await handleSync()
}

async function discardItem(item) {
  if (!confirm(`Discard queued action "${item.label}"?`)) return
  await removeFromQueue(item.id)
  queueItems.value = await getQueue()
}

async function handleSync() {
  showQueue.value = false
  const result = await syncQueue()

  if (result.succeeded > 0) {
    syncSuccess.value = true
    syncResultMsg.value = `✅ ${result.succeeded} action(s) synced successfully!`
    setTimeout(() => { syncSuccess.value = false; syncResultMsg.value = '' }, 4000)
    // Reload Inertia page to get fresh server data
    router.reload({ preserveScroll: true })
  }

  if (result.failed > 0) {
    syncResultMsg.value = `⚠️ ${result.failed} action(s) failed to sync. Check connection.`
    syncSuccess.value = false
  }

  // Refresh queue items if panel is open
  if (showQueue.value) {
    queueItems.value = await getQueue()
  }
}

async function handleClearQueue() {
  if (confirm('Clear all queued offline actions? They will be permanently discarded.')) {
    await clearQueue()
    queueItems.value = []
    showQueue.value = false
  }
}

// When coming back online, refresh queue display
watch(isOnline, async (online) => {
  if (!online) {
    await refreshSnapshotTime()
    return
  }

  if (showQueue.value) {
    queueItems.value = await getQueue()
  }

  if (queueCount.value > 0 && !isSyncing.value) {
    await handleSync()
  }
})

// Also covers reopening the app after connectivity was restored.
watch(queueCount, async (count, previousCount) => {
  if (showQueue.value) queueItems.value = await getQueue()
  if (count > 0 && previousCount === 0 && isOnline.value && !isSyncing.value) {
    await handleSync()
  }
})

const bannerVisible = computed(() =>
  !isOnline.value || queueCount.value > 0 || isSyncing.value || syncSuccess.value || syncResultMsg.value
)

function fmtTime(ts) {
  if (!ts) return ''
  return new Date(ts).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })
}

function prerequisite(item) {
  return item.dependsOn ? `Waiting for ${item.dependsOn}` : 'None'
}
</script>

<template>
  <!-- Main offline / sync banner -->
  <transition name="banner-slide">
    <div v-if="bannerVisible" class="offline-banner-wrapper">
      <!-- ── Offline banner ── -->
      <div
        v-if="!isOnline"
        class="offline-bar offline-bar--offline"
        role="alert"
        aria-live="assertive"
      >
        <div class="offline-bar__left">
          <span class="offline-dot offline-dot--red"></span>
          <span class="offline-bar__icon"></span>
          <div>
            <strong>You are offline</strong>
            <span v-if="queueCount > 0" class="offline-badge">
              {{ queueCount }} action{{ queueCount !== 1 ? 's' : '' }} queued
            </span>
            <span v-else class="offline-sub">Actions will be saved and synced when you reconnect</span>
            <span class="offline-sub">
              Cached data last updated: {{ lastSnapshotAt ? fmtTime(lastSnapshotAt) : 'not available for this page' }}
            </span>
          </div>
        </div>
        <div class="offline-bar__right">
          <button
            v-if="queueCount > 0"
            @click="toggleQueue"
            class="offline-btn offline-btn--ghost"
            :aria-expanded="showQueue"
          >
            {{ showQueue ? 'Hide' : 'View' }} Queue
          </button>
        </div>
      </div>

      <!-- ── Online with pending queue ── -->
      <div
        v-else-if="isOnline && queueCount > 0 && !isSyncing"
        class="offline-bar offline-bar--pending"
        role="alert"
        aria-live="polite"
      >
        <div class="offline-bar__left">
          <span class="offline-dot offline-dot--amber pulse-dot"></span>
          <span class="offline-bar__icon"></span>
          <div>
            <strong>Back online!</strong>
            <span class="offline-sub">
              {{ queueCount }} offline action{{ queueCount !== 1 ? 's' : '' }} pending sync
            </span>
          </div>
        </div>
        <div class="offline-bar__right">
          <button @click="toggleQueue" class="offline-btn offline-btn--ghost">
            {{ showQueue ? 'Hide' : 'View' }} Queue
          </button>
          <button @click="handleSync" class="offline-btn offline-btn--primary">
            🔄 Sync Now
          </button>
        </div>
      </div>

      <!-- ── Syncing ── -->
      <div
        v-else-if="isSyncing"
        class="offline-bar offline-bar--syncing"
        role="status"
        aria-live="polite"
      >
        <div class="offline-bar__left">
          <span class="offline-bar__spinner"></span>
          <strong>Syncing {{ queueCount }} item{{ queueCount !== 1 ? 's' : '' }}...</strong>
          <span class="offline-sub">Please don't close the window</span>
        </div>
      </div>

      <!-- ── Sync success flash ── -->
      <div
        v-else-if="syncSuccess"
        class="offline-bar offline-bar--success"
        role="status"
      >
        <div class="offline-bar__left">
          <span class="offline-dot offline-dot--green"></span>
          <strong>{{ syncResultMsg }}</strong>
        </div>
        <button @click="syncSuccess = false; syncResultMsg = ''" class="offline-btn offline-btn--ghost">✕</button>
      </div>

      <!-- ── Sync error ── -->
      <div
        v-else-if="syncResultMsg && !syncSuccess"
        class="offline-bar offline-bar--error"
        role="alert"
      >
        <div class="offline-bar__left">
          <span>{{ syncResultMsg }}</span>
        </div>
        <button @click="syncResultMsg = ''" class="offline-btn offline-btn--ghost">✕</button>
      </div>

      <!-- ── Queue detail drawer ── -->
      <transition name="drawer-slide">
        <div v-if="showQueue && queueItems.length > 0" class="queue-drawer">
          <div class="queue-drawer__header">
            <span class="queue-drawer__title">🗂️ Offline Action Queue</span>
            <div class="queue-drawer__actions">
              <button @click="handleClearQueue" class="offline-btn offline-btn--danger-ghost">
                🗑 Clear All
              </button>
              <button @click="showQueue = false" class="offline-btn offline-btn--ghost">✕</button>
            </div>
          </div>
          <div class="queue-drawer__body">
            <div
              v-for="item in queueItems"
              :key="item.id"
              class="queue-item"
              :class="{
                'queue-item--error': item.status === 'error',
                'queue-item--syncing': item.status === 'syncing',
              }"
            >
              <div class="queue-item__icon">
                <span v-if="item.method === 'POST'">➕</span>
                <span v-else-if="item.method === 'PUT'">✏️</span>
                <span v-else-if="item.method === 'DELETE'">🗑️</span>
              </div>
              <div class="queue-item__info">
                <div class="queue-item__label">{{ item.label }}</div>
                <div class="queue-item__meta">
                  <span class="queue-item__method" :class="`method--${item.method.toLowerCase()}`">
                    {{ item.method }}
                  </span>
                  <span class="queue-item__url">{{ item.url }}</span>
                  <span class="queue-item__time">{{ fmtTime(item.timestamp) }}</span>
                  <span>By: {{ item.ownerName || 'Current user' }}</span>
                  <span>Type: {{ item.resource || 'record' }}</span>
                  <span>Prerequisite: {{ prerequisite(item) }}</span>
                  <span v-if="item.status === 'error'" class="queue-item__error">
                    Validation error: {{ item.lastError }}
                  </span>
                  <span v-if="item.serverRecord" class="queue-item__error">
                    Conflict detected. Server record: {{ JSON.stringify(item.serverRecord) }}
                  </span>
                </div>
              </div>
              <div class="queue-item__status">
                <span v-if="item.status === 'pending'" class="status-badge status-badge--pending">Pending</span>
                <span v-else-if="item.status === 'error'" class="status-badge status-badge--error">Error</span>
                <span v-else-if="item.status === 'syncing'" class="status-badge status-badge--syncing">
                  <span class="offline-bar__spinner offline-bar__spinner--sm"></span>
                </span>
                <button v-if="item.status === 'error'" @click="retryItem(item)" class="offline-btn offline-btn--ghost">Retry</button>
                <button @click="discardItem(item)" class="offline-btn offline-btn--danger-ghost">Discard</button>
              </div>
            </div>
          </div>
          <div v-if="isOnline" class="queue-drawer__footer">
            <button @click="handleSync" class="offline-btn offline-btn--primary offline-btn--full">
              🔄 Sync All {{ queueItems.length }} Items Now
            </button>
          </div>
        </div>
      </transition>
    </div>
  </transition>
</template>

<style scoped>
/* ── Wrapper ────────────────────────────────────────────── */
.offline-banner-wrapper {
  position: sticky;
  top: 0;
  z-index: 9999;
  width: 100%;
  font-family: inherit;
}

/* ── Base bar ───────────────────────────────────────────── */
.offline-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.55rem 1.25rem;
  font-size: 0.825rem;
  font-weight: 500;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.offline-bar--offline  { background: #7f1d1d; color: #fee2e2; border-bottom: 2px solid #dc2626; }
.offline-bar--pending  { background: #78350f; color: #fef3c7; border-bottom: 2px solid #d97706; }
.offline-bar--syncing  { background: #1e3a8a; color: #dbeafe; border-bottom: 2px solid #3b82f6; }
.offline-bar--success  { background: #14532d; color: #dcfce7; border-bottom: 2px solid #22c55e; }
.offline-bar--error    { background: #7f1d1d; color: #fee2e2; border-bottom: 2px solid #dc2626; }

.offline-bar__left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.offline-bar__icon { font-size: 1rem; }
.offline-bar__right { display: flex; align-items: center; gap: 0.5rem; }

/* Subtle secondary text */
.offline-sub {
  opacity: 0.75;
  font-size: 0.78rem;
  margin-left: 0.3rem;
}

/* ── Status dot ──────────────────────────────────────────── */
.offline-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.offline-dot--red   { background: #f87171; box-shadow: 0 0 0 2px rgba(248,113,113,.3); }
.offline-dot--amber { background: #fbbf24; box-shadow: 0 0 0 2px rgba(251,191,36,.3); }
.offline-dot--green { background: #4ade80; box-shadow: 0 0 0 2px rgba(74,222,128,.3); }

@keyframes dot-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.5; transform: scale(1.4); }
}
.pulse-dot { animation: dot-pulse 1.5s ease-in-out infinite; }

/* ── Count badge ─────────────────────────────────────────── */
.offline-badge {
  background: rgba(255,255,255,0.2);
  border-radius: 999px;
  padding: 0.1rem 0.55rem;
  font-size: 0.72rem;
  font-weight: 700;
  margin-left: 0.4rem;
}

/* ── Spinner ──────────────────────────────────────────────── */
@keyframes spin { to { transform: rotate(360deg); } }
.offline-bar__spinner {
  display: inline-block;
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  flex-shrink: 0;
}
.offline-bar__spinner--sm { width: 12px; height: 12px; }

/* ── Buttons ──────────────────────────────────────────────── */
.offline-btn {
  padding: 0.3rem 0.8rem;
  border-radius: 6px;
  font-size: 0.775rem;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.15s;
  white-space: nowrap;
}
.offline-btn--ghost        { background: rgba(255,255,255,0.12); color: inherit; border-color: rgba(255,255,255,0.2); }
.offline-btn--ghost:hover  { background: rgba(255,255,255,0.22); }
.offline-btn--primary      { background: #d4a843; color: #1a2744; border-color: #d4a843; }
.offline-btn--primary:hover { background: #e0be6a; }
.offline-btn--danger-ghost  { background: rgba(239,68,68,0.15); color: #fca5a5; border-color: rgba(239,68,68,0.3); }
.offline-btn--danger-ghost:hover { background: rgba(239,68,68,0.28); }
.offline-btn--full { width: 100%; justify-content: center; }

/* ── Queue drawer ────────────────────────────────────────── */
.queue-drawer {
  background: #0f172a;
  border-bottom: 2px solid #334155;
  color: #e2e8f0;
  font-size: 0.8rem;
}

.queue-drawer__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.6rem 1.25rem;
  background: #1e293b;
  border-bottom: 1px solid #334155;
}

.queue-drawer__title {
  font-weight: 700;
  font-size: 0.8rem;
  color: #d4a843;
  letter-spacing: 0.05em;
}

.queue-drawer__actions {
  display: flex;
  gap: 0.4rem;
}

.queue-drawer__body {
  max-height: 280px;
  overflow-y: auto;
}

.queue-drawer__footer {
  padding: 0.75rem 1.25rem;
  border-top: 1px solid #334155;
}

/* ── Queue items ─────────────────────────────────────────── */
.queue-item {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.6rem 1.25rem;
  border-bottom: 1px solid #1e293b;
  transition: background 0.15s;
}
.queue-item:hover { background: #1e293b; }
.queue-item--error { border-left: 3px solid #ef4444; }
.queue-item--syncing { opacity: 0.7; }

.queue-item__icon { font-size: 1rem; padding-top: 2px; flex-shrink: 0; }

.queue-item__info { flex: 1; min-width: 0; }
.queue-item__label {
  font-weight: 600;
  color: #e2e8f0;
  margin-bottom: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.queue-item__meta {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
  color: #64748b;
  font-size: 0.72rem;
}
.queue-item__url   { font-family: monospace; color: #94a3b8; }
.queue-item__time  { color: #64748b; }
.queue-item__error { color: #f87171; font-size: 0.7rem; display: block; width: 100%; margin-top: 2px; }

.queue-item__status {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  flex: 0 0 auto;
  min-width: 11rem;
  flex-wrap: wrap;
}

.queue-item__status .offline-btn {
  flex: 0 0 auto;
  white-space: nowrap;
}

/* Method badges */
.queue-item__method {
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  font-weight: 800;
  font-size: 0.65rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.method--post   { background: rgba(34,197,94,.2);  color: #4ade80; }
.method--put    { background: rgba(234,179,8,.2);  color: #facc15; }
.method--delete { background: rgba(239,68,68,.2);  color: #f87171; }

/* Status badges */
.status-badge {
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.status-badge--pending { background: rgba(251,191,36,.15); color: #fbbf24; }
.status-badge--error   { background: rgba(239,68,68,.15);  color: #f87171; }
.status-badge--syncing { background: rgba(59,130,246,.15); color: #60a5fa; display: flex; align-items: center; }

@media (max-width: 700px) {
  .queue-item {
    flex-wrap: wrap;
  }

  .queue-item__info {
    flex: 1 1 calc(100% - 2rem);
  }

  .queue-item__status {
    flex: 1 1 100%;
    min-width: 0;
    justify-content: flex-end;
    padding-left: 1.75rem;
  }
}

/* ── Transitions ─────────────────────────────────────────── */
.banner-slide-enter-active, .banner-slide-leave-active {
  transition: all 0.25s ease;
}
.banner-slide-enter-from, .banner-slide-leave-to {
  opacity: 0;
  transform: translateY(-100%);
}

.drawer-slide-enter-active, .drawer-slide-leave-active {
  transition: all 0.2s ease;
  overflow: hidden;
}
.drawer-slide-enter-from, .drawer-slide-leave-to {
  max-height: 0;
  opacity: 0;
}
.drawer-slide-enter-to, .drawer-slide-leave-from {
  max-height: 600px;
  opacity: 1;
}
</style>
