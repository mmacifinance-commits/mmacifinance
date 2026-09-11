<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import SystemAlert from '@/Components/SystemAlert.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
    receipts: Object,
    fiscalPeriods: Array,
    filters: Object,
    termOptions: Array,
    summary: Object,
})

const PESO = '\u20b1'
const selectedFiscalPeriod = ref(props.filters?.fiscal_period_id || '')
const selectedTerm = ref(props.filters?.term || '')
const searchQuery = ref(props.filters?.search || '')
const showImportModal = ref(false)
const showReceiptModal = ref(false)
const editingReceiptId = ref(null)
const importForm = useForm({ csv_file: null })
const receiptForm = useForm({
    receipt_no: '',
    receipt_type: '',
    source: '',
    description: '',
    amount: '',
    date_encoded: '',
    notes: '',
})
const receiptItems = computed(() => props.receipts?.data || [])
const importErrorMessages = computed(() => Object.values(importForm.errors || {}).flat().filter(Boolean))
const receiptErrorMessages = computed(() => Object.values(receiptForm.errors || {}).flat().filter(Boolean))

function fmt(value) {
    return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(value || 0)
}

function applyFilters() {
    router.get('/receipts', {
        fiscal_period_id: selectedFiscalPeriod.value,
        term: selectedTerm.value,
        search: searchQuery.value,
    }, { preserveState: true, replace: true })
}

function resetFilters() {
    selectedFiscalPeriod.value = props.fiscalPeriods?.[0]?.id || ''
    selectedTerm.value = ''
    searchQuery.value = ''
    applyFilters()
}

function exportCsv() {
    const params = new URLSearchParams({
        fiscal_period_id: selectedFiscalPeriod.value || '',
        term: selectedTerm.value || '',
        search: searchQuery.value || '',
    })
    window.location.href = `/receipts/export-csv?${params.toString()}`
}

function openImport() {
    importForm.reset()
    importForm.clearErrors()
    showImportModal.value = true
}

function importCsv() {
    importForm.post('/receipts/import-csv', {
        forceFormData: true,
        onSuccess: () => {
            showImportModal.value = false
            importForm.reset()
        },
    })
}

function openCreateReceipt() {
    editingReceiptId.value = null
    receiptForm.reset()
    receiptForm.clearErrors()
    receiptForm.date_encoded = new Date().toISOString().slice(0, 10)
    showReceiptModal.value = true
}

function openEditReceipt(item) {
    editingReceiptId.value = item.id
    receiptForm.clearErrors()
    receiptForm.receipt_no = item.receipt_no || ''
    receiptForm.receipt_type = item.receipt_type || ''
    receiptForm.source = item.source || ''
    receiptForm.description = item.description || ''
    receiptForm.amount = item.amount || ''
    receiptForm.date_encoded = item.date_encoded || ''
    receiptForm.notes = item.notes || ''
    showReceiptModal.value = true
}

function saveReceipt() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showReceiptModal.value = false
            receiptForm.reset()
            editingReceiptId.value = null
        },
    }

    if (editingReceiptId.value) {
        receiptForm.put(`/receipts/${editingReceiptId.value}`, options)
        return
    }

    receiptForm.post('/receipts', options)
}

function deleteReceipt(item) {
    if (!window.confirm(`Delete receipt ${item.receipt_no}? This cannot be undone.`)) {
        return
    }

    router.delete(`/receipts/${item.id}`, { preserveScroll: true })
}
</script>

