<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const pageProps = computed(() => usePage().props || {})
const perms = computed(() => ({
    ...(pageProps.value.userPermissions || {}),
    isSuperAdmin: pageProps.value.userPermissions?.isSuperAdmin || pageProps.value.auth?.user?.role === 'super_admin',
    canManageDisbursements: pageProps.value.userPermissions?.canManageDisbursements || pageProps.value.auth?.user?.role === 'super_admin',
}))
const userRole = computed(() => pageProps.value.userRole || pageProps.value.auth?.user?.role)

const props = defineProps({
    disbursements: Array,
    expenses: Array,
    budgetYears: Array,
    availableYears: Array,
    defaultYear: [Number, String],
    userRole: String,
    userPermissions: Object,
})

const disbursementItems = computed(() => props.disbursements?.data || props.disbursements || [])

const showModal = ref(false)
const showAuditModal = ref(false)
const showActionModal = ref(false)
const actionType = ref('') // 'approve', 'post', 'reject', 'return'
const selectedDsb = ref(null)

const actionForm = useForm({
    remarks: ''
})

const editing = ref(null)
const form = useForm({
    expense_id: '',
    description: '',
    source: 'Expenditure',
    pay_to: '',
    amount: 0,
    method: 'check',
    date_encoded: '',
    status: 'draft',
    notes: '',
    remarks: '',
})

const offlineRows = ref([])

const filterSearch = ref('')
const filterYear = ref(props.defaultYear ? String(props.defaultYear) : 'all')
const filterMethod = ref('')
const filterStatus = ref('')
const linkedExpenseFilter = ref('all')
const linkedExpenseSearch = ref('')

function expensePaymentState(expense) {
    const amount = Number(expense?.amount || 0)
    const paid = Number(expense?.paid || 0)
    if (paid <= 0) return 'unpaid'
    if (amount > 0 && paid < amount) return 'partial'
    return 'fully_paid'
}

function expensePaymentLabel(expense) {
    const state = expensePaymentState(expense)
    return {
        unpaid: 'Unpaid',
        partial: 'Partially Paid',
        fully_paid: 'Fully Paid',
    }[state] || 'Unpaid'
}

function expensePaymentBadgeClass(expense) {
    const state = expensePaymentState(expense)
    return {
        unpaid: 'bg-rose-100 text-rose-700 border-rose-200',
        partial: 'bg-amber-100 text-amber-700 border-amber-200',
        fully_paid: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    }[state] || 'bg-slate-100 text-slate-700 border-slate-200'
}

const filteredDisbursements = computed(() => {
    const all = [...disbursementItems.value, ...offlineRows.value]
    return all.filter(d => {
        const matchSearch = filterSearch.value ?
            ((d.disbursement_no || '').toLowerCase().includes(filterSearch.value.toLowerCase()) ||
             (d.description || '').toLowerCase().includes(filterSearch.value.toLowerCase()) ||
             (d.pay_to || '').toLowerCase().includes(filterSearch.value.toLowerCase()) ||
             (d.expense?.ref_no || '').toLowerCase().includes(filterSearch.value.toLowerCase())) : true
        const matchMethod = filterMethod.value ? d.method === filterMethod.value : true
        const matchStatus = filterStatus.value ? d.status === filterStatus.value : true

        let matchYear = true
        if (filterYear.value && filterYear.value !== 'all') {
            const dsbYear = d.date_encoded ? String(d.date_encoded).slice(0, 4) : (d.created_at ? String(d.created_at).slice(0, 4) : null)
            matchYear = dsbYear ? String(dsbYear) === String(filterYear.value) : false
        }

        return matchSearch && matchMethod && matchStatus && matchYear
    })
})

const filteredExpensesForModal = computed(() => {
    let rows = [...(props.expenses || [])].filter(e => String(e.status || '').toLowerCase() === 'approved')

    if (linkedExpenseSearch.value.trim()) {
        const q = linkedExpenseSearch.value.trim().toLowerCase()
        rows = rows.filter(e =>
            (e.ref_no || '').toLowerCase().includes(q) ||
            (e.description || '').toLowerCase().includes(q) ||
            (e.pay_to || '').toLowerCase().includes(q)
        )
    }

    if (filterYear.value && filterYear.value !== 'all') {
        rows = rows.filter(e => {
            const expYear = e.date_encoded ? String(e.date_encoded).slice(0, 4) : (e.created_at ? String(e.created_at).slice(0, 4) : null)
            return String(expYear) === String(filterYear.value)
        })
    }

    if (linkedExpenseFilter.value !== 'all') {
        rows = rows.filter(e => expensePaymentState(e) === linkedExpenseFilter.value)
    }

    const sortOrder = { unpaid: 0, partial: 1, fully_paid: 2 }
    return rows.sort((a, b) => {
        const diff = (sortOrder[expensePaymentState(a)] ?? 9) - (sortOrder[expensePaymentState(b)] ?? 9)
        return diff !== 0 ? diff : String(a.ref_no || '').localeCompare(String(b.ref_no || ''))
    })
})

