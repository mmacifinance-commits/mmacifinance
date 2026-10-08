<script setup>
import { computed, nextTick, ref, watch } from 'vue'
const props = defineProps({ modelValue: [String, Number], options: { type: Array, default: () => [] }, disabled: Boolean, invalid: Boolean })
const emit = defineEmits(['update:modelValue'])
const open = ref(false)
const search = ref('')
const searchInput = ref(null)
const trigger = ref(null)
const selected = computed(() => props.options.find(item => String(item.id) === String(props.modelValue)))
const matches = computed(() => props.options.filter(item => [item.ref_no, item.particulars, item.allocation_month_label, item.budget?.fiscal_year_label].join(' ').toLowerCase().includes(search.value.trim().toLowerCase())))
const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value || 0))
watch(() => props.disabled, value => { if (value) open.value = false })
watch(() => props.options, () => { search.value = '' })
async function toggle() {
    open.value = !open.value
    if (open.value) { search.value = ''; await nextTick(); searchInput.value?.focus() }
}
function close() { open.value = false; trigger.value?.focus() }
function choose(item) { emit('update:modelValue', item.id); close() }
</script>

<template>
    <div class="min-w-0" @keydown.esc.stop.prevent="close">
        <button ref="trigger" type="button" :disabled="disabled" :aria-expanded="open" aria-controls="allocation-choices" @click="toggle"
            class="flex w-full min-w-0 items-center justify-between gap-3 border bg-white px-3 py-2.5 text-left text-sm disabled:bg-gray-100 disabled:text-gray-500" :class="invalid ? 'border-rose-400' : 'border-gray-300'">
            <span class="min-w-0 break-words">{{ selected ? `${selected.ref_no} · ${selected.particulars || selected.allocation_month_label}` : 'Select Monthly Allocation' }}</span>
            <span aria-hidden="true" class="shrink-0">{{ open ? '▴' : '▾' }}</span>
        </button>
        <div v-if="open" id="allocation-choices" class="mt-1 min-w-0 border border-gray-200 bg-white shadow-sm">
            <div class="border-b p-2">
                <input ref="searchInput" v-model="search" type="search" aria-label="Search monthly allocations" placeholder="Search particulars, reference, or month…" class="w-full min-w-0 border border-gray-300 px-3 py-2 text-sm" @keydown.enter.prevent />
            </div>
            <div class="max-h-60 overflow-y-auto p-1" aria-label="Monthly allocations">
                <button v-for="item in matches" :key="item.id" type="button" @click="choose(item)" :aria-pressed="String(item.id) === String(modelValue)"
                    class="block w-full min-w-0 border-b border-gray-100 px-3 py-2.5 text-left hover:bg-slate-50 focus:bg-slate-100" :class="{ 'bg-indigo-50': String(item.id) === String(modelValue) }">
                    <span class="block break-words text-sm font-semibold text-gray-900">{{ item.particulars || 'Unspecified particulars' }}</span>
                    <span class="mt-1 flex flex-wrap justify-between gap-x-3 gap-y-1 text-xs text-gray-600">
                        <span class="break-words">{{ item.ref_no }} · {{ item.allocation_month_label }}</span>
                        <span class="whitespace-nowrap font-semibold text-emerald-800">Available: {{ money(item.balance) }}</span>
                    </span>
                </button>
                <p v-if="!matches.length" class="px-3 py-4 text-sm text-gray-500">No matching allocations.</p>
            </div>
        </div>
    </div>
</template>