<template>
<Head title="Receipts" />
<AppLayout>
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Receipts</h2>
            <p class="text-sm text-gray-500">Cash receipts grouped by the receipt type you enter for each record.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button @click="openCreateReceipt" class="rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-navy">Add Receipt</button>
            <button @click="exportCsv" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">Export CSV</button>
            <button @click="openImport" class="rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">Import CSV</button>
        </div>
    </div>

    <div class="mb-6 space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Filter Receipts</p>
                <p class="mt-1 text-[11px] text-gray-400">Receipts are sourced from income records and official receipt numbers.</p>
            </div>
            <button @click="resetFilters" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">Reset Filters</button>
        </div>
        <div class="grid gap-3 md:grid-cols-[1fr_1fr_2fr]">
            <div class="space-y-1">
                <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-700">Fiscal Year</label>
                <select v-model="selectedFiscalPeriod" @change="applyFilters" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm">
                    <option v-for="period in fiscalPeriods" :key="period.id" :value="period.id">{{ period.label }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-700">Receipt Type</label>
                <select v-model="selectedTerm" @change="applyFilters" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm">
                    <option v-for="option in termOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-700">Search</label>
                <input v-model="searchQuery" @input="applyFilters" type="text" placeholder="Search receipt no, income no, source, or description..." class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm" />
            </div>
        </div>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-3 xl:grid-cols-6">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-navy-dark"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Receipts</p>
                <p class="mt-0.5 text-xl font-extrabold text-navy-dark">{{ PESO }}{{ fmt(summary?.totalAmount) }}</p>
                <p class="mt-1 text-xs text-gray-500">Actual collected cash.</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-rose-500"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Posted Disbursements</p>
                <p class="mt-0.5 text-xl font-extrabold text-rose-700">{{ PESO }}{{ fmt(summary?.postedDisbursements) }}</p>
                <p class="mt-1 text-xs text-gray-500">Actual cash already paid out.</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-emerald-500"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Cash On Hand</p>
                <p class="mt-0.5 text-xl font-extrabold text-emerald-700">{{ PESO }}{{ fmt(summary?.cashOnHand) }}</p>
                <p class="mt-1 text-xs text-gray-500">Receipts less posted disbursements.</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-orange-500"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Committed Cash</p>
                <p class="mt-0.5 text-xl font-extrabold text-orange-700">{{ PESO }}{{ fmt(summary?.committedDisbursements) }}</p>
                <p class="mt-1 text-xs text-gray-500">Draft, pending, approved, and posted releases.</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-teal-500"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Available for Disbursement</p>
                <p class="mt-0.5 text-xl font-extrabold text-teal-700">{{ PESO }}{{ fmt(summary?.availableForDisbursement) }}</p>
                <p class="mt-1 text-xs text-gray-500">Receipts less committed disbursements.</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-amber-500"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Receipt Records</p>
                <p class="mt-0.5 text-xl font-extrabold text-slate-800">{{ summary?.recordCount || 0 }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ summary?.withReceiptNo || 0 }} with receipt no.</p>
            </div>
        </div>
    </div>

    <div v-if="summary?.byType?.length" class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="bg-navy-dark px-5 py-3">
            <h3 class="text-sm font-bold uppercase tracking-wider text-white">Receipt Summary by Type</h3>
        </div>
        <div class="grid divide-y md:grid-cols-5 md:divide-x md:divide-y-0">
            <div v-for="item in summary.byType" :key="item.type" class="p-4">
                <p class="truncate text-xs font-bold uppercase tracking-wide text-gray-500">{{ item.type }}</p>
                <p class="mt-1 text-lg font-extrabold text-gray-900">{{ PESO }}{{ fmt(item.amount) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ item.count }} record(s)</p>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="bg-navy-dark px-5 py-3">
            <h3 class="text-sm font-bold uppercase tracking-wider text-white">Cash Receipt Records</h3>
        </div>
        <div class="divide-y">
            <div class="hidden grid-cols-[1fr_1fr_2fr_0.9fr_0.9fr_auto] items-center gap-4 px-5 py-3 text-[11px] font-bold uppercase tracking-wider text-gray-500 md:grid">
                <div>Receipt No.</div>
                <div>Income No.</div>
                <div>Source / Description</div>
                <div>Date</div>
                <div class="text-right">Amount</div>
                <div class="text-right">Actions</div>
            </div>
            <div v-for="item in receiptItems" :key="item.id" class="grid grid-cols-1 gap-3 px-5 py-4 md:grid-cols-[1fr_1fr_2fr_0.9fr_0.9fr_auto] md:items-center md:gap-4">
                <div>
                    <p class="text-sm font-bold text-gray-900">{{ item.receipt_no }}</p>
                    <p class="mt-1 inline-flex border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800">{{ item.receipt_type }}</p>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800">{{ item.income_no }}</p>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-900">{{ item.source }}</p>
                    <p class="truncate text-xs text-gray-500">{{ item.description }}</p>
                    <p class="mt-1 text-[11px] text-gray-400 md:hidden">{{ item.date_encoded }}</p>
                </div>
                <div class="hidden text-sm text-gray-600 md:block">{{ item.date_encoded }}</div>
                <div class="md:text-right">
                    <p class="text-sm font-bold tabular-nums text-gray-900">{{ PESO }}{{ fmt(item.amount) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ item.notes || 'No notes' }}</p>
                </div>
                <div class="flex justify-start gap-2 md:justify-end">
                    <button @click="openEditReceipt(item)" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Edit</button>
                    <button @click="deleteReceipt(item)" class="rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">Delete</button>
                </div>
            </div>
            <div v-if="!receiptItems.length" class="px-5 py-8 text-center text-gray-400">No receipt records found.</div>
        </div>
    </div>

    <div v-if="receipts?.links?.length" class="mt-4 flex flex-wrap gap-2">
        <button
            v-for="link in receipts.links"
            :key="link.label"
            :disabled="!link.url"
            @click="link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })"
            v-html="link.label"
            class="rounded-md border px-3 py-1.5 text-xs font-semibold transition"
            :class="link.active ? 'border-navy-dark bg-navy-dark text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50'"
        />
    </div>

    <Modal :show="showReceiptModal" :title="editingReceiptId ? 'Edit Receipt' : 'Add Receipt'" subtitle="Receipt number and receipt type are required." max-width="2xl" @close="showReceiptModal = false">
        <form @submit.prevent="saveReceipt" class="space-y-4">
            <SystemAlert v-if="receiptErrorMessages.length" tone="error" title="Please correct the following before saving" :messages="receiptErrorMessages" />

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-gray-700">Receipt No. <span class="text-red-600">*</span></label>
                    <input v-model="receiptForm.receipt_no" type="text" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-navy focus:ring-navy" :class="{ 'border-red-400': receiptForm.errors.receipt_no }" />
                    <p v-if="receiptForm.errors.receipt_no" class="text-xs text-red-600">{{ receiptForm.errors.receipt_no }}</p>
                </div>
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-gray-700">Receipt Type <span class="text-red-600">*</span></label>
                    <input
                        v-model="receiptForm.receipt_type"
                        list="receipt-type-options"
                        type="text"
                        required
                        placeholder="Example: Enrollment"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-navy focus:ring-navy"
                        :class="{ 'border-red-400': receiptForm.errors.receipt_type }"
                    />
                    <datalist id="receipt-type-options">
                        <option v-for="option in termOptions.filter(option => option.value)" :key="option.value" :value="option.value" />
                    </datalist>
                    <p v-if="receiptForm.errors.receipt_type" class="text-xs text-red-600">{{ receiptForm.errors.receipt_type }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-gray-700">Date <span class="text-red-600">*</span></label>
                    <input v-model="receiptForm.date_encoded" type="date" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-navy focus:ring-navy" :class="{ 'border-red-400': receiptForm.errors.date_encoded }" />
                    <p v-if="receiptForm.errors.date_encoded" class="text-xs text-red-600">{{ receiptForm.errors.date_encoded }}</p>
                </div>
            </div>

            <div class="space-y-1">
                <label class="block text-sm font-medium text-gray-700">Source <span class="text-red-600">*</span></label>
                <input v-model="receiptForm.source" type="text" required placeholder="Example: Enrollment Collections" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-navy focus:ring-navy" :class="{ 'border-red-400': receiptForm.errors.source }" />
                <p v-if="receiptForm.errors.source" class="text-xs text-red-600">{{ receiptForm.errors.source }}</p>
            </div>

            <div class="space-y-1">
                <label class="block text-sm font-medium text-gray-700">Description <span class="text-red-600">*</span></label>
                <input v-model="receiptForm.description" type="text" required placeholder="Example: Premidterm Assessment" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-navy focus:ring-navy" :class="{ 'border-red-400': receiptForm.errors.description }" />
                <p v-if="receiptForm.errors.description" class="text-xs text-red-600">{{ receiptForm.errors.description }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-gray-700">Amount <span class="text-red-600">*</span></label>
                    <input v-model="receiptForm.amount" type="number" min="0" step="0.01" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-navy focus:ring-navy" :class="{ 'border-red-400': receiptForm.errors.amount }" />
                    <p v-if="receiptForm.errors.amount" class="text-xs text-red-600">{{ receiptForm.errors.amount }}</p>
                </div>
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <input v-model="receiptForm.notes" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-navy focus:ring-navy" :class="{ 'border-red-400': receiptForm.errors.notes }" />
                    <p v-if="receiptForm.errors.notes" class="text-xs text-red-600">{{ receiptForm.errors.notes }}</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t pt-5">
                <button type="button" @click="showReceiptModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="receiptForm.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-navy">{{ receiptForm.processing ? 'Saving...' : (editingReceiptId ? 'Update Receipt' : 'Create Receipt') }}</button>
            </div>
        </form>
    </Modal>

    <Modal :show="showImportModal" title="Import Receipts CSV" subtitle="Upload cash receipt rows. Required columns: receipt_no, receipt_type, source, description, amount, date_encoded." max-width="lg" @close="showImportModal = false">
        <form @submit.prevent="importCsv" class="space-y-4">
            <SystemAlert v-if="importErrorMessages.length" tone="error" title="The receipts file could not be imported" :messages="importErrorMessages" />
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold">Required columns</p>
                <p class="mt-1 font-mono text-xs">receipt_no, receipt_type, source, description, amount, date_encoded</p>
                <p class="mt-2 text-xs">Optional columns: <span class="font-mono">income_no</span>, <span class="font-mono">notes</span></p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">CSV File</label>
                <input type="file" accept=".csv,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm" required @change="e => importForm.csv_file = e.target.files?.[0] || null" />
            </div>
            <div class="flex items-center justify-end gap-3 border-t pt-5">
                <button type="button" @click="showImportModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</button>
                <button type="submit" :disabled="importForm.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">{{ importForm.processing ? 'Importing...' : 'Import CSV' }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