function clearFilters() {
    filterSearch.value = ''
    filterYear.value = props.defaultYear ? String(props.defaultYear) : 'all'
    filterMethod.value = ''
    filterStatus.value = ''
}

function clearLinkedExpenseFilter() {
    linkedExpenseFilter.value = 'all'
}

function clearLinkedExpenseSearch() {
    linkedExpenseSearch.value = ''
}

const selectedExpense = computed(() => {
    return props.expenses?.find(e => String(e.id) === String(form.expense_id)) || null
})

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(v || 0) }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—' }

const PESO = '₱'

function openCreate() {
    const budgetYears = (props.budgetYears || []).map(y => Number(y))
    if (filterYear.value !== 'all' && budgetYears.length && !budgetYears.includes(Number(filterYear.value))) {
        alert(`No annual budget exists for FY ${filterYear.value}. Please create the annual budget first.`)
        return
    }

    form.reset()
    form.expense_id = filteredExpensesForModal.value[0]?.id || ''
    if (form.expense_id) {
        const exp = props.expenses?.find(e => String(e.id) === String(form.expense_id))
        if (exp) {
            form.description = exp.description
            form.amount = Math.max(0, Number(exp.amount) - Number(exp.paid))
        }
    }
    form.source = 'Expenditure'
    form.status = (perms.value.isCashier ? 'for_approval' : 'draft')
    form.date_encoded = new Date().toISOString().slice(0, 10)
    editing.value = null
    showModal.value = true
}

function openEdit(d) {
    Object.assign(form, {
        expense_id: d.expense_id || '',
        description: d.description,
        source: d.source,
        pay_to: d.pay_to,
        amount: d.amount,
        method: d.method,
        status: d.status,
        notes: d.notes || '',
        remarks: d.remarks || '',
        date_encoded: d.date_encoded?.slice(0, 10) || '',
    })
    editing.value = d.id
    showModal.value = true
}

function onExpenseSelect(event) {
    const selectedId = event.target.value
    if (!selectedId) return
    const exp = props.expenses?.find(e => String(e.id) === String(selectedId))
    if (exp) {
        if (!form.description) form.description = exp.description
        const remaining = Math.max(0, Number(exp.amount) - Number(exp.paid))
        if (!form.amount || form.amount === 0) form.amount = remaining
    }
}

function save() {
    if (editing.value) {
        form.put(`/disbursements/${editing.value}`, { onSuccess: () => { showModal.value = false } })
    } else {
        form.post('/disbursements', { onSuccess: () => { showModal.value = false } })
    }
}

function remove(id) {
    if (confirm('Are you sure you want to delete this disbursement record?')) {
        router.delete(`/disbursements/${id}`)
    }
}

function submitForApproval(d) {
    if (confirm('Submit this disbursement for approval to the Head of Finance?')) {
        router.post(`/disbursements/${d.id}/submit`, { remarks: 'Released and submitted by Cashier' })
    }
}

function openActionModal(d, type) {
    selectedDsb.value = d
    actionType.value = type
    actionForm.reset()
    showActionModal.value = true
}

function executeAction() {
    if (!selectedDsb.value || !actionType.value) return
    const dId = selectedDsb.value.id
    const endpoint = `/disbursements/${dId}/${actionType.value}`
    actionForm.post(endpoint, {
        onSuccess: () => {
            showActionModal.value = false
            selectedDsb.value = null
        }
    })
}

function openAuditLogs(d) {
    selectedDsb.value = d
    showAuditModal.value = true
}

const statusBadgeStyles = {
    draft: 'bg-slate-100 text-slate-700 border-slate-300',
    for_release: 'bg-indigo-100 text-indigo-800 border-indigo-300',
    for_approval: 'bg-amber-100 text-amber-800 border-amber-300',
    approved: 'bg-blue-100 text-blue-800 border-blue-300',
    posted: 'bg-emerald-100 text-emerald-800 border-emerald-300',
    rejected: 'bg-rose-100 text-rose-800 border-rose-300',
    returned_for_revision: 'bg-purple-100 text-purple-800 border-purple-300',
}

