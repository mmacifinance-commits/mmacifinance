<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { useOfflineQueue } from '@/composables/useOfflineQueue'

const perms = computed(() => usePage().props.permissions || {})
const { isOnline, offlinePost, offlinePut, offlineDelete } = useOfflineQueue()

const props = defineProps({ expenses: Array, categories: Array, particulars: Array })
const showModal = ref(false)
const editing = ref(null)
const form = useForm({ description: '', category_id: '', particular_id: '', amount: 0, paid: 0, date_encoded: '', date_approved: '', status: 'pending', notes: '' })

// Local optimistic list for offline-queued items
const offlineRows = ref([])

const filterSearch = ref('')
const filterCategory = ref('')
const filterStatus = ref('')

const filteredExpenses = computed(() => {
    const all = [...props.expenses, ...offlineRows.value]
    return all.filter(e => {
        const matchSearch = filterSearch.value ?
            ((e.ref_no || '').toLowerCase().includes(filterSearch.value.toLowerCase()) ||
             (e.description || '').toLowerCase().includes(filterSearch.value.toLowerCase())) : true
        const matchCategory = filterCategory.value ? String(e.category_id) === String(filterCategory.value) : true
        const matchStatus = filterStatus.value ? e.status === filterStatus.value : true
        return matchSearch && matchCategory && matchStatus
    })
})

function clearFilters() {
    filterSearch.value = ''
    filterCategory.value = ''
    filterStatus.value = ''
}

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(v || 0) }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' }) : '—' }

function openCreate() { form.reset(); form.date_encoded = new Date().toISOString().slice(0,10); editing.value = null; showModal.value = true }
function openEdit(e) {
    Object.assign(form, { description: e.description, category_id: e.category_id, particular_id: e.particular_id, amount: e.amount, paid: e.paid, status: e.status, notes: e.notes||'', date_encoded: e.date_encoded?.slice(0,10)||'', date_approved: e.date_approved?.slice(0,10)||'' })
    editing.value = e.id; showModal.value = true
}

