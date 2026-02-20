<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ budgets: Array, categories: Array, particulars: Array })

const showNewBudget = ref(false)
const budgetForm = useForm({ year: new Date().getFullYear() })

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v) }

function createBudget() {
    budgetForm.post('/annual-budgets', { onSuccess: () => { showNewBudget.value = false } })
}
function removeBudget(id) {
    if (confirm('Delete this entire budget year and all its items?')) router.delete(`/annual-budgets/${id}`)
}

function budgetTotal(items, field) { return items.reduce((s, i) => s + Number(i[field] || 0), 0) }
function utilRate(items) {
    const app = budgetTotal(items, 'appropriation')
    const exp = budgetTotal(items, 'expenditure')
    return app > 0 ? ((exp / app) * 100).toFixed(1) : '0.0'
}
</script>

<template>
<Head title="Annual Budget" />
<AppLayout>
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Annual Budget</h2>
            <p class="text-sm text-gray-500">Manage annual budget allocations</p>
        </div>
        <button @click="showNewBudget = true" class="flex items-center gap-2 rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition">
            <span>+</span> New Annual Budget
        </button>
    </div>

    <!-- Budget Table -->
    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-navy-dark text-white">
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-mustard">Year</th>
                    <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-mustard">Total Appropriation</th>
                    <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-mustard">Total Expenditure</th>
                    <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-mustard">Balance</th>
                    <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Utilization Rate</th>
                    <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-mustard">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="budget in budgets" :key="budget.id" class="border-b border-gray-100 hover:bg-gray-50/50">
                    <td class="px-5 py-4 font-bold text-gray-900">{{ budget.year }}</td>
                    <td class="px-5 py-4 text-right text-gray-700">{{ fmt(budgetTotal(budget.items, 'appropriation')) }}</td>
                    <td class="px-5 py-4 text-right text-gray-700">{{ fmt(budgetTotal(budget.items, 'expenditure')) }}</td>
                    <td class="px-5 py-4 text-right text-gray-700">{{ fmt(budgetTotal(budget.items, 'appropriation') - budgetTotal(budget.items, 'expenditure')) }}</td>
                    <td class="px-5 py-4 text-center">
                        <span :class="parseFloat(utilRate(budget.items)) > 50 ? 'text-red-500' : 'text-green-600'" class="font-medium">{{ utilRate(budget.items) }}%</span>
                    </td>
                    <td class="px-5 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <Link :href="`/annual-budgets/${budget.id}`" class="text-gray-500 hover:text-navy-dark transition" title="View">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </Link>
                            <button @click="removeBudget(budget.id)" class="text-gray-400 hover:text-red-500 transition" title="Delete">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr v-if="!budgets.length">
                    <td colspan="6" class="px-5 py-8 text-center text-gray-400">No annual budgets yet.</td>
                </tr>
            </tbody>
        </table>
        <div class="px-5 py-2.5 bg-gray-50 text-xs text-gray-500 border-t">
            Total Records: {{ budgets.length }}
        </div>
    </div>

    <!-- New Budget Modal -->
    <Modal :show="showNewBudget" title="New Annual Budget" subtitle="Create a new annual budget for a fiscal year." @close="showNewBudget = false">
        <form @submit.prevent="createBudget">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Fiscal Year</label>
                <input v-model.number="budgetForm.year" type="number" min="2000" max="2100" placeholder="e.g. 2026" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-navy focus:ring-navy" required />
                <p v-if="budgetForm.errors.year" class="mt-1 text-xs text-red-500">{{ budgetForm.errors.year }}</p>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showNewBudget = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="budgetForm.processing" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition">Create</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>

<script>
import { Link } from '@inertiajs/vue3'
export default { components: { Link } }
</script>
