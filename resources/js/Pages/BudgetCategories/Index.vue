<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const perms = computed(() => usePage().props.permissions || {})

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
})

const showModal = ref(false)
const showImportModal = ref(false)
const editing = ref(null)

const form = useForm({
    name: '',
    description: '',
})

const importForm = useForm({
    csv_file: null,
})

const formErrorMessages = computed(() =>
    Object.values(form.errors || {})
        .flat()
        .filter(Boolean)
)

// Maximum number of Account Titles displayed in each row
const MAX_VISIBLE_TITLES = 5

function visibleParticulars(category) {
    return (category.particulars || []).slice(0, MAX_VISIBLE_TITLES)
}

function openCreate() {
    form.reset()
    form.clearErrors()
    editing.value = null
    showModal.value = true
}

function openEdit(category) {
    form.clearErrors()

    form.name = category.name
    form.description = category.description || ''

    editing.value = category.id
    showModal.value = true
}

function save() {
    if (editing.value) {
        form.put(`/budget-categories/${editing.value}`, {
            onSuccess: () => {
                showModal.value = false
            },
        })

        return
    }

    form.post('/budget-categories', {
        onSuccess: () => {
            showModal.value = false
        },
    })
}

function remove(id) {
    if (
        confirm(
            'Warning: this cannot be undone. Delete category?'
        )
    ) {
        router.delete(`/budget-categories/${id}`)
    }
}

function exportCsv() {
    window.location.href = '/budget-categories/export-csv'
}

function importCsv() {
    importForm.post('/budget-categories/import-csv', {
        onSuccess: () => {
            showImportModal.value = false
            importForm.reset()
        },
    })
}
</script>

