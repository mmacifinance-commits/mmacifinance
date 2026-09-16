import { test } from 'node:test'
import assert from 'node:assert/strict'
import { indexedDB } from 'fake-indexeddb'
import 'vue'

globalThis.indexedDB = indexedDB
globalThis.window = Object.assign(new EventTarget(), { location: { origin: 'http://localhost' }, __BUDGET_TRACKER_USER_ID__: 1 })
globalThis.document = { querySelector: () => ({ getAttribute: () => 'test-csrf' }) }
Object.defineProperty(globalThis, 'navigator', { value: { onLine: true }, configurable: true })
const { queueOfflineAction, useOfflineQueue, savePageSnapshot, getPageSnapshot } = await import('../resources/js/composables/useOfflineQueue.js')
const { syncQueue, getQueue, clearQueue } = useOfflineQueue()

test('failed saves stay recoverable and retry with the same action ID', async () => {
    await clearQueue()
    const item = await queueOfflineAction('POST', '/income', { amount: 50 })
    let sentId
    globalThis.fetch = async (_url, options) => { sentId = options.headers['X-Offline-Action-Id']; throw new Error('Connection lost') }
    assert.equal((await syncQueue()).failed, 1)
    assert.equal((await getQueue())[0].status, 'error')
    assert.equal(sentId, item.id)
    globalThis.fetch = async (_url, options) => {
        assert.equal(options.headers['X-Offline-Action-Id'], item.id)
        return Response.json({ id: 10 })
    }
    assert.equal((await syncQueue()).succeeded, 1)
    assert.equal((await getQueue()).length, 0)
})

test('dependent record mapping survives a failed sync and restart', async () => {
    await clearQueue()
    await queueOfflineAction('POST', '/expenses', {}, '', { tempId: 'offline-expense-parent', rank: 30 })
    await queueOfflineAction('POST', '/disbursements', { expense_id: 'offline-expense-parent' }, '', { dependsOn: 'offline-expense-parent', rank: 40 })
    globalThis.fetch = async (url) => {
        if (url === '/expenses') return Response.json({ id: 100 })
        throw new Error('Connection lost')
    }
    const first = await syncQueue()
    assert.equal(first.succeeded, 1)
    assert.equal(first.failed, 1)
    const [remaining] = await getQueue()
    assert.equal(remaining.dependsOn, null)
    assert.equal(remaining.data.expense_id, 100)
    globalThis.fetch = async (_url, options) => {
        assert.equal(JSON.parse(options.body).expense_id, 100)
        return Response.json({ id: 101 })
    }
    assert.equal((await syncQueue()).succeeded, 1)
})

test('another account cannot send an existing offline action', async () => {
    await clearQueue()
    await queueOfflineAction('POST', '/income', {})
    window.__BUDGET_TRACKER_USER_ID__ = 2
    globalThis.fetch = async () => { assert.fail('Must not send another account action') }
    assert.equal((await syncQueue()).failed, 1)
    assert.equal((await getQueue()).length, 1)
    window.__BUDGET_TRACKER_USER_ID__ = 1
    await clearQueue()
})

test('snapshots never substitute an unfiltered page for missing filtered data', async () => {
    await savePageSnapshot({ url: '/receipts', component: 'Receipts/Index', props: {} })
    assert.ok(await getPageSnapshot('/receipts'))
    assert.equal(await getPageSnapshot('/receipts?search=missing'), null)
    window.__BUDGET_TRACKER_USER_ID__ = 2
    assert.equal(await getPageSnapshot('/receipts'), null)
    window.__BUDGET_TRACKER_USER_ID__ = 1
})
