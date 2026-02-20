<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ disbursements: Array })
const showModal = ref(false)
const editing = ref(null)
const form = useForm({ description:'', source:'Expenditure', pay_to:'', amount:0, method:'check', date_encoded:'', date_approved:'', status:'pending', notes:'' })

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits:2 }).format(v) }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' }) : '—' }

function openCreate() { form.reset(); form.source='Expenditure'; form.date_encoded=new Date().toISOString().slice(0,10); editing.value=null; showModal.value=true }
function openEdit(d) {
    Object.assign(form, { description:d.description, source:d.source, pay_to:d.pay_to, amount:d.amount, method:d.method, status:d.status, notes:d.notes||'', date_encoded:d.date_encoded?.slice(0,10)||'', date_approved:d.date_approved?.slice(0,10)||'' })
    editing.value=d.id; showModal.value=true
}
function save() {
    if(editing.value) form.put(`/disbursements/${editing.value}`, { onSuccess:()=>{showModal.value=false} })
    else form.post('/disbursements', { onSuccess:()=>{showModal.value=false} })
}
function remove(id) { if(confirm('Delete?')) router.delete(`/disbursements/${id}`) }
const statusColors = { pending:'bg-yellow-100 text-yellow-800', approved:'bg-blue-100 text-blue-800', posted:'bg-green-100 text-green-800', cancelled:'bg-red-100 text-red-800' }
const methodLabels = { check:'Check', cash:'Cash', bank_transfer:'Bank Transfer' }
</script>

<template>
<Head title="Disbursements" />
<AppLayout>
    <div class="flex items-center justify-between mb-6">
        <div><h2 class="text-xl font-bold text-gray-900">Disbursements</h2><p class="text-sm text-gray-500">Manage fund disbursements</p></div>
        <button @click="openCreate" class="flex items-center gap-2 rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition">+ Add Disbursement</button>
    </div>
    <div class="rounded-lg bg-white shadow-sm border overflow-hidden overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="bg-navy-dark text-white">
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">DSB No</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Description</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Pay To</th>
                <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-mustard">Amount</th>
                <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Method</th>
                <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Status</th>
                <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Date</th>
                <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Actions</th>
            </tr></thead>
            <tbody>
                <tr v-for="d in disbursements" :key="d.id" class="border-b hover:bg-gray-50/50">
                    <td class="px-5 py-3 font-mono text-xs">{{ d.disbursement_no }}</td>
                    <td class="px-5 py-3 font-medium text-gray-800">{{ d.description }}</td>
                    <td class="px-5 py-3 text-gray-600">{{ d.pay_to }}</td>
                    <td class="px-5 py-3 text-right font-medium">{{ fmt(d.amount) }}</td>
                    <td class="px-5 py-3 text-center text-xs">{{ methodLabels[d.method] }}</td>
                    <td class="px-5 py-3 text-center"><span :class="[statusColors[d.status],'rounded-full px-3 py-1 text-xs font-semibold uppercase']">{{ d.status }}</span></td>
                    <td class="px-5 py-3 text-center text-xs">{{ fmtDate(d.date_encoded) }}</td>
                    <td class="px-5 py-3 text-center">
                        <button @click="openEdit(d)" class="text-gray-500 hover:text-blue-600 mr-2">✏️</button>
                        <button @click="remove(d.id)" class="text-gray-400 hover:text-red-500">🗑️</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="px-5 py-2.5 bg-gray-50 text-xs text-gray-500 border-t">Total Records: {{ disbursements.length }}</div>
    </div>
    <Modal :show="showModal" :title="editing ? 'Edit Disbursement' : 'Add Disbursement'" :subtitle="editing ? 'Update disbursement.' : 'Record a new disbursement.'" max-width="lg" @close="showModal = false">
        <form @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1.5">Description</label><input v-model="form.description" class="w-full rounded-lg border px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium mb-1.5">Source</label><input v-model="form.source" class="w-full rounded-lg border px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium mb-1.5">Pay To</label><input v-model="form.pay_to" class="w-full rounded-lg border px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium mb-1.5">Amount</label><input v-model.number="form.amount" type="number" step="0.01" class="w-full rounded-lg border px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium mb-1.5">Method</label><select v-model="form.method" class="w-full rounded-lg border px-3 py-2.5 text-sm"><option value="check">Check</option><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option></select></div>
                <div><label class="block text-sm font-medium mb-1.5">Status</label><select v-model="form.status" class="w-full rounded-lg border px-3 py-2.5 text-sm"><option value="pending">Pending</option><option value="approved">Approved</option><option value="posted">Posted</option><option value="cancelled">Cancelled</option></select></div>
                <div><label class="block text-sm font-medium mb-1.5">Date Encoded</label><input v-model="form.date_encoded" type="date" class="w-full rounded-lg border px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium mb-1.5">Date Approved</label><input v-model="form.date_approved" type="date" class="w-full rounded-lg border px-3 py-2.5 text-sm" /></div>
                <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1.5">Notes</label><input v-model="form.notes" class="w-full rounded-lg border px-3 py-2.5 text-sm" /></div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5">
                <button type="button" @click="showModal=false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy">{{ form.processing ? 'Saving...' : (editing ? 'Update' : 'Create') }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
