import { ref } from 'vue'

export const requestError = ref('')

export function requestErrorMessage(status, offline = false) {
    if (offline) return 'This page is not available offline yet. Reconnect to the internet, then open it again.'
    return ({
        401: 'Please sign in again to continue.',
        403: 'Your account does not have permission to perform this action.',
        404: 'The requested page or record could not be found. Refresh the list and try again.',
        409: 'This record has changed. Refresh and review the latest version before trying again.',
        413: 'The uploaded file is too large. Choose a smaller file and try again.',
        419: 'Your session has expired. Refresh the page and sign in again before submitting.',
        422: 'Some information could not be accepted. Check the form or import file and try again.',
        429: 'Too many requests. Please wait a moment before trying again.',
        503: 'The service is temporarily unavailable. Please try again shortly.',
    })[status] || 'We could not complete this request. If you were saving data, check the records before retrying to avoid duplicates.'
}

export function showRequestError(message) {
    // A single shared alert also collapses repeated failures into one message.
    requestError.value = message
}
