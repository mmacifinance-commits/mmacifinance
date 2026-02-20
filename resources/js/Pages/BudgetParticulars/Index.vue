<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ particulars: Array, categories: Array, departments: Array })
const showModal = ref(false)
const editing = ref(null)
const form = useForm({ category_id: '', department_id: '', account_code: '', account_name: '', particular: '', description: '' })

function openCreate() { form.reset(); editing.value = null; showModal.value = true }
function openEdit(p) {
    Object.assign(form, { category_id: p.category_id, department_id: p.department_id, account_code: p.account_code, account_name: p.account_name, particular: p.particular, description: p.description || '' })
    editing.value = p.id; showModal.value = true
}
function save() {
    if (editing.value) form.put(`/budget-particulars/${editing.value}`, { onSuccess: () => { showModal.value = false } })
    else form.post('/budget-particulars', { onSuccess: () => { showModal.value = false } })
}
function remove(id) { if (confirm('Delete?')) router.delete(`/budget-particulars/${id}`) }
</script>

<template>
<Head title="Budget Particulars" />
<AppLayout>
    <div class="flex items-center justify-between mb-6">
        <div><h2 class="text-xl font-bold text-gray-900">Budget Particulars</h2><p class="text-sm text-gray-500">Manage budget line items and accounts</p></div>
        <button @click="openCreate" class="flex items-center gap-2 rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition">+ Add Particular</button>
    </div>
    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="bg-navy-dark text-white">
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Account Code</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Particular</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Category</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Department</th>
                <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Actions</th>
            </tr></thead>
            <tbody>
                <tr v-for="p in particulars" :key="p.id" class="border-b border-gray-100 hover:bg-gray-50/50">
                    <td class="px-5 py-3 font-mono text-sm text-gray-700">{{ p.account_code }}</td>
                    <td class="px-5 py-3 font-medium text-gray-800">{{ p.particular }}</td>
                    <td class="px-5 py-3 text-gray-500 text-xs">{{ p.category?.name }}</td>
                    <td class="px-5 py-3"><span class="rounded bg-navy/10 px-2.5 py-1 text-xs font-semibold text-navy">{{ p.department?.code }}</span></td>
                    <td class="px-5 py-3 text-center">
                        <button @click="openEdit(p)" class="text-gray-500 hover:text-blue-600 mr-2">✏️</button>
                        <button @click="remove(p.id)" class="text-gray-400 hover:text-red-500">🗑️</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="px-5 py-2.5 bg-gray-50 text-xs text-gray-500 border-t">Total Records: {{ particulars.length }}</div>
    </div>
    <Modal :show="showModal" :title="editing ? 'Edit Particular' : 'Add Particular'" :subtitle="editing ? 'Update particular details.' : 'Create a new budget particular.'" max-width="lg" @close="showModal = false">
        <form @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label><select v-model="form.category_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required><option value="">Select</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Department</label><select v-model="form.department_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required><option value="">Select</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Account Code</label><input v-model="form.account_code" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Account Name</label><input v-model="form.account_name" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Particular</label><input v-model="form.particular" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label><input v-model="form.description" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" /></div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5">
                <button type="button" @click="showModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy">{{ form.processing ? 'Saving...' : (editing ? 'Update' : 'Create') }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
