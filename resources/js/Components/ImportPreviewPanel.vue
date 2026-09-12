<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    module: { type: String, required: true },
    form: { type: Object, required: true },
    errorMessages: { type: Array, default: () => [] },
    requiredColumns: { type: String, default: '' },
})

const emit = defineEmits(['confirm', 'cancel'])

const preview = ref(null)
const previewing = ref(false)
const previewError = ref('')
const previewFileName = computed(() => props.form.csv_file?.name || '')
const canConfirm = computed(() => preview.value && !preview.value.missing_columns?.length && !preview.value.invalid_count && !preview.value.duplicate_count)
const amountImpact = computed(() => {
    const rows = preview.value?.valid_rows || []
    return rows.reduce((sum, item) => {
        const row = item.row || {}
        const amount = row.amount ?? row.appropriation ?? 0
        return sum + (Number(amount) || 0)
    }, 0)
})

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

function onFileChange(event) {
    props.form.csv_file = event.target.files?.[0] || null
    props.form.clearErrors?.()
    preview.value = null
    previewError.value = ''
}

async function previewImport() {
    if (!props.form.csv_file) {
        previewError.value = 'Choose a CSV or Excel file first.'
        return
    }

    previewing.value = true
    previewError.value = ''
    preview.value = null

    const data = new FormData()
    data.append('csv_file', props.form.csv_file)

    try {
        const response = await fetch(`/imports/${props.module}/preview`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
            body: data,
        })
        const body = await response.json()
        preview.value = body
        if (!response.ok) {
            previewError.value = body?.message || 'The import preview found problems.'
        }
    } catch (error) {
        previewError.value = 'Unable to preview this file. Please check your connection and try again.'
    } finally {
        previewing.value = false
    }
}

function confirmImport() {
    if (!canConfirm.value) return
    emit('confirm')
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="requiredColumns" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">Required columns</p>
            <p class="mt-1 font-mono text-xs">{{ requiredColumns }}</p>
            <p class="mt-2 text-xs">The file is previewed first. Nothing is saved until you confirm the import.</p>
        </div>

        <div v-if="errorMessages.length" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <p class="font-semibold">The file could not be imported</p>
            <ul class="mt-2 list-disc pl-5">
                <li v-for="message in errorMessages" :key="message">{{ message }}</li>
            </ul>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Excel File</label>
            <input
                type="file"
                accept=".csv,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm"
                required
                @change="onFileChange"
            />
            <p v-if="previewFileName" class="mt-1 text-xs text-gray-500">Selected: {{ previewFileName }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 border-t pt-4">
            <button type="button" @click="$emit('cancel')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Cancel
            </button>
            <div class="flex flex-wrap gap-2">
                <button type="button" :disabled="previewing || !form.csv_file" @click="previewImport" class="rounded-lg border border-navy-dark px-4 py-2 text-sm font-semibold text-navy-dark hover:bg-slate-50 disabled:opacity-50">
                    {{ previewing ? 'Previewing...' : 'Preview Import' }}
                </button>
                <button type="button" :disabled="form.processing || !canConfirm" @click="confirmImport" class="rounded-lg bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:bg-navy disabled:opacity-50">
                    {{ form.processing ? 'Importing...' : 'Confirm Import' }}
                </button>
            </div>
        </div>

        <div v-if="previewError" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ previewError }}
        </div>

        <div v-if="preview" class="space-y-4 rounded-lg border border-gray-200 bg-white p-4">
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-[10px] font-bold uppercase text-emerald-700">Valid Rows</p>
                    <p class="text-xl font-black text-emerald-900">{{ preview.valid_count || 0 }}</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-3">
                    <p class="text-[10px] font-bold uppercase text-rose-700">Invalid Rows</p>
                    <p class="text-xl font-black text-rose-900">{{ preview.invalid_count || 0 }}</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <p class="text-[10px] font-bold uppercase text-amber-700">Duplicates</p>
                    <p class="text-xl font-black text-amber-900">{{ preview.duplicate_count || 0 }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[10px] font-bold uppercase text-slate-700">Amount Impact</p>
                    <p class="text-xl font-black text-slate-900">₱{{ amountImpact.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</p>
                </div>
            </div>

            <div v-if="preview.missing_columns?.length" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                Missing columns: <span class="font-mono">{{ preview.missing_columns.join(', ') }}</span>
            </div>

            <div v-if="preview.invalid_rows?.length || preview.duplicates?.length" class="max-h-52 overflow-auto rounded-lg border border-gray-200">
                <table class="w-full text-xs">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left">Line</th>
                            <th class="px-3 py-2 text-left">Problem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in preview.invalid_rows" :key="`invalid-${row.line}-${row.message}`" class="border-t">
                            <td class="px-3 py-2">{{ row.line || '-' }}</td>
                            <td class="px-3 py-2 text-rose-700">{{ row.message }}</td>
                        </tr>
                        <tr v-for="row in preview.duplicates" :key="`duplicate-${row.line}-${row.message}`" class="border-t">
                            <td class="px-3 py-2">{{ row.line || '-' }}</td>
                            <td class="px-3 py-2 text-amber-700">{{ row.message }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="preview.valid_rows?.length" class="max-h-56 overflow-auto rounded-lg border border-gray-200">
                <table class="w-full text-xs">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left">Line</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in preview.valid_rows" :key="`valid-${row.line}`" class="border-t">
                            <td class="px-3 py-2">{{ row.line }}</td>
                            <td class="px-3 py-2 text-emerald-700">{{ row.message }}</td>
                            <td class="px-3 py-2 font-mono text-[11px] text-gray-600">{{ row.row }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

