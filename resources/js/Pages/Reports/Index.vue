<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
    budgets: Array,
    categories: Array,
    accountTitles: Array,
    particulars: Array,
    departments: Array,
    expenses: Array,
    disbursements: Array,
    annualBudgetItems: Array,
    budgetItems: Array,
    selectedMonthPerformance: Object,
    budgetPerformanceByYear: Array,
    selectedMonthLabel: String,
    availableYears: Array,
    filters: Object,
})

const filterYear = ref(props.filters.year || new Date().getFullYear())
const filterMonth = ref(props.filters.month || '')
const startDate = ref(props.filters.start_date || '')
const endDate = ref(props.filters.end_date || '')
const filterDepartment = ref(props.filters.department_id || '')
const filterCategory = ref(props.filters.category_id || '')
const filterAccountTitle = ref(props.filters.account_title_id || '')
const breakdownOpen = ref(false)
const breakdownYear = ref(null)
const breakdownSearch = ref('')
const breakdownMonthFilter = ref('')
const breakdownSemesterFilter = ref('')

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const PESO = '₱'
const FULL_YEAR_SEMESTER = 'Full Year (Jan-Dec)'
const SEMESTER_ORDER = {
    [FULL_YEAR_SEMESTER]: 0,
    '1st Semester': 1,
    '2nd Semester': 2,
    Summer: 3,
}

function normalizeSemester(value) {
    const semester = String(value || '').trim()
    const lower = semester.toLowerCase()

    if (!semester || lower === 'full year' || lower === 'full year (jan-dec)' || lower === 'full year (jan - dec)' || lower === 'full year (jan – dec)' || lower === 'full year (jan – dec)') {
        return FULL_YEAR_SEMESTER
    }

    return semester
}

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(v || 0) }

function asArray(value) {
    if (Array.isArray(value)) return value
    if (value && typeof value === 'object') return Object.values(value)
    return []
}

const yearlyBudgetPerformance = computed(() => {
    return asArray(props.budgetPerformanceByYear)
        .map((row) => ({
            ...row,
            records: (asArray(props.budgets).filter((budget) => Number(budget.year) === Number(row.year)).map((budget) => ({
                id: budget.id,
                ref_no: budget.ref_no || `AB-${budget.year}-${String(budget.id).padStart(4, '0')}`,
                semester: normalizeSemester(budget.semester),
                appropriation: Number((budget.items || []).reduce((sum, item) => sum + Number(item.appropriation || 0), 0)),
                expenditure: Number((budget.items || []).reduce((sum, item) => sum + Number(item.expenditure || 0), 0)),
            }))),
        }))
        .map((row) => ({
            ...row,
            records: row.records.sort((a, b) => (SEMESTER_ORDER[a.semester] ?? 99) - (SEMESTER_ORDER[b.semester] ?? 99)),
        }))
        .sort((a, b) => b.year - a.year)
})

const selectedYearBreakdown = computed(() => {
    const year = Number(breakdownYear.value)
    if (!year) return []

    const search = breakdownSearch.value.trim().toLowerCase()
    return asArray(props.budgets)
        .filter((budget) => Number(budget.year) === year)
        .map((budget) => {
            const appropriation = Number((budget.items || []).reduce((sum, item) => sum + Number(item.appropriation || 0), 0))
            const expenditure = Number((budget.items || []).reduce((sum, item) => sum + Number(item.expenditure || 0), 0))
            const utilizationRate = appropriation > 0 ? ((expenditure / appropriation) * 100).toFixed(1) : '0.0'

            return {
                id: budget.id,
                ref_no: budget.ref_no || `AB-${budget.year}-${String(budget.id).padStart(4, '0')}`,
                semester: normalizeSemester(budget.semester),
                appropriation,
                expenditure,
                balance: appropriation - expenditure,
                utilizationRate,
                items: asArray(budget.items).map((item) => ({
                    id: item.id,
                    month: item.month,
                    category: item.category.name || '',
                    department: item.particular.department.name || '',
                    account: item.particular.particular || '',
                    appropriation: Number(item.appropriation || 0),
                    expenditure: Number(item.expenditure || 0),
                })),
            }
        })
        .filter((budget) => {
            if (!search) return true

            const haystack = [
                budget.ref_no,
                budget.semester,
                budget.appropriation,
                budget.expenditure,
                budget.balance,
                ...budget.items.flatMap((item) => [item.category, item.department, item.account, item.month]),
            ].join(' ').toLowerCase()

            return haystack.includes(search)
        })
})

