import { test } from 'node:test'
import assert from 'node:assert/strict'
import { queueTitle, queueDetails } from '../resources/js/support/offlineQueueDetails.js'

test('existing queued records get readable action names', () => {
    assert.equal(queueTitle({ method: 'POST', url: '/income', resource: 'record' }), 'Create Income')
    assert.equal(queueTitle({ method: 'PUT', resource: 'receipt' }), 'Update Receipt')
})
test('shows actual business details including zero amounts but omits secrets and raw IDs', () => {
    const details = queueDetails({ source: 'Tuition', amount: 0, notes: 'Pending collection', password: 'secret', category_id: 1 })
    assert.deepEqual(details.map(row => row.key), ['source', 'amount', 'notes'])
    assert.match(details[1].text, /0\.00/)
    assert.deepEqual(queueDetails(null), [])
})
