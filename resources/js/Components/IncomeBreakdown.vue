<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'

const props = defineProps({ periods: Array, filters: Object, title: { type: String, default: 'Projected Income vs Actual Receipts' } })
const opened = ref(false)
const fiscal = ref('')
const month = ref('')
const start = ref('')
const end = ref('')
const search = ref('')
const department = ref('')
const departments = ref([])
const rows = ref([])
const centerRows = ref([])
const accountTitleRows = ref([])
const selectedCategory = ref(null)
const detailRows = ref([])
const detailBusy = ref(false)
const detailError = ref('')
let detailRequestId = 0
const appliedFilters = ref({})
async function showCategory(row) {
    selectedCategory.value = row; detailRows.value = []; detailError.value = ''; detailBusy.value = true
    const id = ++detailRequestId
    try {
        const { data } = await axios.get('/income-breakdown', { params: { ...appliedFilters.value, details: 1, category_id: row.category_id ?? undefined } })
        if (id === detailRequestId) detailRows.value = data.rows
    } catch (e) { if (id === detailRequestId) detailError.value = 'Could not load category details. Please try again.' }
    finally { if (id === detailRequestId) detailBusy.value = false }
}
function closeCategory() { selectedCategory.value = null; ++detailRequestId }
const detailTotals = computed(() => detailRows.value.reduce((sum, row) => ({ projected: sum.projected + row.projected, actual: sum.actual + row.actual, difference: sum.difference + row.difference }), { projected: 0, actual: 0, difference: 0 }))
const busy = ref(false)
const error = ref('')
const overall = ref({})
const quotaAmount = ref('')
const savingQuota = ref(false)
const quotaMessage = ref('')
const collectionRate = computed(() => totals.value.projected > 0 ? totals.value.actual / totals.value.projected * 100 : null)
async function saveQuota() {
    savingQuota.value = true; quotaMessage.value = ''; error.value = ''
    try {
        await axios.put('/income-quota', { fiscal_period_id: fiscal.value, amount: quotaAmount.value })
        quotaMessage.value = 'Quota saved.'; await load()
    } catch (e) { error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Could not save quota.' }
    finally { savingQuota.value = false }
}
let requestId = 0
const months = computed(() => props.periods?.find(p => String(p.id) === String(fiscal.value))?.months || [])
const visibleRows = computed(() => rows.value.filter(row => row.source.toLowerCase().includes(search.value.trim().toLowerCase())))
const totals = computed(() => visibleRows.value.reduce((sum, row) => ({ projected: sum.projected + row.projected, actual: sum.actual + row.actual, difference: sum.difference + row.difference }), { projected: 0, actual: 0, difference: 0 }))
const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value)
async function load() {
    closeCategory()
    const id = ++requestId
    busy.value = true; error.value = ''; rows.value = []; centerRows.value = []; accountTitleRows.value = []; overall.value = {}
    try {
        const params = { department_id: department.value || undefined, fiscal_period_id: fiscal.value || undefined, allocation_month: month.value || undefined, start_date: start.value || undefined, end_date: end.value || undefined }
        const { data } = await axios.get('/income-breakdown', { params })
        if (id === requestId) { appliedFilters.value = params; departments.value = data.departments; rows.value = data.rows; centerRows.value = data.center_rows || []; accountTitleRows.value = data.account_title_rows || []; overall.value = data.overall; quotaAmount.value = data.overall.quota ?? '' }
    } catch (e) {
        if (id === requestId) error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || 'Could not load the breakdown. Please try again.'
    } finally { if (id === requestId) busy.value = false }
}
function open() {
    fiscal.value = props.filters?.fiscal_period_id || props.periods?.[0]?.id || ''
    month.value = props.filters?.allocation_month || ''
    start.value = props.filters?.start_date || ''; end.value = props.filters?.end_date || ''
    department.value = ''; search.value = ''; opened.value = true; load()
}
function editLink(path) { return path + '?' + new URLSearchParams({ fiscal_period_id: fiscal.value || '', search: '' }).toString() }
function reset() { department.value = '';  month.value = ''; start.value = ''; end.value = ''; search.value = ''; load() }
</script>

