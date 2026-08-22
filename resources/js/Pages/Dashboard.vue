<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import ComboTrendChart from '@/Components/ComboTrendChart.vue'
import YearlyCombinedChart from '@/Components/YearlyCombinedChart.vue'

const props = defineProps({
    budgets: Array,
    availableYears: Array,
    departments: Array,
    categories: Array,
    accountTitles: Array,
    filters: Object,
    stats: Object,
    categoryStats: Array,
    multiYearComparison: Array,
    monthlyBreakdown: Array,
    recentDisbursements: Array,
})

const viewMode = ref('monthly') // 'monthly' or 'annual'
const hoveredAnnualPoint = ref(null)

// Active Filter States
const selectedYear = ref(props.filters?.year || 2026)
const selectedMonth = ref(props.filters?.month || '')
const startDate = ref(props.filters?.start_date || '')
const endDate = ref(props.filters?.end_date || '')
const selectedDepartment = ref(props.filters?.department_id || '')
const selectedCategory = ref(props.filters?.category_id || '')
const selectedAccountTitle = ref(props.filters?.account_title_id || '')

const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
]

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v || 0) }

function applyFilters() {
    router.get('/', {
        year: selectedYear.value,
        month: selectedMonth.value,
        start_date: startDate.value,
        end_date: endDate.value,
        department_id: selectedDepartment.value,
        category_id: selectedCategory.value,
        account_title_id: selectedAccountTitle.value,
    }, { preserveState: true, replace: true })
}

function resetFilters() {
    selectedYear.value = 2026
    selectedMonth.value = ''
    startDate.value = ''
    endDate.value = ''
    selectedDepartment.value = ''
    selectedCategory.value = ''
    selectedAccountTitle.value = ''
    applyFilters()
}

// Graph calculation for multi-year line comparison SVG
const maxMultiYearValue = computed(() => {
    if (!props.multiYearComparison?.length) return 100000
    const vals = props.multiYearComparison.flatMap(m => [m.appropriation, m.expenditure])
    return Math.max(...vals, 100000)
})

// Monthly SVG chart calculations
const maxMonthlyValue = computed(() => {
    if (!props.monthlyBreakdown?.length) return 10000
    const vals = props.monthlyBreakdown.flatMap(m => [m.appropriation, m.expenditure])
    return Math.max(...vals, 10000)
})

const monthlySvgLines = computed(() => {
    if (!props.monthlyBreakdown?.length) return { apprPath: '', expPath: '', apprCoords: [], expCoords: [] }
    const items = props.monthlyBreakdown
    const count = items.length
    const maxVal = maxMonthlyValue.value || 1
    const width = 1000
    const height = 200

    const apprCoords = items.map((m, i) => {
        const x = (i / (count - 1)) * (width - 80) + 40
        const y = height - (m.appropriation / maxVal) * (height - 40) - 20
        return { x, y: isNaN(y) ? height - 20 : y, val: m.appropriation, m }
    })

    const expCoords = items.map((m, i) => {
        const x = (i / (count - 1)) * (width - 80) + 40
        const y = height - (m.expenditure / maxVal) * (height - 40) - 20
        return { x, y: isNaN(y) ? height - 20 : y, val: m.expenditure, m }
    })

    const apprPath = apprCoords.reduce((acc, p, i) => i === 0 ? `M ${p.x} ${p.y}` : `${acc} L ${p.x} ${p.y}`, '')
    const expPath = expCoords.reduce((acc, p, i) => i === 0 ? `M ${p.x} ${p.y}` : `${acc} L ${p.x} ${p.y}`, '')

    return { apprPath, expPath, apprCoords, expCoords }
})

