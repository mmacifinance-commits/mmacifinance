<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import ImportPreviewPanel from '@/Components/ImportPreviewPanel.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    categories: { type: Array, default: () => [] }
})

const page = usePage()
const canManage = computed(() => !!page.props.permissions?.canManageBudget)

const showForm = ref(false)
const showImport = ref(false)
const editing = ref(null)
const MAX_TITLES = 5

const form = useForm({ name: '', description: '' })
const importForm = useForm({ csv_file: null })

const errors = computed(() =>
    Object.values(form.errors || {}).flat().filter(Boolean)
)

const clean = value => String(value || '').trim().replace(/\s+/g, ' ')
const normalize = value => clean(value).toLowerCase()
const visibleTitles = category =>
    (category.particulars || []).slice(0, MAX_TITLES)

function resetForm() {
    editing.value = null
    form.reset()
    form.clearErrors()
}

function closeForm() {
    showForm.value = false
    resetForm()
}

function openCreate() {
    resetForm()
    showForm.value = true
}

function openEdit(category) {
    form.clearErrors()
    form.name = category.name
    form.description = category.description || ''
    editing.value = category.id
    showForm.value = true
}

function save() {
    form.clearErrors()

    const name = clean(form.name)

    if (!name)
        return form.setError('name', 'Budget category name is required.')

    if (
        props.categories.some(c =>
            normalize(c.name) === normalize(name) &&
            Number(c.id) !== Number(editing.value)
        )
    ) {
        return form.setError(
            'name',
            `"${name}" already exists as a budget category.`
        )
    }

    form.name = name
    form.description = clean(form.description)

    const options = {
        preserveScroll: true,
        onSuccess: closeForm
    }

    editing.value
        ? form.put(`/budget-categories/${editing.value}`, options)
        : form.post('/budget-categories', options)
}

function remove(id) {
    if (confirm('Warning: this cannot be undone. Delete category?'))
        router.delete(`/budget-categories/${id}`)
}

const exportCsv = () =>
    window.location.assign('/budget-categories/export-csv')

function importCsv() {
    importForm.post('/budget-categories/import-csv', {
        onSuccess: () => {
            showImport.value = false
            importForm.reset()
            importForm.clearErrors()
        }
    })
}
</script>

<template>
    <Head title="Budget Categories" />

    <AppLayout>
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900">
                    Budget Categories
                </h2>
                <p class="text-sm text-gray-500">
                    Manage budget classification categories
                </p>
            </div>

            <div v-if="canManage" class="flex flex-wrap gap-2">
                <button
                    @click="exportCsv"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    Export CSV
                </button>

                <button
                    @click="showImport = true"
                    class="rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100"
                >
                    Import CSV
                </button>

                <button
                    @click="openCreate"
                    class="rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-navy"
                >
                    Add Category
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] table-fixed text-sm">
                    <colgroup>
                        <col class="w-[20%]" />
                        <col class="w-[25%]" />
                        <col class="w-[40%]" />
                        <col v-if="canManage" class="w-[15%]" />
                    </colgroup>

                    <thead>
                        <tr class="border-b-2 border-mustard bg-navy-dark text-white">
                            <th class="px-5 py-3.5 text-left text-xs font-bold uppercase">
                                Name
                            </th>
                            <th class="px-5 py-3.5 text-left text-xs font-bold uppercase">
                                Description
                            </th>
                            <th class="px-5 py-3.5 text-center text-xs font-bold uppercase">
                                Account Titles
                            </th>
                            <th
                                v-if="canManage"
                                class="px-5 py-3.5 text-center text-xs font-bold uppercase"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="category in categories"
                            :key="category.id"
                            class="border-b border-gray-100 hover:bg-gray-50/50"
                        >
                            <td class="break-words px-5 py-4 font-medium text-gray-800">
                                {{ category.name }}
                            </td>

                            <td class="break-words px-5 py-4 text-xs leading-5 text-gray-500">
                                {{ category.description || '-' }}
                            </td>

                            <td class="px-5 py-4 text-center">
                                <div
                                    v-if="category.particulars?.length"
                                    class="mx-auto flex max-w-[520px] flex-wrap justify-center gap-1.5"
                                >
                                    <span
                                        v-for="item in visibleTitles(category)"
                                        :key="item.id"
                                        :title="item.particular"
                                        class="max-w-[220px] truncate rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700"
                                    >
                                        {{ item.particular }}
                                    </span>

                                    <span
                                        v-if="category.particulars.length > MAX_TITLES"
                                        :title="`${category.particulars.length} total Account Titles`"
                                        class="rounded-md bg-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-700"
                                    >
                                        +{{ category.particulars.length - MAX_TITLES }} more
                                    </span>
                                </div>

                                <span v-else class="text-gray-400">—</span>
                            </td>

                            <td v-if="canManage" class="px-5 py-4 text-center">
                                <div class="inline-flex gap-2">
                                    <button
                                        @click="openEdit(category)"
                                        class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        @click="remove(category.id)"
                                        class="rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!categories.length">
                            <td
                                :colspan="canManage ? 4 : 3"
                                class="px-5 py-10 text-center text-gray-500"
                            >
                                No budget categories found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t bg-gray-50 px-5 py-2.5 text-xs text-gray-500">
                Total Records: {{ categories.length }}
            </div>
        </div>

        <!-- Add / Edit -->
        <Modal
            :show="showForm"
            :title="editing ? 'Edit Category' : 'Add Category'"
            :subtitle="editing ? 'Update category details.' : 'Create a new budget category.'"
            @close="closeForm"
        >
            <form @submit.prevent="save">
                <div
                    v-if="errors.length"
                    class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    <p class="font-semibold">
                        The category could not be saved:
                    </p>

                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="error in errors" :key="error">
                            {{ error }}
                        </li>
                    </ul>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">
                            Name
                        </label>

                        <input
                            v-model="form.name"
                            maxlength="255"
                            autocomplete="off"
                            required
                            @input="form.clearErrors('name')"
                            :class="[
                                'w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-2',
                                form.errors.name
                                    ? 'border-red-400 focus:ring-red-100'
                                    : 'border-gray-300 focus:border-navy focus:ring-navy/10'
                            ]"
                        />

                        <p
                            v-if="form.errors.name"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">
                            Description
                        </label>

                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
                        />

                        <p
                            v-if="form.errors.description"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-3 border-t pt-5">
                    <button
                        type="button"
                        @click="closeForm"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy disabled:opacity-60"
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

        <!-- CSV Import -->
        <Modal
            :show="showImport"
            title="Import Budget Categories CSV"
            subtitle="Preview the file before saving budget categories."
            max-width="4xl"
            @close="showImport = false"
        >
            <ImportPreviewPanel
                module="budget-categories"
                :form="importForm"
                :error-messages="Object.values(importForm.errors || {}).flat().filter(Boolean)"
                required-columns="budget_category, description"
                @cancel="showImport = false"
                @confirm="importCsv"
            />
        </Modal>
    </AppLayout>
</template>
