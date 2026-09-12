<script setup>
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
    reportType: String,
    reportLabel: String,
    period: Object,
    monthLabel: String,
    dateRangeLabel: String,
    departmentLabel: String,
    categoryLabel: String,
    rows: Array,
    receiptRows: Array,
    disbursementRows: Array,
    auditRows: Array,
    reconciliationWarnings: Array,
    totals: Object,
    generatedAt: String,
    generatedBy: Object,
})

const PESO = '₱'
const fmt = (value) => new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(value || 0))

const utilization = (appropriation, expenditure) => {
    const app = Number(appropriation || 0)
    const exp = Number(expenditure || 0)
    return app > 0 ? ((exp / app) * 100).toFixed(2) : '0.00'
}

const generatedDate = () => {
    if (!props.generatedAt) return ''
    return new Date(props.generatedAt).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const shouldShowBudgetRows = () => [
    'overall_financial',
    'budget_utilization',
    'income_vs_receipts',
    'fund_balance',
    'responsibility_center',
    'account_title_ledger',
    'closing_report',
].includes(props.reportType || 'overall_financial')

const shouldShowReceiptRows = () => [
    'overall_financial',
    'cash_receipts',
    'income_vs_receipts',
    'fund_balance',
    'closing_report',
].includes(props.reportType || 'overall_financial')

const shouldShowDisbursementRows = () => [
    'overall_financial',
    'disbursements',
    'fund_balance',
    'closing_report',
].includes(props.reportType || 'overall_financial')

const shouldShowAuditRows = () => [
    'overall_financial',
    'audit_trail',
    'closing_report',
].includes(props.reportType || 'overall_financial')
</script>

<template>
    <Head title="Generated Financial Report" />

    <div class="min-h-screen bg-slate-200 text-[#06122a] print:bg-white">
        <div class="sticky top-0 z-20 flex justify-end gap-2 bg-slate-900 px-5 py-3 shadow-lg print:hidden">
            <Link href="/reports" class="border border-slate-600 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                Back to Reports
            </Link>
            <button
                type="button"
                @click="window.print()"
                class="border border-mustard bg-mustard px-4 py-2 text-sm font-bold text-navy-dark hover:bg-[#c99a2c]"
            >
                Print / Save PDF
            </button>
        </div>

        <main class="mx-auto my-6 min-h-[8.5in] w-[11in] bg-white p-[0.45in] shadow-2xl print:m-0 print:min-h-0 print:w-auto print:p-0 print:shadow-none">
            <header class="grid grid-cols-[110px_1fr_150px] items-center gap-4 border-b-[3px] border-black pb-2">
                <div>
                    <img src="/images/logo.png" alt="MMACI Logo" class="h-[92px] w-[92px] object-contain" />
                </div>

                <div class="text-center leading-tight">
                    <h1 class="m-0 text-[22px] font-extrabold tracking-wide text-[#17456e] underline">
                        MERCHANT MARINE ACADEMY OF CARAGA, INC.
                    </h1>
                    <p class="mt-1 text-[13px]">North Montilla Boulevard, Brgy. Ong-Yiu, Butuan City, 8600</p>
                    <p class="text-[13px]">Tel. No: (085) 817 0476 Mobile No.: (+63) 917 105 9644 (Globe)</p>
                    <p class="text-[13px]">E-mail Address: mmaci2018.bxu@gmail.com or schoolpresident@mmacibutuan.edu.ph</p>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <div class="flex h-[82px] w-[74px] flex-col items-center justify-center border-2 border-sky-500 text-center text-[8px] font-bold text-slate-900">
                        <div class="mb-1 h-9 w-9 rounded-full border-[10px] border-sky-500 border-l-teal-700"></div>
                        SOCOTEC<br />ISO 9001
                    </div>
                    <div class="text-center">
                        <div class="relative h-[58px] w-[58px] text-[40px] font-black italic leading-[58px] text-slate-950 before:absolute before:left-2 before:top-4 before:h-0.5 before:w-11 before:-rotate-[32deg] before:bg-red-600 before:content-['']">
                            B
                        </div>
                        <div class="text-[7px] leading-tight">BV ACCREDITED CMS<br />ISO 9001</div>
                    </div>
                </div>
            </header>

            <section class="my-4 text-center">
                <h2 class="text-lg font-black uppercase tracking-[0.08em]">{{ reportLabel || 'Overall Financial Report' }}</h2>
                <p class="mt-1 text-xs text-slate-600">Formal generated report based on the selected fiscal period, dates, and filters</p>
            </section>

            <section class="grid grid-cols-4 gap-2">
                <div class="border border-slate-300 p-2">
                    <span class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Fiscal Year</span>
                    <strong class="mt-1 block text-xs">{{ period?.fiscal_year_label || 'No fiscal year selected' }}</strong>
                </div>
                <div class="border border-slate-300 p-2">
                    <span class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Fiscal Period</span>
                    <strong class="mt-1 block text-xs">{{ period?.period_label || 'N/A' }}</strong>
                </div>
                <div class="border border-slate-300 p-2">
                    <span class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Allocation Month</span>
                    <strong class="mt-1 block text-xs">{{ monthLabel }}</strong>
                </div>
                <div class="border border-slate-300 p-2">
                    <span class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Date Range</span>
                    <strong class="mt-1 block text-xs">{{ dateRangeLabel }}</strong>
                </div>
                <div class="border border-slate-300 p-2">
                    <span class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Responsibility Center</span>
                    <strong class="mt-1 block text-xs">{{ departmentLabel }}</strong>
                </div>
                <div class="border border-slate-300 p-2">
                    <span class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Category</span>
                    <strong class="mt-1 block text-xs">{{ categoryLabel }}</strong>
                </div>
                <div class="border border-slate-300 p-2">
                    <span class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Generated By</span>
                    <strong class="mt-1 block text-xs">{{ generatedBy?.name || 'System User' }}</strong>
                </div>
                <div class="border border-slate-300 p-2">
                    <span class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Generated At</span>
                    <strong class="mt-1 block text-xs">{{ generatedDate() }}</strong>
                </div>
            </section>

            <section class="my-4">
                <div class="border border-slate-300">
                    <div class="border-b border-slate-300 bg-slate-50 px-3 py-2 text-[11px] font-black uppercase tracking-[0.08em] text-slate-700">
                        Statement Summary
                    </div>
                    <table class="w-full border-collapse text-xs">
                        <tbody>
                            <tr>
                                <td class="w-1/2 border-b border-r border-slate-200 px-3 py-2 font-semibold">Approved Budget / Total Appropriation</td>
                                <td class="border-b border-slate-200 px-3 py-2 text-right font-mono">{{ PESO }}{{ fmt(totals?.appropriation) }}</td>
                            </tr>
                            <tr>
                                <td class="border-b border-r border-slate-200 px-3 py-2 font-semibold">Actual Receipts / Cash Received</td>
                                <td class="border-b border-slate-200 px-3 py-2 text-right font-mono">{{ PESO }}{{ fmt(totals?.receipts) }}</td>
                            </tr>
                            <tr>
                                <td class="border-b border-r border-slate-200 px-3 py-2 font-semibold">Actual Posted Disbursements</td>
                                <td class="border-b border-slate-200 px-3 py-2 text-right font-mono">{{ PESO }}{{ fmt(totals?.expenditure) }}</td>
                            </tr>
                            <tr>
                                <td class="border-b border-r border-slate-200 px-3 py-2 font-semibold">Remaining Budget Balance</td>
                                <td class="border-b border-slate-200 px-3 py-2 text-right font-mono">{{ PESO }}{{ fmt(totals?.balance) }}</td>
                            </tr>
                            <tr>
                                <td class="border-b border-r border-slate-200 px-3 py-2 font-semibold">Actual Cash On Hand</td>
                                <td class="border-b border-slate-200 px-3 py-2 text-right font-mono">{{ PESO }}{{ fmt(totals?.cashOnHand) }}</td>
                            </tr>
                            <tr>
                                <td class="border-b border-r border-slate-200 px-3 py-2 font-semibold">Pending / Approved Commitments Not Yet Posted</td>
                                <td class="border-b border-slate-200 px-3 py-2 text-right font-mono">{{ PESO }}{{ fmt(totals?.pendingCommitments) }}</td>
                            </tr>
                            <tr>
                                <td class="border-r border-slate-200 px-3 py-2 font-black">Available Cash After Commitments</td>
                                <td class="px-3 py-2 text-right font-mono font-black">{{ PESO }}{{ fmt(totals?.availableForDisbursement) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="reconciliationWarnings?.length" class="my-4 border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                <p class="font-black uppercase tracking-wider">Reconciliation Warnings</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="warning in reconciliationWarnings" :key="warning">{{ warning }}</li>
                </ul>
            </section>

            <table v-if="shouldShowBudgetRows()" class="mt-3 w-full border-collapse text-xs">
                <thead>
                    <tr class="bg-navy-dark text-white">
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Monthly Ref No.</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Allocation Month</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Responsibility Center</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Category</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Account Title</th>
                        <th class="border border-navy-dark px-2 py-2 text-right uppercase">Appropriation</th>
                        <th class="border border-navy-dark px-2 py-2 text-right uppercase">Posted Expenditure</th>
                        <th class="border border-navy-dark px-2 py-2 text-right uppercase">Balance</th>
                        <th class="border border-navy-dark px-2 py-2 text-right uppercase">Utilization</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="`${row.ref_no}-${row.account_title}`">
                        <td class="border border-slate-200 px-2 py-2 align-top">{{ row.ref_no }}</td>
                        <td class="border border-slate-200 px-2 py-2 align-top">{{ row.allocation_month }}</td>
                        <td class="border border-slate-200 px-2 py-2 align-top">{{ row.responsibility_center }}</td>
                        <td class="border border-slate-200 px-2 py-2 align-top">{{ row.category }}</td>
                        <td class="border border-slate-200 px-2 py-2 align-top">{{ row.account_title }}</td>
                        <td class="border border-slate-200 px-2 py-2 text-right align-top">{{ PESO }}{{ fmt(row.appropriation) }}</td>
                        <td class="border border-slate-200 px-2 py-2 text-right align-top">{{ PESO }}{{ fmt(row.expenditure) }}</td>
                        <td class="border border-slate-200 px-2 py-2 text-right align-top">{{ PESO }}{{ fmt(row.balance) }}</td>
                        <td class="border border-slate-200 px-2 py-2 text-right align-top">{{ fmt(row.utilization_rate) }}%</td>
                    </tr>
                    <tr v-if="!rows?.length">
                        <td colspan="9" class="border border-slate-200 px-2 py-6 text-center text-slate-500">
                            No records match the selected report filters.
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 font-black">
                        <td colspan="5" class="border border-slate-200 px-2 py-2">Grand Total</td>
                        <td class="border border-slate-200 px-2 py-2 text-right">{{ PESO }}{{ fmt(totals?.appropriation) }}</td>
                        <td class="border border-slate-200 px-2 py-2 text-right">{{ PESO }}{{ fmt(totals?.expenditure) }}</td>
                        <td class="border border-slate-200 px-2 py-2 text-right">{{ PESO }}{{ fmt(totals?.balance) }}</td>
                        <td class="border border-slate-200 px-2 py-2 text-right">{{ utilization(totals?.appropriation, totals?.expenditure) }}%</td>
                    </tr>
                </tfoot>
            </table>

            <table v-if="shouldShowReceiptRows()" class="mt-5 w-full border-collapse text-xs">
                <thead>
                    <tr class="bg-navy-dark text-white">
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Receipt No.</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Income No.</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Receipt Type</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Source / Description</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Receipt Date</th>
                        <th class="border border-navy-dark px-2 py-2 text-right uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in receiptRows" :key="row.id">
                        <td class="border border-slate-200 px-2 py-2">{{ row.receipt_no }}</td>
                        <td class="border border-slate-200 px-2 py-2">{{ row.income_no }}</td>
                        <td class="border border-slate-200 px-2 py-2">{{ row.receipt_type }}</td>
                        <td class="border border-slate-200 px-2 py-2"><strong>{{ row.source }}</strong><br>{{ row.description }}</td>
                        <td class="border border-slate-200 px-2 py-2">{{ row.receipt_date }}</td>
                        <td class="border border-slate-200 px-2 py-2 text-right">{{ PESO }}{{ fmt(row.amount) }}</td>
                    </tr>
                    <tr v-if="!receiptRows?.length"><td colspan="6" class="border border-slate-200 px-2 py-6 text-center text-slate-500">No receipt records match the selected report filters.</td></tr>
                </tbody>
            </table>

            <table v-if="shouldShowDisbursementRows()" class="mt-5 w-full border-collapse text-xs">
                <thead>
                    <tr class="bg-navy-dark text-white">
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">DSB No.</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Expense Ref</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Allocation Month</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Expense Date</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Disbursement Date</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Payee</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Status</th>
                        <th class="border border-navy-dark px-2 py-2 text-right uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in disbursementRows" :key="row.id">
                        <td class="border border-slate-200 px-2 py-2">{{ row.disbursement_no }}</td>
                        <td class="border border-slate-200 px-2 py-2">{{ row.expense_ref }}</td>
                        <td class="border border-slate-200 px-2 py-2">{{ row.allocation_month }}</td>
                        <td class="border border-slate-200 px-2 py-2">{{ row.expense_date }}</td>
                        <td class="border border-slate-200 px-2 py-2">{{ row.disbursement_date }}</td>
                        <td class="border border-slate-200 px-2 py-2">{{ row.pay_to }}</td>
                        <td class="border border-slate-200 px-2 py-2 uppercase">{{ row.status }}</td>
                        <td class="border border-slate-200 px-2 py-2 text-right">{{ PESO }}{{ fmt(row.amount) }}</td>
                    </tr>
                    <tr v-if="!disbursementRows?.length"><td colspan="8" class="border border-slate-200 px-2 py-6 text-center text-slate-500">No disbursement records match the selected report filters.</td></tr>
                </tbody>
            </table>

            <table v-if="shouldShowAuditRows()" class="mt-5 w-full border-collapse text-xs">
                <thead>
                    <tr class="bg-navy-dark text-white">
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Date</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">User</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Role</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Action</th>
                        <th class="border border-navy-dark px-2 py-2 text-left uppercase">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in auditRows" :key="row.id">
                        <td class="border border-slate-200 px-2 py-2">{{ row.created_at }}</td>
                        <td class="border border-slate-200 px-2 py-2">{{ row.user_name }}</td>
                        <td class="border border-slate-200 px-2 py-2">{{ row.user_role }}</td>
                        <td class="border border-slate-200 px-2 py-2 uppercase">{{ row.action }}</td>
                        <td class="border border-slate-200 px-2 py-2">{{ row.remarks }}</td>
                    </tr>
                    <tr v-if="!auditRows?.length"><td colspan="5" class="border border-slate-200 px-2 py-6 text-center text-slate-500">No audit records match the selected report filters.</td></tr>
                </tbody>
            </table>

            <section class="mt-10 grid grid-cols-2 gap-12">
                <div class="border-t border-black pt-2 text-center text-xs">Prepared by</div>
                <div class="border-t border-black pt-2 text-center text-xs">Reviewed / Approved by</div>
            </section>
        </main>
    </div>
</template>

<style>
@media print {
    @page {
        size: landscape;
        margin: 0.35in;
    }
}
</style>
