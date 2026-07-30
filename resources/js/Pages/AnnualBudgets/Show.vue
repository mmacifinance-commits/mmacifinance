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
    accountTitles: Array,
    availableYears: Array,
    allBudgets: Array,
})

const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
]

const showItemModal = ref(false)
const editingItem = ref(null)
const selectedMonthFilter = ref('') // '' for all, 1-12 for specific month

const itemForm = useForm({
    category_id: '',
    particular_id: '',
    month: 1,
    appropriation: 0,
    expenditure: 0
})

// Filters
const selectedYear = ref(props.budget.year)
const selectedSemester = ref(props.budget.semester || '')

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

const filteredItems = computed(() => {
    const items = props.budget.items || []
    if (!selectedMonthFilter.value) return items
    return items.filter(i => (i.month || 1) === parseInt(selectedMonthFilter.value))
})

// Group items by category
const groupedItems = computed(() => {
    const items = filteredItems.value
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
    const items = filteredItems.value
    const app = items.reduce((s, i) => s + Number(i.appropriation || 0), 0)
    const exp = items.reduce((s, i) => s + Number(i.expenditure || 0), 0)
    return { appropriation: app, expenditure: exp, balance: app - exp }
})

const utilRate = computed(() => grandTotals.value.appropriation > 0 ? ((grandTotals.value.expenditure / grandTotals.value.appropriation) * 100).toFixed(1) : '0.0')

function openAddItem() {
    itemForm.reset()
    itemForm.month = 1
    editingItem.value = null
    showItemModal.value = true
}

function openEditItem(item) {
    itemForm.category_id = item.category_id
    itemForm.particular_id = item.particular_id
    itemForm.month = item.month || 1
    itemForm.appropriation = item.appropriation
    itemForm.expenditure = item.expenditure
    editingItem.value = item.id
    showItemModal.value = true
}

function saveItem() {
    if (editingItem.value) {
        itemForm.put(`/annual-budgets/${props.budget.id}/items/${editingItem.value}`, { onSuccess: () => { showItemModal.value = false } })
    } else {
        itemForm.post(`/annual-budgets/${props.budget.id}/items`, { onSuccess: () => { showItemModal.value = false } })
    }
}

function removeItem(itemId) {
    if (confirm('Delete this monthly budget allocation item?')) router.delete(`/annual-budgets/${props.budget.id}/items/${itemId}`)
}

function catBalancePercent(group) {
    return group.totals.appropriation > 0 ? (((group.totals.appropriation - group.totals.expenditure) / group.totals.appropriation) * 100).toFixed(0) : '0'
}
</script>

