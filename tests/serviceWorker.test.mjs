import { test } from 'node:test'
import assert from 'node:assert/strict'
import vm from 'node:vm'
import { readFileSync } from 'node:fs'

test('cached Inertia pages are isolated by account and exact filters', async () => {
    const entries = new Map()
    const context = vm.createContext({
        self: { location: { origin: 'http://localhost' }, addEventListener() {} },
        Request, Response, URL,
        caches: { open: async () => ({ put: async (key, value) => entries.set(key.url, value), match: async (key) => entries.get(key.url) }) },
    })
    vm.runInContext(readFileSync(new URL('../public/sw.js', import.meta.url), 'utf8'), context)
    const cachePage = vm.runInContext('cacheInertiaResponse', context)
    const matchPage = vm.runInContext('matchInertiaResponse', context)
    const response = Response.json({ component: 'Receipts/Index', props: { auth: { user: { id: 1 } } } }, { headers: { 'X-Inertia': 'true' } })
    await cachePage('http://localhost/receipts?page=2', response, '1')
    assert.ok(await matchPage('http://localhost/receipts?page=2', '1'))
    assert.equal(await matchPage('http://localhost/receipts?page=2', '2'), undefined)
    assert.equal(await matchPage('http://localhost/receipts?page=1', '1'), undefined)
    assert.equal(await matchPage('http://localhost/receipts?page=2', ''), null)
    await cachePage('http://localhost/receipts', Response.json({ component: 'Auth/Login', props: {} }), '1')
    assert.equal(await matchPage('http://localhost/receipts', '1'), undefined)
})
