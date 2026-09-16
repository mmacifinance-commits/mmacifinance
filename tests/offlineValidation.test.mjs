import { test } from 'node:test'
import assert from 'node:assert/strict'
import { offlineValidation } from '../resources/js/support/offlineValidation.js'
test('income requires receipt type only with a receipt number', () => {
    const data = { source: 'Tuition', description: 'Collection', amount: 10, date_encoded: '2026-09-16' }
    assert.deepEqual(offlineValidation('/income', data), {})
    assert.ok(offlineValidation('/income', { ...data, receipt_no: '123' }).receipt_type)
    assert.deepEqual(offlineValidation('/income', { ...data, receipt_no: '123', receipt_type: 'Tuition' }), {})
})
test('all offline modules reject incomplete forms and invalid amounts and dates', () => {
    for (const path of ['/income', '/receipts', '/expenses', '/disbursements', '/departments', '/budget-categories', '/budget-particulars', '/annual-budgets', '/annual-budgets/1/items']) assert.ok(Object.keys(offlineValidation(path, {})).length, path)
    assert.ok(offlineValidation('/expenses', { amount: -1 }).amount)
    assert.ok(offlineValidation('/income', { date_encoded: '2026-02-30' }).date_encoded)
})
