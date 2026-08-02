<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import ComboTrendChart from '@/Components/ComboTrendChart.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    availableYears: Array,
    filters: Object,
    stats: Object,
    monthlyIncome: Array,
    monthlyRevenue: Array,
    monthlyExpense: Array,
    multiYearComparison: Array,
    budgetItems: Array,
    incomeRecords: Array,
})

const selectedYear = ref(props.filters?.year || new Date().getFullYear())
const selectedMonth = ref(props.filters?.month || '')
const startDate = ref(props.filters?.start_date || '')
const endDate = ref(props.filters?.end_date || '')
const selectedPoint = ref(0)
const selectedAnnualPoint = ref(0)
const hoveredAnnualPoint = ref(null)
const viewMode = ref('monthly')

const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const chartWidth = 1000
const chartHeight = 240
const chartPaddingX = 48
const chartPaddingY = 24
const chartInnerWidth = chartWidth - (chartPaddingX * 2)
const chartInnerHeight = chartHeight - (chartPaddingY * 2)

const maxIncome = computed(() => Math.max(...(props.monthlyIncome || []).map((item) => Number(item.amount || 0)), 1))
const maxAppropriation = computed(() => Math.max(...(props.monthlyRevenue || []).map((item) => Number(item.amount || 0)), 1))
const maxExpense = computed(() => Math.max(...(props.monthlyExpense || []).map((item) => Number(item.amount || 0)), 1))
const maxMultiYear = computed(() => Math.max(...(props.multiYearComparison || []).flatMap((item) => [
    Number(item.income || 0),
    Number(item.appropriation || 0),
    Number(item.expense || 0),
]), 1))

const activeMonthlyPoint = computed(() => {
    if (!props.monthlyRevenue?.length) return 0

    const monthNum = Number(selectedMonth.value)
    if (monthNum >= 1 && monthNum <= 12) {
        const idx = props.monthlyRevenue.findIndex((item) => Number(item.month_num) === monthNum)
        if (idx >= 0) return idx
    }

    return Math.min(selectedPoint.value || 0, props.monthlyRevenue.length - 1)
})

const monthlyComboItems = computed(() => {
    return (props.monthlyRevenue || []).map((item, idx) => ({
        ...item,
        label: item.month,
        tooltipTitle: `${item.month} ${selectedYear.value}`,
        income: monthlyIncomeAmount(item.month_num),
        appropriation: Number(item.amount || 0),
        expense: monthlyExpenseAmount(idx),
        balance: Number(item.amount || 0) - Number(monthlyExpenseAmount(idx) || 0),
        utilization: item.amount ? (Number(monthlyExpenseAmount(idx) || 0) / Number(item.amount || 1)) * 100 : 0,
    }))
})

const annualComboItems = computed(() => {
    return (props.multiYearComparison || []).map((item) => ({
        ...item,
        label: String(item.year),
        tooltipTitle: `FY ${item.year}`,
        income: Number(item.income || 0),
        appropriation: Number(item.appropriation || 0),
        expense: Number(item.expense || 0),
        balance: Number(item.remainingAppropriation || 0),
        utilization: item.appropriation ? (Number(item.expense || 0) / Number(item.appropriation || 1)) * 100 : 0,
    }))
})

function fmt(value) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(Number(value || 0))
}

function applyFilters() {
    const monthNum = Number(selectedMonth.value)
    if (monthNum >= 1 && monthNum <= 12) {
        const idx = props.monthlyRevenue?.findIndex((item) => Number(item.month_num) === monthNum)
        if (idx >= 0) selectedPoint.value = idx
    }
    router.get('/iaeo', {
        year: selectedYear.value,
        month: selectedMonth.value,
        start_date: startDate.value,
        end_date: endDate.value,
    }, {
        preserveState: true,
        replace: true,
    })
}

function syncSelectedPointToMonth() {
    if (!props.monthlyRevenue?.length) {
        selectedPoint.value = 0
        return
    }

    const monthNum = Number(selectedMonth.value)
    if (monthNum >= 1 && monthNum <= 12) {
        const idx = props.monthlyRevenue.findIndex((item) => Number(item.month_num) === monthNum)
        selectedPoint.value = idx >= 0 ? idx : 0
        return
    }

    if (selectedPoint.value >= props.monthlyRevenue.length) {
        selectedPoint.value = 0
    }
}

function selectPoint(index) {
    selectedPoint.value = index
}

