<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ expenses: Array, categories: Array, particulars: Array })
const showModal = ref(false)
const editing = ref(null)
const form = useForm({ description: '', category_id: '', particular_id: '', amount: 0, paid: 0, date_encoded: '', date_approved: '', status: 'pending', notes: '' })

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(v) }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' }) : '—' }

function openCreate() { form.reset(); form.date_encoded = new Date().toISOString().slice(0,10); editing.value = null; showModal.value = true }
function openEdit(e) {
    Object.assign(form, { description: e.description, category_id: e.category_id, particular_id: e.particular_id, amount: e.amount, paid: e.paid, status: e.status, notes: e.notes||'', date_encoded: e.date_encoded?.slice(0,10)||'', date_approved: e.date_approved?.slice(0,10)||'' })
    editing.value = e.id; showModal.value = true
}
function save() {
    if (editing.value) form.put(`/expenses/${editing.value}`, { onSuccess: () => { showModal.value = false } })
    else form.post('/expenses', { onSuccess: () => { showModal.value = false } })
}
function remove(id) { if (confirm('Delete?')) router.delete(`/expenses/${id}`) }
const statusColors = { pending:'bg-yellow-100 text-yellow-800', approved:'bg-green-100 text-green-800', cancelled:'bg-red-100 text-red-800' }
</script>

<template>
<Head title="Expenditures" />
<AppLayout>
    <div class="flex items-center justify-between mb-6">
        <div><h2 class="text-xl font-bold text-gray-900">Expenditures</h2><p class="text-sm text-gray-500">Track and manage expenses</p></div>
        <button @click="openCreate" class="flex items-center gap-2 rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition">+ Add Expense</button>
    </div>
    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="bg-navy-dark text-white">
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Ref No</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Description</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Category</th>
                <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-mustard">Amount</th>
                <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-mustard">Paid</th>
                <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Status</th>
                <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Date</th>
                <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Actions</th>
            </tr></thead>
            <tbody>
                <tr v-for="e in expenses" :key="e.id" class="border-b border-gray-100 hover:bg-gray-50/50">
                    <td class="px-5 py-3 font-mono text-xs text-gray-600">{{ e.ref_no }}</td>
                    <td class="px-5 py-3 font-medium text-gray-800">{{ e.description }}</td>
                    <td class="px-5 py-3 text-gray-500 text-xs">{{ e.category?.name }}</td>
                    <td class="px-5 py-3 text-right font-medium">{{ fmt(e.amount) }}</td>
                    <td class="px-5 py-3 text-right font-medium">{{ fmt(e.paid) }}</td>
                    <td class="px-5 py-3 text-center"><span :class="[statusColors[e.status],'rounded-full px-3 py-1 text-xs font-semibold uppercase']">{{ e.status }}</span></td>
                    <td class="px-5 py-3 text-center text-xs text-gray-500">{{ fmtDate(e.date_encoded) }}</td>
                    <td class="px-5 py-3 text-center">
                        <button @click="openEdit(e)" class="text-gray-500 hover:text-blue-600 mr-2">✏️</button>
                        <button @click="remove(e.id)" class="text-gray-400 hover:text-red-500">🗑️</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="px-5 py-2.5 bg-gray-50 text-xs text-gray-500 border-t">Total Records: {{ expenses.length }}</div>
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
