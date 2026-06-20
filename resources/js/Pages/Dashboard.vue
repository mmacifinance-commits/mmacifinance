<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    budgets: Array,
    stats: Object,
    categoryStats: Array,
    recentExpenses: Array,
    recentDisbursements: Array,
    latestYear: Number,
    availableYears: Array,
})

function changeYear(event) {
    router.get('/', { year: event.target.value })
}


function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(v || 0) }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' }) : '—' }

const overallUtil = computed(() => {
    if (!props.stats?.totalAppropriation || props.stats.totalAppropriation === 0) return 0
    return (props.stats.totalExpenditure / props.stats.totalAppropriation * 100).toFixed(1)
})

const pendingTotal = computed(() => (props.stats?.pendingExpenses || 0) + (props.stats?.pendingDisbursements || 0))

const barColors = ['#243352', '#d4a843', '#2c3e5e', '#1a2744', '#e0be6a']

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    posted: 'bg-blue-100 text-blue-800',
    cancelled: 'bg-red-100 text-red-800',
}

// Compute max appropriation for bar chart scaling
const maxBarVal = computed(() => {
    if (!props.categoryStats?.length) return 1
    return Math.max(...props.categoryStats.map(c => Math.max(c.appropriation, c.expenditure))) || 1
})
</script>

