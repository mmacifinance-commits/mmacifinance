<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const perms = computed(() => usePage().props.permissions || {})

const props = defineProps({ categories: Array })
const showModal = ref(false)
const editing = ref(null)
const form = useForm({ name: '', description: '' })

function openCreate() { form.reset(); editing.value = null; showModal.value = true }
function openEdit(c) { form.name = c.name; form.description = c.description || ''; editing.value = c.id; showModal.value = true }
function save() {
    if (editing.value) form.put(`/budget-categories/${editing.value}`, { onSuccess: () => { showModal.value = false } })
    else form.post('/budget-categories', { onSuccess: () => { showModal.value = false } })
}
function remove(id) { if (confirm('Delete?')) router.delete(`/budget-categories/${id}`) }
</script>

<template>
<Head title="Budget Categories" />
<AppLayout>
    <div class="flex items-center justify-between mb-6">
        <div><h2 class="text-xl font-bold text-gray-900">Budget Categories</h2><p class="text-sm text-gray-500">Manage budget classification categories</p></div>
        <button v-if="perms.canManageBudget" @click="openCreate" class="flex items-center gap-2 rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition">+ Add Category</button>
    </div>
    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto w-full pb-4">
            <table class="w-full text-sm min-w-max whitespace-nowrap">
                <thead><tr class="bg-navy-dark text-white">
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Name</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Description</th>
                    <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Particulars</th>
                    <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Actions</th>
                </tr></thead>
                <tbody>
                    <tr v-for="c in categories" :key="c.id" class="border-b border-gray-100 hover:bg-gray-50/50">
                        <td class="px-5 py-3 font-medium text-gray-800">{{ c.name }}</td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ c.description }}</td>
                        <td class="px-5 py-3 text-center text-gray-600">
                            <div class="flex flex-wrap justify-center gap-1.5 max-w-xs mx-auto">
                                <span v-for="p in c.particulars" :key="p.id" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                    {{ p.particular }}
                                </span>
                                <span v-if="!c.particulars || c.particulars.length === 0" class="text-gray-400">—</span>
                            </div>
                        </td>
                        <td v-if="perms.canManageBudget" class="px-5 py-3 text-center">
                            <button @click="openEdit(c)" class="text-blue-600 hover:text-blue-800 font-medium mr-3 transition">Update</button>
                            <button @click="remove(c.id)" class="text-red-600 hover:text-red-800 font-medium transition">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-2.5 bg-gray-50 text-xs text-gray-500 border-t">Total Records: {{ categories.length }}</div>
    </div>
    <Modal :show="showModal" :title="editing ? 'Edit Category' : 'Add Category'" :subtitle="editing ? 'Update category details.' : 'Create a new budget category.'" @close="showModal = false">
        <form @submit.prevent="save">
            <div class="space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Name</label><input v-model="form.name" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label><textarea v-model="form.description" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"></textarea></div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5">
                <button type="button" @click="showModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy">{{ form.processing ? 'Saving...' : (editing ? 'Update' : 'Create') }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