const selectedYearItems = computed(() => {
    const year = Number(breakdownYear.value)
    if (!year) return []

    const search = breakdownSearch.value.trim().toLowerCase()
    const monthFilter = breakdownMonthFilter.value ? Number(breakdownMonthFilter.value) : null
    const semesterFilter = normalizeSemester(breakdownSemesterFilter.value)

    return asArray(props.budgets)
        .filter((budget) => Number(budget.year) === year)
        .filter((budget) => !semesterFilter || normalizeSemester(budget.semester) === semesterFilter)
        .flatMap((budget) => {
            const refNo = budget.ref_no || `AB-${budget.year}-${String(budget.id).padStart(4, '0')}`
            const semester = normalizeSemester(budget.semester)

            return asArray(budget.items)
                .map((item) => {
                    const monthNumber = Number(item.month || 0)
                    const monthLabel = monthNumber >= 1 && monthNumber <= monthNames.length ? monthNames[monthNumber - 1] : 'N/A'
                    const category = item.category.name || 'Uncategorized'
                    const department = item.particular.department.name || 'No RC'
                    const account = item.particular.particular || 'Untitled'
                    const appropriation = Number(item.appropriation || 0)
                    const expenditure = Number(item.expenditure || 0)
                    const balance = appropriation - expenditure

                    return {
                        rowKey: `${budget.id}-${item.id}`,
                        ref_no: refNo,
                        semester,
                        monthNumber,
                        monthLabel,
                        category,
                        department,
                        account,
                        appropriation,
                        expenditure,
                        balance,
                        haystack: [refNo, semester, monthLabel, category, department, account, appropriation, expenditure, balance].join(' ').toLowerCase(),
                    }
                })
                .filter((item) => {
                    if (monthFilter && item.monthNumber !== monthFilter) return false
                    if (!search) return true
                    return item.haystack.includes(search)
                })
        })
        .sort((a, b) => {
            const semesterDiff = (SEMESTER_ORDER[a.semester] ?? 99) - (SEMESTER_ORDER[b.semester] ?? 99)
            if (semesterDiff !== 0) return semesterDiff
            if (a.monthNumber !== b.monthNumber) return a.monthNumber - b.monthNumber
            return a.account.localeCompare(b.account)
        })
})

const selectedPeriodLabel = computed(() => {
    return props.selectedMonthPerformance?.month_label || 'All Months'
})

function openBreakdown(year) {

    breakdownYear.value = year
    breakdownSearch.value = ''
    breakdownMonthFilter.value = ''
    breakdownSemesterFilter.value = ''
    breakdownOpen.value = true
}

function applyFilters() {
    router.get('/reports', {
        year: filterYear.value,
        month: filterMonth.value,
        start_date: startDate.value,
        end_date: endDate.value,
        department_id: filterDepartment.value,
        category_id: filterCategory.value,
        account_title_id: filterAccountTitle.value,
    }, { preserveState: true, replace: true })
}

function clearFilters() {
    filterYear.value = new Date().getFullYear()
    filterMonth.value = ''
    startDate.value = ''
    endDate.value = ''
    filterDepartment.value = ''
    filterCategory.value = ''
    filterAccountTitle.value = ''
    applyFilters()
}

function totalApp() { return asArray(props.annualBudgetItems).reduce((s, i) => s + Number(i.appropriation || 0), 0) }
function totalExp() { return asArray(props.budgetItems).reduce((s, i) => s + Number(i.expenditure || 0), 0) }
function utilRate(app, exp) { return Number(app || 0) > 0 ? ((Number(exp || 0) / Number(app || 0)) * 100).toFixed(1) : '0.0' }
</script>