<template>
<Head title="Dashboard" />
<AppLayout>
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <img src="/images/logo.png" alt="" class="h-10 w-10 object-contain" />
            <div>
                <h2 class="text-xl font-bold text-gray-900">Dashboard</h2>
                <p class="text-sm text-gray-500">Budget & Funds Utilization Overview — FY {{ latestYear || new Date().getFullYear() }}</p>
            </div>
        </div>
        <!-- Year Selector Dropdown -->
        <div class="flex items-center gap-2" v-if="availableYears && availableYears.length >= 1">
            <label for="year-select" class="text-xs font-bold uppercase tracking-wider text-gray-500">Fiscal Year:</label>
            <select
                id="year-select"
                :value="latestYear"
                @change="changeYear"
                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm focus:border-navy focus:outline-none focus:ring-1 focus:ring-navy transition cursor-pointer"
            >
                <option v-for="yr in availableYears" :key="yr" :value="yr">
                    FY {{ yr }}
                </option>
            </select>
        </div>
    </div>

    <!-- 4 Summary Cards -->
    <div class="grid gap-4 md:grid-cols-4 mb-6">
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div class="h-1 bg-navy-dark"></div>
            <div class="p-4">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Total Appropriation</p>
                    <span class="text-gray-400"></span>
                </div>
                <p class="text-xl font-bold text-gray-900">{{ fmt(stats?.totalAppropriation) }}</p>
                <p class="text-[11px] text-gray-400 mt-0.5">FY {{ latestYear }} Budget</p>
            </div>
        </div>
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div class="h-1 bg-red-500"></div>
            <div class="p-4">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Total Expenditure</p>
                    <span class="text-gray-400"></span>
                </div>
                <p class="text-xl font-bold text-gray-900">{{ fmt(stats?.totalExpenditure) }}</p>
                <p class="text-[11px] text-red-400 mt-0.5">{{ overallUtil }}% utilized</p>
            </div>
        </div>
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div class="h-1 bg-green-500"></div>
            <div class="p-4">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Remaining Balance</p>
                    <span class="text-gray-400"></span>
                </div>
                <p class="text-xl font-bold text-gray-900">{{ fmt(stats?.balance) }}</p>
                <p class="text-[11px] text-green-500 mt-0.5">{{ (100 - overallUtil).toFixed(1) }}% remaining</p>
            </div>
        </div>
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div class="h-1 bg-mustard"></div>
            <div class="p-4">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Pending Approvals</p>
                    <span class="text-gray-400"></span>
                </div>
                <p class="text-xl font-bold text-gray-900">{{ pendingTotal }}</p>
                <p class="text-[11px] text-gray-400 mt-0.5">{{ stats?.pendingExpenses }} expenses, {{ stats?.pendingDisbursements }} disbursements</p>
            </div>
        </div>
    </div>

    <!-- Budget Utilization Rate -->
    <div class="bg-white border border-gray-200 overflow-hidden mb-6">
        <div class="bg-navy-dark px-5 py-3">
            <h3 class="text-sm font-bold text-white">Budget Utilization Rate</h3>
        </div>
        <div class="p-5">
            <!-- Overall -->
            <div class="mb-5">
                <div class="flex justify-between items-center mb-1.5">
                    <span class="text-sm text-gray-700">Overall Utilization</span>
                    <span class="text-sm font-bold text-gray-900">{{ overallUtil }}%</span>
                </div>
                <div class="w-full h-3 bg-gray-200">
                    <div class="h-full bg-navy-dark transition-all" :style="{ width: Math.min(100, overallUtil) + '%' }"></div>
                </div>
            </div>
            <!-- Per-category bars -->
            <div v-if="categoryStats?.length" class="grid gap-3 md:grid-cols-2">
                <div v-for="(cat, i) in categoryStats" :key="cat.name">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-bold uppercase text-gray-600">{{ cat.name }}</span>
                        <span class="text-xs font-bold text-gray-900">{{ cat.utilization }}%</span>
                    </div>
                    <div class="w-full h-2.5 bg-gray-200">
                        <div class="h-full transition-all" :style="{ width: Math.min(100, cat.utilization) + '%', backgroundColor: barColors[i % barColors.length] }"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Two Charts Row: Appropriation vs Expenditure + Budget Allocation by Category -->
    <div class="grid gap-4 md:grid-cols-2 mb-6">
        <!-- Bar Chart: Appropriation vs Expenditure -->
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div class="bg-navy-dark px-5 py-3">
                <h3 class="text-sm font-bold text-white">📊 Appropriation vs Expenditure</h3>
            </div>
            <div class="p-5">
                <div v-if="categoryStats?.length" class="space-y-4">
                    <div v-for="cat in categoryStats" :key="cat.name" class="space-y-1">
                        <p class="text-[10px] font-bold uppercase text-gray-500">{{ cat.name }}</p>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-4 bg-gray-100 relative">
                                <div class="absolute inset-y-0 left-0 bg-navy-dark" :style="{ width: (cat.appropriation / maxBarVal * 100) + '%' }"></div>
                            </div>
                            <span class="text-[10px] w-16 text-right font-mono text-gray-600">{{ fmt(cat.appropriation) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-4 bg-gray-100 relative">
                                <div class="absolute inset-y-0 left-0 bg-mustard" :style="{ width: (cat.expenditure / maxBarVal * 100) + '%' }"></div>
                            </div>
                            <span class="text-[10px] w-16 text-right font-mono text-gray-600">{{ fmt(cat.expenditure) }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 pt-2 border-t text-[10px] text-gray-500">
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 bg-navy-dark"></span> Appropriation</span>
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 bg-mustard"></span> Expenditure</span>
                    </div>
                </div>
                <p v-else class="text-center text-gray-400 py-8">No data</p>
            </div>
        </div>

        <!-- Donut-style allocation chart -->
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div class="bg-navy-dark px-5 py-3">
                <h3 class="text-sm font-bold text-white">📁 Budget Allocation by Category</h3>
            </div>
            <div class="p-5">
                <div v-if="categoryStats?.length">
                    <!-- Visual allocation bars -->
                    <div class="space-y-3 mb-4">
                        <div v-for="(cat, i) in categoryStats" :key="cat.name">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-medium text-gray-700">{{ cat.name }}</span>
                                <span class="font-bold">{{ fmt(cat.appropriation) }}</span>
                            </div>
                            <div class="w-full h-3 bg-gray-100">
                                <div class="h-full transition-all" :style="{ width: (stats?.totalAppropriation > 0 ? cat.appropriation / stats.totalAppropriation * 100 : 0) + '%', backgroundColor: barColors[i % barColors.length] }"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Legend -->
                    <div class="flex flex-wrap gap-3 pt-3 border-t">
                        <span v-for="(cat, i) in categoryStats" :key="cat.name" class="flex items-center gap-1 text-[10px] text-gray-600">
                            <span class="inline-block w-2.5 h-2.5" :style="{ backgroundColor: barColors[i % barColors.length] }"></span>
                            {{ cat.name }}
                        </span>
                    </div>
                </div>
                <p v-else class="text-center text-gray-400 py-8">No data</p>
            </div>
        </div>
    </div>

    <!-- Recent Expenses & Disbursements -->
    <div class="grid gap-4 md:grid-cols-2 mb-6">
        <!-- Recent Expenses -->
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div class="bg-navy-dark px-5 py-3">
                <h3 class="text-sm font-bold text-white">Recent Expenses</h3>
            </div>
            <div class="divide-y">
                <div v-for="e in recentExpenses" :key="e.id" class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-gray-900">{{ e.ref_no }}</p>
                        <p class="text-xs text-gray-500">{{ e.category?.name }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-gray-900">{{ fmt(e.amount) }}</p>
                        <span :class="[statusColors[e.status], 'inline-block px-2 py-0.5 text-[10px] font-bold uppercase']">{{ e.status }}</span>
                    </div>
                </div>
                <div v-if="!recentExpenses?.length" class="px-5 py-6 text-center text-gray-400 text-sm">No recent expenses</div>
            </div>
        </div>

        <!-- Recent Disbursements -->
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div class="bg-navy-dark px-5 py-3">
                <h3 class="text-sm font-bold text-white">Recent Disbursements</h3>
            </div>
            <div class="divide-y">
                <div v-for="d in recentDisbursements" :key="d.id" class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-gray-900">{{ d.disbursement_no }}</p>
                        <p class="text-xs text-gray-500">{{ d.description }} — {{ d.pay_to }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-gray-900">{{ fmt(d.amount) }}</p>
                        <span :class="[statusColors[d.status], 'inline-block px-2 py-0.5 text-[10px] font-bold uppercase']">{{ d.status }}</span>
                    </div>
                </div>
                <div v-if="!recentDisbursements?.length" class="px-5 py-6 text-center text-gray-400 text-sm">No recent disbursements</div>
            </div>
        </div>
    </div>
</AppLayout>
</template>