<template>
    <button type="button" @click="open" class="text-left"><slot>View income breakdown</slot></button>
    <Modal :show="opened" :title="title" subtitle="Grouped by budget category. Projected income and actual receipts are calculated separately." max-width="5xl" @close="opened = false">
        <form @submit.prevent="load" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="text-xs">Fiscal Year<select v-model="fiscal" @change="month = ''" class="mt-1 block w-full border p-2"><option v-for="p in periods" :key="p.id" :value="p.id">{{ p.label }}</option></select></label>
            <label class="text-xs">Month<select v-model="month" class="mt-1 block w-full border p-2"><option value="">All months</option><option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option></select></label>
            <label class="text-xs">Start Date<input v-model="start" type="date" class="mt-1 block w-full border p-2" /></label>
            <label class="text-xs">End Date<input v-model="end" type="date" class="mt-1 block w-full border p-2" /></label>
            <label class="text-xs sm:col-span-2">Responsibility Center<select v-model="department" class="mt-1 block w-full border p-2"><option value="">All Responsibility Centers</option><option v-for="center in departments" :key="center.id" :value="center.id">{{ center.name }}</option></select></label>
            <input v-model="search" aria-label="Filter budget categories" placeholder="Search budget categories..." class="border p-2 sm:col-span-2" />
            <button class="bg-navy-dark px-4 py-2 text-white">Apply Filters</button><button type="button" @click="reset" class="border px-4 py-2">Reset</button>
        </form>
        <form v-if="$page.props.permissions?.isSuperAdmin" @submit.prevent="saveQuota" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border bg-slate-50 p-3">
            <label class="text-xs">Overall fiscal-year quota (₱)<input v-model="quotaAmount" type="number" min="0" step="0.01" required class="mt-1 block w-full border p-2" /></label>
            <button :disabled="savingQuota || !fiscal" class="bg-navy-dark px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ savingQuota ? 'Saving...' : 'Save Quota' }}</button>
            <p v-if="quotaMessage" role="status" class="w-full text-xs text-emerald-700">{{ quotaMessage }}</p>
        </form>
        <div v-if="!busy && !error" class="mb-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border bg-slate-50 p-3"><p class="text-xs text-slate-500">Overall Collection Rate</p><strong>{{ overall.collection_rate == null ? '—' : overall.collection_rate.toFixed(2) + '%' }}</strong><p class="text-xs text-slate-500">All sources · receipts ÷ projected income</p></div>
            <div class="rounded-lg border bg-slate-50 p-3"><p class="text-xs text-slate-500">Overall Fiscal-Year Quota</p><strong>{{ overall.quota == null ? 'Not set' : money(overall.quota) }}</strong></div>
            <div class="rounded-lg border bg-slate-50 p-3"><p class="text-xs text-slate-500">Quota Progress</p><strong>{{ overall.quota_progress == null ? '—' : overall.quota_progress.toFixed(2) + '%' }}</strong><p v-if="overall.quota_remaining != null" class="text-xs text-slate-500">{{ money(overall.quota_remaining) }} remaining</p></div>
        </div>
        <p v-if="error" role="alert" class="mb-3 text-red-700">{{ error }}</p>
        <p v-if="busy" class="py-8 text-center">Calculating breakdown...</p>
        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm"><thead class="bg-navy-dark text-white"><tr><th class="p-3 text-left">Budget Category</th><th class="p-3 text-right">Projected Income</th><th class="p-3 text-right">Actual Receipts</th><th class="p-3 text-right">Difference</th><th class="p-3 text-right">Collection Rate</th></tr></thead>
                <tbody><tr v-for="row in visibleRows" :key="row.source" class="border-b"><td class="p-3"><button type="button" @click="showCategory(row)" class="text-left font-semibold text-indigo-700 underline hover:text-indigo-900" :aria-label="`View breakdown for ${row.source}`">{{ row.source }}</button></td><td class="p-3 text-right whitespace-nowrap">{{ money(row.projected) }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.actual) }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.difference) }}</td><td class="p-3 text-right">{{ row.collection_rate === null ? '—' : row.collection_rate.toFixed(2) + '%' }}</td></tr><tr v-if="!visibleRows.length"><td colspan="5" class="p-6 text-center">No matching records.</td></tr></tbody>
                <tfoot class="bg-slate-100 font-bold"><tr><td class="p-3">{{ search ? 'Filtered Total' : 'Total' }}</td><td class="p-3 text-right">{{ money(totals.projected) }}</td><td class="p-3 text-right">{{ money(totals.actual) }}</td><td class="p-3 text-right">{{ money(totals.difference) }}</td><td class="p-3 text-right">{{ collectionRate === null ? '—' : collectionRate.toFixed(2) + '%' }}</td></tr></tfoot>
            </table>
        </div>
        <section v-if="!busy && !error" class="mt-5">
            <h3 class="mb-2 text-base font-bold text-slate-800">Summary by Responsibility Center</h3>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-700 text-white"><tr><th class="p-3 text-left">Responsibility Center</th><th class="p-3 text-right">Projected Income</th><th class="p-3 text-right">Actual Receipts</th><th class="p-3 text-right">Difference</th><th class="p-3 text-right">Collection Rate</th></tr></thead>
                <tbody><tr v-for="row in centerRows" :key="row.department_id ?? 'unassigned'" class="border-b"><td class="p-3">{{ row.department }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.projected) }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.actual) }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.difference) }}</td><td class="p-3 text-right">{{ row.collection_rate === null ? '—' : row.collection_rate.toFixed(2) + '%' }}</td></tr><tr v-if="!centerRows.length"><td colspan="5" class="p-6 text-center">No responsibility center data.</td></tr></tbody>
            </table></div>
        </section>

        <section v-if="!busy && !error" class="mt-5">
            <h3 class="mb-2 text-base font-bold text-slate-800">Summary by Account Title</h3>
            <p class="mb-2 text-xs text-slate-500">Account Title is represented by the income source.</p>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-700 text-white"><tr><th class="p-3 text-left">Account Title</th><th class="p-3 text-right">Projected Income</th><th class="p-3 text-right">Actual Receipts</th><th class="p-3 text-right">Difference</th><th class="p-3 text-right">Collection Rate</th></tr></thead>
                <tbody><tr v-for="(row, index) in accountTitleRows" :key="`${row.account_title}-${index}`" class="border-b"><td class="p-3 font-semibold">{{ row.account_title }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.projected) }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.actual) }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.difference) }}</td><td class="p-3 text-right">{{ row.collection_rate === null ? '—' : row.collection_rate.toFixed(2) + '%' }}</td></tr><tr v-if="!accountTitleRows.length"><td colspan="5" class="p-6 text-center">No account title data.</td></tr></tbody>
            </table></div>
        </section>

        <section v-if="selectedCategory" class="mt-5 rounded-lg border border-indigo-200 p-4" aria-live="polite">
            <div class="mb-3 flex items-center justify-between gap-3"><h3 class="font-semibold">{{ selectedCategory.source }}: Breakdown by Income Source</h3><button type="button" @click="closeCategory" class="text-sm text-indigo-700 underline">Close breakdown</button></div>
            <p class="mb-3 text-xs text-gray-500">Uses the applied fiscal year, date and responsibility center filters. Sources are matched by name, ignoring case and surrounding spaces.</p>
            <p v-if="detailBusy">Loading category breakdown...</p>
            <div v-else-if="detailError" role="alert">{{ detailError }} <button type="button" @click="showCategory(selectedCategory)" class="underline">Retry</button></div>
            <div v-else class="overflow-x-auto"><table class="w-full text-sm">
                <thead class="bg-slate-100"><tr><th class="p-3 text-left">Income Source</th><th class="p-3 text-right">Projected Income</th><th class="p-3 text-right">Actual Receipts</th><th class="p-3 text-right">Difference</th><th class="p-3 text-right">Collection Rate</th></tr></thead>
                <tbody><tr v-for="row in detailRows" :key="row.source" class="border-b"><td class="p-3">{{ row.source || 'Unnamed source' }}</td><td class="p-3 text-right">{{ money(row.projected) }}</td><td class="p-3 text-right">{{ money(row.actual) }}</td><td class="p-3 text-right">{{ money(row.difference) }}</td><td class="p-3 text-right">{{ row.collection_rate == null ? 'N/A' : row.collection_rate.toFixed(2) + '%' }}</td></tr><tr v-if="!detailRows.length"><td colspan="5" class="p-3 text-center">No records for this category and filter selection.</td></tr></tbody>
                <tfoot class="bg-slate-100 font-semibold"><tr><td class="p-3">Category Total</td><td class="p-3 text-right">{{ money(detailTotals.projected) }}</td><td class="p-3 text-right">{{ money(detailTotals.actual) }}</td><td class="p-3 text-right">{{ money(detailTotals.difference) }}</td><td class="p-3 text-right">{{ detailTotals.projected > 0 ? (detailTotals.actual / detailTotals.projected * 100).toFixed(2) + '%' : 'N/A' }}</td></tr></tfoot>
            </table></div>
        </section>
        <div v-if="$page.props.permissions?.isSuperAdmin" class="mt-4 flex gap-4 text-sm font-semibold text-indigo-700"><Link :href="editLink('/income')">Edit projected income and student calculations</Link><Link :href="editLink('/receipts')">Edit actual receipts</Link></div>
        <p class="mt-3 text-xs text-slate-500">Collection rate = actual receipts ÷ projected income. Overall collection rate includes all categories for the selected dates and responsibility center and does not change when searching. Quota progress = total receipts ÷ the single fiscal-year quota. Date filters do not change the annual quota. Records without an assigned budget category appear under Uncategorized. Difference means projected less received, not unpaid student balances.</p>
    </Modal>
</template>
