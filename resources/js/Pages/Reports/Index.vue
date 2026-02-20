<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({ budgets: Array, expenses: Array, disbursements: Array })
function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(v) }

function totalApp() { return props.budgets.reduce((s, b) => s + b.items.reduce((ss, i) => ss + Number(i.appropriation), 0), 0) }
function totalExp() { return props.expenses.reduce((s, e) => s + Number(e.amount), 0) }
function totalDsb() { return props.disbursements.reduce((s, d) => s + Number(d.amount), 0) }
</script>

<template>
<Head title="Financial Reports" />
<AppLayout>
    <h2 class="text-xl font-bold text-gray-900 mb-6">Financial Reports</h2>

    <!-- Summary Cards -->
    <div class="grid gap-5 md:grid-cols-3 mb-8">
        <div class="rounded-lg bg-white shadow-sm border overflow-hidden"><div class="h-1 bg-green-500"></div><div class="p-5"><p class="text-xs font-bold uppercase text-gray-500 mb-1">Total Appropriation</p><p class="text-2xl font-bold text-gray-900">₱{{ fmt(totalApp()) }}</p></div></div>
        <div class="rounded-lg bg-white shadow-sm border overflow-hidden"><div class="h-1 bg-red-500"></div><div class="p-5"><p class="text-xs font-bold uppercase text-gray-500 mb-1">Total Expenditure</p><p class="text-2xl font-bold text-gray-900">₱{{ fmt(totalExp()) }}</p></div></div>
        <div class="rounded-lg bg-white shadow-sm border overflow-hidden"><div class="h-1 bg-mustard"></div><div class="p-5"><p class="text-xs font-bold uppercase text-gray-500 mb-1">Total Disbursements</p><p class="text-2xl font-bold text-gray-900">₱{{ fmt(totalDsb()) }}</p></div></div>
    </div>

    <!-- Category breakdown -->
    <div class="rounded-lg bg-white shadow-sm border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b bg-gray-50"><h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Budget by Year</h3></div>
        <table class="w-full text-sm">
            <thead><tr class="bg-navy-dark">
                <th class="px-5 py-3 text-left text-xs font-bold uppercase text-mustard">Year</th>
                <th class="px-5 py-3 text-right text-xs font-bold uppercase text-mustard">Appropriation</th>
                <th class="px-5 py-3 text-right text-xs font-bold uppercase text-mustard">Expenditure</th>
                <th class="px-5 py-3 text-center text-xs font-bold uppercase text-mustard">Utilization</th>
            </tr></thead>
            <tbody>
                <tr v-for="b in budgets" :key="b.id" class="border-b hover:bg-gray-50/50">
                    <td class="px-5 py-3 font-bold text-gray-900">{{ b.year }}</td>
                    <td class="px-5 py-3 text-right">{{ fmt(b.items.reduce((s,i) => s + Number(i.appropriation), 0)) }}</td>
                    <td class="px-5 py-3 text-right">{{ fmt(b.items.reduce((s,i) => s + Number(i.expenditure), 0)) }}</td>
                    <td class="px-5 py-3 text-center">
                        <div class="relative h-5 rounded-full overflow-hidden bg-gray-200">
                            <div class="absolute left-0 top-0 h-full bg-mustard rounded-full transition-all"
                                :style="{ width: (b.items.reduce((s,i)=>s+Number(i.appropriation),0) > 0 ? Math.min(100, b.items.reduce((s,i)=>s+Number(i.expenditure),0) / b.items.reduce((s,i)=>s+Number(i.appropriation),0) * 100) : 0) + '%' }">
                            </div>
                            <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-gray-700">
                                {{ b.items.reduce((s,i)=>s+Number(i.appropriation),0) > 0 ? (b.items.reduce((s,i)=>s+Number(i.expenditure),0) / b.items.reduce((s,i)=>s+Number(i.appropriation),0) * 100).toFixed(1) : 0 }}%
                            </span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</AppLayout>
</template>
