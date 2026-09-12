<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed, nextTick } from 'vue'

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

const budgetScheduleRows = computed(() => {
    const groups = new Map()

    ;(props.rows || []).forEach((row) => {
        const key = row.category || 'Uncategorized'
        if (!groups.has(key)) {
            groups.set(key, {
                type: 'group',
                key,
                label: key,
                appropriation: 0,
                expenditure: 0,
                balance: 0,
                children: [],
            })
        }

        const group = groups.get(key)
        group.appropriation += Number(row.appropriation || 0)
        group.expenditure += Number(row.expenditure || 0)
        group.balance += Number(row.balance || 0)
        group.children.push({ type: 'item', ...row })
    })

    return Array.from(groups.values()).flatMap((group) => [group, ...group.children])
})

const receiptTotal = computed(() => (props.receiptRows || []).reduce((sum, row) => sum + Number(row.amount || 0), 0))
const disbursementTotal = computed(() => (props.disbursementRows || []).reduce((sum, row) => sum + Number(row.amount || 0), 0))

const printReport = async () => {
    await nextTick()

    requestAnimationFrame(() => {
        window.focus()
        window.print()
    })
}

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
                @click="printReport"
                class="border border-mustard bg-mustard px-4 py-2 text-sm font-bold text-navy-dark hover:bg-[#c99a2c]"
            >
                Print / Save PDF
            </button>
        </div>

        <main class="report-sheet mx-auto my-6 min-h-[297mm] w-[210mm] bg-white p-[14mm] shadow-2xl print:m-0 print:min-h-0 print:w-auto print:shadow-none">
            <header class="grid grid-cols-[110px_1fr_150px] items-center gap-4 border-b-[3px] border-black pb-2">
                <div>
                    <img src="/images/logo.png" alt="MMACI Logo" class="h-[92px] w-[92px] object-contain" />
                </div>

                <div class="text-center leading-tight">
                    <h1 class="m-0 text-[22px] font-extrabold tracking-wide text-[#17456e]">
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

            <section v-if="reconciliationWarnings?.length" class="my-4 border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                <p class="font-black uppercase tracking-wider">Reconciliation Warnings</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="warning in reconciliationWarnings" :key="warning">{{ warning }}</li>
                </ul>
            </section>

            <table v-if="shouldShowBudgetRows()" class="report-table mt-5 w-full table-fixed border-collapse text-[11px]">
                <colgroup>
                    <col class="w-[24%]" />
                    <col class="w-[16%]" />
                    <col class="w-[25%]" />
                    <col class="w-[12%]" />
                    <col class="w-[14%]" />
                    <col class="w-[9%]" />
                </colgroup>
                <thead>
                    <tr>
                        <th colspan="6" class="border border-black bg-white px-2 py-2 text-left text-[11px] font-black uppercase tracking-wider text-black">
                            Budget Utilization
                        </th>
                    </tr>
                    <tr class="bg-white text-black">
                        <th class="border border-black px-2 py-2 text-left uppercase">Programs / Projects</th>
                        <th class="border border-black px-2 py-2 text-right uppercase">Appropriation</th>
                        <th class="border border-black px-2 py-2 text-right uppercase">Total Cost Incurred to Date</th>
                        <th class="border border-black px-2 py-2 text-right uppercase">Balance</th>
                        <th class="border border-black px-2 py-2 text-right uppercase">% Utilization</th>
                        <th class="border border-black px-2 py-2 text-left uppercase">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in budgetScheduleRows" :key="row.type === 'group' ? `group-${row.key}` : `${row.ref_no}-${row.account_title}`" :class="row.type === 'group' ? 'bg-gray-100 font-black uppercase' : ''">
                        <td class="border border-black px-2 py-1.5 align-top" :class="row.type === 'item' ? 'pl-6' : ''">
                            <template v-if="row.type === 'group'">{{ row.label }}</template>
                            <template v-else>
                                {{ row.account_title }}<br />
                                <span class="text-[10px] text-slate-600">{{ row.responsibility_center }} · {{ row.allocation_month }} · {{ row.ref_no }}</span>
                            </template>
                        </td>
                        <td class="border border-black px-2 py-1.5 text-right align-top">{{ PESO }}{{ fmt(row.appropriation) }}</td>
                        <td class="border border-black px-2 py-1.5 text-right align-top">{{ PESO }}{{ fmt(row.expenditure) }}</td>
                        <td class="border border-black px-2 py-1.5 text-right align-top">{{ PESO }}{{ fmt(row.balance) }}</td>
                        <td class="border border-black px-2 py-1.5 text-right align-top">{{ utilization(row.appropriation, row.expenditure) }}%</td>
                        <td class="border border-black px-2 py-1.5 align-top">{{ row.type === 'group' ? '' : 'Posted disbursements only' }}</td>
                    </tr>
                    <tr v-if="!budgetScheduleRows.length">
                        <td colspan="6" class="border border-black px-2 py-6 text-center text-slate-500">
                            No records match the selected report filters.
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-black">
                        <td class="border border-black px-2 py-2">TOTAL</td>
                        <td class="border border-black px-2 py-2 text-right">{{ PESO }}{{ fmt(totals?.appropriation) }}</td>
                        <td class="border border-black px-2 py-2 text-right">{{ PESO }}{{ fmt(totals?.expenditure) }}</td>
                        <td class="border border-black px-2 py-2 text-right">{{ PESO }}{{ fmt(totals?.balance) }}</td>
                        <td class="border border-black px-2 py-2 text-right">{{ utilization(totals?.appropriation, totals?.expenditure) }}%</td>
                        <td class="border border-black px-2 py-2"></td>
                    </tr>
                </tfoot>
            </table>

            <table v-if="shouldShowReceiptRows()" class="report-table mt-5 w-full table-fixed border-collapse text-[11px]">
                <colgroup>
                    <col class="w-[15%]" />
                    <col class="w-[15%]" />
                    <col class="w-[16%]" />
                    <col class="w-[28%]" />
                    <col class="w-[16%]" />
                    <col class="w-[10%]" />
                </colgroup>
                <thead>
                    <tr>
                        <th colspan="6" class="border border-black bg-white px-2 py-2 text-left text-[11px] font-black uppercase tracking-wider text-black">
                            Cash Receipts
                        </th>
                    </tr>
                    <tr class="bg-white text-black">
                        <th class="border border-black px-2 py-2 text-left uppercase">Receipt No.</th>
                        <th class="border border-black px-2 py-2 text-left uppercase">Income No.</th>
                        <th class="border border-black px-2 py-2 text-left uppercase">Receipt Type</th>
                        <th class="border border-black px-2 py-2 text-left uppercase">Source / Description</th>
                        <th class="border border-black px-2 py-2 text-left uppercase">Receipt Date</th>
                        <th class="border border-black px-2 py-2 text-right uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in receiptRows" :key="row.id">
                        <td class="border border-black px-2 py-2">{{ row.receipt_no }}</td>
                        <td class="border border-black px-2 py-2">{{ row.income_no }}</td>
                        <td class="border border-black px-2 py-2">{{ row.receipt_type }}</td>
                        <td class="border border-black px-2 py-2"><strong>{{ row.source }}</strong><br>{{ row.description }}</td>
                        <td class="border border-black px-2 py-2">{{ row.receipt_date }}</td>
                        <td class="border border-black px-2 py-2 text-right">{{ PESO }}{{ fmt(row.amount) }}</td>
                    </tr>
                    <tr v-if="!receiptRows?.length"><td colspan="6" class="border border-black px-2 py-6 text-center text-slate-500">No receipt records match the selected report filters.</td></tr>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-black">
                        <td colspan="5" class="border border-black px-2 py-2">TOTAL CASH RECEIPTS</td>
                        <td class="border border-black px-2 py-2 text-right">{{ PESO }}{{ fmt(receiptTotal) }}</td>
                    </tr>
                </tfoot>
            </table>

            <table v-if="shouldShowDisbursementRows()" class="report-table mt-5 w-full table-fixed border-collapse text-[10px]">
                <colgroup>
                    <col class="w-[10%]" />
                    <col class="w-[13%]" />
                    <col class="w-[15%]" />
                    <col class="w-[13%]" />
                    <col class="w-[15%]" />
                    <col class="w-[12%]" />
                    <col class="w-[10%]" />
                    <col class="w-[12%]" />
                </colgroup>
                <thead>
                    <tr>
                        <th colspan="8" class="border border-black bg-white px-2 py-2 text-left text-[11px] font-black uppercase tracking-wider text-black">
                            Disbursement Details
                        </th>
                    </tr>
                    <tr class="bg-white text-black">
                        <th class="border border-black px-2 py-2 text-left uppercase">DSB No.</th>
                        <th class="border border-black px-2 py-2 text-left uppercase">Expense Ref</th>
                        <th class="border border-black px-2 py-2 text-left uppercase">Allocation Month</th>
                        <th class="border border-black px-2 py-2 text-left uppercase">Expense Date</th>
                        <th class="border border-black px-2 py-2 text-left uppercase">Disbursement Date</th>
                        <th class="border border-black px-2 py-2 text-left uppercase">Payee</th>
                        <th class="border border-black px-2 py-2 text-left uppercase">Status</th>
                        <th class="border border-black px-2 py-2 text-right uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in disbursementRows" :key="row.id">
                        <td class="border border-black px-2 py-2">{{ row.disbursement_no }}</td>
                        <td class="border border-black px-2 py-2">{{ row.expense_ref }}</td>
                        <td class="border border-black px-2 py-2">{{ row.allocation_month }}</td>
                        <td class="border border-black px-2 py-2">{{ row.expense_date }}</td>
                        <td class="border border-black px-2 py-2">{{ row.disbursement_date }}</td>
                        <td class="border border-black px-2 py-2">{{ row.pay_to }}</td>
                        <td class="border border-black px-2 py-2 uppercase">{{ row.status }}</td>
                        <td class="border border-black px-2 py-2 text-right">{{ PESO }}{{ fmt(row.amount) }}</td>
                    </tr>
                    <tr v-if="!disbursementRows?.length"><td colspan="8" class="border border-black px-2 py-6 text-center text-slate-500">No disbursement records match the selected report filters.</td></tr>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-black">
                        <td colspan="7" class="border border-black px-2 py-2">TOTAL DISBURSEMENTS</td>
                        <td class="border border-black px-2 py-2 text-right">{{ PESO }}{{ fmt(disbursementTotal) }}</td>
                    </tr>
                </tfoot>
            </table>

            <section class="mt-10 grid grid-cols-2 gap-12">
                <div class="border-t border-black pt-2 text-center text-xs">Prepared by</div>
                <div class="border-t border-black pt-2 text-center text-xs">Reviewed / Approved by</div>
            </section>
        </main>
    </div>
