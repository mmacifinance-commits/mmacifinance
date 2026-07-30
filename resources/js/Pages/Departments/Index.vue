<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const perms = computed(() => usePage().props.permissions || {})

const props = defineProps({ departments: Array })
const showModal = ref(false)
const editing = ref(null)
const form = useForm({ name: '', code: '' })

function openCreate() { form.reset(); editing.value = null; showModal.value = true }
function openEdit(d) { form.name = d.name; form.code = d.code; editing.value = d.id; showModal.value = true }
function save() {
    if (editing.value) form.put(`/departments/${editing.value}`, { onSuccess: () => { showModal.value = false } })
    else form.post('/departments', { onSuccess: () => { showModal.value = false } })
}
function remove(id) { if (confirm('Delete this department?')) router.delete(`/departments/${id}`) }
</script>

<template>
<Head title="Departments" />
<AppLayout>
    <div class="flex items-center justify-between mb-6">
        <div><h2 class="text-xl font-bold text-gray-900">Departments</h2><p class="text-sm text-gray-500">Manage organizational departments and responsibility centers</p></div>
        <button v-if="perms.canManageBudget" @click="openCreate" class="rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">Add Department</button>
    </div>
    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto w-full pb-4">
            <table class="w-full text-sm min-w-max whitespace-nowrap">
                <thead><tr class="bg-navy-dark text-white border-b-2 border-mustard">
                    <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-white">Name</th>
                    <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-white">Code</th>
                    <th class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-white">Account Titles</th>
                    <th v-if="perms.canManageBudget" class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-white">Actions</th>
                </tr></thead>
                <tbody>
                    <tr v-for="d in departments" :key="d.id" class="border-b border-gray-100 hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-3.5 font-medium text-gray-800 align-middle">{{ d.name }}</td>
                        <td class="px-5 py-3.5 align-middle"><span class="rounded-md bg-navy/10 px-2.5 py-1 text-xs font-semibold text-navy">{{ d.code }}</span></td>
                        <td class="px-5 py-3.5 text-center text-gray-600 align-middle">
                            <div class="flex flex-wrap justify-center gap-1.5 max-w-xs mx-auto">
                                <span v-for="p in d.particulars" :key="p.id" class="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-700 font-medium">
                                    {{ p.particular }}
                                </span>
                                <span v-if="!d.particulars || d.particulars.length === 0" class="text-gray-400">—</span>
                            </div>
                        </td>
                        <td v-if="perms.canManageBudget" class="px-5 py-3.5 text-center align-middle">
                            <div class="inline-flex items-center gap-2">
                                <button @click="openEdit(d)" class="px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-md text-xs font-semibold shadow-sm transition-all duration-150 border border-indigo-200">
                                    Edit
                                </button>
                                <button @click="remove(d.id)" class="px-3 py-1.5 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-md text-xs font-semibold shadow-sm transition-all duration-150 border border-rose-200">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-2.5 bg-gray-50 text-xs text-gray-500 border-t">Total Records: {{ departments.length }}</div>
    </div>
    <Modal :show="showModal" :title="editing ? 'Edit Department' : 'Add Department'" :subtitle="editing ? 'Update department details.' : 'Create a new department.'" @close="showModal = false">
        <form @submit.prevent="save">
            <div class="space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Name</label><input v-model="form.name" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /><p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Code</label><input v-model="form.code" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /><p v-if="form.errors.code" class="mt-1 text-xs text-red-500">{{ form.errors.code }}</p></div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5 border-t mt-4">
                <button type="button" @click="showModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">{{ form.processing ? 'Saving...' : (editing ? 'Update' : 'Create') }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