function selectAnnualPoint(index) {
    selectedAnnualPoint.value = index
}

function hoverAnnualPoint(index) {
    hoveredAnnualPoint.value = index
}

function clearAnnualHover() {
    hoveredAnnualPoint.value = null
}

function monthlyIncomeAmount(monthNum) {
    return (props.monthlyIncome || []).find((item) => Number(item.month_num) === Number(monthNum))?.amount || 0
}

function monthlyExpenseAmount(index) {
    return props.monthlyExpense?.[index]?.amount || 0
}

function pct(value, maxValue) {
    return Math.max(4, (Number(value || 0) / Math.max(maxValue, 1)) * 100)
}

function maxOfSeries(series) {
    return Math.max(...series.map((item) => Number(item.amount || 0)), 1)
}

function lineX(index, total) {
    const stepX = total > 1 ? chartInnerWidth / (total - 1) : chartInnerWidth
    return chartPaddingX + (index * stepX)
}

function lineY(value, maxValue) {
    return chartPaddingY + chartInnerHeight - ((Number(value || 0) / maxValue) * chartInnerHeight)
}

function linePoints(series) {
    const values = series.map((item) => Number(item.amount || 0))
    const maxValue = maxOfSeries(series)
    const stepX = values.length > 1 ? chartInnerWidth / (values.length - 1) : chartInnerWidth
    return values.map((value, index) => {
        const x = chartPaddingX + (index * stepX)
        const y = chartPaddingY + chartInnerHeight - ((value / maxValue) * chartInnerHeight)
        return `${x},${y}`
    }).join(' ')
}

function lineArea(series) {
    const values = series.map((item) => Number(item.amount || 0))
    const maxValue = maxOfSeries(series)
    const stepX = values.length > 1 ? chartInnerWidth / (values.length - 1) : chartInnerWidth
    const bottomY = chartPaddingY + chartInnerHeight

    const points = values.map((value, index) => {
        const x = chartPaddingX + (index * stepX)
        const y = chartPaddingY + chartInnerHeight - ((value / maxValue) * chartInnerHeight)
        return `${x} ${y}`
    })

    return `M ${chartPaddingX} ${bottomY} L ${points.join(' L ')} L ${chartPaddingX + chartInnerWidth} ${bottomY} Z`
}

function annualLinePoints(key) {
    const series = props.multiYearComparison || []
    const values = series.map((item) => Number(item[key] || 0))
    const maxValue = Math.max(...values, 1)
    const stepX = values.length > 1 ? chartInnerWidth / (values.length - 1) : chartInnerWidth
    return values.map((value, index) => {
        const x = chartPaddingX + (index * stepX)
        const y = chartPaddingY + chartInnerHeight - ((value / maxValue) * chartInnerHeight)
        return `${x},${y}`
    }).join(' ')
}

function annualTooltipStyle(index) {
    const total = props.multiYearComparison?.length || 1
    const x = lineX(index, total)
    const left = total > 1 && index === 0 ? x - 24 : total > 1 && index === total - 1 ? x - 180 : x - 90
    return {
        left: `${Math.max(12, Math.min(left, chartWidth - 360))}px`,
        top: '38px',
    }
}

watch(
    () => [selectedMonth.value, props.monthlyRevenue],
    () => {
        syncSelectedPointToMonth()
    },
    { immediate: true, deep: true }
)
</script>

