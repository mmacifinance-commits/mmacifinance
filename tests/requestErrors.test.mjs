import { test } from 'node:test'
import assert from 'node:assert/strict'
import { requestError, requestErrorMessage, showRequestError } from '../resources/js/support/requestErrors.js'

test('offline cache misses explain how to recover', () => {
    assert.match(requestErrorMessage(503, true), /not available offline/)
    assert.match(requestErrorMessage(503, false), /temporarily unavailable/)
})

test('session, permissions, uploads and throttling have actionable messages', () => {
    for (const [status, expected] of [[419, /session has expired/], [403, /permission/], [413, /smaller file/], [429, /wait/]]) {
        assert.match(requestErrorMessage(status), expected)
    }
    assert.match(requestErrorMessage(500), /check the records before retrying/)
})

test('repeated errors use one shared message instead of stacking notifications', () => {
    showRequestError('Connection lost')
    showRequestError('Connection lost')
    assert.equal(requestError.value, 'Connection lost')
    showRequestError('Session expired')
    assert.equal(requestError.value, 'Session expired')
})
