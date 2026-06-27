<script setup>
import { useForm, Head } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
    cooldownSeconds: {
        type: Number,
        default: 0
    }
})

const form = useForm({
    otp: '',
})

const digits = ref(['', '', '', '', '', ''])
const inputs = ref([])
const timeLeft = ref(props.cooldownSeconds)
let timerInterval = null

const startTimer = () => {
    if (timerInterval) clearInterval(timerInterval)
    timerInterval = setInterval(() => {
        if (timeLeft.value > 0) {
            timeLeft.value--
        } else {
            clearInterval(timerInterval)
        }
    }, 1000)
}

onMounted(() => {
    if (inputs.value[0]) {
        inputs.value[0].focus()
    }
    if (timeLeft.value > 0) {
        startTimer()
    }
})

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval)
})

watch(() => props.cooldownSeconds, (newVal) => {
    timeLeft.value = newVal
    if (newVal > 0) {
        startTimer()
    }
})

const formatTime = (seconds) => {
    const mins = Math.floor(seconds / 60)
    const secs = seconds % 60
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

const handleInput = (index, event) => {
    let val = event.target.value.replace(/[^0-9]/g, '')
    digits.value[index] = val.substring(0, 1)

    if (val && index < 5) {
        inputs.value[index + 1].focus()
    }
}

const handleKeydown = (index, event) => {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        inputs.value[index - 1].focus()
    } else if (event.key === 'ArrowLeft' && index > 0) {
        inputs.value[index - 1].focus()
    } else if (event.key === 'ArrowRight' && index < 5) {
        inputs.value[index + 1].focus()
    }
}

const handlePaste = (event) => {
    event.preventDefault()
    const pastedData = event.clipboardData.getData('text/plain').trim()
    const matches = pastedData.match(/\d/g)
    
    if (matches) {
        for (let i = 0; i < Math.min(6, matches.length); i++) {
            digits.value[i] = matches[i]
        }
        const nextIndex = Math.min(5, matches.length)
        if (inputs.value[nextIndex]) {
            if (nextIndex < 5 || digits.value[5] === '') {
                inputs.value[nextIndex].focus()
            } else {
                inputs.value[5].focus()
            }
        }
    }
}

function submit() {
    form.otp = digits.value.join('')
    form.post('/2fa/verify', {
        onError: () => {
            digits.value = ['', '', '', '', '', '']
            if (inputs.value[0]) inputs.value[0].focus()
            form.reset('otp')
        }
    })
}

const resendForm = useForm({})
function resendCode() {
    resendForm.post('/2fa/resend', {
        preserveScroll: true,
        onSuccess: () => {
            digits.value = ['', '', '', '', '', '']
            if (inputs.value[0]) inputs.value[0].focus()
            form.reset('otp')
        }
    })
}
</script>

<template>
    <Head title="Verify Your Identity" />
    <div class="flex min-h-screen items-center justify-center bg-[#253955] px-4 font-sans">
        <div class="w-full max-w-md">
            <!-- Card -->
            <div class="rounded-2xl bg-white p-8 sm:px-12 shadow-2xl relative pt-12 text-center pb-10">
                <!-- Icon top center -->
                <div class="absolute -top-10 left-1/2 -translate-x-1/2 flex items-center justify-center h-[5.5rem] w-[5.5rem] rounded-full bg-[#1b2b41] border-[5px] border-[#253955]">
                    <svg class="w-8 h-8 text-mustard" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>

                <h1 class="mt-4 text-[1.4rem] font-medium text-[#1b2b41] tracking-normal mb-3">Verify Your Identity</h1>
                <p class="text-[0.95rem] text-gray-400 mt-2 mb-8 leading-relaxed font-light px-2">
                    We've sent a 6-digit verification code to your email address. Enter the code below to continue.
                </p>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-7">
                    <div class="flex justify-between gap-2 sm:gap-3 px-1">
                        <input
                            v-for="(digit, index) in digits"
                            :key="index"
                            ref="el => { if(el) inputs[index] = el }"
                            v-model="digits[index]"
                            @input="e => handleInput(index, e)"
                            @keydown="e => handleKeydown(index, e)"
                            @paste="handlePaste"
                            type="text"
                            inputmode="numeric"
                            maxlength="1"
                            class="w-[3.1rem] h-[3.8rem] rounded-xl border border-gray-200 bg-white text-center text-3xl font-medium text-[#1b2b41] shadow-sm focus:border-mustard focus:ring-1 focus:ring-mustard/50 transition-all outline-none"
                        />
                    </div>
                    
                    <p v-if="form.errors.otp" class="text-[0.85rem] text-red-500 font-medium">{{ form.errors.otp }}</p>
                    <p v-if="$page.props.flash?.message" class="text-[0.85rem] text-green-600 font-medium">{{ $page.props.flash.message }}</p>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full mt-2 rounded-xl bg-[#1b2b41] py-3.5 font-semibold text-white uppercase tracking-widest text-[0.85rem] hover:bg-opacity-90 transition disabled:opacity-70 shadow-lg shadow-[#1b2b41]/20"
                    >
                        {{ form.processing ? 'Verifying...' : 'VERIFY & LOGIN' }}
                    </button>
                    
                    <div class="mt-8 text-center text-[0.95rem]">
                        <span class="text-gray-400 font-light">Didn't receive the code? </span><br class="sm:hidden">
                        <button 
                            type="button" 
                            @click="resendCode" 
                            :disabled="resendForm.processing || timeLeft > 0" 
                            class="text-mustard font-semibold hover:text-mustard-dark transition ml-1 tracking-wide disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ timeLeft > 0 ? `Resend Code (${formatTime(timeLeft)})` : 'Resend Code' }}
                        </button>
                    </div>

                    <div class="mt-4 text-center text-[0.85rem] text-gray-300 font-light tracking-wide">
                        Code expires in 10 minutes
                    </div>
                </form>
            </div>
            
            <div class="mt-10 text-center text-[0.85rem] text-white/50 font-light tracking-wide">
                &copy; {{ new Date().getFullYear() }} MMACI. All rights reserved.
            </div>
        </div>
    </div>
</template>
