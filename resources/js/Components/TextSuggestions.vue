<script setup>
import { computed, nextTick, ref, watch } from 'vue'

const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    required: Boolean,
    invalid: Boolean,
})
const emit = defineEmits(['update:modelValue'])
const open = ref(false)
const active = ref(-1)
const list = ref(null)
const matches = computed(() => props.options.filter(option => option.value && option.value.toLowerCase().includes(props.modelValue.trim().toLowerCase())))
watch(matches, () => { active.value = -1 })
function choose(option) {
    emit('update:modelValue', option.value)
    open.value = false
    active.value = -1
}
async function navigate(event) {
    if (event.key === 'Escape') {
        if (open.value) { event.preventDefault(); event.stopPropagation() }
        open.value = false
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault()
        open.value = true
        if (!matches.value.length) return
        active.value = (active.value + (event.key === 'ArrowDown' ? 1 : -1) + matches.value.length) % matches.value.length
        await nextTick()
        list.value?.children[active.value]?.scrollIntoView({ block: 'nearest' })
    } else if (event.key === 'Enter' && open.value && active.value >= 0) {
        event.preventDefault()
        choose(matches.value[active.value])
    }
}
</script>

<template>
    <div class="relative">
        <input :id="id" :value="modelValue" @input="emit('update:modelValue', $event.target.value); open = true" @focus="open = true" @blur="open = false; active = -1" @keydown="navigate"
            type="text" autocomplete="off" :required="required" :placeholder="placeholder" role="combobox" aria-autocomplete="list"
            :aria-expanded="open && matches.length > 0" :aria-controls="`${id}-suggestions`" :aria-activedescendant="open && active >= 0 ? `${id}-option-${active}` : undefined" :aria-invalid="invalid"
            class="w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-navy focus:ring-navy" :class="invalid ? 'border-red-400' : 'border-gray-300'" />
        <ul v-show="open && matches.length" :id="`${id}-suggestions`" ref="list" role="listbox" aria-label="Suggested receipt types"
            class="absolute inset-x-0 top-full z-20 mt-1 max-h-48 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
            <li v-for="(option, index) in matches" :id="`${id}-option-${index}`" :key="option.value" role="option" :aria-selected="active === index"
                @pointerdown.prevent @click="choose(option)" @pointermove="active = index"
                class="cursor-pointer break-words px-3 py-2 text-sm text-gray-800 hover:bg-slate-100" :class="{ 'bg-slate-100': active === index }">{{ option.label || option.value }}</li>
        </ul>
    </div>
</template>
