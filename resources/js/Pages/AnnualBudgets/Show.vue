<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({ budget: Object, categories: Array, particulars: Array })

const showItemModal = ref(false)
const editingItem = ref(null)
const itemForm = useForm({ category_id: '', particular_id: '', appropriation: 0, expenditure: 0 })

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v) }

const totals = computed(() => {
    const items = props.budget.items || []
    const app = items.reduce((s, i) => s + Number(i.appropriation || 0), 0)
    const exp = items.reduce((s, i) => s + Number(i.expenditure || 0), 0)
    return { appropriation: app, expenditure: exp, balance: app - exp }
})
const utilRate = computed(() => totals.value.appropriation > 0 ? ((totals.value.expenditure / totals.value.appropriation) * 100).toFixed(1) : '0.0')
const balancePercent = computed(() => totals.value.appropriation > 0 ? (100 - parseFloat(utilRate.value)).toFixed(1) : '100.0')

function openAddItem() { itemForm.reset(); editingItem.value = null; showItemModal.value = true }
function openEditItem(item) {
    itemForm.category_id = item.category_id; itemForm.particular_id = item.particular_id
    itemForm.appropriation = item.appropriation; itemForm.expenditure = item.expenditure
    editingItem.value = item.id; showItemModal.value = true
}
function saveItem() {
    if (editingItem.value) {
        itemForm.put(`/annual-budgets/${props.budget.id}/items/${editingItem.value}`, { onSuccess: () => { showItemModal.value = false } })
    } else {
        itemForm.post(`/annual-budgets/${props.budget.id}/items`, { onSuccess: () => { showItemModal.value = false } })
    }
}
function removeItem(itemId) {
    if (confirm('Delete this budget item?')) router.delete(`/annual-budgets/${props.budget.id}/items/${itemId}`)
}
</script>

<template>
<Head :title="`FY ${budget.year} Budget`" />
<AppLayout>
    <!-- Back + Title -->
    <div class="flex items-center gap-3 mb-4">
        <Link href="/annual-budgets" class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-600 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </Link>
        <div>
            <h2 class="text-xl font-bold text-gray-900">View Annual Budget</h2>
            <p class="text-sm text-gray-500">Fiscal Year {{ budget.year }}</p>
        </div>
    </div>

    <!-- Add Button -->
    <button @click="openAddItem" class="mb-4 flex items-center gap-2 rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition">
        <span>+</span> Add Budget Item
    </button>

    <!-- Summary Card -->
    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden mb-6">
        <!-- Items Table -->
        <div v-if="budget.items && budget.items.length" class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-navy-dark text-white">
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Particular</th>
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Category</th>
                        <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-mustard">Appropriation</th>
                        <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-mustard">Expenditure</th>
                        <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in budget.items" :key="item.id" class="border-b border-gray-100 hover:bg-gray-50/50">
                        <td class="px-5 py-3 text-gray-800">{{ item.particular?.particular || 'N/A' }}</td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ item.category?.name }}</td>
                        <td class="px-5 py-3 text-right font-medium">{{ fmt(item.appropriation) }}</td>
                        <td class="px-5 py-3 text-right font-medium">{{ fmt(item.expenditure) }}</td>
                        <td class="px-5 py-3 text-center">
                            <button @click="openEditItem(item)" class="text-gray-500 hover:text-blue-600 mr-2" title="Edit">✏️</button>
                            <button @click="removeItem(item.id)" class="text-gray-400 hover:text-red-500" title="Delete">🗑️</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="p-5 border-t border-gray-200">
            <div class="flex items-center justify-end gap-8">
                <div class="text-right">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Total Appropriation:</p>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Total Expenditure:</p>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Balance:</p>
                </div>
                <div class="text-right w-32">
                    <p class="font-bold text-gray-900 mb-1">{{ fmt(totals.appropriation) }}</p>
                    <p class="font-bold text-gray-900 mb-1">{{ fmt(totals.expenditure) }}</p>
                    <p class="font-bold text-gray-900">{{ fmt(totals.balance) }}</p>
                </div>
                <div class="text-right w-16">
                    <p class="mb-1">&nbsp;</p>
                    <p class="mb-1 font-medium text-red-500 text-sm">{{ utilRate }}%</p>
                    <p class="font-medium text-green-600 text-sm">{{ balancePercent }}%</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Item Modal -->
    <Modal :show="showItemModal" :title="editingItem ? 'Edit Budget Item' : 'Add Budget Item'" :subtitle="editingItem ? 'Update this budget line item.' : 'Add a new appropriation line item.'" @close="showItemModal = false">
        <form @submit.prevent="saveItem">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label>
                    <select v-model="itemForm.category_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required>
                        <option value="">Select category</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Particular</label>
                    <select v-model="itemForm.particular_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required>
                        <option value="">Select particular</option>
                        <option v-for="p in particulars" :key="p.id" :value="p.id">{{ p.particular }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Appropriation Amount</label>
                    <input v-model.number="itemForm.appropriation" type="number" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required />
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5">
                <button type="button" @click="showItemModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="itemForm.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition">{{ editingItem ? 'Update Item' : 'Add Item' }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
