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
const rows = ref([])
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
    const id = ++requestId
    busy.value = true; error.value = ''; rows.value = []; overall.value = {}
    try {
        const { data } = await axios.get('/income-breakdown', { params: { fiscal_period_id: fiscal.value || undefined, allocation_month: month.value || undefined, start_date: start.value || undefined, end_date: end.value || undefined } })
        if (id === requestId) { rows.value = data.rows; overall.value = data.overall; quotaAmount.value = data.overall.quota ?? '' }
    } catch (e) {
        if (id === requestId) error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || 'Could not load the breakdown. Please try again.'
    } finally { if (id === requestId) busy.value = false }
}
function open() {
    fiscal.value = props.filters?.fiscal_period_id || props.periods?.[0]?.id || ''
    month.value = props.filters?.allocation_month || ''
    start.value = props.filters?.start_date || ''; end.value = props.filters?.end_date || ''
    search.value = ''; opened.value = true; load()
}
function editLink(path) { return path + '?' + new URLSearchParams({ fiscal_period_id: fiscal.value || '', search: search.value }).toString() }
function reset() { month.value = ''; start.value = ''; end.value = ''; search.value = ''; load() }
</script>

<template>
    <button type="button" @click="open" class="text-left"><slot>View income breakdown</slot></button>
    <Modal :show="opened" :title="title" subtitle="Grouped by source. Projected income and actual receipts are calculated separately." max-width="5xl" @close="opened = false">
        <form @submit.prevent="load" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="text-xs">Fiscal Year<select v-model="fiscal" @change="month = ''" class="mt-1 block w-full border p-2"><option v-for="p in periods" :key="p.id" :value="p.id">{{ p.label }}</option></select></label>
            <label class="text-xs">Month<select v-model="month" class="mt-1 block w-full border p-2"><option value="">All months</option><option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option></select></label>
            <label class="text-xs">Start Date<input v-model="start" type="date" class="mt-1 block w-full border p-2" /></label>
            <label class="text-xs">End Date<input v-model="end" type="date" class="mt-1 block w-full border p-2" /></label>
            <input v-model="search" aria-label="Filter income sources" placeholder="Search tuition, trust fund, developmental fee..." class="border p-2 sm:col-span-2" />
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
            <table class="w-full text-sm"><thead class="bg-navy-dark text-white"><tr><th class="p-3 text-left">Income Source</th><th class="p-3 text-right">Projected Income</th><th class="p-3 text-right">Actual Receipts</th><th class="p-3 text-right">Difference</th><th class="p-3 text-right">Collection Rate</th></tr></thead>
                <tbody><tr v-for="row in visibleRows" :key="row.source" class="border-b"><td class="p-3">{{ row.source }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.projected) }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.actual) }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.difference) }}</td><td class="p-3 text-right">{{ row.collection_rate === null ? '—' : row.collection_rate.toFixed(2) + '%' }}</td></tr><tr v-if="!visibleRows.length"><td colspan="5" class="p-6 text-center">No matching records.</td></tr></tbody>
                <tfoot class="bg-slate-100 font-bold"><tr><td class="p-3">{{ search ? 'Filtered Total' : 'Total' }}</td><td class="p-3 text-right">{{ money(totals.projected) }}</td><td class="p-3 text-right">{{ money(totals.actual) }}</td><td class="p-3 text-right">{{ money(totals.difference) }}</td><td class="p-3 text-right">{{ collectionRate === null ? '—' : collectionRate.toFixed(2) + '%' }}</td></tr></tfoot>
            </table>
        </div>
        <div v-if="$page.props.permissions?.isSuperAdmin" class="mt-4 flex gap-4 text-sm font-semibold text-indigo-700"><Link :href="editLink('/income')">Edit projected income and student calculations</Link><Link :href="editLink('/receipts')">Edit actual receipts</Link></div>
        <p class="mt-3 text-xs text-slate-500">Collection rate = actual receipts ÷ projected income. Overall collection rate includes all sources for the selected dates and does not change when searching. Quota progress = total receipts ÷ the single fiscal-year quota. Date filters do not change the annual quota. Sources match by name, ignoring case and surrounding spaces. Differently named sources remain separate. Difference means projected less received, not unpaid student balances.</p>
    </Modal>
</template>
