<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'

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
const fiscalMonths = computed(() => props.budget.fiscal_months || [])

const showItemModal = ref(false)
const showImportModal = ref(false)
const editingItem = ref(null)
const selectedMonthFilter = ref('') // '' for all, 1-12 for specific month
const searchTerm = ref('')
const importForm = useForm({
    csv_file: null,
})

const itemForm = useForm({
    category_id: '',
    department_id: '',
    particular_id: '',
    allocation_month: '',
    appropriation: 0,
})

const itemErrorMessages = computed(() => [...new Set(Object.values(itemForm.errors).filter(Boolean))])

const availableDepartments = computed(() => {
    const titles = props.accountTitles || props.particulars || []
    const categoryId = Number(itemForm.category_id || 0)

    const departments = titles
        .filter((title) => categoryId === 0 || Number(title.category_id || title.category?.id || 0) === categoryId)
        .map((title) => title.department)
        .filter(Boolean)
        .reduce((acc, department) => {
            const id = Number(department.id || 0)
            if (id && !acc.some((dept) => Number(dept.id) === id)) {
                acc.push(department)
            }
            return acc
        }, [])
        .sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')))
    return departments
})

const filteredAccountTitles = computed(() => {
    const titles = props.accountTitles || props.particulars || []
    const categoryId = Number(itemForm.category_id || 0)
    const departmentId = Number(itemForm.department_id || 0)

    if (!categoryId || !departmentId) return []

    return titles.filter((title) =>
        Number(title.category_id || title.category?.id || 0) === categoryId &&
        Number(title.department_id || title.department?.id || 0) === departmentId
    )
})

watch(() => itemForm.category_id, () => {
    itemForm.department_id = ''
    itemForm.particular_id = ''
})

watch(() => itemForm.department_id, () => {
    itemForm.particular_id = ''
})

// Filters
const selectedBudgetId = ref(props.budget.id)

watch(() => props.budget, (newBudget) => {
    if (newBudget) {
        selectedBudgetId.value = newBudget.id
    }
}, { immediate: true })

function applyFilter() {
    if (Number(selectedBudgetId.value) !== Number(props.budget.id)) {
        router.get(`/annual-budgets/${selectedBudgetId.value}`)
    }
}

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v || 0) }

const filteredItems = computed(() => {
    const items = props.budget.items || []
    return items.filter((item) => {
        const matchesMonth = !selectedMonthFilter.value || String(item.allocation_month || '').slice(0, 10) === selectedMonthFilter.value
        const term = searchTerm.value.trim().toLowerCase()
        const matchesSearch = !term || [
            item.ref_no,
            item.particular?.particular,
            item.particular?.department?.name,
            item.category?.name,
        ].some((value) => String(value || '').toLowerCase().includes(term))

        return matchesMonth && matchesSearch
    })
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
    itemForm.clearErrors()
    itemForm.allocation_month = fiscalMonths.value[0]?.value || ''
    editingItem.value = null
    showItemModal.value = true
}

function openEditItem(item) {
    itemForm.clearErrors()
    itemForm.category_id = item.category_id
    itemForm.department_id = Number(item.particular?.department_id || item.particular?.department?.id || 0) || ''
    itemForm.particular_id = item.particular_id
    itemForm.allocation_month = String(item.allocation_month || '').slice(0, 10)
    itemForm.appropriation = item.appropriation
    editingItem.value = item.id
    showItemModal.value = true
}

function saveItem() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showItemModal.value = false
            itemForm.clearErrors()
        },
        onError: () => {
            showItemModal.value = true
        },
    }

    if (editingItem.value) {
        itemForm.put(`/annual-budgets/${props.budget.id}/items/${editingItem.value}`, options)
    } else {
        itemForm.post(`/annual-budgets/${props.budget.id}/items`, options)
    }
}

function removeItem(itemId) {
    if (confirm('Warning: this cannot be undone. Delete this monthly budget allocation item?')) router.delete(`/annual-budgets/${props.budget.id}/items/${itemId}`)
}

function exportCsv() {
    window.location.href = `/annual-budgets/${props.budget.id}/export-csv`
}

function handleImportCsv() {
    importForm.post(`/annual-budgets/${props.budget.id}/import-csv`, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showImportModal.value = false
            importForm.reset()
        },
        onError: () => {
            showImportModal.value = true
        },
    })
}

function catBalancePercent(group) {
    return group.totals.appropriation > 0 ? (((group.totals.appropriation - group.totals.expenditure) / group.totals.appropriation) * 100).toFixed(0) : '0'
}
</script>