const multiYearSvgLines = computed(() => {
    if (!props.multiYearComparison?.length) return { apprPath: '', expPath: '', apprCoords: [], expCoords: [] }
    const items = props.multiYearComparison
    const count = items.length
    const maxVal = maxMultiYearValue.value || 1
    const width = 800
    const height = 200

    const apprCoords = items.map((y, i) => {
        const x = count === 1 ? width / 2 : (i / (count - 1)) * (width - 100) + 50
        const yVal = height - (y.appropriation / maxVal) * (height - 40) - 20
        return { x, y: isNaN(yVal) ? height - 20 : yVal, val: y.appropriation, yData: y }
    })

    const expCoords = items.map((y, i) => {
        const x = count === 1 ? width / 2 : (i / (count - 1)) * (width - 100) + 50
        const yVal = height - (y.expenditure / maxVal) * (height - 40) - 20
        return { x, y: isNaN(yVal) ? height - 20 : yVal, val: y.expenditure, yData: y }
    })

    const apprPath = apprCoords.reduce((acc, p, i) => i === 0 ? `M ${p.x} ${p.y}` : `${acc} L ${p.x} ${p.y}`, '')
    const expPath = expCoords.reduce((acc, p, i) => i === 0 ? `M ${p.x} ${p.y}` : `${acc} L ${p.x} ${p.y}`, '')

    const baselineY = height - 20
    const apprAreaPath = `${apprPath} L ${apprCoords[apprCoords.length - 1].x} ${baselineY} L ${apprCoords[0].x} ${baselineY} Z`
    const expAreaPath = `${expPath} L ${expCoords[expCoords.length - 1].x} ${baselineY} L ${expCoords[0].x} ${baselineY} Z`

    return { apprPath, expPath, apprAreaPath, expAreaPath, apprCoords, expCoords }
})

const monthlyCombinedItems = computed(() => {
    return (props.monthlyBreakdown || []).map(m => ({
        ...m,
        label: m.month,
        tooltipTitle: `${m.month} ${selectedYear.value}`,
        appropriation: Number(m.appropriation || 0),
        expenditure: Number(m.expenditure || 0),
        expense: Number(m.expenditure || 0),
        balance: Number(m.balance || 0),
        utilization: Number(m.utilization || 0),
    }))
})

const annualYAxisTicks = computed(() => {
    const maxVal = maxMultiYearValue.value || 1
    const steps = 4
    const ticks = []
    for (let i = 0; i <= steps; i++) {
        const value = (maxVal / steps) * (steps - i)
        ticks.push({
            value,
            y: 42 + ((140 / steps) * i),
        })
    }
    return ticks
})

const annualHoverZones = computed(() => {
    const items = props.multiYearComparison || []
    const { apprCoords, expCoords } = multiYearSvgLines.value
    if (!items.length) return []

    const width = 800
    const height = 220
    const chartPadding = 56
    const usableWidth = width - chartPadding * 2
    const zoneWidth = items.length > 1 ? usableWidth / items.length : usableWidth

    return items.map((y, idx) => {
        const anchor = expCoords[idx] || apprCoords[idx] || { x: width / 2, y: 80 }
        return {
            year: y.year,
            left: items.length === 1 ? chartPadding : (idx * zoneWidth) + chartPadding,
            width: zoneWidth,
            anchorX: anchor.x,
            anchorY: anchor.y,
        }
    })
})

function hoverAnnualPoint(idx) {
    hoveredAnnualPoint.value = idx
}

function clearAnnualHover() {
    hoveredAnnualPoint.value = null
}

const statusBadgeStyles = {
    draft: 'bg-slate-100 text-slate-700',
    for_approval: 'bg-amber-100 text-amber-800',
    approved: 'bg-blue-100 text-blue-800',
    posted: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
}

const barColors = ['#1e293b', '#d4a843', '#2563eb', '#059669', '#7c3aed', '#db2777']
</script>

