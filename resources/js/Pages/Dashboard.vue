<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

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

    return { apprPath, expPath, apprCoords, expCoords }
})

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
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6 mb-6">
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
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-navy-dark"></span> Budget Allocation</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-mustard"></span> Actual Expenditures</span>
            </div>
        </div>

        <!-- Monthly Line & Bar Graph Visualizer -->
        <div v-if="viewMode === 'monthly'" class="space-y-4">
            <div class="relative h-72 pt-6 pb-2 px-2 border-b border-gray-200 bg-slate-50/50 rounded-lg overflow-visible">
                <!-- SVG Trend Lines Overlay -->
                <svg class="absolute inset-0 w-full h-full pointer-events-none z-10" viewBox="0 0 1000 200" preserveAspectRatio="none">
                    <path class="dashboard-line dashboard-line--appr" :d="monthlySvgLines.apprPath" fill="none" stroke="#1e293b" stroke-width="2" stroke-dasharray="4 2" opacity="0.6" />
                    <path class="dashboard-line dashboard-line--exp" :d="monthlySvgLines.expPath" fill="none" stroke="#d4a843" stroke-width="3" />
                    <circle v-for="(p, idx) in monthlySvgLines.expCoords" :key="'e-'+idx" :cx="p.x" :cy="p.y" r="4" fill="#d4a843" stroke="#ffffff" stroke-width="2" />
                    <circle v-for="(p, idx) in monthlySvgLines.apprCoords" :key="'a-'+idx" :cx="p.x" :cy="p.y" r="3.5" fill="#1e293b" stroke="#ffffff" stroke-width="1.5" />
                </svg>

                <div class="h-full flex items-end justify-between gap-2 relative z-20">
                    <div v-for="m in (monthlyBreakdown || [])" :key="m.month_num" class="flex-1 flex flex-col items-center h-full justify-end group relative">
                        <!-- Tooltip -->
                        <div class="absolute bottom-full left-1/2 z-30 mb-3 w-60 -translate-x-1/2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-[11px] text-slate-700 shadow-2xl opacity-0 transition-all duration-200 group-hover:opacity-100 group-hover:-translate-y-1 pointer-events-none">
                            <div class="font-bold text-navy-dark border-b border-slate-200 pb-1 mb-1">{{ m.month }} {{ selectedYear }}</div>
                            <div>Allocation: ₱{{ fmt(m.appropriation) }}</div>
                            <div>Expenditures: ₱{{ fmt(m.expenditure) }}</div>
                            <div>Balance: ₱{{ fmt(m.balance) }}</div>
                            <div class="font-semibold text-emerald-600">Utilization: {{ m.utilization }}%</div>
                        </div>

                        <!-- Bars Container -->
                        <div class="w-full flex items-end justify-center gap-1 h-full px-1">
                            <!-- Allocation Bar -->
                            <div class="w-1/2 bg-navy-dark/80 group-hover:bg-navy transition-all duration-700 ease-out rounded-t-xs origin-bottom"
                                :style="{ height: (m.appropriation > 0 ? Math.max(4, (m.appropriation / maxMonthlyValue) * 100) : 0) + '%' }"></div>
                            <!-- Expense Bar -->
                            <div class="w-1/2 bg-mustard group-hover:bg-amber-400 transition-all duration-700 ease-out rounded-t-xs origin-bottom"
                                :style="{ height: (m.expenditure > 0 ? Math.max(4, (m.expenditure / maxMonthlyValue) * 100) : 0) + '%' }"></div>
                        </div>

                        <span class="text-[11px] font-bold text-slate-600 mt-2">{{ m.month }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Multi-Year Comparative Trends -->
        <div v-else class="space-y-4">
            <div class="relative h-72 pt-6 pb-2 px-4 border-b border-gray-200 bg-slate-50/50 rounded-lg overflow-visible">
                <!-- SVG Trend Lines Overlay for Multi-Year -->
                <svg class="absolute inset-0 w-full h-full pointer-events-none z-10" viewBox="0 0 800 200" preserveAspectRatio="none">
                    <path class="dashboard-line dashboard-line--appr" :d="multiYearSvgLines.apprPath" fill="none" stroke="#1e293b" stroke-width="2" stroke-dasharray="4 2" opacity="0.6" />
                    <path class="dashboard-line dashboard-line--exp" :d="multiYearSvgLines.expPath" fill="none" stroke="#d4a843" stroke-width="3" />
                    <circle v-for="(p, idx) in multiYearSvgLines.expCoords" :key="'mye-'+idx" :cx="p.x" :cy="p.y" r="5" fill="#d4a843" stroke="#ffffff" stroke-width="2" />
                    <circle v-for="(p, idx) in multiYearSvgLines.apprCoords" :key="'mya-'+idx" :cx="p.x" :cy="p.y" r="4" fill="#1e293b" stroke="#ffffff" stroke-width="1.5" />
                </svg>

                <div class="h-full flex items-end justify-around gap-6 relative z-20">
                    <div v-for="y in (multiYearComparison || [])" :key="y.year" class="flex-1 max-w-[120px] flex flex-col items-center h-full justify-end group relative">
                        <!-- Tooltip -->
                        <div class="absolute bottom-full left-1/2 z-30 mb-3 w-60 -translate-x-1/2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-[11px] text-slate-700 shadow-2xl opacity-0 transition-all duration-200 group-hover:opacity-100 group-hover:-translate-y-1 pointer-events-none">
                            <div class="font-bold text-navy-dark border-b border-slate-200 pb-1 mb-1">FY {{ y.year }} Performance</div>
                            <div>Allocation: ₱{{ fmt(y.appropriation) }}</div>
                            <div>Expenditures: ₱{{ fmt(y.expenditure) }}</div>
                            <div>Remaining: ₱{{ fmt(y.balance) }}</div>
                            <div class="font-semibold text-emerald-600">Util Rate: {{ y.utilization }}%</div>
                        </div>

                        <!-- Bars Container -->
                        <div class="w-full flex items-end justify-center gap-2 h-full px-2">
                            <div class="w-1/2 bg-navy-dark/80 rounded-t-sm transition-all duration-700 ease-out origin-bottom"
                                :style="{ height: (y.appropriation > 0 ? Math.max(6, (y.appropriation / maxMultiYearValue) * 100) : 0) + '%' }"></div>
                            <div class="w-1/2 bg-mustard rounded-t-sm transition-all duration-700 ease-out origin-bottom"
                                :style="{ height: (y.expenditure > 0 ? Math.max(6, (y.expenditure / maxMultiYearValue) * 100) : 0) + '%' }"></div>
                        </div>

                        <span class="text-xs font-bold text-navy-dark mt-2">FY {{ y.year }}</span>
                    </div>
                </div>
            </div>
        </div>
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
</style>
