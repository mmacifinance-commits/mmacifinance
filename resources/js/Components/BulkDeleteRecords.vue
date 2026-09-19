<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import { requestErrorMessage } from '@/support/requestErrors'

const props = defineProps({ module: String, records: Array })
const opened = ref(false)
const selected = ref([])
const scope = ref('selected')
const preview = ref(null)
const confirmation = ref('')
const busy = ref(false)
const error = ref('')
const success = ref('')
const sharedIncome = computed(() => ['income', 'receipts'].includes(props.module))
watch(() => props.records, () => { selected.value = []; preview.value = null })
watch([selected, scope], () => { preview.value = null; confirmation.value = ''; error.value = '' }, { deep: true })

function open() {
    selected.value = []; scope.value = 'selected'; preview.value = null
    error.value = ''; success.value = ''; confirmation.value = ''; opened.value = true
}
function close() { if (!busy.value) opened.value = false }
function label(row) {
    return [row.income_no || row.disbursement_no || row.ref_no || `Record ${row.id}`, row.receipt_no, row.description].filter(Boolean).join(' / ')
}
async function submit() {
    if (busy.value) return
    if (!navigator.onLine) { error.value = 'Deletion is not available offline. Reconnect before continuing.'; return }
    busy.value = true; error.value = ''
    const deleting = Boolean(preview.value)
    try {
        const response = await fetch(`/financial-records/${props.module}/bulk-delete`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: JSON.stringify({ scope: scope.value, ids: selected.value, token: preview.value?.token, confirmation: confirmation.value }),
        })
        const data = await response.json().catch(() => ({}))
        if (!response.ok) {
            error.value = response.status === 422
                ? Object.values(data.errors || {}).flat().join(' ') || requestErrorMessage(422)
                : data.safe_message === true ? data.message : requestErrorMessage(response.status)
            preview.value = null; confirmation.value = ''
            return
        }
        if (deleting) {
            success.value = data.message; opened.value = false; selected.value = []; preview.value = null
            router.reload()
        } else { preview.value = data }
    } catch {
        preview.value = null; confirmation.value = ''
        error.value = deleting
            ? 'The connection was interrupted. Refresh the list to check whether deletion completed before trying again.'
            : 'The deletion preview could not load. Check your connection and try again. Nothing was deleted.'
    } finally { busy.value = false }
}
</script>

<template>
    <div>
        <button type="button" @click="open" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-100">Delete Records</button>
        <p v-if="success" role="status" class="mt-2 text-sm text-emerald-700">{{ success }}</p>
        <Modal :show="opened" title="Delete Records" max-width="2xl" @close="close">
            <div class="space-y-4 text-sm">
                <div class="border border-amber-200 bg-amber-50 p-3 text-amber-900">
                    Deletion is permanent and changes report totals. Export a copy first if needed. Protected records stop the entire batch; no partial deletion.
                    <p v-if="sharedIncome" class="mt-2 font-semibold">Income and Receipts share records. Deleting here also removes the matching entry from the other page.</p>
                    <p v-if="module === 'disbursements'" class="mt-2">Deleting posted payments reverses expenditure totals. Only the Head of Finance can delete approved or posted payments.</p>
                </div>
                <div v-if="error" role="alert" class="border border-red-200 bg-red-50 p-3 text-red-700">{{ error }}</div>
                <fieldset :disabled="busy || Boolean(preview)" class="space-y-3">
                    <label class="flex items-center gap-2"><input v-model="scope" type="radio" value="selected">Choose records from this page</label>
                    <label class="flex items-center gap-2"><input v-model="scope" type="radio" value="all">Delete all records in this module (all pages, ignores filters)</label>
                    <div v-if="scope === 'selected'" class="max-h-64 overflow-y-auto border border-gray-200">
                        <label class="flex items-center gap-2 border-b bg-gray-50 p-3 font-semibold">
                            <input type="checkbox" :checked="records.length > 0 && selected.length === records.length" @change="selected = $event.target.checked ? records.map(row => row.id) : []">Select this page
                        </label>
                        <label v-for="row in records" :key="row.id" class="flex items-start gap-2 border-b p-3">
                            <input v-model="selected" type="checkbox" :value="row.id" class="mt-1"><span class="break-words">{{ label(row) }}</span>
                        </label>
                        <p v-if="!records.length" class="p-3 text-gray-500">No records on this page.</p>
                    </div>
                </fieldset>
                <div v-if="preview" class="space-y-3 border border-rose-200 bg-rose-50 p-3">
                    <p class="font-semibold">Permanently delete {{ preview.count }} record(s)? Total amount: {{ Number(preview.amount).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' }) }}.</p>
                    <label class="block">Type DELETE to confirm<input v-model="confirmation" :disabled="busy" class="mt-1 block w-full border border-gray-300 bg-white px-3 py-2" autocomplete="off"></label>
                </div>
            </div>
            <template #footer>
                <button :disabled="busy" @click="close" class="border border-gray-300 px-4 py-2 text-sm">Cancel</button>
                <button :disabled="busy || (scope === 'selected' && !selected.length) || (preview && confirmation !== 'DELETE')" @click="submit" class="bg-rose-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ busy ? 'Please wait...' : preview ? 'Permanently Delete' : 'Review Deletion' }}</button>
            </template>
        </Modal>
    </div>
</template>
