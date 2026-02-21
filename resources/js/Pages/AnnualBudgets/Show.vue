<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const perms = computed(() => usePage().props.permissions || {})

const props = defineProps({
    budget: Object,
    categories: Array,
    particulars: Array,
    availableYears: Array,
    allBudgets: Array,
})

const showItemModal = ref(false)
const editingItem = ref(null)
const itemForm = useForm({ category_id: '', particular_id: '', appropriation: 0, expenditure: 0 })

// Filters
const selectedYear = ref(props.budget.year)
const selectedSemester = ref(props.budget.semester || '')

// Get unique semesters for the selected year
const semestersForYear = computed(() => {
    if (!props.allBudgets) return []
    return props.allBudgets
        .filter(b => b.year === selectedYear.value)
        .map(b => b.semester)
        .filter(Boolean)
})

function applyFilter() {
    const match = props.allBudgets?.find(b =>
        b.year === selectedYear.value &&
        (b.semester || '') === selectedSemester.value
    )
    if (match) {
        router.get(`/annual-budgets/${match.id}`)
    }
}

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v || 0) }

// Group items by category
const groupedItems = computed(() => {
    const items = props.budget.items || []
    const groups = {}
    items.forEach(item => {
        const catName = item.category?.name || 'Uncategorized'
        const catId = item.category_id
        if (!groups[catName]) {
            groups[catName] = { id: catId, name: catName, items: [], totals: { appropriation: 0, expenditure: 0 } }
        }
        groups[catName].items.push(item)
        groups[catName].totals.appropriation += Number(item.appropriation || 0)
        groups[catName].totals.expenditure += Number(item.expenditure || 0)
    })
    return Object.values(groups)
})

const grandTotals = computed(() => {
    const items = props.budget.items || []
    const app = items.reduce((s, i) => s + Number(i.appropriation || 0), 0)
    const exp = items.reduce((s, i) => s + Number(i.expenditure || 0), 0)
    return { appropriation: app, expenditure: exp, balance: app - exp }
})

const utilRate = computed(() => grandTotals.value.appropriation > 0 ? ((grandTotals.value.expenditure / grandTotals.value.appropriation) * 100).toFixed(1) : '0.0')

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

function catUtilPercent(group) {
    return group.totals.appropriation > 0 ? ((group.totals.expenditure / group.totals.appropriation) * 100).toFixed(0) : '0'
}
function catBalancePercent(group) {
    return group.totals.appropriation > 0 ? (((group.totals.appropriation - group.totals.expenditure) / group.totals.appropriation) * 100).toFixed(0) : '0'
}
</script>

