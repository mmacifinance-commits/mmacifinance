<script setup>
const props = defineProps({
    title: {
        type: String,
        default: '',
    },
    messages: {
        type: [Array, String, Object],
        default: () => [],
    },
    tone: {
        type: String,
        default: 'error',
    },
    dismissible: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['dismiss'])

function normalizeMessages(value) {
    if (!value) return []
    if (typeof value === 'string') return [value]
    if (Array.isArray(value)) return value.flatMap(normalizeMessages)
    if (typeof value === 'object') return Object.values(value).flatMap(normalizeMessages)
    return [String(value)]
}
</script>

<template>
    <div
        v-if="normalizeMessages(messages).length"
        class="rounded-lg border px-4 py-3 text-sm shadow-sm"
        :class="{
            'border-red-200 bg-red-50 text-red-700': tone === 'error',
            'border-green-200 bg-green-50 text-green-700': tone === 'success',
            'border-amber-200 bg-amber-50 text-amber-800': tone === 'warning',
            'border-blue-200 bg-blue-50 text-blue-800': tone === 'info',
        }"
        role="alert"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p v-if="title" class="font-semibold">{{ title }}</p>
                <ul v-if="normalizeMessages(messages).length > 1" class="mt-1 list-disc space-y-1 pl-5">
                    <li v-for="message in normalizeMessages(messages)" :key="message">{{ message }}</li>
                </ul>
                <p v-else :class="title ? 'mt-1' : ''">{{ normalizeMessages(messages)[0] }}</p>
            </div>
            <button
                v-if="dismissible"
                type="button"
                class="shrink-0 font-semibold opacity-70 hover:opacity-100"
                @click="emit('dismiss')"
            >
                &times;
            </button>
        </div>
    </div>
</template>