<template>
<Head title="IAEO" />
<AppLayout>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Income, Appropriation, and Expense Overview</h2>
            <p class="text-sm text-gray-500">IAEO shows how income funds the budget, how appropriation is allocated, and how expenses reduce what remains.</p>
        </div>
    </div>

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="space-y-1">
                <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-700">Fiscal Year</label>
                <select v-model="selectedYear" @change="applyFilters" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm">
                    <option v-for="yr in availableYears" :key="yr" :value="yr">FY {{ yr }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-700">Month</label>
                <select v-model="selectedMonth" @change="applyFilters" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm">
                    <option value="">All Months</option>
                    <option v-for="(m, idx) in monthLabels" :key="idx" :value="idx + 1">{{ m }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-700">Start Date</label>
                <input v-model="startDate" @change="applyFilters" type="date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm" />
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-700">End Date</label>
                <input v-model="endDate" @change="applyFilters" type="date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm" />
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-700">Budget Items</label>
                <input :value="budgetItems?.length || 0" disabled class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700 shadow-sm" />
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-700">Income Records</label>
                <input :value="incomeRecords?.length || 0" disabled class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700 shadow-sm" />
            </div>
        </div>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-6">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"><div class="h-1 bg-slate-500"></div><div class="p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Income</p><p class="mt-1 text-xl font-extrabold text-slate-800">{{ fmt(stats?.totalIncome) }}</p></div></div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"><div class="h-1 bg-indigo-500"></div><div class="p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Appropriation</p><p class="mt-1 text-xl font-extrabold text-indigo-700">{{ fmt(stats?.totalRevenue) }}</p><p class="mt-1 text-xs text-gray-500">Deducted from income for the budget source.</p></div></div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"><div class="h-1 bg-rose-500"></div><div class="p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Expense</p><p class="mt-1 text-xl font-extrabold text-rose-700">{{ fmt(stats?.totalExpense) }}</p><p class="mt-1 text-xs text-gray-500">Posted expenses only.</p></div></div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"><div class="h-1 bg-emerald-500"></div><div class="p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Remaining Appropriation</p><p class="mt-1 text-xl font-extrabold text-emerald-700">{{ fmt(stats?.balance) }}</p><p class="mt-1 text-xs text-gray-500">Appropriation less expenses.</p></div></div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"><div class="h-1 bg-amber-500"></div><div class="p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Remaining Income After Appropriation</p><p class="mt-1 text-xl font-extrabold text-amber-700">{{ fmt(stats?.remainingIncome) }}</p><p class="mt-1 text-xs text-gray-500">Income less appropriation.</p></div></div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"><div class="h-1 bg-orange-500"></div><div class="p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Remaining Income After Expense</p><p class="mt-1 text-xl font-extrabold text-orange-700">{{ fmt(stats?.remainingIncomeAfterExpense) }}</p><p class="mt-1 text-xs text-gray-500">Income less expenses.</p></div></div>
    </div>

    <div class="mb-6 overflow-visible rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-navy-dark">IAEO Analytics</h3>
                <p class="text-xs text-gray-500">Switch between monthly analytics and multi-year trends.</p>
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

        <ComboTrendChart
            v-if="viewMode === 'monthly'"
            :items="monthlyComboItems"
            label-key="label"
            :series="[
                { key: 'income', label: 'Income', type: 'bar', color: '#1e293b', fadeColor: 'rgba(30, 41, 59, 0.62)', barPercentage: 0.72, categoryPercentage: 0.72, maxBarThickness: 54, yAxisID: 'y' },
                { key: 'appropriation', label: 'Appropriation', type: 'bar', color: '#0f766e', fadeColor: 'rgba(15, 118, 110, 0.62)', barPercentage: 0.72, categoryPercentage: 0.72, maxBarThickness: 54, yAxisID: 'y' },
                { key: 'expense', label: 'Expense', type: 'bar', color: '#64748b', fadeColor: 'rgba(100, 116, 139, 0.56)', barPercentage: 0.72, categoryPercentage: 0.72, maxBarThickness: 54, yAxisID: 'y' },
                { key: 'utilization', label: 'Utilization Rate', type: 'line', color: '#d4a843', fadeColor: 'rgba(212, 168, 67, 0.18)', yAxisID: 'y2' },
            ]"
            :show-header="false"
            :show-legend="false"
            :is-active="viewMode === 'monthly'"
        />

        <div v-else class="rounded-lg bg-slate-50/60 p-4">
            <ComboTrendChart
                :items="annualComboItems"
                label-key="label"
                :series="[
                    { key: 'income', label: 'Income', type: 'bar', color: '#1e293b', fadeColor: 'rgba(30, 41, 59, 0.62)', barPercentage: 0.84, categoryPercentage: 0.84, maxBarThickness: 58, yAxisID: 'y' },
                    { key: 'appropriation', label: 'Appropriation', type: 'bar', color: '#0f766e', fadeColor: 'rgba(15, 118, 110, 0.62)', barPercentage: 0.84, categoryPercentage: 0.84, maxBarThickness: 58, yAxisID: 'y' },
                    { key: 'expense', label: 'Expense', type: 'bar', color: '#64748b', fadeColor: 'rgba(100, 116, 139, 0.56)', barPercentage: 0.84, categoryPercentage: 0.84, maxBarThickness: 58, yAxisID: 'y' },
                    { key: 'utilization', label: 'Utilization Rate', type: 'line', color: '#d4a843', fadeColor: 'rgba(212, 168, 67, 0.18)', yAxisID: 'y2' },
                ]"
                :show-header="false"
                :show-legend="false"
                :is-active="viewMode === 'annual'"
            />
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="bg-navy-dark px-5 py-3"><h3 class="text-sm font-bold uppercase tracking-wider text-white">Income Records</h3></div>
            <div class="divide-y">
                <div v-for="item in incomeRecords" :key="item.id" class="flex items-center justify-between gap-4 px-5 py-4">
                    <div>
                        <p class="text-sm font-bold text-gray-900">{{ item.income_no }}</p>
                        <p class="text-xs text-gray-500">{{ item.source }} - {{ item.description }}</p>
                        <p class="mt-1 text-[11px] text-gray-400">{{ item.date_encoded?.slice?.(0, 10) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-gray-900">{{ fmt(item.amount) }}</p>
                        <p class="text-xs text-gray-500">{{ item.notes || 'No notes' }}</p>
                    </div>
                </div>
                <div v-if="!incomeRecords?.length" class="px-5 py-8 text-center text-gray-400">No income records found.</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="bg-navy-dark px-5 py-3"><h3 class="text-sm font-bold uppercase tracking-wider text-white">Appropriation Summary</h3></div>
            <div class="divide-y">
                <div v-for="item in budgetItems" :key="item.id" class="px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-gray-900">{{ item.category?.name || 'Uncategorized' }}</p>
                            <p class="text-xs text-gray-500">{{ item.particular?.name || 'No particular' }} <span v-if="item.particular?.department">- {{ item.particular.department.name }}</span></p>
                            <p class="mt-1 text-[11px] text-gray-400">Month {{ item.month }} · {{ item.budget?.year || selectedYear }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-gray-900">{{ fmt(item.appropriation) }}</p>
                            <p class="text-xs text-gray-500">{{ item.account_title?.name || 'No account title' }}</p>
                        </div>
                    </div>
                </div>
                <div v-if="!budgetItems?.length" class="px-5 py-8 text-center text-gray-400">No appropriation records found.</div>
            </div>
        </div>
    </div>
</AppLayout>
</template>

<style scoped>
.annual-chart__glow {
    background:
        radial-gradient(circle at 18% 30%, rgba(217, 174, 68, 0.14), transparent 32%),
        radial-gradient(circle at 78% 28%, rgba(31, 42, 68, 0.12), transparent 28%),
        linear-gradient(90deg, rgba(113, 128, 150, 0.04), rgba(255, 255, 255, 0));
    animation: annualGlowShift 8s ease-in-out infinite alternate;
}

.annual-line,
.annual-area,
.annual-point {
    transform-box: fill-box;
    transform-origin: center;
}

.annual-line {
    stroke-dasharray: 1200;
    stroke-dashoffset: 1200;
    animation: annualDraw 1.8s ease forwards;
}

.annual-line--income {
    animation-delay: 0.05s;
}

.annual-line--appropriation {
    animation-delay: 0.12s;
}

.annual-line--expense {
    animation-delay: 0.18s;
}

.annual-area {
    opacity: 0;
    transform: translateY(6px);
    animation: annualAreaIn 0.9s ease forwards;
}

.annual-area--income {
    animation-delay: 0.15s;
}

.annual-area--appropriation {
    animation-delay: 0.22s;
}

.annual-area--expense {
    animation-delay: 0.28s;
}

.annual-point {
    opacity: 0;
    transform: scale(0.6);
    animation: annualPointPop 0.7s ease forwards;
}

.annual-point--income {
    animation-delay: 0.75s;
}

.annual-point--appropriation {
    animation-delay: 0.85s;
}

.annual-point--expense {
    animation-delay: 0.95s;
}

.annual-hit:hover ~ svg .annual-line,
.annual-hit:focus ~ svg .annual-line {
    filter: drop-shadow(0 0 10px rgba(31, 42, 68, 0.24));
}

.annual-hit:hover ~ svg .annual-point,
.annual-hit:focus ~ svg .annual-point {
    filter: drop-shadow(0 0 8px rgba(217, 174, 68, 0.35));
}

@keyframes annualDraw {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes annualAreaIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes annualPointPop {
    0% {
        opacity: 0;
        transform: scale(0.5);
    }
    70% {
        opacity: 1;
        transform: scale(1.2);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes annualGlowShift {
    from {
        transform: translate3d(-2%, 0, 0) scale(1);
    }
    to {
        transform: translate3d(2%, -1%, 0) scale(1.02);
    }
}
</style>
