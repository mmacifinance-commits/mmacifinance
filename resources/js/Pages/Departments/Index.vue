<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const perms = computed(() => usePage().props.permissions || {})

const props = defineProps({
    departments: { type: Array, default: () => [] },
    canManageResponsibilityCenters: { type: Boolean, default: false },
})

const canManage = computed(() => props.canManageResponsibilityCenters || perms.value.isSuperAdmin)
const showModal = ref(false)
const editing = ref(null)
const form = useForm({ name: '', code: '' })

function openCreate() {
    form.reset()
    editing.value = null
    showModal.value = true
}

function openEdit(department) {
    form.name = department.name
    form.code = department.code
    editing.value = department.id
    showModal.value = true
}

function save() {
    if (editing.value) {
        form.put(`/departments/${editing.value}`, { onSuccess: () => { showModal.value = false } })
        return
    }

    form.post('/departments', { onSuccess: () => { showModal.value = false } })
}

function remove(id) {
    if (confirm('Delete this responsibility center?')) {
        router.delete(`/departments/${id}`)
    }
}
</script>

<template>
    <Head title="Responsibility Centers" />
    <AppLayout>
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Responsibility Centers</h2>
                <p class="text-sm text-gray-500">
                    Manage programs and centers used across account titles, budgets, expenses, and reports.
                </p>
            </div>
            <button
                v-if="canManage"
                @click="openCreate"
                class="rounded-lg bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-navy"
            >
                Add Responsibility Center
            </button>
        </div>

        <div
            v-if="!canManage"
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800"
        >
            Head of Finance only can add, edit, or delete responsibility centers.
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="w-full overflow-x-auto pb-4">
                <table class="w-full min-w-max whitespace-nowrap text-sm">
                    <thead>
                        <tr class="border-b-2 border-mustard bg-navy-dark text-white">
                            <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-white">
                                Responsibility Center
                            </th>
                            <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-white">
                                Code
                            </th>
                            <th class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-white">
                                Linked Account Titles
                            </th>
                            <th
                                v-if="canManage"
                                class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-white"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="department in departments"
                            :key="department.id"
                            class="border-b border-gray-100 transition-colors hover:bg-gray-50/50"
                        >
                            <td class="px-5 py-3.5 align-middle font-medium text-gray-800">
                                {{ department.name }}
                            </td>
                            <td class="px-5 py-3.5 align-middle">
                                <span class="rounded-md bg-navy/10 px-2.5 py-1 text-xs font-semibold text-navy">
                                    {{ department.code }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-center align-middle text-gray-600">
                                <div class="mx-auto flex max-w-xl flex-wrap justify-center gap-1.5">
                                    <span
                                        v-for="particular in department.particulars"
                                        :key="particular.id"
                                        class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                                    >
                                        {{ particular.particular }}
                                    </span>
                                    <span
                                        v-if="!department.particulars || department.particulars.length === 0"
                                        class="text-gray-400"
                                    >
                                        -
                                    </span>
                                </div>
                            </td>
                            <td v-if="canManage" class="px-5 py-3.5 text-center align-middle">
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        @click="openEdit(department)"
                                        class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm transition-all duration-150 hover:bg-indigo-100"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="remove(department.id)"
                                        class="rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 shadow-sm transition-all duration-150 hover:bg-rose-100"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="departments.length === 0">
                            <td :colspan="canManage ? 4 : 3" class="px-5 py-10 text-center text-gray-500">
                                No responsibility centers found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t bg-gray-50 px-5 py-2.5 text-xs text-gray-500">
                Total Records: {{ departments.length }}
            </div>
        </div>

        <Modal
            :show="showModal"
            :title="editing ? 'Edit Responsibility Center' : 'Add Responsibility Center'"
            :subtitle="editing ? 'Update responsibility center details.' : 'Create a new responsibility center.'"
            @close="showModal = false"
        >
            <form @submit.prevent="save">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">
                            Responsibility Center Name
                        </label>
                        <input
                            v-model="form.name"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
                            required
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Center Code</label>
                        <input
                            v-model="form.code"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
                            required
                        />
                        <p v-if="form.errors.code" class="mt-1 text-xs text-red-500">{{ form.errors.code }}</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-end gap-3 border-t pt-5">
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
                        class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-navy"
                    >
                        {{ form.processing ? 'Saving...' : (editing ? 'Update Responsibility Center' : 'Save Responsibility Center') }}
                    </button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
