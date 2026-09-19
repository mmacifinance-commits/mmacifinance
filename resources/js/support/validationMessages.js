export function uniqueMessages(value) {
    if (!value) return []
    if (typeof value === 'object') return [...new Set(Object.values(value).flatMap(uniqueMessages))]
    return typeof value === 'string' && value.trim() ? [value.trim()] : []
}

export function summaryMessages(errors, inlineFields = []) {
    return uniqueMessages(Object.fromEntries(Object.entries(errors || {}).filter(([field]) => !inlineFields.includes(field))))
}

export function unhandledMessages(errors, root) {
    const visibleText = [...root.querySelectorAll('p, li, [role="alert"]')]
        .filter(element => !element.closest('[data-global-request-error]') && element.getClientRects().length)
        .map(element => element.textContent.trim())
    return uniqueMessages(errors).filter(message => !visibleText.some(text => text.includes(message)))
}