<template>
<Head :title="`${budget.fiscal_year_label} Budget Allocations`" />
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
                <p class="text-sm text-gray-500">{{ budget.fiscal_year_label }} — {{ budget.period_label }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button @click="exportCsv" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition shadow-sm">
                Export CSV
            </button>
            <button v-if="perms.canManageBudget" @click="showImportModal = true" class="rounded-lg bg-navy-dark px-4 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">
                Import CSV
            </button>
        </div>
    </div>

    <!-- Filters: Fiscal period and allocation month -->
    <div class="flex flex-wrap items-end gap-3 mb-6 bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
        <div class="flex items-center gap-2">
            <label class="text-xs font-bold uppercase text-gray-500">Fiscal Year:</label>
            <select v-model.number="selectedBudgetId" @change="applyFilter" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm bg-white min-w-[170px]">
                <option v-for="period in allBudgets" :key="period.id" :value="period.id">{{ period.fiscal_year_label }}</option>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <label class="text-xs font-bold uppercase text-gray-500">Budget Month:</label>
            <select v-model="selectedMonthFilter" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm bg-white min-w-[140px]">
                <option value="">All Fiscal Months</option>
                <option v-for="month in fiscalMonths" :key="month.value" :value="month.value">{{ month.label }}</option>
            </select>
        </div>

        <div class="flex items-center gap-2 min-w-[280px] flex-1">
            <label class="text-xs font-bold uppercase text-gray-500 whitespace-nowrap">Search:</label>
            <input
                v-model="searchTerm"
                type="text"
                class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm bg-white"
                placeholder="Search account title, responsibility center, or ref no..."
            />
        </div>

        <button v-if="perms.canManageBudget" @click="openAddItem" class="ml-auto rounded-lg bg-navy-dark px-4 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">
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
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-white">Responsibility Center</th>
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
                            {{ item.allocation_month_label || monthNames[(item.month || 1) - 1] }}
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
    <div v-if="groupedItems.length" class="overflow-x-auto bg-white rounded-lg border border-gray-200 mt-4 shadow-sm">
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
            <div v-if="itemErrorMessages.length" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <p class="font-semibold">Please correct the following before saving:</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li v-for="message in itemErrorMessages" :key="message">{{ message }}</li>
                </ul>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Budget Month</label>
                    <select v-model="itemForm.allocation_month" :class="itemForm.errors.allocation_month ? 'border-red-400' : 'border-gray-300'" class="w-full rounded-lg border px-3 py-2.5 text-sm" required>
                        <option v-for="month in fiscalMonths" :key="month.value" :value="month.value">{{ month.label }}</option>
                    </select>
                    <p v-if="itemForm.errors.allocation_month" class="mt-1 text-xs text-red-600">{{ itemForm.errors.allocation_month }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Budget Category</label>
                    <select v-model="itemForm.category_id" :class="itemForm.errors.category_id ? 'border-red-400' : 'border-gray-300'" class="w-full rounded-lg border px-3 py-2.5 text-sm" required>
                        <option value="">Select category</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <p v-if="itemForm.errors.category_id" class="mt-1 text-xs text-red-600">{{ itemForm.errors.category_id }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Responsibility Center</label>
                    <select v-model="itemForm.department_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" :disabled="!itemForm.category_id" required>
                        <option value="">Select responsibility center</option>
                        <option v-for="d in availableDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <p v-if="itemForm.errors.department_id" class="mt-1 text-xs text-red-600">{{ itemForm.errors.department_id }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Account Title</label>
                    <select v-model="itemForm.particular_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" :disabled="!itemForm.category_id || !itemForm.department_id" required>
                        <option value="">Select account title</option>
                        <option v-for="p in filteredAccountTitles" :key="p.id" :value="p.id">{{ p.particular }}</option>
                    </select>
                    <p v-if="itemForm.errors.particular_id" class="mt-1 text-xs text-red-600">{{ itemForm.errors.particular_id }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Monthly Appropriation Amount (₱)</label>
                    <input v-model.number="itemForm.appropriation" type="number" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required />
                    <p v-if="itemForm.errors.appropriation" class="mt-1 text-xs text-red-600">{{ itemForm.errors.appropriation }}</p>
                </div>
                <div class="sm:col-span-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                    Expenditure is now calculated automatically from linked disbursements with status <span class="font-semibold">Posted (GL)</span>. It is no longer editable here.
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5 border-t mt-4">
                <button type="button" @click="showItemModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="itemForm.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm disabled:cursor-wait disabled:opacity-60">{{ itemForm.processing ? (editingItem ? 'Updating...' : 'Saving...') : (editingItem ? 'Update Allocation' : 'Save Allocation') }}</button>
            </div>
        </form>
    </Modal>

    <Modal :show="showImportModal" title="Import Budget CSV" subtitle="Upload a CSV file to add or update monthly budget allocation rows. Missing budget categories, departments, and account titles will be created automatically." max-width="lg" @close="showImportModal = false">
        <form @submit.prevent="handleImportCsv">
            <div class="space-y-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    <p class="font-semibold text-slate-900 mb-2">Required columns</p>
                    <p class="text-xs leading-6">
                        <span class="font-semibold">allocation_month</span> (YYYY-MM), <span class="font-semibold">budget_category</span>, <span class="font-semibold">responsibility_center</span>, <span class="font-semibold">account_title</span>, <span class="font-semibold">appropriation</span>
                    </p>
                    <p class="mt-2 text-xs leading-6">
                    Optional columns: <span class="font-semibold">month</span> (legacy), <span class="font-semibold">fiscal_year_label</span>, <span class="font-semibold">fiscal_start_date</span>, <span class="font-semibold">fiscal_end_date</span>, <span class="font-semibold">account_code</span>, <span class="font-semibold">description</span>. Expenditure is recalculated automatically and ignored on import.
                </p>
            </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">CSV File</label>
                    <input
                        type="file"
                        accept=".csv,text/csv"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
                        @change="(e) => importForm.csv_file = e.target.files?.[0] || null"
                        required
                    />
                </div>
                <div v-if="importForm.errors.csv_file" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ importForm.errors.csv_file }}
                </div>
                <div v-if="importForm.processing" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-800">
                    Importing CSV... please wait.
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5 border-t mt-4">
                <button type="button" @click="showImportModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="importForm.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">{{ importForm.processing ? 'Importing...' : 'Import CSV' }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
