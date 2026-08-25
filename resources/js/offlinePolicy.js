const ONLINE_ONLY_PATTERNS = [
    /^\/(login|logout|2fa|forgot-password|reset-password)(\/|$)/,
    /^\/tutorial(\/|$)/,
    /\/(import-csv|export-csv)(\/|$)/,
    /\/(approve|reject|return|post|submit)(\/|$)/,
    /\/rollover(\/|$)/,
]

const SAFE_DRAFT_PATTERNS = [
    { resource: 'income', rank: 10, pattern: /^\/income(?:\/\d+)?$/ },
    { resource: 'budget', rank: 20, pattern: /^\/annual-budgets(?:\/\d+\/items(?:\/\d+)?)?$/ },
    { resource: 'expense', rank: 30, pattern: /^\/expenses(?:\/\d+)?$/ },
    { resource: 'disbursement', rank: 40, pattern: /^\/disbursements(?:\/\d+)?$/ },
]

export function offlinePolicy(method, url, data = {}) {
    const pathname = new URL(String(url), window.location.origin).pathname
    const normalizedMethod = String(method).toUpperCase()

    if (ONLINE_ONLY_PATTERNS.some((pattern) => pattern.test(pathname))) {
        return { allowed: false, reason: 'This workflow action requires an internet connection.' }
    }

    if (normalizedMethod === 'DELETE') {
        return { allowed: false, reason: 'Financial record deletion requires an internet connection.' }
    }

    const match = SAFE_DRAFT_PATTERNS.find(({ pattern }) => pattern.test(pathname))
    if (!match || !['POST', 'PUT', 'PATCH'].includes(normalizedMethod)) {
        return { allowed: false, reason: 'This action is not available offline.' }
    }

    if (match.resource === 'disbursement' && !data.expense_id) {
        return { allowed: false, reason: 'An offline disbursement draft must reference an existing expense.' }
    }

    return { allowed: true, resource: match.resource, rank: match.rank, pathname }
}

export function findRecordVersion(props, resource, pathname) {
    const ids = [...pathname.matchAll(/\/(\d+)(?=\/|$)/g)]
    const id = ids.at(-1)?.[1]
    if (!id) return null

    const roots = {
        income: ['incomeRecords'],
        budget: ['budgets', 'budget', 'annualBudgetItems', 'budgetItems'],
        expense: ['expenses'],
        disbursement: ['disbursements'],
    }[resource] || []

    const visit = (value) => {
        if (!value || typeof value !== 'object') return null
        if (String(value.id ?? '') === String(id) && value.updated_at) return value.updated_at
        const values = Array.isArray(value) ? value : Object.values(value)
        for (const child of values) {
            const found = visit(child)
            if (found) return found
        }
        return null
    }

    for (const key of roots) {
        const found = visit(props?.[key])
        if (found) return found
    }
    return null
}
