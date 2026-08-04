<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { useOfflineQueue } from '@/composables/useOfflineQueue'

const perms = computed(() => usePage().props.permissions || {})
const { isOnline, offlinePost, offlinePut, offlineDelete } = useOfflineQueue()

const props = defineProps({
    expenses: Array,
    categories: Array,
    particulars: Array,
    accountTitles: Array,
    budgetYears: Array,
    availableYears: Array,
    defaultYear: [Number, String]
})

const showModal = ref(false)
const showAuditModal = ref(false)
const editing = ref(null)
const selectedExpense = ref(null)
const form = useForm({ description: '', category_id: '', particular_id: '', amount: 0, date_encoded: '', date_approved: '', status: 'pending', notes: '' })

// Local optimistic list for offline-queued items
const offlineRows = ref([])

const filterSearch = ref('')
const filterCategory = ref('')
const filterStatus = ref('')
const filterYear = ref(props.defaultYear ? String(props.defaultYear) : 'all')

const filteredExpenses = computed(() => {
    const all = [...props.expenses, ...offlineRows.value]
    return all.filter(e => {
        const matchSearch = filterSearch.value ?
            ((e.ref_no || '').toLowerCase().includes(filterSearch.value.toLowerCase()) ||
             (e.description || '').toLowerCase().includes(filterSearch.value.toLowerCase())) : true
        const matchCategory = filterCategory.value ? String(e.category_id) === String(filterCategory.value) : true
        const matchStatus = filterStatus.value ? e.status === filterStatus.value : true

        let matchYear = true
        if (filterYear.value && filterYear.value !== 'all') {
            const expYear = e.date_encoded ? String(e.date_encoded).slice(0, 4) : (e.created_at ? String(e.created_at).slice(0, 4) : null)
            matchYear = expYear ? String(expYear) === String(filterYear.value) : false
        }

        return matchSearch && matchCategory && matchStatus && matchYear
    })
})

function clearFilters() {
    filterSearch.value = ''
    filterCategory.value = ''
    filterStatus.value = ''
    filterYear.value = props.defaultYear ? String(props.defaultYear) : 'all'
}

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(v || 0) }
function fmtDate(d) { return d ? new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—' }

function disbursementRefText(expense) {
    const refs = (expense?.disbursements || [])
        .map((d) => d.disbursement_no)
        .filter(Boolean)
    if (!refs.length) return 'No DSB ref yet'
    return refs.length === 1 ? refs[0] : refs.join(', ')
}

function expenseAuditLogs(expense) {
    selectedExpense.value = expense
    showAuditModal.value = true
}

function openCreate() {
    const year = new Date().getFullYear()
    const budgetYears = (props.budgetYears || []).map(y => Number(y))
    if (budgetYears.length && !budgetYears.includes(year)) {
        alert(`No annual budget exists for FY ${year}. Please create the annual budget first.`)
        return
    }

    form.reset()
    form.date_encoded = new Date().toISOString().slice(0,10)
    editing.value = null
    showModal.value = true
}
function openEdit(e) {
    Object.assign(form, { description: e.description, category_id: e.category_id, particular_id: e.particular_id, amount: e.amount, status: e.status, notes: e.notes||'', date_encoded: e.date_encoded?.slice(0,10)||'', date_approved: e.date_approved?.slice(0,10)||'' })
    editing.value = e.id; showModal.value = true
}

async function save() {
    const data = {
        description: form.description,
        category_id: form.category_id,
        particular_id: form.particular_id,
        amount: form.amount,
        status: form.status,
        notes: form.notes,
        date_encoded: form.date_encoded,
        date_approved: perms.value.isSuperAdmin ? form.date_approved : '',
    }

    if (editing.value) {
        const { queued } = await offlinePut(
            `/expenses/${editing.value}`,
            data,
            `Edit Expense: '${form.description}'`
        )
        if (queued) {
            const idx = offlineRows.value.findIndex(r => r.id === editing.value)
            if (idx !== -1) offlineRows.value[idx] = { ...offlineRows.value[idx], ...data }
            showModal.value = false
        } else {
            form.put(`/expenses/${editing.value}`, { onSuccess: () => { showModal.value = false } })
        }
    } else {
        const { queued, item } = await offlinePost(
            '/expenses',
            data,
            `Add Expense: '${form.description}'`
        )
        if (queued) {
            offlineRows.value.unshift({
                id: `offline-${item.id}`,
                ref_no: '(pending)',
                description: form.description,
                category_id: form.category_id,
                category: props.categories?.find(c => String(c.id) === String(form.category_id)),
                particular_id: form.particular_id,
                particular: (props.accountTitles || props.particulars)?.find(p => String(p.id) === String(form.particular_id)),
                amount: form.amount,
                status: form.status,
                date_encoded: form.date_encoded,
                date_approved: form.date_approved,
                notes: form.notes,
                _offline: true,
            })
            showModal.value = false
        } else {
            form.post('/expenses', { onSuccess: () => { showModal.value = false } })
        }
    }
}

async function remove(id) {
    if (!confirm('Are you sure you want to delete this expense record?')) return
    if (String(id).startsWith('offline-')) {
        offlineRows.value = offlineRows.value.filter(r => r.id !== id)
        return
    }
    const { queued } = await offlineDelete(`/expenses/${id}`, `Delete Expense #${id}`)
    if (queued) {
        offlineRows.value = offlineRows.value.filter(r => r.id !== id)
    } else {
        router.delete(`/expenses/${id}`)
    }
}

function submitForApproval(expense) {
    if (!confirm('Submit this expenditure for Head of Finance approval?')) return
    router.post(`/expenses/${expense.id}/submit`, {
        remarks: 'Submitted for approval from Expenditures page.',
    })
}

function approveExpense(expense) {
    if (!confirm('Approve this expenditure as Head of Finance?')) return
    router.post(`/expenses/${expense.id}/approve`, {
        remarks: 'Approved by Head of Finance.',
    })
}

function returnExpense(expense) {
    if (!confirm('Return this expenditure for revision?')) return
    router.post(`/expenses/${expense.id}/return`, {
        remarks: 'Returned for revision by Head of Finance.',
    })
}

function rejectExpense(expense) {
    if (!confirm('Reject this expenditure?')) return
    router.post(`/expenses/${expense.id}/reject`, {
        remarks: 'Rejected by Head of Finance.',
    })
}

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800 border border-yellow-200',
    for_approval: 'bg-amber-100 text-amber-800 border border-amber-200',
    approved: 'bg-blue-100 text-blue-800 border border-blue-200',
    returned_for_revision: 'bg-purple-100 text-purple-800 border border-purple-200',
    rejected: 'bg-rose-100 text-rose-800 border border-rose-200',
    posted: 'bg-green-100 text-green-800 border border-green-200',
    cancelled: 'bg-red-100 text-red-800 border border-red-200',
}