const statusLabels = {
    draft: 'Draft',
    for_release: 'For Release',
    for_approval: 'For Approval',
    approved: 'Approved',
    posted: 'Posted (GL)',
    rejected: 'Rejected',
    returned_for_revision: 'Returned for Revision',
}

const methodLabels = { check: 'Check', cash: 'Cash', bank_transfer: 'Bank Transfer' }
</script>

<template>
<Head title="Disbursements" />
<AppLayout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Disbursements & Workflow</h2>
            <p class="text-sm text-gray-500">Manage payment release, approval, and posting of linked expenses to General Ledger</p>
        </div>
        <button v-if="perms.canManageDisbursements || perms.isCashier || perms.isSuperAdmin" @click="openCreate" class="rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">
            Create Payment Release
        </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <input v-model="filterSearch" type="text" placeholder="Search DSB no, pay to, description, expense ref..." class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm w-full max-w-sm shadow-sm" />
        <select v-model="filterYear" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 min-w-[140px] shadow-sm">
            <option value="all">All Years</option>
            <option v-for="y in (availableYears || [])" :key="y" :value="String(y)">Year {{ y }}</option>
        </select>
        <select v-model="filterMethod" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 min-w-[150px] shadow-sm">
            <option value="">All Methods</option>
            <option value="check">Check</option>
            <option value="cash">Cash</option>
            <option value="bank_transfer">Bank Transfer</option>
        </select>
        <select v-model="filterStatus" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 min-w-[180px] shadow-sm">
            <option value="">All Workflow Stages</option>
            <option value="draft">Draft</option>
            <option value="for_approval">For Approval</option>
            <option value="approved">Approved</option>
            <option value="posted">Posted</option>
            <option value="rejected">Rejected</option>
            <option value="returned_for_revision">Returned for Revision</option>
        </select>
    </div>

    <!-- Table -->
    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto w-full pb-4">
            <table class="w-full text-sm min-w-max whitespace-nowrap">
                <thead>
                    <tr class="bg-navy-dark text-white border-b-2 border-mustard">
                        <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-white">DSB Reference</th>
                        <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-white">Expense Ref</th>
                        <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-white">Payee / Description</th>
                        <th class="px-5 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-white">Disbursement Amount</th>
                        <th class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-white">Payment Method</th>
                        <th class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-white">Workflow Status</th>
                        <th class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-white">Audit Trail</th>
                        <th class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-white">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in filteredDisbursements" :key="d.id" class="border-b border-gray-100 hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-4 font-mono text-xs font-bold text-navy align-middle">
                            {{ d.disbursement_no }}
                        </td>
                        <td class="px-5 py-4 font-mono text-xs text-blue-600 font-semibold align-middle">
                            {{ d.expense?.ref_no || '—' }}
                        </td>
                        <td class="px-5 py-4 align-middle">
                            <div class="font-semibold text-gray-900 text-sm">{{ d.pay_to }}</div>
                            <div class="text-xs text-gray-500">{{ d.description }}</div>
                        </td>
                        <td class="px-5 py-4 text-right font-mono text-sm font-bold text-gray-900 align-middle">{{ PESO }}{{ fmt(d.amount) }}</td>
                        <td class="px-5 py-4 text-center text-xs align-middle">
                            <span class="px-2.5 py-1 rounded bg-slate-100 text-slate-700 font-medium border border-slate-200">
                                {{ methodLabels[d.method] || d.method }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-center align-middle">
                            <span :class="[statusBadgeStyles[d.status] || 'bg-gray-100 text-gray-800', 'inline-flex items-center rounded-full px-3 py-1 text-xs font-bold border shadow-2xs']">
                                {{ statusLabels[d.status] || d.status }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-center align-middle">
                            <button @click="openAuditLogs(d)" class="text-xs text-indigo-700 hover:text-indigo-900 font-semibold bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded border border-indigo-200 transition">
                                View Stamps
                            </button>
                        </td>
                        <td class="px-5 py-4 text-center align-middle">
                            <div class="inline-flex items-center gap-2">
                                <!-- Cashier Submit Action -->
                                <button v-if="(d.status === 'draft' || d.status === 'returned_for_revision') && (perms.isCashier || perms.canManageDisbursements || perms.isSuperAdmin)"
                                    @click="submitForApproval(d)"
                                    class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded text-xs font-bold shadow-sm transition">
                                    Submit Release
                                </button>

                                <!-- Head of Finance Approve Action -->
                                <button v-if="d.status === 'for_approval' && perms.canApprove"
                                    @click="openActionModal(d, 'approve')"
                                    class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-bold shadow-sm transition">
                                    Approve
                                </button>

                                <!-- Head of Finance Post Action -->
                                <button v-if="d.status === 'approved' && perms.canPost"
                                    @click="openActionModal(d, 'post')"
                                    class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-bold shadow-sm transition">
                                    Post Release
                                </button>

                                <!-- Head of Finance Reject / Return Actions -->
                                <button v-if="d.status === 'for_approval' && perms.canApprove"
                                    @click="openActionModal(d, 'return')"
                                    class="px-2 py-1 bg-purple-50 text-purple-700 hover:bg-purple-100 rounded text-xs font-semibold border border-purple-200 transition">
                                    Return
                                </button>

                                <button v-if="d.status === 'for_approval' && perms.canApprove"
                                    @click="openActionModal(d, 'reject')"
                                    class="px-2 py-1 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded text-xs font-semibold border border-rose-200 transition">
                                    Reject
                                </button>

                                <!-- Standard Edit Button -->
                                <button v-if="d.status !== 'posted' || perms.isSuperAdmin"
                                    @click="openEdit(d)"
                                    class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded text-xs font-semibold border border-indigo-200 shadow-2xs transition">
                                    Edit
                                </button>

                                <!-- Standard Delete Button -->
                                <button v-if="d.status !== 'posted' || perms.isSuperAdmin"
                                    @click="remove(d.id)"
                                    class="px-2.5 py-1 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded text-xs font-semibold border border-rose-200 shadow-2xs transition">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="filteredDisbursements.length === 0">
                        <td colspan="8" class="px-5 py-8 text-center text-sm text-gray-500">No disbursement records found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 bg-gray-50 text-xs text-gray-500 border-t flex items-center justify-between">
            <span>Total Records: {{ filteredDisbursements.length }}</span>
            <button type="button" @click="clearFilters" class="px-3 py-1.5 rounded bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 font-semibold shadow-sm">Clear Filters</button>
        </div>
    </div>

    <div v-if="disbursements?.links?.length" class="mt-4 flex flex-wrap gap-2">
        <button
            v-for="link in disbursements.links"
            :key="link.label"
            :disabled="!link.url"
            @click="link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })"
            v-html="link.label"
            class="rounded-md border px-3 py-1.5 text-xs font-semibold transition"
            :class="link.active ? 'border-navy-dark bg-navy-dark text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50'"
        />
    </div>

    <!-- Create/Edit Disbursement Modal -->
    <Modal :show="showModal" :title="editing ? 'Edit Disbursement' : 'Create Disbursement'" :subtitle="editing ? 'Update disbursement release details.' : 'Enter release details and submit for approval.'" max-width="5xl" @close="showModal = false">
        <form @submit.prevent="save">
            <div class="grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
                <!-- Linked expense picker -->
                <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <div class="min-w-[220px] flex-1">
                            <label class="block text-sm font-medium">Linked Expense <span class="text-red-500">*</span></label>
                            <p class="mt-1 text-xs text-slate-500">
                                Search expenses by reference, description, or payee.
                            </p>
                        </div>
                        <div class="inline-flex items-center rounded-lg border border-slate-200 bg-white p-1 text-[11px] font-semibold text-slate-600 shadow-sm">
                            <button type="button" @click="linkedExpenseFilter = 'all'" :class="linkedExpenseFilter === 'all' ? 'bg-navy-dark text-white shadow-sm' : 'text-slate-500'" class="rounded-md px-2 py-1 transition">All</button>
                            <button type="button" @click="linkedExpenseFilter = 'unpaid'" :class="linkedExpenseFilter === 'unpaid' ? 'bg-rose-600 text-white shadow-sm' : 'text-slate-500'" class="rounded-md px-2 py-1 transition">Unpaid</button>
                            <button type="button" @click="linkedExpenseFilter = 'partial'" :class="linkedExpenseFilter === 'partial' ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-500'" class="rounded-md px-2 py-1 transition">Partial</button>
                            <button type="button" @click="linkedExpenseFilter = 'fully_paid'" :class="linkedExpenseFilter === 'fully_paid' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-500'" class="rounded-md px-2 py-1 transition">Paid</button>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                        <div class="relative">
                            <input
                                v-model="linkedExpenseSearch"
                                type="text"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 pr-16 text-sm focus:border-navy focus:outline-none"
                                placeholder="Search ref no, description, payee..."
                            />
                            <button
                                v-if="linkedExpenseSearch"
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md px-2 py-1 text-[11px] font-semibold text-indigo-600 hover:bg-indigo-50"
                                @click="clearLinkedExpenseSearch"
                            >
                                Clear
                            </button>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                            @click="clearLinkedExpenseFilter"
                        >
                            Reset Filters
                        </button>
                    </div>

                    <p class="text-xs text-slate-500">
                        Showing {{ filteredExpensesForModal.length }} approved expenses eligible for disbursement for {{ filterYear === 'all' ? 'all years' : `FY ${filterYear}` }}.
                    </p>

                    <div v-if="selectedExpense" class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selected</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ selectedExpense.ref_no }} - {{ selectedExpense.description }}</p>
                                <p class="text-xs text-slate-500">{{ selectedExpense.pay_to || 'No payee yet' }}</p>
                            </div>
                            <span :class="['rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider', expensePaymentBadgeClass(selectedExpense)]">
                                {{ expensePaymentLabel(selectedExpense) }}
                            </span>
                        </div>
                        <div class="mt-2.5 grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[10px] uppercase tracking-wide text-slate-400">Total</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ PESO }}{{ fmt(selectedExpense.amount) }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[10px] uppercase tracking-wide text-slate-400">Paid</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ PESO }}{{ fmt(selectedExpense.paid) }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[10px] uppercase tracking-wide text-slate-400">Remaining</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ PESO }}{{ fmt(Math.max(0, Number(selectedExpense.amount || 0) - Number(selectedExpense.paid || 0))) }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="filteredExpensesForModal.length" class="max-h-[18rem] overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                        <button
                            v-for="e in filteredExpensesForModal"
                            :key="e.id"
                            type="button"
                            class="w-full border-b border-slate-100 px-3 py-2.5 text-left transition last:border-b-0 hover:bg-slate-50"
                            :class="String(form.expense_id) === String(e.id) ? 'bg-indigo-50' : ''"
                            @click="form.expense_id = e.id; onExpenseSelect({ target: { value: e.id } })"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ e.ref_no }} - {{ e.description }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ e.pay_to || 'No payee yet' }}</p>
                                </div>
                                <span :class="['rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider', expensePaymentBadgeClass(e)]">
                                    {{ expensePaymentLabel(e) }}
                                </span>
                            </div>

                        </button>
                    </div>
                    <div v-else class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-xs text-slate-500">
                        No linked expenses match your search or filter.
                    </div>
                </div>

                <!-- Disbursement fields -->
                <div class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium mb-1.5">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm leading-6"
                            placeholder="Enter a clearer description for the disbursement"
                            required
                        />
                    </div>
                    <div><label class="block text-sm font-medium mb-1.5">Category / Source</label><input v-model="form.source" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                    <div><label class="block text-sm font-medium mb-1.5">Pay To (Payee)</label><input v-model="form.pay_to" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                    <div><label class="block text-sm font-medium mb-1.5">Disbursement Amount (₱)</label><input v-model.number="form.amount" type="number" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                    <div><label class="block text-sm font-medium mb-1.5">Payment Method</label><select v-model="form.method" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"><option value="check">Check</option><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option></select></div>
                    <div><label class="block text-sm font-medium mb-1.5">Date Encoded</label><input v-model="form.date_encoded" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Workflow Status</label>
                        <select v-model="form.status" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" :disabled="perms.isCashier">
                            <option value="draft">Draft</option>
                            <option value="for_release" v-if="!perms.isCashier">For Release</option>
                            <option value="for_approval">For Approval</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1.5">Notes / Purpose</label><input v-model="form.notes" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" placeholder="Supporting details or notes" /></div>
                    <div class="sm:col-span-2 flex items-center justify-end gap-3 pt-2 border-t mt-2">
                        <button type="button" @click="showModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy shadow-sm">
                            {{ form.processing ? 'Saving...' : (editing ? 'Update Record' : (perms.isCashier ? 'Submit for Approval' : 'Save Record')) }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </Modal>

    <!-- Head of Finance Action Modal (Approve, Post, Reject, Return) -->
    <Modal :show="showActionModal" :title="`Workflow Action: ${actionType.toUpperCase()}`" subtitle="Enter remarks or approval notes for this disbursement transaction." max-width="md" @close="showActionModal = false">
        <form @submit.prevent="executeAction">
            <div class="space-y-4">
                <div class="p-3 bg-slate-50 border rounded text-xs text-slate-700 space-y-1">
                    <div><span class="font-bold">DSB Reference:</span> {{ selectedDsb?.disbursement_no }}</div>
                    <div><span class="font-bold">Payee:</span> {{ selectedDsb?.pay_to }}</div>
                    <div><span class="font-bold">Amount:</span> {{ PESO }}{{ fmt(selectedDsb?.amount) }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Workflow Remarks</label>
                    <textarea v-model="actionForm.remarks" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Enter optional or mandatory remarks..." :required="actionType === 'reject' || actionType === 'return'"></textarea>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-4 border-t mt-4">
                <button type="button" @click="showActionModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="actionForm.processing" class="rounded-lg px-5 py-2 text-sm font-semibold text-white shadow-sm"
                    :class="actionType === 'approve' ? 'bg-emerald-600 hover:bg-emerald-700' : (actionType === 'post' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-rose-600 hover:bg-rose-700')">
                    Confirm {{ actionType.toUpperCase() }}
                </button>
            </div>
        </form>
    </Modal>

    <!-- Audit Trail Stamps Drawer / Modal -->
    <Modal :show="showAuditModal" title="Audit Trail & User Stamps" subtitle="Full transaction history and user authorization stamps" max-width="5xl" @close="showAuditModal = false">
        <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-1">
            <div class="p-3 bg-navy-dark text-white rounded-lg flex justify-between items-center text-xs">
                <div><span class="font-bold">DSB Reference:</span> {{ selectedDsb?.disbursement_no }}</div>
                <div><span class="font-bold">Current Status:</span> <span class="uppercase text-mustard font-bold">{{ statusLabels[selectedDsb?.status] || selectedDsb?.status }}</span></div>
            </div>

            <!-- User Stamps List -->
            <div class="border rounded-lg divide-y bg-white">
                <div v-if="selectedDsb?.prepared_by" class="p-3 text-xs">
                    <span class="font-bold text-slate-700">Prepared By:</span> {{ selectedDsb.prepared_by?.name }} – {{ selectedDsb.prepared_by?.role_label }}
                </div>
                <div v-if="selectedDsb?.released_by" class="p-3 text-xs">
                    <span class="font-bold text-slate-700">Released By:</span> {{ selectedDsb.released_by?.name }} – {{ selectedDsb.released_by?.role_label }}
                </div>
                <div v-if="selectedDsb?.submitted_by" class="p-3 text-xs">
                    <span class="font-bold text-slate-700">Submitted By:</span> {{ selectedDsb.submitted_by?.name }} – {{ selectedDsb.submitted_by?.role_label }}
                </div>
                <div v-if="selectedDsb?.approved_by" class="p-3 text-xs">
                    <span class="font-bold text-slate-700">Approved By:</span> {{ selectedDsb.approved_by?.name }} – {{ selectedDsb.approved_by?.role_label }}
                </div>
                <div v-if="selectedDsb?.posted_by" class="p-3 text-xs">
                    <span class="font-bold text-slate-700">Posted By:</span> {{ selectedDsb.posted_by?.name }} – {{ selectedDsb.posted_by?.role_label }}
                </div>
            </div>

            <!-- Log Entries -->
            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mt-4">Full Activity Log History</h4>
            <div class="space-y-2">
                <div v-for="log in (selectedDsb?.audit_trails || [])" :key="log.id" class="p-3 rounded-md bg-gray-50 border border-gray-200 text-xs">
                    <div class="flex justify-between items-center font-semibold text-gray-900 mb-1">
                        <span class="capitalize text-navy">{{ log.action }} by {{ log.user_name }} ({{ log.user_role }})</span>
                        <span class="text-gray-500 text-[11px]">{{ fmtDate(log.created_at) }}</span>
                    </div>
                    <p class="text-gray-600 italic" v-if="log.remarks">"{{ log.remarks }}"</p>
                </div>
                <div v-if="!selectedDsb?.audit_trails?.length" class="text-xs text-gray-500 italic p-3 text-center bg-gray-50 rounded">
                    No explicit audit log entries recorded yet.
                </div>
            </div>
        </div>
        <div class="flex justify-end pt-4 border-t mt-4">
            <button type="button" @click="showAuditModal = false" class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300">Close</button>
        </div>
    </Modal>
</AppLayout>
</template>
