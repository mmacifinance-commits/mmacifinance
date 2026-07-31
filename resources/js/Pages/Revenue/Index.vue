<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
    availableYears: Array,
    filters: Object,
    stats: Object,
    monthlyIncome: Array,
    monthlyRevenue: Array,
    monthlyExpense: Array,
    budgetItems: Array,
    incomeRecords: Array,
})

const selectedYear = ref(props.filters?.year || new Date().getFullYear())
const selectedMonth = ref(props.filters?.month || '')
const startDate = ref(props.filters?.start_date || '')
const endDate = ref(props.filters?.end_date || '')

const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const maxIncome = computed(() => Math.max(...(props.monthlyIncome || []).map((item) => Number(item.amount || 0)), 1))
const maxAppropriation = computed(() => Math.max(...(props.monthlyRevenue || []).map((item) => Number(item.amount || 0)), 1))
const maxExpense = computed(() => Math.max(...(props.monthlyExpense || []).map((item) => Number(item.amount || 0)), 1))

function monthlyIncomeAmount(monthNum) {
    return (props.monthlyIncome || []).find((item) => Number(item.month_num) === Number(monthNum))?.amount || 0
}

function monthlyExpenseAmount(index) {
    return props.monthlyExpense?.[index]?.amount || 0
}

function fmt(value) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(Number(value || 0))
}

function applyFilters() {
    router.get('/revenue', {
        year: selectedYear.value,
        month: selectedMonth.value,
        start_date: startDate.value,
        end_date: endDate.value,
    }, {
        preserveState: true,
        replace: true,
    })
}
</script>

<template>
<Head title="Revenue" />
<AppLayout>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Revenue</h2>
            <p class="text-sm text-gray-500">Income funds the budget, appropriation is allocated from it, and expenses reduce what remains.</p>
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

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-slate-500"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Income</p>
                <p class="mt-1 text-xl font-extrabold text-slate-800">{{ fmt(stats?.totalIncome) }}</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-indigo-500"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Appropriation</p>
                <p class="mt-1 text-xl font-extrabold text-indigo-700">{{ fmt(stats?.totalRevenue) }}</p>
                <p class="mt-1 text-xs text-gray-500">Deducted from income for the budget source.</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-rose-500"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Expense</p>
                <p class="mt-1 text-xl font-extrabold text-rose-700">{{ fmt(stats?.totalExpense) }}</p>
                <p class="mt-1 text-xs text-gray-500">Posted and paid expenses only.</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-emerald-500"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Remaining Appropriation</p>
                <p class="mt-1 text-xl font-extrabold text-emerald-700">{{ fmt(stats?.balance) }}</p>
                <p class="mt-1 text-xs text-gray-500">Appropriation less expenses.</p>
            </div>
        </div>
    </div>

    <div class="mb-6 overflow-visible rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-navy-dark">Income -> Appropriation -> Expense</h3>
                <p class="text-xs text-gray-500">Use the filters above to change the year, month, or date range.</p>
            </div>
            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-600">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-slate-500"></span> Income</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-navy-dark"></span> Appropriation</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-mustard"></span> Expense</span>
            </div>
        </div>

        <div class="relative h-72 overflow-visible rounded-lg bg-slate-50/60 p-4">
            <div class="absolute inset-x-0 bottom-0 top-0 flex items-end justify-between gap-3 px-2">
                <div v-for="(item, idx) in monthlyRevenue" :key="item.month_num" class="group relative flex h-full flex-1 flex-col items-center justify-end">
                    <div class="pointer-events-none absolute bottom-full left-1/2 mb-3 w-56 -translate-x-1/2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] text-slate-700 opacity-0 shadow-2xl transition-all duration-200 group-hover:opacity-100">
                        <div class="mb-1 border-b border-slate-200 pb-1 font-bold text-navy-dark">{{ item.month }} {{ selectedYear }}</div>
                        <div>Income: {{ fmt(monthlyIncomeAmount(item.month_num)) }}</div>
                        <div>Appropriation: {{ fmt(item.amount) }}</div>
                        <div>Expense: {{ fmt(monthlyExpenseAmount(idx)) }}</div>
                    </div>
                    <div class="flex h-full w-full items-end justify-center gap-1 px-1">
                        <div class="w-1/3 origin-bottom rounded-t-sm bg-slate-500 transition-all duration-700 ease-out" :style="{ height: `${Math.max(4, (monthlyIncomeAmount(item.month_num) / maxIncome) * 100)}%` }"></div>
                        <div class="w-1/3 origin-bottom rounded-t-sm bg-navy-dark transition-all duration-700 ease-out" :style="{ height: `${Math.max(4, (item.amount / maxAppropriation) * 100)}%` }"></div>
                        <div class="w-1/3 origin-bottom rounded-t-sm bg-mustard transition-all duration-700 ease-out" :style="{ height: `${Math.max(4, (monthlyExpenseAmount(idx) / maxExpense) * 100)}%` }"></div>
                    </div>
                    <span class="mt-2 text-[11px] font-bold text-slate-600">{{ item.month }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="bg-navy-dark px-5 py-3">
                <h3 class="text-sm font-bold uppercase tracking-wider text-white">Income Records</h3>
            </div>
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
            <div class="bg-navy-dark px-5 py-3">
                <h3 class="text-sm font-bold uppercase tracking-wider text-white">Appropriation Summary</h3>
            </div>
            <div class="divide-y">
                <div v-for="item in budgetItems" :key="item.id" class="px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-gray-900">{{ item.category?.name || 'Uncategorized' }}</p>
                            <p class="text-xs text-gray-500">
                                {{ item.particular?.name || 'No particular' }}
                                <span v-if="item.particular?.department"> - {{ item.particular.department.name }}</span>
                            </p>
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
