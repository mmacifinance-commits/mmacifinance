const names = { income: 'Income', receipt: 'Receipt', expense: 'Expenditure', disbursement: 'Disbursement', department: 'Responsibility Center', budgetCategory: 'Budget Category', accountTitle: 'Account Title', budget: 'Budget Allocation' }
const paths = { income: 'income', receipts: 'receipt', expenses: 'expense', disbursements: 'disbursement', departments: 'department', 'budget-categories': 'budgetCategory', 'budget-particulars': 'accountTitle', 'annual-budgets': 'budget' }

export function queueTitle(item) {
    const path = String(item.url || '').replace(/^https?:\/\/[^/]+/, '').split('/').filter(Boolean)[0]
    const resource = names[item.resource] || names[paths[path]] || 'Record'
    const action = { POST: 'Create', PUT: 'Update', PATCH: 'Update', DELETE: 'Delete' }[String(item.method).toUpperCase()] || 'Save'
    return `${action} ${resource}`
}

// Only show known business fields, never arbitrary queued payloads or credentials.
export function queueDetails(data = {}) {
    const fields = {
        receipt_no: 'Receipt number', receipt_type: 'Receipt type', income_no: 'Income number',
        ref_no: 'Reference', disbursement_no: 'Disbursement number', source: 'Source',
        description: 'Description', pay_to: 'Payee', amount: 'Amount', appropriation: 'Appropriation',
        date_encoded: 'Date', allocation_month: 'Allocation month', method: 'Payment method',
        name: 'Name', code: 'Code', account_code: 'Account code', account_name: 'Account name',
        particular: 'Account title', status: 'Status', notes: 'Notes', remarks: 'Remarks',
    }
    return Object.entries(fields).flatMap(([key, label]) => {
        const value = data?.[key]
        if (value == null || value === '' || typeof value === 'object') return []
        const text = ['amount', 'appropriation'].includes(key) && Number.isFinite(Number(value))
            ? new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value))
            : ['status', 'method'].includes(key) ? String(value).replaceAll('_', ' ') : String(value)
        return [{ key, label, text }]
    })
}