<template>
<Head :title="`FY ${budget.year} Budget Allocations`" />
<AppLayout>
    <!-- Back + Title -->
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <Link href="/annual-budgets" class="flex items-center justify-center px-3 py-1.5 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-semibold transition">
                Back
            </Link>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold text-gray-900">Annual Budget Allocations</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-navy/10 text-navy border border-navy/20">
                        {{ budget.ref_no || ('AB-' + budget.year + '-000' + budget.id) }}
                    </span>
                </div>
                <p class="text-sm text-gray-500">Fiscal Year {{ budget.year }}{{ budget.semester ? ' — ' + budget.semester : '' }}</p>
            </div>
        </div>
    </div>

    <!-- Filters: Year, Month, Semester -->
    <div class="flex flex-wrap items-center gap-3 mb-6 bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
        <div class="flex items-center gap-2">
            <label class="text-xs font-bold uppercase text-gray-500">Fiscal Year:</label>
            <select v-model="selectedYear" @change="selectedSemester = ''; applyFilter()" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm bg-white min-w-[100px]">
                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <label class="text-xs font-bold uppercase text-gray-500">Budget Month:</label>
            <select v-model="selectedMonthFilter" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm bg-white min-w-[140px]">
                <option value="">All Months (Jan-Dec)</option>
                <option v-for="(mName, idx) in monthNames" :key="idx+1" :value="idx+1">{{ mName }}</option>
            </select>
        </div>

        <div v-if="semestersForYear.length" class="flex items-center gap-2">
            <label class="text-xs font-bold uppercase text-gray-500">Semester:</label>
            <select v-model="selectedSemester" @change="applyFilter()" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm bg-white min-w-[120px]">
                <option value="">All</option>
                <option v-for="s in semestersForYear" :key="s" :value="s">{{ s }}</option>
            </select>
        </div>

        <div class="flex-1"></div>
        <button v-if="perms.canManageBudget" @click="openAddItem" class="rounded-lg bg-navy-dark px-4 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">
            Add Monthly Allocation Item
        </button>
    </div>

    <!-- Grouped by Category -->
    <div v-for="group in groupedItems" :key="group.name" class="bg-white rounded-lg border border-gray-200 overflow-hidden mb-6 shadow-sm">
        <!-- Category Header -->
        <div class="bg-navy-dark px-5 py-3 flex items-center justify-between">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider">{{ group.name }}</h3>
            <span class="text-xs text-mustard font-semibold">Subtotal Appropriation: ₱{{ fmt(group.totals.appropriation) }}</span>
        </div>
        <!-- Table Header -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-navy/90 text-white border-b border-mustard">
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-white">Monthly Ref No.</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-white">Month</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-white">Department</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-white">Account Title</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-white">Appropriation</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-white">Expenditure</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-white">Balance</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-white">Util %</th>
                        <th v-if="perms.canManageBudget" class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-white">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in group.items" :key="item.id" class="border-b border-gray-100 hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3 align-middle">
                            <span class="font-mono text-xs font-semibold px-2 py-0.5 bg-slate-100 text-slate-800 rounded border border-slate-200">
                                {{ item.ref_no || (`MB-${budget.year}-${String(item.month || 1).padStart(2, '0')}-000${item.id}`) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-semibold text-gray-700 text-xs align-middle">
                            {{ monthNames[(item.month || 1) - 1] }}
                        </td>
                        <td class="px-4 py-3 text-gray-700 text-xs align-middle">
                            {{ item.particular?.department?.name || '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-900 font-medium text-sm align-middle">
                            {{ item.particular?.particular || 'N/A' }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-gray-900 align-middle">₱{{ fmt(item.appropriation) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-700 align-middle">₱{{ fmt(item.expenditure) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-700 align-middle">₱{{ fmt(Number(item.appropriation || 0) - Number(item.expenditure || 0)) }}</td>
                        <td class="px-4 py-3 text-center align-middle">
                            <span :class="Number(item.appropriation) > 0 && Number(item.expenditure) / Number(item.appropriation) > 0.5 ? 'text-rose-600 bg-rose-50' : 'text-emerald-700 bg-emerald-50'" class="font-bold text-xs px-2 py-0.5 rounded-full">
                                {{ Number(item.appropriation) > 0 ? ((Number(item.expenditure) / Number(item.appropriation)) * 100).toFixed(0) : 0 }}%
                            </span>
                        </td>
                        <td v-if="perms.canManageBudget" class="px-4 py-3 text-center align-middle">
                            <div class="inline-flex items-center gap-1.5">
                                <button @click="openEditItem(item)" class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded text-xs font-semibold shadow-sm transition border border-indigo-200">
                                    Edit
                                </button>
                                <button @click="removeItem(item.id)" class="inline-flex items-center gap-1 px-2.5 py-1 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded text-xs font-semibold shadow-sm transition border border-rose-200">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 border-t-2 border-gray-300 font-bold">
                        <td colspan="4" class="px-4 py-2.5 text-gray-700 text-xs uppercase">Category Subtotal:</td>
                        <td class="px-4 py-2.5 text-right text-gray-900">₱{{ fmt(group.totals.appropriation) }}</td>
                        <td class="px-4 py-2.5 text-right text-gray-900">₱{{ fmt(group.totals.expenditure) }}</td>
                        <td class="px-4 py-2.5 text-right text-gray-900">₱{{ fmt(group.totals.appropriation - group.totals.expenditure) }}</td>
                        <td class="px-4 py-2.5 text-center text-emerald-700 text-xs">{{ catBalancePercent(group) }}% Balance</td>
                        <td v-if="perms.canManageBudget"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Empty state -->
    <div v-if="!groupedItems.length" class="bg-white rounded-lg border border-gray-200 p-8 text-center text-gray-400 shadow-sm">
        No budget allocation items found for this selection. Click "Add Monthly Allocation Item" to get started.
    </div>

    <!-- Grand Total -->
    <div v-if="groupedItems.length" class="bg-white rounded-lg border border-gray-200 overflow-hidden mt-4 shadow-sm">
        <table class="w-full text-sm">
            <tfoot>
                <tr class="bg-navy-dark text-white font-bold">
                    <td class="px-5 py-3 text-mustard text-xs uppercase tracking-wider">Grand Total Budget Performance:</td>
                    <td class="px-5 py-3 text-right text-white">Appropriation: ₱{{ fmt(grandTotals.appropriation) }}</td>
                    <td class="px-5 py-3 text-right text-white">Expenditures: ₱{{ fmt(grandTotals.expenditure) }}</td>
                    <td class="px-5 py-3 text-right text-white">Remaining Balance: ₱{{ fmt(grandTotals.balance) }}</td>
                    <td class="px-5 py-3 text-center text-mustard text-xs">Utilization: {{ utilRate }}%</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Add/Edit Item Modal -->
    <Modal :show="showItemModal" :title="editingItem ? 'Edit Monthly Budget Allocation' : 'Add Monthly Budget Allocation'" :subtitle="editingItem ? 'Update monthly budget item details.' : 'Allocate budget for a specific month and account title.'" max-width="lg" @close="showItemModal = false">
        <form @submit.prevent="saveItem">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Budget Month</label>
                    <select v-model.number="itemForm.month" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required>
                        <option v-for="(mName, idx) in monthNames" :key="idx+1" :value="idx+1">{{ mName }} (Month {{ idx+1 }})</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Budget Category</label>
                    <select v-model="itemForm.category_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required>
                        <option value="">Select category</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Account Title</label>
                    <select v-model="itemForm.particular_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required>
                        <option value="">Select account title</option>
                        <option v-for="p in (accountTitles || particulars)" :key="p.id" :value="p.id">{{ p.particular }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Monthly Appropriation Amount (₱)</label>
                    <input v-model.number="itemForm.appropriation" type="number" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Expenditure (₱)</label>
                    <input v-model.number="itemForm.expenditure" type="number" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5 border-t mt-4">
                <button type="button" @click="showItemModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="itemForm.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">{{ editingItem ? 'Update Allocation' : 'Save Allocation' }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
