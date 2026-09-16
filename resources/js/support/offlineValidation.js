export function offlineValidation(url, data = {}) {
    const path = String(url).replace(/^https?:\/\/[^/]+/, '').split('?')[0]
    const section = path.split('/').filter(Boolean)[0]
    const required = {
        income: ['source', 'description', 'amount', 'date_encoded'],
        receipts: ['receipt_no', 'receipt_type', 'source', 'description', 'amount', 'date_encoded'],
        expenses: ['description', 'category_id', 'particular_id', 'budget_item_id', 'amount', 'date_encoded'],
        disbursements: ['expense_id', 'description', 'source', 'pay_to', 'amount', 'method', 'date_encoded'],
        departments: ['name', 'code'],
        'budget-categories': ['name'],
        'budget-particulars': ['category_id', 'department_id', 'account_code', 'account_name', 'particular'],
        'annual-budgets': path.includes('/items') ? ['category_id', 'department_id', 'particular_id', 'allocation_month', 'appropriation'] : ['start_date', 'end_date'],
    }[section] || []
    const errors = {}
    const label = key => key.replaceAll('_', ' ').replace(/ id$/, '')
    const blank = value => value == null || String(value).trim() === ''
    for (const key of required) if (blank(data[key])) errors[key] = `Please enter ${label(key)}.`
    if (section === 'income' && !blank(data.receipt_no) && blank(data.receipt_type)) errors.receipt_type = 'Receipt type is required when a receipt number is entered.'
    for (const key of ['amount', 'appropriation']) {
        if (blank(data[key])) continue
        const minimum = ['expenses', 'disbursements'].includes(section) ? 0.01 : 0
        if (!Number.isFinite(Number(data[key])) || Number(data[key]) < minimum) errors[key] = `${label(key)} must be a valid number of at least ${minimum}.`
    }
    for (const key of ['date_encoded', 'allocation_month', 'start_date', 'end_date']) {
        if (blank(data[key])) continue
        const value = String(data[key])
        const date = new Date(`${value}T00:00:00Z`)
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== value) errors[key] = `Please enter a valid ${label(key)}.`
    }
    if (data.method && !['cash', 'check', 'bank_transfer'].includes(data.method)) errors.method = 'Choose a valid payment method.'
    if (data.start_date && data.end_date && data.end_date <= data.start_date) errors.end_date = 'End date must be after the start date.'
    for (const key of ['receipt_no', 'receipt_type', 'source', 'description', 'name', 'code', 'account_code', 'account_name', 'particular', 'pay_to']) {
        const max = ['receipt_no', 'receipt_type'].includes(key) ? 100 : section === 'departments' && key === 'code' ? 10 : 255
        if (data[key] != null && String(data[key]).length > max) errors[key] = `${label(key)} must not exceed ${max} characters.`
    }
    return errors
}
