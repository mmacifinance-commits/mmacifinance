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
const quotaSource = ref('')
const quotaAmount = ref('')
const savingQuota = ref(false)
const quotaMessage = ref('')
const quotaTotal = computed(() => visibleRows.value.reduce((sum, row) => sum + (row.quota || 0), 0))
const collectionRate = computed(() => quotaTotal.value > 0 ? visibleRows.value.filter(row => row.quota > 0).reduce((sum, row) => sum + row.actual, 0) / quotaTotal.value * 100 : null)
async function saveQuota() {
    savingQuota.value = true; quotaMessage.value = ''; error.value = ''
    try {
        await axios.put('/income-quota', { fiscal_period_id: fiscal.value, source: quotaSource.value, amount: quotaAmount.value })
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
    busy.value = true; error.value = ''; rows.value = []
    try {
        const { data } = await axios.get('/income-breakdown', { params: { fiscal_period_id: fiscal.value || undefined, allocation_month: month.value || undefined, start_date: start.value || undefined, end_date: end.value || undefined } })
        if (id === requestId) rows.value = data.rows
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
            <label class="flex-1 text-xs">Income source<input v-model="quotaSource" list="quota-sources" required maxlength="255" class="mt-1 block w-full border p-2" placeholder="Tuition Fee, Trust Fund..." /><datalist id="quota-sources"><option v-for="row in rows" :key="row.source" :value="row.source" /></datalist></label>
            <label class="text-xs">Fiscal-year quota (₱)<input v-model="quotaAmount" type="number" min="0" step="0.01" required class="mt-1 block w-full border p-2" /></label>
            <button :disabled="savingQuota || !fiscal" class="bg-navy-dark px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ savingQuota ? 'Saving...' : 'Save Quota' }}</button>
            <p v-if="quotaMessage" role="status" class="w-full text-xs text-emerald-700">{{ quotaMessage }}</p>
        </form>
        <p class="mb-3 text-sm">Fiscal-year quota: <strong>{{ money(quotaTotal) }}</strong> · Collection rate: <strong>{{ collectionRate === null ? 'No positive quota set' : collectionRate.toFixed(2) + '%' }}</strong></p>
        <p v-if="error" role="alert" class="mb-3 text-red-700">{{ error }}</p>
        <p v-if="busy" class="py-8 text-center">Calculating breakdown...</p>
        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm"><thead class="bg-navy-dark text-white"><tr><th class="p-3 text-left">Income Source</th><th class="p-3 text-right">Projected Income</th><th class="p-3 text-right">Actual Receipts</th><th class="p-3 text-right">Difference</th><th class="p-3 text-right">FY Quota</th><th class="p-3 text-right">Collection Rate</th><th class="p-3 text-right">Quota Remaining</th></tr></thead>
                <tbody><tr v-for="row in visibleRows" :key="row.source" class="border-b"><td class="p-3">{{ row.source }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.projected) }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.actual) }}</td><td class="p-3 text-right whitespace-nowrap">{{ money(row.difference) }}</td><td class="p-3 text-right whitespace-nowrap"><button v-if="$page.props.permissions?.isSuperAdmin" @click="quotaSource = row.source; quotaAmount = row.quota ?? ''" class="text-indigo-700 underline">{{ row.quota === null ? 'Set quota' : money(row.quota) }}</button><span v-else>{{ row.quota === null ? 'Not set' : money(row.quota) }}</span></td><td class="p-3 text-right">{{ row.collection_rate === null ? '—' : row.collection_rate.toFixed(2) + '%' }}</td><td class="p-3 text-right whitespace-nowrap">{{ row.quota_remaining === null ? '—' : money(row.quota_remaining) }}</td></tr><tr v-if="!visibleRows.length"><td colspan="7" class="p-6 text-center">No matching records.</td></tr></tbody>
                <tfoot class="bg-slate-100 font-bold"><tr><td class="p-3">Total</td><td class="p-3 text-right">{{ money(totals.projected) }}</td><td class="p-3 text-right">{{ money(totals.actual) }}</td><td class="p-3 text-right">{{ money(totals.difference) }}</td><td class="p-3 text-right">{{ money(quotaTotal) }}</td><td class="p-3 text-right">{{ collectionRate === null ? '—' : collectionRate.toFixed(2) + '%' }}</td><td class="p-3 text-right">{{ money(visibleRows.reduce((sum, row) => sum + (row.quota_remaining || 0), 0)) }}</td></tr></tfoot>
            </table>
        </div>
        <div v-if="$page.props.permissions?.isSuperAdmin" class="mt-4 flex gap-4 text-sm font-semibold text-indigo-700"><Link :href="editLink('/income')">Edit projected income and student calculations</Link><Link :href="editLink('/receipts')">Edit actual receipts</Link></div>
        <p class="mt-3 text-xs text-slate-500">Collection rate uses actual receipts divided by the fiscal-year quota. Date filters limit receipts, not the annual quota. The overall rate includes only sources with a positive quota. Sources match by name, ignoring case and surrounding spaces. Differently named sources remain separate. Difference means projected less received, not unpaid student balances.</p>
    </Modal>
</template>
