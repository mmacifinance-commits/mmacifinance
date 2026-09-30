<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
defineProps({ records: Object, canCreate: Boolean })
const form = useForm({ as_of_date: new Date().toISOString().slice(0, 10), opening_balance: 0, actual_cash: 0, bank_balance: 0, deposits_in_transit: 0, outstanding_payments: 0, notes: '' })
const fields = { opening_balance: 'Opening balance before the first recorded transaction', actual_cash: 'Counted cash on hand', bank_balance: 'Bank statement closing balance', deposits_in_transit: 'Deposits recorded but not yet credited by bank', outstanding_payments: 'Posted payments not yet deducted by bank' }
const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value))
</script>

<template>
    <Head title="Cash & Bank Reconciliation" />
    <AppLayout>
        <div class="space-y-6">
            <div><h1 class="text-2xl font-bold text-slate-900">Cash &amp; Bank Reconciliation</h1><p class="mt-2 text-sm text-slate-600">Compare all recorded receipts and posted payments through the selected date with counted cash and bank statements. Combine all bank accounts. Each saved record keeps its original totals.</p></div>
            <form v-if="canCreate" @submit.prevent="form.post('/reconciliations')" class="rounded-xl border bg-white p-6 space-y-4">
                <label class="block text-sm font-semibold">As of date<input v-model="form.as_of_date" type="date" required class="block mt-1 rounded border p-2" /></label>
                <div class="grid gap-4 md:grid-cols-2"><label v-for="(label, key) in fields" :key="key" class="block text-sm font-semibold">{{ label }}<input v-model="form[key]" type="number" step="0.01" required class="block w-full mt-1 rounded border p-2" /></label></div>
                <label class="block text-sm font-semibold">Explanation / statement reference<textarea v-model="form.notes" maxlength="3000" class="block w-full mt-1 rounded border p-2" placeholder="Required when balances differ. Include the statement or cash-count reference." /></label>
                <p class="text-sm text-slate-600">Book balance = opening balance + receipts − posted payments. Adjusted actual balance = counted cash + bank balance + deposits in transit − outstanding payments. This records a comparison; it does not change the ledger.</p>
                <p v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-700">{{ error }}</p>
                <button :disabled="form.processing" class="rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50">Save reconciliation</button>
            </form>
            <div v-for="record in records.data" :key="record.id" class="rounded-xl border bg-white p-5 space-y-3">
                <div class="flex justify-between gap-3"><h2 class="font-bold">{{ record.as_of_date }}</h2><span :class="Number(record.difference) === 0 ? 'text-green-700' : 'text-red-700'">{{ Number(record.difference) === 0 ? 'Balanced' : 'Difference: ' + money(record.difference) }}</span></div>
                <div class="grid gap-2 text-sm md:grid-cols-3"><div>Receipts: {{ money(record.receipts) }}</div><div>Posted payments: {{ money(record.payments) }}</div><div class="font-semibold">Book balance: {{ money(record.book_balance) }}</div><div v-for="(label, key) in fields" :key="key">{{ label }}: {{ money(record[key]) }}</div></div>
                <p class="whitespace-pre-wrap text-sm">{{ record.notes }}</p><p class="text-xs text-slate-500">Recorded by {{ record.created_by_name }} ({{ record.created_by_role }}) · {{ new Date(record.created_at).toLocaleString() }}</p>
            </div>
            <p v-if="!records.data.length" class="text-slate-500">No reconciliations recorded yet.</p>
            <div class="flex gap-4"><Link v-if="records.prev_page_url" :href="records.prev_page_url">Previous</Link><Link v-if="records.next_page_url" :href="records.next_page_url">Next</Link></div>
        </div>
    </AppLayout>
</template>
