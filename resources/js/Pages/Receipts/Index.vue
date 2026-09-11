<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
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
const importForm = useForm({ csv_file: null })
const receiptItems = computed(() => props.receipts?.data || [])
const importErrorMessages = computed(() => Object.values(importForm.errors || {}).flat().filter(Boolean))

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
</script>

<template>
<Head title="Receipts" />
<AppLayout>
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Receipts</h2>
            <p class="text-sm text-gray-500">Cash receipts from enrollment, premidterm, midterm, pre-final, and final exam collections</p>
        </div>
        <div class="flex flex-wrap gap-2">
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

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-navy-dark"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Receipts</p>
                <p class="mt-0.5 text-xl font-extrabold text-navy-dark">{{ PESO }}{{ fmt(summary?.totalAmount) }}</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-emerald-500"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Receipt Records</p>
                <p class="mt-0.5 text-xl font-extrabold text-slate-800">{{ summary?.recordCount || 0 }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ summary?.withReceiptNo || 0 }} with receipt no.</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="h-1 bg-amber-500"></div>
            <div class="p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Top Receipt Type</p>
                <p class="mt-0.5 truncate text-xl font-extrabold text-amber-700">{{ summary?.byType?.[0]?.type || 'None' }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ summary?.byType?.[0]?.count || 0 }} record(s)</p>
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
            <div class="hidden grid-cols-[1fr_1fr_2fr_0.9fr_0.9fr] items-center gap-4 px-5 py-3 text-[11px] font-bold uppercase tracking-wider text-gray-500 md:grid">
                <div>Receipt No.</div>
                <div>Income No.</div>
                <div>Source / Description</div>
                <div>Date</div>
                <div class="text-right">Amount</div>
            </div>
            <div v-for="item in receiptItems" :key="item.id" class="grid grid-cols-1 gap-3 px-5 py-4 md:grid-cols-[1fr_1fr_2fr_0.9fr_0.9fr] md:items-center md:gap-4">
                <div>
                    <p class="text-sm font-bold text-gray-900">{{ item.receipt_no || 'No receipt no.' }}</p>
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

    <Modal :show="showImportModal" title="Import Receipts CSV" subtitle="Upload cash receipt rows. Required columns: receipt_no, source, description, amount, date_encoded." max-width="lg" @close="showImportModal = false">
        <form @submit.prevent="importCsv" class="space-y-4">
            <div v-if="importErrorMessages.length" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <p class="font-semibold">The receipts CSV could not be imported:</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li v-for="message in importErrorMessages" :key="message">{{ message }}</li>
                </ul>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold">Required columns</p>
                <p class="mt-1 font-mono text-xs">receipt_no, source, description, amount, date_encoded</p>
                <p class="mt-2 text-xs">Optional columns: <span class="font-mono">income_no</span>, <span class="font-mono">receipt_type</span>, <span class="font-mono">notes</span></p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">CSV File</label>
                <input type="file" accept=".csv,text/csv" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm" required @change="e => importForm.csv_file = e.target.files?.[0] || null" />
            </div>
            <div class="flex items-center justify-end gap-3 border-t pt-5">
                <button type="button" @click="showImportModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</button>
                <button type="submit" :disabled="importForm.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">{{ importForm.processing ? 'Importing...' : 'Import CSV' }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