<template>
<Head :title="`FY ${budget.year} Budget`" />
<AppLayout>
    <!-- Back + Title -->
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <Link href="/annual-budgets" class="flex items-center justify-center w-8 h-8 bg-gray-200 hover:bg-gray-300 text-gray-600 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <div>
                <h2 class="text-xl font-bold text-gray-900">View Annual Budget</h2>
                <p class="text-sm text-gray-500">Fiscal Year {{ budget.year }}{{ budget.semester ? ' — ' + budget.semester : '' }}</p>
            </div>
        </div>
    </div>

    <!-- Filters: SY and Semester -->
    <div class="flex items-center gap-3 mb-4">
        <div class="flex items-center gap-2">
            <label class="text-xs font-bold uppercase text-gray-500">SY:</label>
            <select v-model="selectedYear" @change="selectedSemester = ''; applyFilter()" class="border border-gray-300 px-3 py-1.5 text-sm bg-white min-w-[100px]">
                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
            </select>
        </div>
        <div v-if="semestersForYear.length" class="flex items-center gap-2">
            <label class="text-xs font-bold uppercase text-gray-500">Semester:</label>
            <select v-model="selectedSemester" @change="applyFilter()" class="border border-gray-300 px-3 py-1.5 text-sm bg-white min-w-[120px]">
                <option value="">All</option>
                <option v-for="s in semestersForYear" :key="s" :value="s">{{ s }}</option>
            </select>
        </div>
        <div class="flex-1"></div>
        <button v-if="perms.canManageBudget" @click="openAddItem" class="flex items-center gap-2 bg-navy-dark px-4 py-2 text-sm font-semibold text-white hover:bg-navy transition">
            <span>+</span> Add Budget Item
        </button>
    </div>

    <!-- Grouped by Category -->
    <div v-for="group in groupedItems" :key="group.name" class="bg-white border border-gray-200 overflow-hidden mb-4">
        <!-- Category Header -->
        <div class="bg-navy-dark px-5 py-2.5">
            <h3 class="text-sm font-bold text-white uppercase tracking-wide">{{ group.name }}</h3>
        </div>
        <!-- Table Header -->
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-navy/90 text-white">
                    <th class="px-5 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-mustard">Responsibility Center</th>
                    <th class="px-5 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-mustard">Particular</th>
                    <th class="px-5 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-mustard">Appropriation</th>
                    <th class="px-5 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-mustard">Expenditure</th>
                    <th class="px-5 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-mustard">Balance</th>
                    <th class="px-5 py-2.5 text-center text-xs font-bold uppercase tracking-wider text-mustard">%</th>
                    <th class="px-5 py-2.5 text-center text-xs font-bold uppercase tracking-wider text-mustard w-20"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in group.items" :key="item.id" class="border-b border-gray-100 hover:bg-gray-50/50">
                    <td class="px-5 py-3 text-gray-700 text-xs">{{ item.particular?.department?.name || '—' }}</td>
                    <td class="px-5 py-3 text-mustard-light font-medium text-sm" style="font-family: Inter, sans-serif;">{{ item.particular?.particular || 'N/A' }}</td>
                    <td class="px-5 py-3 text-right font-medium">{{ fmt(item.appropriation) }}</td>
                    <td class="px-5 py-3 text-right font-medium">{{ fmt(item.expenditure) }}</td>
                    <td class="px-5 py-3 text-right font-medium">{{ fmt(Number(item.appropriation || 0) - Number(item.expenditure || 0)) }}</td>
                    <td class="px-5 py-3 text-center">
                        <span :class="Number(item.appropriation) > 0 && Number(item.expenditure) / Number(item.appropriation) > 0.5 ? 'text-red-500' : 'text-green-600'" class="font-bold text-xs">
                            {{ Number(item.appropriation) > 0 ? ((Number(item.appropriation) - Number(item.expenditure)) / Number(item.appropriation) * 100).toFixed(0) : 0 }}%
                        </span>
                    </td>
                    <td v-if="perms.canManageBudget" class="px-5 py-3 text-center">
                        <button @click="openEditItem(item)" class="text-gray-500 hover:text-blue-600 mr-1" title="Edit">✏️</button>
                        <button @click="removeItem(item.id)" class="text-gray-400 hover:text-red-500" title="Delete">🗑️</button>
                    </td>
                </tr>
            </tbody>
            <!-- Category Subtotal -->
            <tfoot>
                <tr class="bg-gray-50 border-t-2 border-gray-300">
                    <td colspan="2" class="px-5 py-2.5 font-bold text-gray-700 text-xs uppercase" style="font-family: Inter, sans-serif;">Sub Total:</td>
                    <td class="px-5 py-2.5 text-right font-bold text-gray-900">{{ fmt(group.totals.appropriation) }}</td>
                    <td class="px-5 py-2.5 text-right font-bold text-gray-900">{{ fmt(group.totals.expenditure) }}</td>
                    <td class="px-5 py-2.5 text-right font-bold text-gray-900">{{ fmt(group.totals.appropriation - group.totals.expenditure) }}</td>
                    <td class="px-5 py-2.5 text-center font-bold text-green-600 text-xs">{{ catBalancePercent(group) }}%</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Empty state -->
    <div v-if="!groupedItems.length" class="bg-white border border-gray-200 p-8 text-center text-gray-400">
        No budget items yet. Click "Add Budget Item" to get started.
    </div>

    <!-- Grand Total -->
    <div v-if="groupedItems.length" class="bg-white border border-gray-200 overflow-hidden mt-2">
        <table class="w-full text-sm">
            <tfoot>
                <tr class="bg-navy-dark text-white">
                    <td colspan="2" class="px-5 py-3 font-bold text-mustard text-xs uppercase tracking-wider">Grand Total:</td>
                    <td class="px-5 py-3 text-right font-bold text-white">{{ fmt(grandTotals.appropriation) }}</td>
                    <td class="px-5 py-3 text-right font-bold text-white">{{ fmt(grandTotals.expenditure) }}</td>
                    <td class="px-5 py-3 text-right font-bold text-white">{{ fmt(grandTotals.balance) }}</td>
                    <td class="px-5 py-3 text-center font-bold text-mustard text-xs">{{ utilRate }}%</td>
                    <td class="w-20"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Add/Edit Item Modal -->
    <Modal :show="showItemModal" :title="editingItem ? 'Edit Budget Item' : 'Add Budget Item'" :subtitle="editingItem ? 'Update this budget line item.' : 'Add a new appropriation line item.'" @close="showItemModal = false">
        <form @submit.prevent="saveItem">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label>
                    <select v-model="itemForm.category_id" class="w-full border border-gray-300 px-3 py-2.5 text-sm" required>
                        <option value="">Select category</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Particular</label>
                    <select v-model="itemForm.particular_id" class="w-full border border-gray-300 px-3 py-2.5 text-sm" required>
                        <option value="">Select particular</option>
                        <option v-for="p in particulars" :key="p.id" :value="p.id">{{ p.particular }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Appropriation Amount</label>
                    <input v-model.number="itemForm.appropriation" type="number" step="0.01" min="0" placeholder="0.00" class="w-full border border-gray-300 px-3 py-2.5 text-sm" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Expenditure Amount</label>
                    <input v-model.number="itemForm.expenditure" type="number" step="0.01" min="0" placeholder="0.00" class="w-full border border-gray-300 px-3 py-2.5 text-sm" />
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5">
                <button type="button" @click="showItemModal = false" class="border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="itemForm.processing" class="bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition">{{ editingItem ? 'Update Item' : 'Add Item' }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
