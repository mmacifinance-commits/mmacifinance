<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const page = usePage()
const perms = computed(() => page.props.permissions || {})

const props = defineProps({
    incomeRecords: Array,
    availableYears: Array,
    filters: Object,
    stats: Object,
})

const showModal = ref(false)
const editing = ref(null)
const form = useForm({ source: '', description: '', amount: 0, date_encoded: '', notes: '' })
const PESO = '\u20b1'

const selectedYear = ref(props.filters?.year || new Date().getFullYear())
const selectedMonth = ref(props.filters?.month || '')
const startDate = ref(props.filters?.start_date || '')
const endDate = ref(props.filters?.end_date || '')
const searchQuery = ref(props.filters?.search || '')

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(v || 0) }

function applyFilters() {
    router.get('/income', {
        year: selectedYear.value,
        month: selectedMonth.value,
        start_date: startDate.value,
        end_date: endDate.value,
        search: searchQuery.value,
    }, { preserveState: true, replace: true })
}

function resetFilters() {
    selectedYear.value = (props.availableYears && props.availableYears[0]) || new Date().getFullYear()
    selectedMonth.value = ''
    startDate.value = ''
    endDate.value = ''
    searchQuery.value = ''
    applyFilters()
}

function openCreate() {
    form.reset()
    editing.value = null
    showModal.value = true
}

function openEdit(item) {
    form.source = item.source
    form.description = item.description
    form.amount = item.amount
    form.date_encoded = item.date_encoded?.slice?.(0, 10) || ''
    form.notes = item.notes || ''
    editing.value = item.id
    showModal.value = true
}

function save() {
    const opts = { onSuccess: () => { showModal.value = false } }
    if (editing.value) form.put(`/income/${editing.value}`, opts)
    else form.post('/income', opts)
}

function remove(id) {
    if (confirm('Delete this income item?')) router.delete(`/income/${id}`)
}
</script>

<template>
<Head title="Income" />
<AppLayout>
    <div class="flex items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Income</h2>
            <p class="text-sm text-gray-500">Record collections and income entries</p>
        </div>
        <button v-if="perms.canManageIncome" @click="openCreate" class="rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">
            Add Income
        </button>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm mb-6 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Filter Income Data</p>
                <p class="text-[11px] text-gray-400 mt-1">Income records update with each filter change.</p>
            </div>
            <button @click="resetFilters" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">Reset All Filters</button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-4">
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
                    <option v-for="(m, idx) in ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']" :key="idx" :value="idx + 1">{{ m }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-700">Search</label>
                <input v-model="searchQuery" @input="applyFilters" type="text" placeholder="Search source or description..." class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm" />
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-700">Start Date</label>
                <input v-model="startDate" @change="applyFilters" type="date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm" />
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden"><div class="h-1 bg-navy-dark"></div><div class="p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Income</p><p class="text-xl font-extrabold text-navy-dark mt-0.5">{{ PESO }}{{ fmt(stats?.totalRevenue) }}</p></div></div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden"><div class="h-1 bg-slate-700"></div><div class="p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Income Records</p><p class="text-xl font-extrabold text-slate-800 mt-0.5">{{ stats?.recordCount || 0 }}</p></div></div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="bg-navy-dark px-5 py-3 flex justify-between items-center">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider">Income Records</h3>
            <span class="text-xs text-mustard font-semibold">Head of Finance only can add</span>
        </div>
        <div class="divide-y">
            <div class="hidden grid-cols-[1.15fr_1.9fr_0.9fr_0.85fr_auto] items-center gap-4 px-5 py-3 text-[11px] font-bold uppercase tracking-wider text-gray-500 sm:grid">
                <div>Income No.</div>
                <div>Source / Description</div>
                <div>Date</div>
                <div class="text-right">Amount</div>
                <div class="text-right">Actions</div>
            </div>
            <div v-for="item in incomeRecords" :key="item.id" class="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-[1.15fr_1.9fr_0.9fr_0.85fr_auto] sm:items-center sm:gap-4">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900">{{ item.income_no }}</p>
                    <p class="mt-1 text-[11px] text-gray-400 sm:hidden">{{ item.date_encoded?.slice?.(0, 10) }}</p>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold leading-5 text-gray-900">{{ item.source }}</p>
                    <p class="truncate text-xs leading-5 text-gray-500">{{ item.description }}</p>
                </div>
                <div class="hidden text-sm text-gray-600 sm:block">{{ item.date_encoded?.slice?.(0, 10) }}</div>
                <div class="sm:justify-self-end sm:text-right">
                    <p class="text-sm font-bold text-gray-900 tabular-nums">{{ PESO }}{{ fmt(item.amount) }}</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500">{{ item.notes || 'No notes' }}</p>
                </div>
                <div v-if="perms.canManageIncome" class="flex items-center gap-2 sm:justify-self-end sm:self-center">
                    <button @click="openEdit(item)" class="px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-md text-xs font-semibold border border-indigo-200">Edit</button>
                    <button @click="remove(item.id)" class="px-3 py-1.5 bg-rose-50 text-rose-700 rounded-md text-xs font-semibold border border-rose-200">Delete</button>
                </div>
            </div>
            <div v-if="!incomeRecords?.length" class="px-5 py-8 text-center text-gray-400">No income records found.</div>
        </div>
    </div>

    <Modal :show="showModal" :title="editing ? 'Edit Income' : 'Add Income'" :subtitle="editing ? 'Update income details.' : 'Create a new income item.'" max-width="lg" @close="showModal = false">
        <form @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Source</label><input v-model="form.source" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Amount</label><input v-model.number="form.amount" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Date</label><input v-model="form.date_encoded" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label><input v-model="form.description" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" required /></div>
                <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label><textarea v-model="form.notes" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"></textarea></div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-5 border-t mt-4">
                <button type="button" @click="showModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">{{ form.processing ? 'Saving...' : (editing ? 'Update' : 'Save Income') }}</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
