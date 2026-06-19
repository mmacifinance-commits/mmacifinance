<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const perms = computed(() => usePage().props.permissions || {})

const props = defineProps({ particulars: Array, categories: Array, departments: Array })
const showModal = ref(false)
const editing = ref(null)
const form = useForm({ category_id: '', department_id: '', account_code: '', account_name: '', particular: '', description: '' })

const filterCategory = ref('')
const filterDepartment = ref('')
const searchQuery = ref('')

const filteredParticulars = computed(() => {
    return props.particulars.filter(p => {
        const matchCategory = filterCategory.value ? p.category_id === filterCategory.value : true
        const matchDepartment = filterDepartment.value ? p.department_id === filterDepartment.value : true
        const matchSearch = searchQuery.value ?
            (p.particular.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
             p.account_code.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
             p.account_name.toLowerCase().includes(searchQuery.value.toLowerCase())) : true
        return matchCategory && matchDepartment && matchSearch
    })
})

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
        <button v-if="perms.canManageBudget" @click="openCreate" class="flex items-center gap-2 rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition">+ Add Particular</button>
    </div>
    <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <select v-model="filterCategory" class="rounded border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 min-w-[200px]">
            <option value="">All Categories</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select v-model="filterDepartment" class="rounded border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 min-w-[200px]">
            <option value="">All Departments</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <input v-model="searchQuery" type="text" placeholder="Search particulars..." class="rounded border border-gray-200 bg-white px-4 py-2 text-sm w-full max-w-sm" />
    </div>

    <div class="rounded-sm bg-white shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto w-full pb-4">
            <table class="w-full text-sm min-w-max whitespace-nowrap">
                <thead><tr class="bg-navy-dark text-white border-b-2 border-mustard">
                    <th class="px-6 py-4 text-left font-bold text-sm tracking-wide">Category</th>
                    <th class="px-6 py-4 text-left font-bold text-sm tracking-wide">Responsibility Center</th>
                    <th class="px-6 py-4 text-left font-bold text-sm tracking-wide">Account</th>
                    <th class="px-6 py-4 text-left font-bold text-sm tracking-wide">Particular</th>
                    <th class="px-6 py-4 text-left font-bold text-sm tracking-wide">Description</th>
                    <th v-if="perms.canManageBudget" class="px-6 py-4 text-center font-bold text-sm tracking-wide">Actions</th>
                </tr></thead>
                <tbody>
                    <tr v-for="p in filteredParticulars" :key="p.id" class="border-b border-gray-100 hover:bg-gray-50/50">
                        <td class="px-6 py-4 text-gray-800 text-sm align-top pt-5">{{ p.category?.name }}</td>
                        <td class="px-6 py-4 text-gray-800 text-sm align-top pt-5">{{ p.department?.name || p.department?.code }}</td>
                        <td class="px-6 py-4 align-top pt-5">
                            <div class="font-mono text-sm text-gray-700">{{ p.account_code }}</div>
                            <div class="text-[11px] text-gray-400 mt-0.5 leading-tight max-w-[200px] whitespace-normal">{{ p.account_name }}</div>
                        </td>
                        <td class="px-6 py-4 text-gray-800 text-sm align-top pt-5">{{ p.particular }}</td>
                        <td class="px-6 py-4 text-gray-500 text-sm align-top pt-5 whitespace-normal max-w-xs">{{ p.description }}</td>
                        <td v-if="perms.canManageBudget" class="px-6 py-4 text-center align-top pt-5">
                            <button @click="openEdit(p)" class="text-blue-600 hover:text-blue-800 font-medium mr-3 transition">Update</button>
                            <button @click="remove(p.id)" class="text-red-600 hover:text-red-800 font-medium transition">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="filteredParticulars.length === 0">
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No particulars found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 bg-gray-50 text-xs text-gray-500 border-t">Total Records: {{ filteredParticulars.length }}</div>
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
