<script setup>
defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    maxWidth: { type: String, default: 'md' },
})
defineEmits(['close'])

const widths = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
    '4xl': 'max-w-4xl',
    '5xl': 'max-w-5xl',
    '6xl': 'max-w-6xl',
    full: 'max-w-[96vw]',
}
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 md:p-6">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50" @click="$emit('close')"></div>
                <!-- Modal -->
                <div :class="['relative w-full bg-white rounded-lg shadow-2xl max-h-[92vh] overflow-hidden flex flex-col', widths[maxWidth] || 'max-w-md']">
                    <!-- Header -->
                    <div class="flex items-start justify-between p-5 pb-3 border-b border-gray-100">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">{{ title }}</h3>
                            <p v-if="subtitle" class="text-sm text-gray-500 mt-0.5">{{ subtitle }}</p>
                        </div>
                        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                    </div>
                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto overflow-x-auto p-5">
                        <slot />
                    </div>
                    <!-- Footer -->
                    <div v-if="$slots.footer" class="flex items-center justify-end gap-3 px-5 pb-5">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