const statusOptions = computed(() => {
    const base = [
        { value: 'pending', label: 'Pending' },
        { value: 'cancelled', label: 'Cancelled' },
    ]

    return base
})

function splitDate(d) {
    if (!d) return { top: '—', bottom: '' }
    const dateOnly = d.split('T')[0]
    const parts = dateOnly.split('-')
    if (parts.length === 3) return { top: parts[0] + '-', bottom: parts[1] + '-' + parts[2] }
    return { top: dateOnly, bottom: '' }
}
</script>

<template>
<Head title="Expenditures" />
<AppLayout>
    <div class="flex items-center justify-between mb-6">
        <div><h2 class="text-xl font-bold text-gray-900">Expenditures</h2><p class="text-sm text-gray-500">Track and manage official expenditures</p></div>
        <button v-if="perms.canManageExpenses" @click="openCreate" class="rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">Add Expense</button>
    </div>
    <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <input v-model="filterSearch" type="text" placeholder="Search by ref no or description..." class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm w-full max-w-sm shadow-sm" />
        <select v-model="filterYear" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 min-w-[150px] shadow-sm">
            <option value="all">All Years</option>
            <option v-for="y in (availableYears || [])" :key="y" :value="String(y)">Year {{ y }}</option>
        </select>
        <select v-model="filterCategory" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 min-w-[180px] shadow-sm">
            <option value="">All Categories</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select v-model="filterStatus" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 min-w-[160px] shadow-sm">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="returned_for_revision">Returned for Revision</option>
            <option value="rejected">Rejected</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden flex flex-col">
        <div class="overflow-x-auto w-full pb-4">
            <table class="w-full text-sm min-w-max whitespace-nowrap">
                <thead>
                    <tr class="bg-navy-dark text-white border-b-2 border-mustard">
                        <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Ref. No.</th>
                        <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Description</th>
                        <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Category</th>
                        <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Responsibility Center</th>
                        <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Account Title</th>
                        <th class="px-5 py-4 text-right font-bold text-xs tracking-wide">Amount</th>
                        <th class="px-5 py-4 text-right font-bold text-xs tracking-wide">Paid via Disbursements</th>
                        <th class="px-5 py-4 text-right font-bold text-xs tracking-wide">Balance</th>
                        <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Date Encoded</th>
                        <th class="px-5 py-4 text-center font-bold text-xs tracking-wide">Status</th>
                        <th class="px-5 py-4 text-center font-bold text-xs tracking-wide">Trail</th>
                        <th v-if="perms.canManageExpenses" class="px-5 py-4 text-center font-bold text-xs tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="e in filteredExpenses"
                        :key="e.id"
                        :class="[
                            'border-b border-gray-100 hover:bg-gray-50/50 transition-colors',
                            e._offline ? 'bg-amber-50/60' : ''
                        ]"
                    >
                        <td class="px-5 py-4 font-mono text-xs font-semibold text-navy align-middle">
                            {{ e.ref_no }}
                            <span v-if="e._offline" class="ml-1 inline-block bg-amber-100 text-amber-700 border border-amber-200 rounded px-1 py-0.5 text-[9px] font-bold uppercase leading-none">⏳ Queued</span>
                        </td>
                        <td class="px-5 py-4 text-gray-800 font-medium text-sm align-middle">{{ e.description }}</td>
                        <td class="px-5 py-4 text-gray-500 text-xs uppercase align-middle">{{ e.category?.name }}</td>
                        <td class="px-5 py-4 text-gray-600 text-xs align-middle">
                            {{ e.particular?.department?.name || e.particular?.department?.code || '—' }}
                        </td>
                        <td class="px-5 py-4 text-gray-800 font-medium text-xs align-middle">
                            {{ e.particular?.particular || '—' }}
                        </td>
                        <td class="px-5 py-4 text-right font-mono text-sm text-gray-700 font-medium align-middle">₱{{ fmt(e.amount) }}</td>
                        <td class="px-5 py-4 text-right font-mono text-sm text-emerald-700 font-bold align-middle">
                            ₱{{ fmt(e.paid) }}
                            <div class="text-[10px] font-normal text-emerald-600">DSB Ref: {{ disbursementRefText(e) }}</div>
                        </td>
                        <td class="px-5 py-4 text-right font-mono text-sm font-bold text-gray-900 align-middle">₱{{ fmt(e.amount - e.paid) }}</td>
                        <td class="px-5 py-4 text-gray-500 text-xs align-middle">
                            {{ splitDate(e.date_encoded).top }}{{ splitDate(e.date_encoded).bottom }}
                        </td>
                        <td class="px-5 py-4 text-center align-middle">
                            <span v-if="e._offline" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">
                                ⏳ Queued
                            </span>
                            <span v-else :class="[statusColors[e.status] || 'bg-gray-100 text-gray-800', 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize']">
                                {{ e.status }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-center align-middle">
                            <button @click="expenseAuditLogs(e)" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded text-xs font-semibold border border-indigo-200 transition">
                                View Stamps
                            </button>
                        </td>
                        <td v-if="perms.canManageExpenses || perms.canSubmitExpenses" class="px-5 py-4 text-center align-middle">
                            <div class="inline-flex items-center gap-2">
                                <button v-if="perms.canSubmitExpenses && ['pending', 'returned_for_revision'].includes(e.status) && !e._offline"
                                    @click="submitForApproval(e)"
                                    class="px-3 py-1.5 bg-amber-50 text-amber-800 hover:bg-amber-100 rounded-md text-xs font-semibold shadow-sm transition-all duration-150 border border-amber-200">
                                    {{ e.status === 'returned_for_revision' ? 'Resubmit for Approval' : 'Submit for Approval' }}
                                </button>
                                <button v-if="e.status === 'for_approval' && perms.isSuperAdmin"
                                    @click="approveExpense(e)"
                                    class="px-3 py-1.5 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 rounded-md text-xs font-semibold shadow-sm transition-all duration-150 border border-emerald-200">
                                    Approve
                                </button>
                                <button v-if="e.status === 'for_approval' && perms.isSuperAdmin"
                                    @click="returnExpense(e)"
                                    class="px-3 py-1.5 bg-purple-50 text-purple-700 hover:bg-purple-100 rounded-md text-xs font-semibold shadow-sm transition-all duration-150 border border-purple-200">
                                    Return
                                </button>
                                <button v-if="e.status === 'for_approval' && perms.isSuperAdmin"
                                    @click="rejectExpense(e)"
                                    class="px-3 py-1.5 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-md text-xs font-semibold shadow-sm transition-all duration-150 border border-rose-200">
                                    Reject
                                </button>
                                <button v-if="perms.canManageExpenses" @click="openEdit(e)" class="px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-md text-xs font-semibold shadow-sm transition-all duration-150 border border-indigo-200" :disabled="e._offline">
                                    Edit
                                </button>
                                <button v-if="perms.canManageExpenses" @click="remove(e.id)" class="px-3 py-1.5 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-md text-xs font-semibold shadow-sm transition-all duration-150 border border-rose-200">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="filteredExpenses.length === 0">
                        <td colspan="12" class="px-5 py-8 text-center text-sm text-gray-500">No expenditures found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 bg-gray-50 text-xs text-gray-500 border-t flex items-center justify-between">
            <span>Total Records: {{ filteredExpenses.length }}</span>
            <button type="button" @click="clearFilters" class="px-3 py-1.5 rounded-md bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 font-semibold shadow-sm">Clear Filters</button>
        </div>
    </div>

    <Modal :show="showModal" :title="editing ? 'Edit Expense' : 'Add Expense'" :subtitle="editing ? 'Update expenditure details.' : 'Record a new expenditure line item.'" max-width="lg" @close="showModal = false">
        <form @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1.5">Description</label><input v-model="form.description" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium mb-1.5">Category</label><select v-model="form.category_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required><option value="">Select Category</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                <div><label class="block text-sm font-medium mb-1.5">Account Title</label><select v-model="form.particular_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required><option value="">Select Account Title</option><option v-for="p in (accountTitles || particulars)" :key="p.id" :value="p.id">{{ p.particular }}</option></select></div>
                <div><label class="block text-sm font-medium mb-1.5">Amount (₱)</label><input v-model.number="form.amount" type="number" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div class="rounded-lg border border-dashed border-emerald-200 bg-emerald-50/60 px-3 py-2 text-xs text-emerald-800">
                    Paid amounts are controlled by linked disbursements only.
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5">Status</label>
                    <div v-if="editing && ['approved', 'posted', 'rejected', 'returned_for_revision'].includes(form.status)" class="w-full rounded-lg border border-green-200 bg-green-50 px-3 py-2.5 text-sm font-semibold text-green-700">
                        {{ form.status === 'for_approval' ? 'For Approval (workflow-controlled)' : (form.status === 'posted' ? 'Posted (controlled by linked disbursements)' : (form.status === 'approved' ? 'Approved (controlled by Head of Finance)' : (form.status === 'rejected' ? 'Rejected (controlled by Head of Finance)' : 'Returned for Revision (controlled by Head of Finance)'))) }}
                    </div>
                    <select v-else v-model="form.status" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <p v-if="editing && ['for_approval', 'approved', 'posted', 'rejected', 'returned_for_revision'].includes(form.status)" class="mt-1 text-xs text-gray-500">
                        {{ form.status === 'for_approval' ? 'For Approval is managed through the submit action only.' : (form.status === 'posted' ? 'Posted status is managed from the disbursement workflow only.' : (form.status === 'approved' ? 'Approved status is managed through the approval action only.' : (form.status === 'rejected' ? 'Rejected status is managed through the approval action only.' : 'Returned for Revision is managed through the approval action only.'))) }}
                    </p>
                </div>
                <div><label class="block text-sm font-medium mb-1.5">Date Encoded</label><input v-model="form.date_encoded" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div v-if="perms.isSuperAdmin">
                    <label class="block text-sm font-medium mb-1.5">Date Approved</label>
                    <input v-model="form.date_approved" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                </div>
                <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1.5">Notes</label><input v-model="form.notes" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" placeholder="Optional expense notes" /></div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5 border-t mt-4">
                <button type="button" @click="showModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy shadow-sm">{{ form.processing ? 'Saving...' : (editing ? 'Update' : 'Create Expense') }}</button>
            </div>
        </form>
    </Modal>

    <Modal :show="showAuditModal" title="Audit Trail & User Stamps" subtitle="Expense approval history and user actions" max-width="4xl" @close="showAuditModal = false">
        <div class="space-y-4">
            <div v-if="selectedExpense" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selected Expense</div>
                        <div class="mt-1 text-lg font-bold text-slate-900">{{ selectedExpense.ref_no }} - {{ selectedExpense.description }}</div>
                        <div class="text-sm text-slate-600">{{ selectedExpense.particular?.department?.name || selectedExpense.particular?.department?.code || '—' }} • {{ selectedExpense.particular?.particular || '—' }}</div>
                    </div>
                    <span :class="[statusColors[selectedExpense.status] || 'bg-gray-100 text-gray-800', 'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold capitalize']">
                        {{ selectedExpense.status }}
                    </span>
                </div>
            </div>

            <div class="space-y-3">
                <div v-for="log in (selectedExpense?.audit_trails || [])" :key="log.id" class="rounded-lg border border-gray-200 bg-white p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="font-semibold text-gray-900">{{ log.action }}</div>
                        <div class="text-xs text-gray-500">{{ fmtDate(log.created_at) }}</div>
                    </div>
                    <div class="mt-1 text-xs text-gray-600">
                        <span class="font-semibold text-gray-800">{{ log.user_name }}</span>
                        <span class="mx-1">•</span>
                        <span>{{ log.user_role }}</span>
                    </div>
                    <div v-if="log.remarks" class="mt-1 text-xs text-gray-600">{{ log.remarks }}</div>
                </div>
                <div v-if="!selectedExpense?.audit_trails?.length" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500">
                    No audit trail entries recorded yet.
                </div>
            </div>
        </div>
    </Modal>
</AppLayout>
</template>
