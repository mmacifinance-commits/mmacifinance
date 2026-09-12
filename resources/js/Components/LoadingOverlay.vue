<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    text: {
        type: String,
        default: 'Loading...',
    },
    subtext: {
        type: String,
        default: 'Please wait while we prepare your request.',
    },
    fullScreen: {
        type: Boolean,
        default: false,
    },
})
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="show"
            :class="[
                fullScreen ? 'fixed inset-0' : 'absolute inset-0',
                'z-[9999] flex items-center justify-center bg-slate-950/35 px-4 backdrop-blur-[2px]'
            ]"
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <div class="relative w-full max-w-xs overflow-hidden border border-white/70 bg-white p-6 text-center shadow-2xl">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-1 overflow-hidden bg-slate-100">
                    <div class="h-full w-2/5 animate-[loading-flow_1.1s_ease-in-out_infinite] bg-gradient-to-r from-transparent via-mustard to-transparent"></div>
                </div>

                <div class="mx-auto flex h-20 w-20 items-center justify-center border border-mustard/40 bg-navy-dark shadow-lg shadow-navy-dark/20">
                    <img src="/images/logo.png" alt="MMACI Logo" class="h-14 w-14 animate-[loading-pulse_1.4s_ease-in-out_infinite] object-contain" />
                </div>

                <div class="mx-auto mt-5 flex h-10 w-10 items-center justify-center">
                    <div class="h-8 w-8 animate-spin border-4 border-mustard bg-mustard/10 shadow-[0_0_18px_rgba(212,168,67,0.35)]"></div>
                </div>

                <p class="mt-4 text-sm font-bold uppercase tracking-[0.22em] text-navy-dark">{{ text }}</p>
                <p class="mt-2 text-xs leading-5 text-slate-500">{{ subtext }}</p>
            </div>
        </div>
    </Transition>
</template>
