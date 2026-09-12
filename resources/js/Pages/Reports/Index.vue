<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
    reportTypes: Array,
    reportType: String,
    budgets: Array,
    categories: Array,
    departments: Array,
    summaryCards: Object,
    receiptRows: Array,
    disbursementRows: Array,
    auditRows: Array,
    reconciliationWarnings: Array,
    selectedMonthPerformance: Object,
    budgetPerformanceByYear: Array,
    yearEndUnusedBalances: Array,
    selectedMonthLabel: String,
    availableYears: Array,
    fiscalPeriods: Array,
    filters: Object,
})

const filterReportType = ref(props.filters.report_type || props.reportType || 'overall_financial')
const filterYear = ref(props.filters.fiscal_period_id || '')
const filterMonth = ref(props.filters.allocation_month || '')
const startDate = ref(props.filters.start_date || '')
const endDate = ref(props.filters.end_date || '')
const filterDepartment = ref(props.filters.department_id || '')
const filterCategory = ref(props.filters.category_id || '')
const filterAccountTitle = ref(props.filters.account_title_id || '')
const breakdownOpen = ref(false)
const breakdownYear = ref(null)
const breakdownSearch = ref('')
const breakdownMonthFilter = ref('')
const activeFiscalPeriod = computed(() => asArray(props.fiscalPeriods).find(period => Number(period.id) === Number(filterYear.value)))
const fiscalMonths = computed(() => activeFiscalPeriod.value?.months || [])

const PESO = '₱'
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
            records: (asArray(props.budgets).filter((budget) => Number(budget.id) === Number(row.id)).map((budget) => ({
                id: budget.id,
                ref_no: budget.ref_no || `AB-${budget.year}-${String(budget.id).padStart(4, '0')}`,
                semester: budget.fiscal_year_label,
                appropriation: Number((budget.items || []).reduce((sum, item) => sum + Number(item.appropriation || 0), 0)),
                expenditure: Number((budget.items || []).reduce((sum, item) => sum + Number(item.expenditure || 0), 0)),
            }))),
        }))
        .sort((a, b) => b.year - a.year)
})