<template>
<Head title="Financial Dashboard" />
<AppLayout>
    <!-- Page Header & Title -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <img src="/images/logo.png" alt="Logo" class="h-10 w-10 object-contain" />
            <div>
                <h2 class="text-xl font-bold text-gray-900">Executive Dashboard & Analytics</h2>
                <p class="text-sm text-gray-500">Real-time Budget Monitoring & Posted Expenditure Analytics</p>
            </div>
        </div>
        <div class="inline-flex rounded-lg bg-gray-200 p-1 shadow-inner text-xs font-bold">
            <button @click="viewMode = 'monthly'" :class="viewMode === 'monthly' ? 'bg-white text-navy-dark shadow' : 'text-gray-600 hover:text-gray-900'" class="px-3 py-1.5 rounded-md transition">
                Monthly Analytics
            </button>
            <button @click="viewMode = 'annual'" :class="viewMode === 'annual' ? 'bg-white text-navy-dark shadow' : 'text-gray-600 hover:text-gray-900'" class="px-3 py-1.5 rounded-md transition">
                Multi-Year Trends
            </button>
        </div>
    </div>

    <!-- Filters Bar (Auto-refresh on change) -->
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm mb-6 space-y-3">
        <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-gray-500 border-b pb-2">
            <span>Filter Dashboard Data</span>
            <button @click="resetFilters" class="text-indigo-600 hover:text-indigo-800 font-semibold normal-case">Reset All Filters</button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
            <div>
                <label class="block text-[11px] font-bold text-gray-700 mb-1">Fiscal Year</label>
                <select v-model="selectedYear" @change="applyFilters" class="w-full rounded-lg border-gray-300 text-xs py-2 bg-white focus:ring-navy focus:border-navy">
                    <option v-for="yr in (availableYears || [2026, 2025, 2024])" :key="yr" :value="yr">FY {{ yr }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-700 mb-1">Budget Month</label>
                <select v-model="selectedMonth" @change="applyFilters" class="w-full rounded-lg border-gray-300 text-xs py-2 bg-white focus:ring-navy focus:border-navy">
                    <option value="">All Months (Jan-Dec)</option>
                    <option v-for="(mName, idx) in monthNames" :key="idx+1" :value="idx+1">{{ mName }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-700 mb-1">Responsibility Center</label>
                <select v-model="selectedDepartment" @change="applyFilters" class="w-full rounded-lg border-gray-300 text-xs py-2 bg-white focus:ring-navy focus:border-navy">
                    <option value="">All Responsibility Centers</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }} ({{ d.code }})</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-700 mb-1">Budget Category</label>
                <select v-model="selectedCategory" @change="applyFilters" class="w-full rounded-lg border-gray-300 text-xs py-2 bg-white focus:ring-navy focus:border-navy">
                    <option value="">All Categories</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-700 mb-1">Account Title</label>
                <select v-model="selectedAccountTitle" @change="applyFilters" class="w-full rounded-lg border-gray-300 text-xs py-2 bg-white focus:ring-navy focus:border-navy">
                    <option value="">All Account Titles</option>
                    <option v-for="a in accountTitles" :key="a.id" :value="a.id">{{ a.particular }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-700 mb-1">Custom Date Range</label>
                <div class="flex gap-1">
                    <input v-model="startDate" type="date" @change="applyFilters" class="w-1/2 rounded-lg border-gray-300 text-[10px] py-1.5 px-1 bg-white" placeholder="Start" />
                    <input v-model="endDate" type="date" @change="applyFilters" class="w-1/2 rounded-lg border-gray-300 text-[10px] py-1.5 px-1 bg-white" placeholder="End" />
                </div>
            </div>
        </div>
    </div>

    <!-- 6 Core KPI Summary Cards -->
    <div
        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6 mb-6"
        data-onboarding-target="dashboard-summary"
        data-onboarding-click="dashboard-summary"
    >
        <div class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-visible">
            <div class="h-1 bg-navy-dark"></div>
            <div class="p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Annual Budget</p>
                <p class="text-lg font-extrabold text-navy-dark mt-0.5">₱{{ fmt(stats?.annualBudget) }}</p>
                <p class="text-[10px] text-gray-400 mt-1">FY {{ selectedYear }} Allocated</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden">
            <div class="h-1 bg-indigo-500"></div>
            <div class="p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Filtered Budget</p>
                <p class="text-lg font-extrabold text-indigo-900 mt-0.5">₱{{ fmt(stats?.totalAppropriation) }}</p>
                <p class="text-[10px] text-indigo-600 mt-1">{{ selectedMonth ? monthNames[selectedMonth - 1] : 'Jan - Dec Total' }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden">
            <div class="h-1 bg-rose-500"></div>
            <div class="p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Expenditures</p>
                <p class="text-lg font-extrabold text-rose-700 mt-0.5">₱{{ fmt(stats?.totalExpenditure) }}</p>
                <p class="text-[10px] text-rose-500 mt-1">Posted Transactions</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden">
            <div class="h-1 bg-emerald-500"></div>
            <div class="p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Remaining Balance</p>
                <p class="text-lg font-extrabold text-emerald-700 mt-0.5">₱{{ fmt(stats?.balance) }}</p>
                <p class="text-[10px] text-emerald-600 mt-1">Available Funds</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden">
            <div class="h-1 bg-mustard"></div>
            <div class="p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Utilization Rate</p>
                <p class="text-lg font-extrabold text-amber-800 mt-0.5">{{ stats?.utilizationRate || 0 }}%</p>
                <div class="w-full h-1.5 bg-gray-100 rounded-full mt-1.5 overflow-hidden">
                    <div class="h-full bg-mustard transition-all" :style="{ width: Math.min(100, stats?.utilizationRate || 0) + '%' }"></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden">
            <div class="h-1 bg-slate-700"></div>
            <div class="p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Posted Transactions</p>
                <p class="text-lg font-extrabold text-slate-800 mt-0.5">{{ stats?.totalTransactions || 0 }}</p>
                <p class="text-[10px] text-slate-500 mt-1">Pending: {{ stats?.pendingDisbursements || 0 }}</p>
            </div>
        </div>
    </div>

    <!-- DYNAMIC DASHBOARD GRAPH SECTION -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-gray-100 mb-4">
            <div>
                <h3 class="text-base font-bold text-navy-dark">
                    {{ viewMode === 'monthly' ? `Monthly Performance Breakdown (Jan – Dec ${selectedYear})` : 'Multi-Year Comparative Performance Trends' }}
                </h3>
                <p class="text-xs text-gray-500">Comparing budget allocations vs actual posted expenditures</p>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold text-gray-600 mt-2 sm:mt-0">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-navy-dark"></span> Budget Appropriation</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background-color: #0f766e"></span> Expenditures</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background-color: #d4a843"></span> Utilization Rate</span>
            </div>
        </div>

        <!-- Monthly Line & Bar Graph Visualizer -->
        <ComboTrendChart
            v-if="viewMode === 'monthly'"
            :items="monthlyCombinedItems"
            label-key="label"
            :series="[
                { key: 'appropriation', label: 'Budget Appropriation', type: 'bar', color: '#1e293b', fadeColor: 'rgba(30, 41, 59, 0.62)', barPercentage: 0.86, categoryPercentage: 0.86, maxBarThickness: 62, yAxisID: 'y' },
                { key: 'expenditure', label: 'Expenditures', type: 'bar', color: '#0f766e', fadeColor: 'rgba(15, 118, 110, 0.62)', barPercentage: 0.86, categoryPercentage: 0.86, maxBarThickness: 62, yAxisID: 'y' },
                { key: 'utilization', label: 'Utilization Rate', type: 'line', color: '#d4a843', fadeColor: 'rgba(212, 168, 67, 0.18)', yAxisID: 'y2' },
            ]"
            :show-header="false"
            :show-legend="false"
            :is-active="viewMode === 'monthly'"
        />

        <!-- Multi-Year Comparative Trends -->
        <YearlyCombinedChart
            v-else
            :items="multiYearComparison || []"
            :show-header="false"
            :show-legend="false"
            :is-active="viewMode === 'annual'"
            :series="[
                { key: 'appropriation', label: 'Budget Appropriation', type: 'bar', color: '#1e293b', fadeColor: 'rgba(30, 41, 59, 0.62)', barPercentage: 0.84, categoryPercentage: 0.84, maxBarThickness: 64, yAxisID: 'y' },
                { key: 'expenditure', label: 'Expenditures', type: 'bar', color: '#0f766e', fadeColor: 'rgba(15, 118, 110, 0.62)', barPercentage: 0.84, categoryPercentage: 0.84, maxBarThickness: 64, yAxisID: 'y' },
                { key: 'utilization', label: 'Utilization Rate', type: 'line', color: '#d4a843', fadeColor: 'rgba(212, 168, 67, 0.18)', yAxisID: 'y2' },
            ]"
        />
    </div>

    <!-- Bottom Section: Category Breakdown + Recent Posted Transactions -->
    <div class="grid gap-6 lg:grid-cols-2 mb-6">
        <!-- Category Utilization Breakdown -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="bg-navy-dark px-5 py-3 flex justify-between items-center">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Account Category Performance</h3>
                <span class="text-xs text-mustard font-semibold">{{ categoryStats?.length || 0 }} Categories</span>
            </div>
            <div class="p-5 space-y-4">
                <div v-for="(cat, idx) in (categoryStats || [])" :key="cat.name" class="space-y-1.5">
                    <div class="flex justify-between items-center text-xs">
                        <span class="font-bold text-gray-800">{{ cat.name }}</span>
                        <span class="font-mono text-gray-600">₱{{ fmt(cat.expenditure) }} / ₱{{ fmt(cat.appropriation) }} ({{ cat.utilization }}%)</span>
                    </div>
                    <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full transition-all rounded-full"
                            :style="{ width: Math.min(100, cat.utilization) + '%', backgroundColor: barColors[idx % barColors.length] }"></div>
                    </div>
                </div>
                <div v-if="!categoryStats?.length" class="text-center text-gray-400 py-6 text-sm">
                    No budget allocations found for the selected filter parameters.
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
            <div class="bg-navy-dark px-5 py-3 flex justify-between items-center">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Recent Transactions</h3>
                <span class="text-xs text-mustard font-semibold">Financial Activity</span>
            </div>
            <div class="divide-y divide-gray-100 flex-1 overflow-y-auto">
                <div v-for="d in (recentDisbursements || [])" :key="d.id" class="p-4 flex items-center justify-between hover:bg-gray-50/60 transition">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold text-navy">{{ d.disbursement_no }}</span>
                            <span :class="[statusBadgeStyles[d.status] || 'bg-gray-100 text-gray-700', 'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase']">{{ d.status }}</span>
                        </div>
                        <p class="text-xs font-semibold text-gray-900 mt-1">{{ d.pay_to }}</p>
                        <p class="text-[11px] text-gray-500">{{ d.description }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-extrabold text-gray-900">₱{{ fmt(d.amount) }}</p>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ d.date_encoded?.slice(0, 10) }}</p>
                    </div>
                </div>
                <div v-if="!recentDisbursements?.length" class="p-8 text-center text-gray-400 text-sm">
                    No recent transaction records available.
                </div>
            </div>
        </div>
    </div>
</AppLayout>
</template>

<style scoped>
.dashboard-line {
    stroke-dashoffset: 0;
    animation: dashboard-line-draw 1.1s ease-out both;
}

.dashboard-line--exp {
    animation-delay: 0.08s;
}

.annual-line {
    stroke-dasharray: 1;
    stroke-dashoffset: 1;
    animation: annual-line-draw 1.6s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.annual-line--exp {
    animation-delay: 0.16s;
}

.annual-point {
    transform-box: fill-box;
    transform-origin: center;
    animation: annual-point-pop 0.55s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.annual-point--exp {
    animation-delay: 0.12s;
}

@keyframes dashboard-line-draw {
    from {
        opacity: 0;
        stroke-dasharray: 8 18;
        stroke-dashoffset: 120;
    }
    to {
        opacity: 1;
        stroke-dasharray: 8 18;
        stroke-dashoffset: 0;
    }
}

@keyframes annual-line-draw {
    from {
        opacity: 0;
        stroke-dashoffset: 1;
    }
    to {
        opacity: 1;
        stroke-dashoffset: 0;
    }
}

@keyframes annual-point-pop {
    from {
        opacity: 0;
        transform: scale(0.4);
    }
    70% {
        opacity: 1;
        transform: scale(1.15);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.writing-vertical {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
}
</style>