</template>

<style>
.report-table {
    inline-size: 100%;
    max-inline-size: 100%;
    table-layout: fixed;
}

.report-table th,
.report-table td {
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
    vertical-align: top;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 0;
    }

    html,
    body {
        background: #fff !important;
        overflow: visible !important;
        width: 210mm !important;
    }

    .report-sheet {
        box-sizing: border-box !important;
        min-height: 297mm !important;
        overflow: visible !important;
        padding: 8mm !important;
        width: 210mm !important;
    }

    .report-table {
        border-collapse: collapse !important;
        font-size: 8.25px !important;
        inline-size: 100% !important;
        max-inline-size: 100% !important;
        page-break-inside: auto;
        table-layout: fixed !important;
        width: 100% !important;
    }

    .report-table thead {
        display: table-header-group;
    }

    .report-table tfoot {
        display: table-row-group;
    }

    .report-table tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .report-table th,
    .report-table td {
        box-sizing: border-box !important;
        line-height: 1.15 !important;
        max-width: 0 !important;
        overflow-wrap: anywhere !important;
        padding: 3.5px !important;
        white-space: normal !important;
        word-break: break-word !important;
    }

    .report-table .text-right {
        text-align: right;
    }

    .report-table + .report-table {
        margin-top: 7mm !important;
    }

    .report-sheet section,
    .report-sheet header,
    .report-sheet table {
        max-width: 100% !important;
    }

    body * {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