const selectedYearBreakdown = computed(() => {
    const periodId = Number(breakdownYear.value)
    if (!periodId) return []

    const search = breakdownSearch.value.trim().toLowerCase()
    return asArray(props.budgets)
        .filter((budget) => Number(budget.id) === periodId)
        .map((budget) => {
            const appropriation = Number((budget.items || []).reduce((sum, item) => sum + Number(item.appropriation || 0), 0))
            const expenditure = Number((budget.items || []).reduce((sum, item) => sum + Number(item.expenditure || 0), 0))
            const utilizationRate = appropriation > 0 ? ((expenditure / appropriation) * 100).toFixed(1) : '0.0'

            return {
                id: budget.id,
                ref_no: budget.ref_no || `AB-${budget.year}-${String(budget.id).padStart(4, '0')}`,
                semester: budget.fiscal_year_label,
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
    const periodId = Number(breakdownYear.value)
    if (!periodId) return []

    const search = breakdownSearch.value.trim().toLowerCase()
    const monthFilter = breakdownMonthFilter.value || null

    return asArray(props.budgets)
        .filter((budget) => Number(budget.id) === periodId)
        .flatMap((budget) => {
            const refNo = budget.ref_no || `AB-${budget.year}-${String(budget.id).padStart(4, '0')}`
            const semester = budget.fiscal_year_label

            return asArray(budget.items)
                .map((item) => {
                    const monthNumber = String(item.allocation_month || '').slice(0, 10)
                    const monthLabel = item.allocation_month_label || monthNumber
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
            if (a.monthNumber !== b.monthNumber) return String(a.monthNumber).localeCompare(String(b.monthNumber))
            return a.account.localeCompare(b.account)
        })
})

const selectedPeriodLabel = computed(() => {
    return props.selectedMonthPerformance?.month_label || 'All Months'
})

const selectedDateRangeLabel = computed(() => {
    if (props.filters.start_date && props.filters.end_date) {
        return `Posted ${props.filters.start_date} to ${props.filters.end_date}`
    }
    if (props.filters.start_date) return `Posted from ${props.filters.start_date}`
    if (props.filters.end_date) return `Posted through ${props.filters.end_date}`
    return 'All posting dates'
})

function openBreakdown(year) {

    breakdownYear.value = year
    breakdownSearch.value = ''
    breakdownMonthFilter.value = ''
    breakdownOpen.value = true
}

function applyFilters() {
    router.get('/reports', {
        report_type: filterReportType.value,
        fiscal_period_id: filterYear.value,
        allocation_month: filterMonth.value,
        start_date: startDate.value,
        end_date: endDate.value,
        department_id: filterDepartment.value,
        category_id: filterCategory.value,
        account_title_id: filterAccountTitle.value,
    }, { preserveState: true, replace: true })
}

function applyMonthFilter() {
    applyFilters()
}

function applyDateFilter() {
    if (startDate.value && endDate.value && endDate.value < startDate.value) {
        [startDate.value, endDate.value] = [endDate.value, startDate.value]
    }
    applyFilters()
}

function clearFilters() {
    filterYear.value = props.fiscalPeriods?.[0]?.id || ''
    filterReportType.value = 'overall_financial'
    filterMonth.value = ''
    startDate.value = ''
    endDate.value = ''
    filterDepartment.value = ''
    filterCategory.value = ''
    filterAccountTitle.value = ''
    applyFilters()
}

const yearEndSummary = computed(() => {
    return asArray(props.yearEndUnusedBalances)
        .sort((a, b) => String(a.allocation_month || '').localeCompare(String(b.allocation_month || '')))
})

const yearEndSummaryTotals = computed(() => {
    const items = asArray(props.yearEndUnusedBalances)
    return {
        balance: items.reduce((sum, item) => sum + Number(item.balance || 0), 0),
        appropriation: items.reduce((sum, item) => sum + Number(item.appropriation || 0), 0),
        expenditure: items.reduce((sum, item) => sum + Number(item.expenditure || 0), 0),
    }
})

const generatedReportUrl = computed(() => {
    const params = new URLSearchParams()
    if (filterReportType.value) params.set('report_type', filterReportType.value)
    if (filterYear.value) params.set('fiscal_period_id', filterYear.value)
    if (filterMonth.value) params.set('allocation_month', filterMonth.value)
    if (startDate.value) params.set('start_date', startDate.value)
    if (endDate.value) params.set('end_date', endDate.value)
    if (filterDepartment.value) params.set('department_id', filterDepartment.value)
    if (filterCategory.value) params.set('category_id', filterCategory.value)
    if (filterAccountTitle.value) params.set('account_title_id', filterAccountTitle.value)

    const query = params.toString()
    return `/reports/generate${query ? `?${query}` : ''}`
})

const exportReportUrl = computed(() => generatedReportUrl.value.replace('/reports/generate', '/reports/export'))

const summary = computed(() => props.summaryCards || {})

const activeReportLabel = computed(() => {
    return asArray(props.reportTypes).find(type => type.value === filterReportType.value)?.label || 'Financial Report'
})
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
                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Report Type</label>
                <select v-model="filterReportType" @change="applyFilters" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white">
                    <option v-for="type in asArray(reportTypes)" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Fiscal Year</label>
                <select v-model="filterYear" @change="filterMonth = ''; applyFilters()" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white">
                    <option v-for="period in asArray(fiscalPeriods)" :key="period.id" :value="period.id">{{ period.label }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Month</label>
                <select v-model="filterMonth" @change="applyMonthFilter" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white">
                    <option value="">All Fiscal Months</option>
                    <option v-for="month in fiscalMonths" :key="month.value" :value="month.value">{{ month.label }}</option>
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
                <input v-model="startDate" type="date" @change="applyDateFilter" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white" />
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600 mb-1">End Date</label>
                <input v-model="endDate" type="date" @change="applyDateFilter" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white" />
            </div>
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t">
            <button @click="clearFilters" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-xs font-semibold">Reset Filters</button>
            <a
                :href="exportReportUrl"
                class="px-3 py-1 bg-white hover:bg-gray-100 text-navy-dark border border-gray-300 rounded text-xs font-semibold"
            >
                Export Current Report
            </a>
            <a
                :href="generatedReportUrl"
                target="_blank"
                rel="noopener"
                class="px-3 py-1 bg-navy-dark hover:bg-navy text-white rounded text-xs font-semibold"
            >
                Generate Report
            </a>
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <div class="border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-navy-dark">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Appropriation</p>
            <p class="mt-1 text-xl font-extrabold text-navy-dark font-sans">{{ PESO }}{{ fmt(summary.totalAppropriation) }}</p>
            <p class="mt-1 text-xs text-gray-500">Approved budget / planned spending</p>
        </div>
        <div class="border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-emerald-500">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Receipts</p>
            <p class="mt-1 text-xl font-extrabold text-emerald-700 font-sans">{{ PESO }}{{ fmt(summary.totalReceipts) }}</p>
            <p class="mt-1 text-xs text-gray-500">Actual cash received</p>
        </div>
        <div class="border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-rose-500">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Posted Disbursements</p>
            <p class="mt-1 text-xl font-extrabold text-rose-700 font-sans">{{ PESO }}{{ fmt(summary.postedDisbursements) }}</p>
            <p class="mt-1 text-xs text-gray-500">Actual cash released</p>
        </div>
        <div class="border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-indigo-500">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Budget Balance</p>
            <p class="mt-1 text-xl font-extrabold text-indigo-700 font-sans">{{ PESO }}{{ fmt(summary.budgetBalance) }}</p>
            <p class="mt-1 text-xs text-gray-500">Appropriation less posted use</p>
        </div>
        <div class="border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-teal-500">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Cash On Hand</p>
            <p class="mt-1 text-xl font-extrabold text-teal-700 font-sans">{{ PESO }}{{ fmt(summary.cashOnHand) }}</p>
            <p class="mt-1 text-xs text-gray-500">Receipts less posted releases</p>
        </div>
        <div class="border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-amber-500">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Pending Commitments</p>
            <p class="mt-1 text-xl font-extrabold text-amber-700 font-sans">{{ PESO }}{{ fmt(summary.pendingCommitments) }}</p>
            <p class="mt-1 text-xs text-gray-500">Requested but not posted</p>
        </div>
    </div>

    <div v-if="asArray(reconciliationWarnings).length" class="mb-6 border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-bold uppercase tracking-wider">Reconciliation Warnings</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li v-for="warning in asArray(reconciliationWarnings)" :key="warning">{{ warning }}</li>
        </ul>
    </div>

    <div class="mb-6 rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">{{ activeReportLabel }}</h3>
            <p class="text-xs text-gray-500 mt-1">Drilldown rows for the selected report type and filters.</p>
        </div>
        <div v-if="filterReportType === 'cash_receipts' || filterReportType === 'income_vs_receipts'" class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-navy-dark text-white border-b-2 border-mustard"><th class="px-4 py-3 text-left">Receipt No.</th><th class="px-4 py-3 text-left">Income No.</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Source / Description</th><th class="px-4 py-3 text-left">Receipt Date</th><th class="px-4 py-3 text-right">Amount</th></tr></thead>
                <tbody>
                    <tr v-for="row in asArray(receiptRows)" :key="row.id" class="border-b"><td class="px-4 py-3 font-semibold">{{ row.receipt_no }}</td><td class="px-4 py-3">{{ row.income_no }}</td><td class="px-4 py-3">{{ row.receipt_type }}</td><td class="px-4 py-3"><b>{{ row.source }}</b><br><span class="text-xs text-gray-500">{{ row.description }}</span></td><td class="px-4 py-3">{{ row.receipt_date }}</td><td class="px-4 py-3 text-right font-sans">{{ PESO }}{{ fmt(row.amount) }}</td></tr>
                    <tr v-if="!asArray(receiptRows).length"><td colspan="6" class="px-4 py-8 text-center text-gray-400">No receipt rows match the selected filters.</td></tr>
                </tbody>
            </table>
        </div>
        <div v-else-if="filterReportType === 'disbursements' || filterReportType === 'fund_balance'" class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-navy-dark text-white border-b-2 border-mustard"><th class="px-4 py-3 text-left">DSB No.</th><th class="px-4 py-3 text-left">Expense Ref</th><th class="px-4 py-3 text-left">Allocation Month</th><th class="px-4 py-3 text-left">Expense Date</th><th class="px-4 py-3 text-left">Disbursement Date</th><th class="px-4 py-3 text-left">Payee</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-right">Amount</th></tr></thead>
                <tbody>
                    <tr v-for="row in asArray(disbursementRows)" :key="row.id" class="border-b"><td class="px-4 py-3 font-semibold">{{ row.disbursement_no }}</td><td class="px-4 py-3">{{ row.expense_ref }}</td><td class="px-4 py-3">{{ row.allocation_month }}</td><td class="px-4 py-3">{{ row.expense_date }}</td><td class="px-4 py-3">{{ row.disbursement_date }}</td><td class="px-4 py-3">{{ row.pay_to }}</td><td class="px-4 py-3 uppercase text-xs font-bold">{{ row.status }}</td><td class="px-4 py-3 text-right font-sans">{{ PESO }}{{ fmt(row.amount) }}</td></tr>
                    <tr v-if="!asArray(disbursementRows).length"><td colspan="8" class="px-4 py-8 text-center text-gray-400">No disbursement rows match the selected filters.</td></tr>
                </tbody>
            </table>
        </div>
        <div v-else-if="filterReportType === 'audit_trail' || filterReportType === 'closing_report'" class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-navy-dark text-white border-b-2 border-mustard"><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">User</th><th class="px-4 py-3 text-left">Role</th><th class="px-4 py-3 text-left">Action</th><th class="px-4 py-3 text-left">Remarks</th></tr></thead>
                <tbody>
                    <tr v-for="row in asArray(auditRows)" :key="row.id" class="border-b"><td class="px-4 py-3">{{ row.created_at }}</td><td class="px-4 py-3">{{ row.user_name }}</td><td class="px-4 py-3">{{ row.user_role }}</td><td class="px-4 py-3 uppercase text-xs font-bold">{{ row.action }}</td><td class="px-4 py-3">{{ row.remarks }}</td></tr>
                    <tr v-if="!asArray(auditRows).length"><td colspan="5" class="px-4 py-8 text-center text-gray-400">No audit rows match the selected filters.</td></tr>
                </tbody>
            </table>
        </div>
        <div v-else class="px-5 py-4 text-sm text-gray-600">
            This report type uses the budget utilization and monthly reconciliation sections below. Click fiscal year totals or use Generate Report for a printable layout.
        </div>
    </div>

    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-gray-50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Appropriation and Expenditure for Selected Month</h3>
                <p class="text-xs text-gray-500 mt-1">{{ activeFiscalPeriod?.label }} - {{ selectedMonthPerformance.month_label }} - {{ selectedDateRangeLabel }}</p>
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

    <!-- Year-End Unused Balance Summary -->
    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-gray-50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Monthly Budget Reconciliation</h3>
                <p class="text-xs text-gray-500 mt-1">Monthly totals dynamically follow the fiscal year, month, date range, responsibility center, and category filters above.</p>
            </div>
            <div class="flex gap-2 text-xs">
                <span class="rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-700">Unused: {{ PESO }}{{ fmt(yearEndSummaryTotals.balance) }}</span>
                <span class="rounded-full bg-slate-50 px-3 py-1 font-semibold text-slate-700">Appropriation: {{ PESO }}{{ fmt(yearEndSummaryTotals.appropriation) }}</span>
                <span class="rounded-full bg-rose-50 px-3 py-1 font-semibold text-rose-700">Expenditure: {{ PESO }}{{ fmt(yearEndSummaryTotals.expenditure) }}</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-navy-dark text-white border-b-2 border-mustard">
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-white">Month</th>
                        <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-white">Appropriation</th>
                        <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-white">Posted Expenditure</th>
                        <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-white">Unused Balance</th>
                        <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-white">Utilization</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in yearEndSummary" :key="item.month" class="border-b hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-3 text-gray-700">{{ item.month_label }}</td>
                        <td class="px-5 py-3 text-right font-medium font-sans tabular-nums">{{ PESO }}{{ fmt(item.appropriation) }}</td>
                        <td class="px-5 py-3 text-right font-medium font-sans tabular-nums">{{ PESO }}{{ fmt(item.expenditure) }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-emerald-700 font-sans tabular-nums">{{ PESO }}{{ fmt(item.balance) }}</td>
                        <td class="px-5 py-3 text-center">
                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">
                                {{ item.utilization_rate }}%
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!yearEndSummary.length">
                        <td colspan="5" class="px-5 py-8 text-center text-gray-400">No budget records match the selected report filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Budget Performance -->
    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-gray-50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Budget Performance by Fiscal Year</h3>
                <p class="text-xs text-gray-500 mt-1">Totals are grouped by complete configured fiscal periods.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
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
                <tr v-for="row in yearlyBudgetPerformance" :key="row.id" class="border-b hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-3 font-bold text-gray-900">
                        <button
                            type="button"
                            @click="openBreakdown(row.id)"
                            class="text-navy hover:text-mustard underline decoration-dotted underline-offset-2"
                        >
                            {{ row.label || `FY ${row.year}` }}
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
    </div>

    <Modal
        :show="breakdownOpen"
        title="Fiscal Year Breakdown"
        :subtitle="breakdownYear ? `Inspect every allocation row in the selected fiscal period.` : ''"
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
                        <option value="">All Fiscal Months</option>
                        <option v-for="month in (asArray(props.budgets).find(budget => Number(budget.id) === Number(breakdownYear))?.fiscal_months || [])" :key="month.value" :value="month.value">{{ month.label }}</option>
                    </select>
                </div>
                <div class="rounded-lg border border-gray-200 bg-slate-50 px-4 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Fiscal Period</p>
                    <p class="text-lg font-extrabold text-navy-dark">{{ asArray(props.budgets).find(budget => Number(budget.id) === Number(breakdownYear))?.fiscal_year_label }}</p>
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
