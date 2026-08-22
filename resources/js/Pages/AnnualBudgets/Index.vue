<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router, usePage, Link } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'

const props = defineProps({ budgets: Array, categories: Array, particulars: Array, availableYears: Array })
const perms = computed(() => usePage().props.permissions || {})
const FULL_YEAR_SEMESTER = 'Full Year (Jan-Dec)'
const CANONICAL_SEMESTERS = [FULL_YEAR_SEMESTER, '1st Semester', '2nd Semester', 'Summer']

const showNewBudget = ref(false)
const budgetForm = useForm({ year: new Date().getFullYear(), semester: FULL_YEAR_SEMESTER })

function normalizeSemester(value) {
    const semester = String(value || '').trim()
    const lower = semester.toLowerCase()

    if (!semester || lower === 'full year' || lower === 'full year (jan-dec)' || lower === 'full year (jan - dec)' || lower === 'full year (jan–dec)' || lower === 'full year (jan – dec)') {
        return FULL_YEAR_SEMESTER
    }

    return semester
}

const semesterOptionsForYear = computed(() => {
    const selectedYear = Number(budgetForm.year)
    const yearSemesters = (props.budgets || [])
        .filter((budget) => Number(budget.year) === selectedYear)
        .map((budget) => normalizeSemester(budget.semester))
        .filter(Boolean)

    const uniqueSemesters = [...new Set(yearSemesters)]
    return uniqueSemesters.length ? uniqueSemesters : CANONICAL_SEMESTERS
})

watch(semesterOptionsForYear, (options) => {
    if (!options.includes(budgetForm.semester)) {
        budgetForm.semester = options[0] || FULL_YEAR_SEMESTER
    }
}, { immediate: true })

function fmt(v) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v) }

function createBudget() {
    budgetForm.post('/annual-budgets', { onSuccess: () => { showNewBudget.value = false } })
}
function removeBudget(id) {
    if (confirm('Delete this entire budget year and all its items?')) router.delete(`/annual-budgets/${id}`)
}

function budgetTotal(items, field) { return items ? items.reduce((s, i) => s + Number(i[field] || 0), 0) : 0 }
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
            <h2 class="text-xl font-bold text-gray-900">Annual Budget Records</h2>
            <p class="text-sm text-gray-500">Manage annual reference numbers and monthly allocations</p>
        </div>
        <button v-if="perms.canManageBudget" @click="showNewBudget = true" data-onboarding-target="budget-open" data-onboarding-click="budget-open" class="rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">
            New Annual Budget
        </button>
    </div>

    <!-- Budget Table -->
    <div class="rounded-lg bg-white shadow-sm border border-gray-200 overflow-hidden" data-onboarding-target="budget-table" data-onboarding-click="budget-table">
        <div class="overflow-x-auto w-full pb-4">
            <table class="w-full text-sm min-w-max whitespace-nowrap">
                <thead>
                    <tr class="bg-navy-dark text-white border-b-2 border-mustard">
                        <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-white">Annual Ref No.</th>
                        <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-white">Fiscal Year</th>
                        <th class="px-5 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-white">Total Appropriation</th>
                        <th class="px-5 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-white">Total Expenditure</th>
                        <th class="px-5 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-white">Balance</th>
                        <th class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-white">Utilization Rate</th>
                        <th class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-white">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="budget in budgets" :key="budget.id" class="border-b border-gray-100 hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-4 font-mono font-semibold text-navy align-middle">
                            <span class="px-2 py-1 bg-slate-100 rounded text-xs border border-slate-200">{{ budget.ref_no || ('AB-' + budget.year + '-000' + budget.id) }}</span>
                        </td>
                        <td class="px-5 py-4 font-bold text-gray-900 align-middle">{{ budget.year }}{{ budget.semester ? ' — ' + budget.semester : '' }}</td>
                        <td class="px-5 py-4 text-right text-gray-700 font-medium align-middle">₱{{ fmt(budgetTotal(budget.items, 'appropriation')) }}</td>
                        <td class="px-5 py-4 text-right text-gray-700 font-medium align-middle">₱{{ fmt(budgetTotal(budget.items, 'expenditure')) }}</td>
                        <td class="px-5 py-4 text-right text-gray-700 font-medium align-middle">₱{{ fmt(budgetTotal(budget.items, 'appropriation') - budgetTotal(budget.items, 'expenditure')) }}</td>
                        <td class="px-5 py-4 text-center align-middle">
                            <span :class="parseFloat(utilRate(budget.items)) > 50 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'" class="font-bold px-2.5 py-1 rounded-full text-xs">
                                {{ utilRate(budget.items) }}%
                            </span>
                        </td>
                        <td class="px-5 py-4 text-center align-middle">
                            <div class="inline-flex items-center gap-2">
                                <Link :href="`/annual-budgets/${budget.id}`" class="px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-md text-xs font-semibold shadow-sm transition-all duration-150 border border-blue-200">
                                    Manage Items
                                </Link>
                                <button v-if="perms.canManageBudget" @click="removeBudget(budget.id)" class="px-3 py-1.5 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-md text-xs font-semibold shadow-sm transition-all duration-150 border border-rose-200">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!budgets.length">
                        <td colspan="7" class="px-5 py-8 text-center text-gray-400">No annual budgets recorded yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-2.5 bg-gray-50 text-xs text-gray-500 border-t">
            Total Records: {{ budgets.length }}
        </div>
    </div>

    <!-- New Budget Modal -->
    <Modal :show="showNewBudget" title="New Annual Budget" subtitle="Create a new annual budget record with an automatic annual reference number." @close="showNewBudget = false">
        <form @submit.prevent="createBudget">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Fiscal Year</label>
                <input v-model.number="budgetForm.year" type="number" min="2000" max="2100" placeholder="e.g. 2026" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" required />
                <p v-if="budgetForm.errors.year" class="mt-1 text-xs text-red-500">{{ budgetForm.errors.year }}</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Semester (optional)</label>
                <select v-model="budgetForm.semester" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                    <option v-for="semester in semesterOptionsForYear" :key="semester" :value="semester">
                        {{ semester }}
                    </option>
                </select>
            </div>
            <div class="flex items-center justify-end gap-3 pt-4 border-t">
                <button type="button" @click="showNewBudget = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="budgetForm.processing" data-onboarding-target="budget-create" data-onboarding-click="budget-create" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy transition shadow-sm">Create Annual Budget</button>
            </div>
        </form>
    </Modal>
</AppLayout>
</template>
