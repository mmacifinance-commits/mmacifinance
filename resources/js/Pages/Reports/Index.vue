<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    budgets: Array,
    categories: Array,
    accountTitles: Array,
    particulars: Array,
    departments: Array,
    expenses: Array,
    disbursements: Array,
    availableYears: Array,
    filters: Object,
})

const filterYear = ref(props.filters?.year || new Date().getFullYear())
const filterMonth = ref(props.filters?.month || '')
const startDate = ref(props.filters?.start_date || '')
const endDate = ref(props.filters?.end_date || '')
const filterDepartment = ref(props.filters?.department_id || '')
const filterCategory = ref(props.filters?.category_id || '')
const filterAccountTitle = ref(props.filters?.account_title_id || '')

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(v || 0) }

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

function totalApp() { return (props.budgets || []).reduce((s, b) => s + (b.items || []).reduce((ss, i) => ss + Number(i.appropriation || 0), 0), 0) }
function totalExp() { return (props.expenses || []).reduce((s, e) => s + Number(e.paid || e.amount || 0), 0) }
function totalPostedDsb() { return (props.disbursements || []).filter(d => d.status === 'posted').reduce((s, d) => s + Number(d.amount || 0), 0) }
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
                    <option v-for="y in (availableYears || [2026, 2025, 2024])" :key="y" :value="y">{{ y }}</option>
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
                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Department</label>
                <select v-model="filterDepartment" @change="applyFilters" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white">
                    <option value="">All Departments</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Category</label>
                <select v-model="filterCategory" @change="applyFilters" class="w-full rounded-md border-gray-300 text-xs py-1.5 bg-white">
                    <option value="">All Categories</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
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

    <!-- Summary Cards -->
    <div class="grid gap-5 md:grid-cols-3 mb-8">
        <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden">
            <div class="h-1.5 bg-emerald-500"></div>
            <div class="p-5">
                <p class="text-xs font-bold uppercase text-gray-500 mb-1">Total Appropriation</p>
                <p class="text-2xl font-bold text-gray-900">₱{{ fmt(totalApp()) }}</p>
            </div>
        </div>
        <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden">
            <div class="h-1.5 bg-rose-500"></div>
            <div class="p-5">
                <p class="text-xs font-bold uppercase text-gray-500 mb-1">Total Expenditure (Posted)</p>
                <p class="text-2xl font-bold text-gray-900">₱{{ fmt(totalExp()) }}</p>
            </div>
        </div>
        <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden">
            <div class="h-1.5 bg-mustard"></div>
            <div class="p-5">
                <p class="text-xs font-bold uppercase text-gray-500 mb-1">Total Posted Disbursements</p>
                <p class="text-2xl font-bold text-gray-900">₱{{ fmt(totalPostedDsb()) }}</p>
            </div>
        </div>
    </div>

    <!-- Category breakdown -->
    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Budget Performance by Fiscal Year</h3>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-navy-dark text-white border-b-2 border-mustard">
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-white">Annual Reference</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-white">Fiscal Year</th>
                    <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-white">Appropriation</th>
                    <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-white">Expenditure</th>
                    <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-white">Utilization</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="b in budgets" :key="b.id" class="border-b hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-3 font-mono text-xs font-bold text-navy">
                        {{ b.ref_no || ('AB-' + b.year + '-000' + b.id) }}
                    </td>
                    <td class="px-5 py-3 font-bold text-gray-900">{{ b.year }}</td>
                    <td class="px-5 py-3 text-right font-medium">₱{{ fmt(b.items.reduce((s,i) => s + Number(i.appropriation || 0), 0)) }}</td>
                    <td class="px-5 py-3 text-right font-medium">₱{{ fmt(b.items.reduce((s,i) => s + Number(i.expenditure || 0), 0)) }}</td>
                    <td class="px-5 py-3 text-center">
                        <div class="relative h-5 rounded-full overflow-hidden bg-gray-200 w-32 mx-auto">
                            <div class="absolute left-0 top-0 h-full bg-mustard rounded-full transition-all"
                                :style="{ width: (b.items.reduce((s,i)=>s+Number(i.appropriation || 0),0) > 0 ? Math.min(100, (b.items.reduce((s,i)=>s+Number(i.expenditure || 0),0) / b.items.reduce((s,i)=>s+Number(i.appropriation || 0),0)) * 100) : 0) + '%' }">
                            </div>
                            <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-gray-800">
                                {{ b.items.reduce((s,i)=>s+Number(i.appropriation || 0),0) > 0 ? ((b.items.reduce((s,i)=>s+Number(i.expenditure || 0),0) / b.items.reduce((s,i)=>s+Number(i.appropriation || 0),0)) * 100).toFixed(1) : 0 }}%
                            </span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</AppLayout>
</template>
