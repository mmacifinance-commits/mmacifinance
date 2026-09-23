<script setup>
import FinancialReportTables from '@/Components/FinancialReportTables.vue'
import { Head, Link } from '@inertiajs/vue3'
import { nextTick } from 'vue'

const props = defineProps({
    reportType: String,
    sections: Array,
    accountTitleLabel: String,
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

            <div class="report-tables"><FinancialReportTables :sections="sections" /></div>
            <section class="mt-4 text-[10px] leading-relaxed">
                <p class="font-bold">Account Title: {{ accountTitleLabel }}</p>
            </section>

            <section class="mt-10 grid grid-cols-2 gap-12">
                <div class="border-t border-black pt-2 text-center text-xs">Prepared by</div>
                <div class="border-t border-black pt-2 text-center text-xs">Reviewed / Approved by</div>
            </section>
        </main>
    </div>
</template>

<style>
@media screen and (max-width: 820px) {
    .report-sheet {
        width: 100%;
        min-height: 0;
        padding: 1rem;
        margin: 0;
    }

    .report-sheet > header {
        grid-template-columns: 1fr;
        justify-items: center;
    }

    .report-sheet > header h1 {
        font-size: 1.125rem;
    }

    .report-sheet > header p {
        overflow-wrap: anywhere;
    }

    .report-sheet > section.grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
    }

    .report-sheet > section.grid > div {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .report-sheet .report-tables {
        overflow-x: auto;
        max-width: 100%;
    }

    .report-sheet .report-table {
        min-width: 680px;
        font-size: 11px;
    }
}

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