<template>
    <Head title="Budget Categories" />

    <AppLayout>
        <!-- Page Header -->
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900">
                    Budget Categories
                </h2>

                <p class="text-sm text-gray-500">
                    Manage budget classification categories
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-if="perms.canManageBudget"
                    @click="exportCsv"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                >
                    Export CSV
                </button>

                <button
                    v-if="perms.canManageBudget"
                    @click="showImportModal = true"
                    class="rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100"
                >
                    Import CSV
                </button>

                <button
                    v-if="perms.canManageBudget"
                    @click="openCreate"
                    class="rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-navy"
                >
                    Add Category
                </button>
            </div>
        </div>

        <!-- Table -->
        <div
            class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
        >
            <div class="w-full overflow-x-auto">
                <table class="w-full min-w-[900px] table-fixed text-sm">
                    <!-- Fixed column widths -->
                    <colgroup>
                        <col class="w-[20%]" />
                        <col class="w-[25%]" />
                        <col class="w-[40%]" />
                        <col
                            v-if="perms.canManageBudget"
                            class="w-[15%]"
                        />
                    </colgroup>

                    <thead>
                        <tr
                            class="border-b-2 border-mustard bg-navy-dark text-white"
                        >
                            <th
                                class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-white"
                            >
                                Name
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-white"
                            >
                                Description
                            </th>

                            <th
                                class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-white"
                            >
                                Account Titles
                            </th>

                            <th
                                v-if="perms.canManageBudget"
                                class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-white"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="category in categories"
                            :key="category.id"
                            class="border-b border-gray-100 transition-colors hover:bg-gray-50/50"
                        >
                            <!-- Category Name -->
                            <td
                                class="whitespace-normal break-words px-5 py-4 align-middle font-medium text-gray-800"
                            >
                                {{ category.name }}
                            </td>

                            <!-- Description -->
                            <td
                                class="whitespace-normal break-words px-5 py-4 align-middle text-xs leading-5 text-gray-500"
                            >
                                {{ category.description || '-' }}
                            </td>

                            <!-- Account Titles -->
                            <td
                                class="px-5 py-4 text-center align-middle text-gray-600"
                            >
                                <div
                                    v-if="
                                        category.particulars &&
                                        category.particulars.length
                                    "
                                    class="mx-auto flex max-w-[520px] flex-wrap justify-center gap-1.5"
                                >
                                    <span
                                        v-for="particular in visibleParticulars(category)"
                                        :key="particular.id"
                                        :title="particular.particular"
                                        class="inline-block max-w-[220px] truncate rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700"
                                    >
                                        {{ particular.particular }}
                                    </span>

                                    <span
                                        v-if="
                                            category.particulars.length >
                                            MAX_VISIBLE_TITLES
                                        "
                                        :title="`${category.particulars.length} total Account Titles`"
                                        class="inline-block rounded-md bg-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-700"
                                    >
                                        +{{
                                            category.particulars.length -
                                            MAX_VISIBLE_TITLES
                                        }}
                                        more
                                    </span>
                                </div>

                                <span
                                    v-else
                                    class="text-sm text-gray-400"
                                >
                                    —
                                </span>
                            </td>

                            <!-- Actions -->
                            <td
                                v-if="perms.canManageBudget"
                                class="px-5 py-4 text-center align-middle"
                            >
                                <div
                                    class="inline-flex items-center justify-center gap-2"
                                >
                                    <button
                                        @click="openEdit(category)"
                                        class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm transition-all duration-150 hover:bg-indigo-100"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        @click="remove(category.id)"
                                        class="rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 shadow-sm transition-all duration-150 hover:bg-rose-100"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="categories.length === 0">
                            <td
                                :colspan="
                                    perms.canManageBudget
                                        ? 4
                                        : 3
                                "
                                class="px-5 py-10 text-center text-gray-500"
                            >
                                No budget categories found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div
                class="border-t bg-gray-50 px-5 py-2.5 text-xs text-gray-500"
            >
                Total Records: {{ categories.length }}
            </div>
        </div>

        <!-- Add/Edit Category Modal -->
        <Modal
            :show="showModal"
            :title="
                editing
                    ? 'Edit Category'
                    : 'Add Category'
            "
            :subtitle="
                editing
                    ? 'Update category details.'
                    : 'Create a new budget category.'
            "
            @close="showModal = false"
        >
            <form @submit.prevent="save">
                <!-- Validation Errors -->
                <div
                    v-if="formErrorMessages.length"
                    class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                    role="alert"
                >
                    <p class="font-semibold">
                        The category could not be saved:
                    </p>

                    <ul class="mt-1 list-disc space-y-1 pl-5">
                        <li
                            v-for="message in formErrorMessages"
                            :key="message"
                        >
                            {{ message }}
                        </li>
                    </ul>
                </div>

                <div class="space-y-4">
                    <!-- Name -->
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700"
                        >
                            Name
                        </label>

                        <input
                            v-model="form.name"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
                            required
                        />

                        <p
                            v-if="form.errors.name"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700"
                        >
                            Description
                        </label>

                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
                        ></textarea>

                        <p
                            v-if="form.errors.description"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>
                </div>

                <!-- Modal Buttons -->
                <div
                    class="mt-4 flex items-center justify-end gap-3 border-t pt-5"
                >
                    <button
                        type="button"
                        @click="showModal = false"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-navy disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{
                            form.processing
                                ? 'Saving...'
                                : editing
                                    ? 'Update'
                                    : 'Create'
                        }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Import CSV Modal -->
        <Modal
            :show="showImportModal"
            title="Import Budget Categories CSV"
            subtitle="Required columns: budget_category, description"
            @close="showImportModal = false"
        >
            <form
                @submit.prevent="importCsv"
                class="space-y-4"
            >
                <div
                    class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                >
                    <p class="font-semibold">
                        Required columns
                    </p>

                    <p class="mt-1 font-mono text-xs">
                        budget_category, description
                    </p>

                    <p class="mt-2 text-xs">
                        Budget Categories group Account Titles and Annual
                        Budget Allocations. Use clear category names because
                        Expenditures depend on these categories later.
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700"
                    >
                        CSV File
                    </label>

                    <input
                        type="file"
                        accept=".csv,text/csv"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
                        @change="
                            (e) =>
                                importForm.csv_file =
                                    e.target.files?.[0] || null
                        "
                    />

                    <p
                        v-if="importForm.errors.csv_file"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ importForm.errors.csv_file }}
                    </p>
                </div>

                <div
                    class="flex items-center justify-end gap-3 border-t pt-5"
                >
                    <button
                        type="button"
                        @click="showImportModal = false"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        :disabled="importForm.processing"
                        class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-navy disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{
                            importForm.processing
                                ? 'Importing...'
                                : 'Import CSV'
                        }}
                    </button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>