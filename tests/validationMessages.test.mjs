import { test } from 'node:test'
import assert from 'node:assert/strict'
import { uniqueMessages, summaryMessages, unhandledMessages } from '../resources/js/support/validationMessages.js'

test('summaries retain general errors without repeating inline errors', () => {
    assert.deepEqual(summaryMessages({ amount: 'Too large', offline: 'Reconnect', other: 'Reconnect' }, ['amount']), ['Reconnect'])
    assert.deepEqual(uniqueMessages(['Same', { field: ['Same', 'Different'] }]), ['Same', 'Different'])
})

test('global fallback shows only errors not already visible locally', () => {
    const element = (text, visible = true, global = false) => ({ textContent: text, getClientRects: () => visible ? [{}] : [], closest: () => global ? {} : null })
    const root = { querySelectorAll: () => [element('Period overlaps'), element('Hidden error', false), element('Deletion blocked', true, true)] }
    assert.deepEqual(unhandledMessages({ date: 'Period overlaps', deletion: 'Deletion blocked', other: 'Hidden error' }, root), ['Deletion blocked', 'Hidden error'])
})