<template>
<Head title="Financial Reports" />
<AppLayout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Financial Reports & Performance</h2>
                <p class="text-sm text-gray-500">Filtered financial statements, budget utilization, and posted expenditure reports</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm mb-6 space-y-3">
        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Report Filters & Date Range</div>
        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6">
            <div>
                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Fiscal Year</label>
                <select v-model="filterYear" @change="applyFilters" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white">
                    <option v-for="y in (asArray(availableYears).length ? asArray(availableYears) : [2026, 2025, 2024])" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Month</label>
                <select v-model="filterMonth" @change="applyFilters" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white">
                    <option value="">All Months</option>
                    <option v-for="(mName, idx) in monthNames" :key="idx+1" :value="idx+1">{{ mName }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Responsibility Center</label>
                <select v-model="filterDepartment" @change="applyFilters" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white">
                    <option value="">All Responsibility Centers</option>
                    <option v-for="d in asArray(departments)" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Category</label>
                <select v-model="filterCategory" @change="applyFilters" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white">
                    <option value="">All Categories</option>
                    <option v-for="c in asArray(categories)" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Start Date</label>
                <input v-model="startDate" type="date" @change="applyFilters" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white" />
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600 mb-1">End Date</label>
                <input v-model="endDate" type="date" @change="applyFilters" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white" />
            </div>
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t">
            <button @click="clearFilters" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-xs font-semibold">Reset Filters</button>
        </div>
    </div>

    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-gray-50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Appropriation and Expenditure for Selected Month</h3>
                <p class="text-xs text-gray-500 mt-1">FY {{ filterYear }} - {{ selectedMonthPerformance.month_label }}</p>
            </div>
        </div>
        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-slate-50 p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Selected Period</p>
                <p class="mt-1 text-xl font-extrabold text-navy-dark font-sans tabular-nums">{{ selectedPeriodLabel }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-slate-50 p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Appropriation</p>
                <p class="mt-1 text-xl font-extrabold text-navy-dark font-sans tabular-nums">{{ PESO }}{{ fmt(selectedMonthPerformance.appropriation) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-slate-50 p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Expenditure</p>
                <p class="mt-1 text-xl font-extrabold text-mustard font-sans tabular-nums">{{ PESO }}{{ fmt(selectedMonthPerformance.expenditure) }}</p>
                <p class="mt-1 text-xs font-semibold text-gray-500">Utilization: {{ selectedMonthPerformance.utilizationRate }}%</p>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid gap-5 md:grid-cols-2 mb-8">
        <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden">
            <div class="h-1.5 bg-emerald-500"></div>
            <div class="p-5">
                <p class="text-xs font-bold uppercase text-gray-500 mb-1">Total Appropriation</p>
                <p class="text-2xl font-bold text-gray-900 font-sans tabular-nums">{{ PESO }}{{ fmt(totalApp()) }}</p>
            </div>
        </div>
        <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden">
            <div class="h-1.5 bg-rose-500"></div>
            <div class="p-5">
                <p class="text-xs font-bold uppercase text-gray-500 mb-1">Total Expenditure (Posted)</p>
                <p class="text-2xl font-bold text-gray-900 font-sans tabular-nums">{{ PESO }}{{ fmt(totalExp()) }}</p>
            </div>
        </div>
    </div>

    <!-- Budget Performance -->
    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-gray-50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Budget Performance by Fiscal Year</h3>
                <p class="text-xs text-gray-500 mt-1">
                    Totals are grouped by year. Semester, summer, and full-year records remain separate on the Annual Budget page.
                </p>
            </div>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-navy-dark text-white border-b-2 border-mustard">
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-white">Fiscal Year</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-white">Record Types</th>
                    <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-white">Appropriation</th>
                    <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-white">Expenditure</th>
                    <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-white">Utilization</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in yearlyBudgetPerformance" :key="row.year" class="border-b hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-3 font-bold text-gray-900">
                        <button
                            type="button"
                            @click="openBreakdown(row.year)"
                            class="text-navy hover:text-mustard underline decoration-dotted underline-offset-2"
                        >
                            {{ row.year }}
                        </button>
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-700">
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="record in row.records"
                                :key="record.id"
                                class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-[11px] font-semibold text-gray-700"
                            >
                                {{ record.semester }}
                            </span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-right font-medium font-sans tabular-nums">{{ PESO }}{{ fmt(row.appropriation) }}</td>
                    <td class="px-5 py-3 text-right font-medium font-sans tabular-nums">{{ PESO }}{{ fmt(row.expenditure) }}</td>
                    <td class="px-5 py-3 text-center">
                        <div class="relative h-5 rounded-full overflow-hidden bg-gray-200 w-32 mx-auto">
                            <div class="absolute left-0 top-0 h-full bg-mustard rounded-full transition-all"
                                :style="{ width: (row.appropriation > 0 ? Math.min(100, (row.expenditure / row.appropriation) * 100) : 0) + '%' }">
                            </div>
                            <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-gray-800">
                                {{ row.utilizationRate }}%
                            </span>
                        </div>
                    </td>
                </tr>
                <tr v-if="!yearlyBudgetPerformance.length">
                    <td colspan="5" class="px-5 py-8 text-center text-gray-400">No annual budget records found.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <Modal
        :show="breakdownOpen"
        title="Fiscal Year Breakdown"
        :subtitle="breakdownYear ? `Click a year to inspect every allocation row for FY ${breakdownYear}.` : ''"
        maxWidth="full"
        @close="breakdownOpen = false"
    >
        <div class="space-y-4">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[260px] flex-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Search</label>
                    <input
                        v-model="breakdownSearch"
                        type="text"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        placeholder="Search semester, month, category, account title, or responsibility center..."
                    />
                </div>
                <div class="min-w-[170px]">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Month</label>
                    <select v-model="breakdownMonthFilter" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white">
                        <option value="">All Months</option>
                        <option v-for="(month, idx) in monthNames" :key="idx + 1" :value="idx + 1">{{ month }}</option>
                    </select>
                </div>
                <div class="min-w-[180px]">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Semester</label>
                    <select v-model="breakdownSemesterFilter" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white">
                        <option value="">All Semesters</option>
                        <option value="Full Year (Jan-Dec)">Full Year (Jan-Dec)</option>
                        <option value="1st Semester">1st Semester</option>
                        <option value="2nd Semester">2nd Semester</option>
                        <option value="Summer">Summer</option>
                    </select>
                </div>
                <div class="rounded-lg border border-gray-200 bg-slate-50 px-4 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Year</p>
                    <p class="text-lg font-extrabold text-navy-dark">{{ breakdownYear }}</p>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Items</p>
                    <p class="mt-1 text-xl font-extrabold text-navy-dark">{{ selectedYearItems.length }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Appropriation</p>
                    <p class="mt-1 text-xl font-extrabold text-navy-dark font-sans tabular-nums">{{ PESO }}{{ fmt(selectedYearItems.reduce((sum, item) => sum + Number(item.appropriation || 0), 0)) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Expenditure</p>
                    <p class="mt-1 text-xl font-extrabold text-mustard font-sans tabular-nums">{{ PESO }}{{ fmt(selectedYearItems.reduce((sum, item) => sum + Number(item.expenditure || 0), 0)) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Utilization</p>
                    <p class="mt-1 text-xl font-extrabold text-navy-dark">
                        {{ (() => {
                            const app = selectedYearItems.reduce((sum, item) => sum + Number(item.appropriation || 0), 0)
                            const exp = selectedYearItems.reduce((sum, item) => sum + Number(item.expenditure || 0), 0)
                            return app > 0 ? ((exp / app) * 100).toFixed(1) : '0.0'
                        })() }}%
                    </p>
                </div>
            </div>

            <div class="max-h-[55vh] overflow-y-auto overflow-x-auto rounded-lg border border-gray-200">
                <table class="w-full min-w-[1520px] table-fixed text-sm">
                    <thead class="sticky top-0 z-10 bg-navy-dark text-white">
                        <tr>
                            <th class="w-[13%] px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Record</th>
                            <th class="w-[11%] px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Semester</th>
                            <th class="w-[9%] px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Month</th>
                            <th class="w-[17%] px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Responsibility Center</th>
                            <th class="w-[17%] px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Account Title</th>
                            <th class="w-[13%] px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Category</th>
                            <th class="w-[10%] px-6 py-3 text-right text-xs font-bold uppercase tracking-wider whitespace-nowrap">Appropriation</th>
                            <th class="w-[10%] px-6 py-3 text-right text-xs font-bold uppercase tracking-wider whitespace-nowrap">Expenditure</th>
                            <th class="w-[10%] px-6 py-3 text-right text-xs font-bold uppercase tracking-wider whitespace-nowrap">Balance</th>
                            <th class="w-[10%] px-6 py-3 text-center text-xs font-bold uppercase tracking-wider whitespace-nowrap">Utilization</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in selectedYearItems" :key="item.rowKey" class="border-b align-top hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-navy break-words">{{ item.ref_no }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-800 break-words">{{ item.semester }}</td>
                            <td class="px-4 py-3 break-words text-gray-700">{{ item.monthLabel }}</td>
                            <td class="px-4 py-3 break-words text-gray-700">{{ item.department || 'No RC' }}</td>
                            <td class="px-4 py-3 break-words font-semibold text-gray-800">{{ item.account || 'Untitled' }}</td>
                            <td class="px-4 py-3 break-words text-gray-700">{{ item.category || 'Uncategorized' }}</td>
                            <td class="px-6 py-3 whitespace-nowrap text-right font-medium font-sans tabular-nums">{{ PESO }}{{ fmt(item.appropriation) }}</td>
                            <td class="px-6 py-3 whitespace-nowrap text-right font-medium font-sans tabular-nums">{{ PESO }}{{ fmt(item.expenditure) }}</td>
                            <td class="px-6 py-3 whitespace-nowrap text-right font-medium font-sans tabular-nums">{{ PESO }}{{ fmt(item.balance) }}</td>
                            <td class="px-6 py-3 text-center">
                                <div class="relative h-5 rounded-full overflow-hidden bg-gray-200 w-28 mx-auto">
                                    <div
                                        class="absolute left-0 top-0 h-full bg-mustard rounded-full transition-all"
                                        :style="{ width: (item.appropriation > 0 ? Math.min(100, (item.expenditure / item.appropriation) * 100) : 0) + '%' }"
                                    ></div>
                                    <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-gray-800">
                                        {{ item.appropriation > 0 ? ((item.expenditure / item.appropriation) * 100).toFixed(1) : '0.0' }}%
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!selectedYearItems.length">
                            <td colspan="10" class="px-4 py-8 text-center text-gray-400">No matching budget records found for this year.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </Modal>

</AppLayout>
</template>