async function save() {
    const data = {
        description: form.description,
        category_id: form.category_id,
        particular_id: form.particular_id,
        amount: form.amount,
        paid: form.paid,
        status: form.status,
        notes: form.notes,
        date_encoded: form.date_encoded,
        date_approved: form.date_approved,
    }

    if (editing.value) {
        // ── Update ──
        const { queued } = await offlinePut(
            `/expenses/${editing.value}`,
            data,
            `Edit Expense: '${form.description}'`
        )
        if (queued) {
            // Optimistically update the offline row if it exists, else mark it
            const idx = offlineRows.value.findIndex(r => r.id === editing.value)
            if (idx !== -1) offlineRows.value[idx] = { ...offlineRows.value[idx], ...data }
            showModal.value = false
        } else {
            form.put(`/expenses/${editing.value}`, { onSuccess: () => { showModal.value = false } })
        }
    } else {
        // ── Create ──
        const { queued, item } = await offlinePost(
            '/expenses',
            data,
            `Add Expense: '${form.description}'`
        )
        if (queued) {
            // Build an optimistic local row so the user sees it immediately
            offlineRows.value.unshift({
                id: `offline-${item.id}`,
                ref_no: '(pending)',
                description: form.description,
                category_id: form.category_id,
                category: props.categories?.find(c => String(c.id) === String(form.category_id)),
                particular_id: form.particular_id,
                particular: props.particulars?.find(p => String(p.id) === String(form.particular_id)),
                amount: form.amount,
                paid: form.paid,
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
    if (!confirm('Delete?')) return
    // If it's a local offline row, just remove from offlineRows
    if (String(id).startsWith('offline-')) {
        offlineRows.value = offlineRows.value.filter(r => r.id !== id)
        return
    }
    const { queued } = await offlineDelete(`/expenses/${id}`, `Delete Expense #${id}`)
    if (queued) {
        // Optimistically hide the row
        offlineRows.value = offlineRows.value.filter(r => r.id !== id)
    } else {
        router.delete(`/expenses/${id}`)
    }
}

const statusColors = { pending:'bg-yellow-100 text-yellow-800 border border-yellow-200', approved:'bg-green-100 text-green-800 border border-green-200', cancelled:'bg-red-100 text-red-800 border border-red-200' }
const statusIcons = {
    pending: '<svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
    approved: '<svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>',
    cancelled: '<svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>'
}

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
        <div><h2 class="text-xl font-bold text-gray-900">Expenditures</h2><p class="text-sm text-gray-500">Track and manage expenses</p></div>
        <button v-if="perms.canManageExpenses" @click="openCreate" class="flex items-center gap-2 rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition">+ Add Expense</button>
    </div>
    <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <input v-model="filterSearch" type="text" placeholder="Search by ref no or description..." class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm w-full max-w-sm" />
        <select v-model="filterCategory" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 min-w-[200px]">
            <option value="">All Categories</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select v-model="filterStatus" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 min-w-[200px]">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    <div class="rounded-sm bg-white shadow-sm border border-gray-200 overflow-hidden overflow-x-auto flex flex-col">
        <div class="overflow-x-auto w-full pb-4">
            <table class="w-full text-sm min-w-max whitespace-nowrap">
                <thead><tr class="bg-navy-dark text-white border-b-2 border-mustard">
                    <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Ref. No.</th>
                    <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Description</th>
                    <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Category</th>
                    <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Responsibility<br/>Center</th>
                    <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Particular</th>
                    <th class="px-5 py-4 text-center font-bold text-xs tracking-wide">Amount</th>
                    <th class="px-5 py-4 text-center font-bold text-xs tracking-wide">Paid</th>
                    <th class="px-5 py-4 text-center font-bold text-xs tracking-wide">Balance</th>
                    <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Date<br/>Encoded</th>
                    <th class="px-5 py-4 text-left font-bold text-xs tracking-wide">Date<br/>Approved</th>
                    <th class="px-5 py-4 text-center font-bold text-xs tracking-wide">Status</th>
                    <th class="px-5 py-4 text-center font-bold text-xs tracking-wide">Actions</th>
                </tr></thead>
                <tbody>
                    <tr
                        v-for="e in filteredExpenses"
                        :key="e.id"
                        :class="[
                            'border-b border-gray-100 hover:bg-gray-50/50',
                            e._offline ? 'bg-amber-50/60' : ''
                        ]"
                    >
                        <td class="px-5 py-5 font-mono text-xs text-gray-600 align-middle">
                            {{ e.ref_no }}
                            <span v-if="e._offline" class="ml-1 inline-block bg-amber-100 text-amber-700 border border-amber-200 rounded px-1 py-0.5 text-[9px] font-bold uppercase leading-none">⏳ Queued</span>
                        </td>
                        <td class="px-5 py-5 text-gray-800 text-sm align-middle">{{ e.description }}</td>
                        <td class="px-5 py-5 text-gray-500 text-xs uppercase align-middle">{{ e.category?.name }}</td>
                        <td class="px-5 py-5 text-gray-500 text-xs align-middle w-40 max-w-[160px] whitespace-normal">
                            {{ e.particular?.department?.name || e.particular?.department?.code || '—' }}
                        </td>
                        <td class="px-5 py-5 text-gray-500 text-xs align-middle w-40 max-w-[160px] whitespace-normal">
                            {{ e.particular?.particular || '—' }}
                        </td>
                        <td class="px-5 py-5 text-center font-mono text-sm text-gray-700 align-middle">{{ fmt(e.amount) }}</td>
                        <td class="px-5 py-5 text-center font-mono text-sm text-gray-700 align-middle">{{ fmt(e.paid) }}</td>
                        <td class="px-5 py-5 text-center font-mono text-sm font-bold text-gray-800 align-middle">{{ fmt(e.amount - e.paid) }}</td>
                        <td class="px-5 py-5 text-gray-500 text-xs align-middle">
                            <div class="leading-tight">{{ splitDate(e.date_encoded).top }}<br/>{{ splitDate(e.date_encoded).bottom }}</div>
                        </td>
                        <td class="px-5 py-5 text-gray-500 text-xs align-middle">
                            <div v-if="e.date_approved" class="leading-tight">{{ splitDate(e.date_approved).top }}<br/>{{ splitDate(e.date_approved).bottom }}</div>
                            <div v-else>—</div>
                        </td>
                        <td class="px-5 py-5 text-center align-middle">
                            <!-- Offline queued badge overrides status -->
                            <span v-if="e._offline" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">
                                ⏳ Queued
                            </span>
                            <span v-else :class="[statusColors[e.status], 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize']">
                                <span v-html="statusIcons[e.status]"></span>
                                {{ e.status }}
                            </span>
                        </td>
                        <td v-if="perms.canManageExpenses" class="px-5 py-5 text-center align-middle">
                            <button @click="openEdit(e)" class="text-gray-500 hover:text-blue-600 mr-2" :disabled="e._offline" :class="{ 'opacity-40 cursor-not-allowed': e._offline }">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                            </button>
                            <button @click="remove(e.id)" class="text-red-400 hover:text-red-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="filteredExpenses.length === 0">
                        <td colspan="12" class="px-5 py-8 text-center text-sm text-gray-500">No expenses found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 bg-gray-50 text-xs text-gray-500 border-t flex items-center justify-between">
            <span>Total Records: {{ filteredExpenses.length }}</span>
            <div class="flex gap-2">
                <button type="button" @click="() => {}" class="px-3 py-1.5 rounded bg-gray-100 border border-gray-200 hover:bg-gray-200 text-gray-600 font-medium">Apply Filters</button>
                <button type="button" @click="clearFilters" class="px-3 py-1.5 rounded bg-white border border-gray-200 hover:bg-gray-100 text-gray-600 font-medium">Clear Filters</button>
            </div>
        </div>
    </div>
    <Modal :show="showModal" :title="editing ? 'Edit Expense' : 'Add Expense'" :subtitle="editing ? 'Update expense details.' : 'Record a new expenditure.'" max-width="lg" @close="showModal = false">
        <form @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1.5">Description</label><input v-model="form.description" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium mb-1.5">Category</label><select v-model="form.category_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required><option value="">Select</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                <div><label class="block text-sm font-medium mb-1.5">Particular</label><select v-model="form.particular_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required><option value="">Select</option><option v-for="p in particulars" :key="p.id" :value="p.id">{{ p.particular }}</option></select></div>
                <div><label class="block text-sm font-medium mb-1.5">Amount</label><input v-model.number="form.amount" type="number" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium mb-1.5">Paid</label><input v-model.number="form.paid" type="number" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" /></div>
                <div><label class="block text-sm font-medium mb-1.5">Status</label><select v-model="form.status" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"><option value="pending">Pending</option><option value="approved">Approved</option><option value="cancelled">Cancelled</option></select></div>
                <div><label class="block text-sm font-medium mb-1.5">Date Encoded</label><input v-model="form.date_encoded" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium mb-1.5">Date Approved</label><input v-model="form.date_approved" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" /></div>
                <div><label class="block text-sm font-medium mb-1.5">Notes</label><input v-model="form.notes" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" /></div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5">
                <button type="button" @click="showModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy">{{ form.processing ? 'Saving...' : (editing ? 'Update' : 'Create') }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
