<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const perms = computed(() => usePage().props.permissions || {})

const props = defineProps({
    particulars: Array,
    accountTitles: Array,
    categories: Array,
    departments: Array
})

const listData = computed(() => props.accountTitles || props.particulars || [])
const showModal = ref(false)
const showImportModal = ref(false)
const editing = ref(null)
const form = useForm({ category_id: '', department_id: '', account_code: '', account_name: '', particular: '', description: '' })
const importForm = useForm({ csv_file: null })

const filterCategory = ref('')
const filterDepartment = ref('')
const searchQuery = ref('')

const filteredParticulars = computed(() => {
    return listData.value.filter(p => {
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
function remove(id) { if (confirm('Warning: this cannot be undone. Delete this Account Title?')) router.delete(`/budget-particulars/${id}`) }
function exportCsv() { window.location.href = '/budget-particulars/export-csv' }
function importCsv() {
    importForm.post('/budget-particulars/import-csv', {
        onSuccess: () => { showImportModal.value = false; importForm.reset() },
    })
}
</script>

<template>
<Head title="Account Titles" />
<AppLayout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Account Titles</h2>
            <p class="text-sm text-gray-500">Manage budget account titles, line items, and codes</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button v-if="perms.canManageBudget" @click="exportCsv" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">Export CSV</button>
            <button v-if="perms.canManageBudget" @click="showImportModal = true" class="rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">Import CSV</button>
            <button v-if="perms.canManageBudget" @click="openCreate" class="rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">
                Add Account Title
            </button>
        </div>
    </div>
    <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <select v-model="filterCategory" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 min-w-[200px] shadow-sm">
            <option value="">All Categories</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select v-model="filterDepartment" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 min-w-[200px] shadow-sm">
            <option value="">All Responsibility Centers</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <input v-model="searchQuery" type="text" placeholder="Search account title or code..." class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm w-full max-w-sm shadow-sm" />
    </div>

    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto w-full pb-4">
            <table class="w-full text-sm min-w-max whitespace-nowrap">
                <thead>
                    <tr class="bg-navy-dark text-white border-b-2 border-mustard">
                        <th class="px-6 py-4 text-left font-bold text-sm tracking-wide">Category</th>
                        <th class="px-6 py-4 text-left font-bold text-sm tracking-wide">Responsibility Center</th>
                        <th class="px-6 py-4 text-left font-bold text-sm tracking-wide">Account Code</th>
                        <th class="px-6 py-4 text-left font-bold text-sm tracking-wide">Account Title</th>
                        <th class="px-6 py-4 text-left font-bold text-sm tracking-wide">Description</th>
                        <th v-if="perms.canManageBudget" class="px-6 py-4 text-center font-bold text-sm tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in filteredParticulars" :key="p.id" class="border-b border-gray-100 hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 text-gray-800 text-sm align-middle">{{ p.category?.name }}</td>
                        <td class="px-6 py-4 text-gray-800 text-sm align-middle">{{ p.department?.name || p.department?.code }}</td>
                        <td class="px-6 py-4 align-middle">
                            <span class="font-mono text-xs font-semibold px-2 py-1 bg-gray-100 text-gray-800 rounded">{{ p.account_code }}</span>
                            <div class="text-[11px] text-gray-500 mt-0.5 max-w-[200px] whitespace-normal">{{ p.account_name }}</div>
                        </td>
                        <td class="px-6 py-4 text-gray-900 font-medium text-sm align-middle">{{ p.particular }}</td>
                        <td class="px-6 py-4 text-gray-500 text-sm align-middle whitespace-normal max-w-xs">{{ p.description || '-' }}</td>
                        <td v-if="perms.canManageBudget" class="px-6 py-4 text-center align-middle">
                            <div class="inline-flex items-center gap-2">
                                <button @click="openEdit(p)" class="px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-md text-xs font-semibold shadow-sm transition-all duration-150 border border-indigo-200">
                                    Edit
                                </button>
                                <button @click="remove(p.id)" class="px-3 py-1.5 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-md text-xs font-semibold shadow-sm transition-all duration-150 border border-rose-200">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="filteredParticulars.length === 0">
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No account titles found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 bg-gray-50 text-xs text-gray-500 border-t">Total Records: {{ filteredParticulars.length }}</div>
    </div>

    <Modal :show="showModal" :title="editing ? 'Edit Account Title' : 'Add Account Title'" :subtitle="editing ? 'Update account title details.' : 'Create a new account title line item.'" max-width="lg" @close="showModal = false">
        <form @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label><select v-model="form.category_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required><option value="">Select Category</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Responsibility Center</label><select v-model="form.department_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required><option value="">Select Responsibility Center</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Account Code</label><input v-model="form.account_code" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" placeholder="e.g. 50201010" required /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Account Name</label><input v-model="form.account_name" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" placeholder="e.g. Office Supplies Expense" required /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Account Title</label><input v-model="form.particular" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" placeholder="e.g. Paper & Ink Supplies" required /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label><input v-model="form.description" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" placeholder="Optional notes" /></div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5 border-t mt-4">
                <button type="button" @click="showModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">{{ form.processing ? 'Saving...' : (editing ? 'Update' : 'Save Account Title') }}</button>
            </div>
        </form>
    </Modal>

    <Modal :show="showImportModal" title="Import Account Titles CSV" subtitle="Required columns: budget_category, responsibility_center, account_code, account_name, account_title, description" max-width="lg" @close="showImportModal = false">
        <form @submit.prevent="importCsv" class="space-y-4">
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold">Required columns</p>
                <p class="mt-1 font-mono text-xs">budget_category, responsibility_center, account_code, account_name, account_title, description</p>
                <p class="mt-2 text-xs">
                    Account Titles must match an existing Budget Category and Responsibility Center. Annual Budget Allocations and Expenditures use these account titles later.
                </p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">CSV File</label>
                <input type="file" accept=".csv,text/csv" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" @change="(e) => importForm.csv_file = e.target.files?.[0] || null" />
                <p v-if="importForm.errors.csv_file" class="mt-1 text-xs text-red-500">{{ importForm.errors.csv_file }}</p>
            </div>
            <div class="flex items-center justify-end gap-3 border-t pt-5">
                <button type="button" @click="showImportModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</button>
                <button type="submit" :disabled="importForm.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">{{ importForm.processing ? 'Importing...' : 'Import CSV' }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
