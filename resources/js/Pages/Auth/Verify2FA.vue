<script setup>
import { useForm, Head } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
    cooldownSeconds: {
        type: Number,
        default: 0
    },
    devOtp: {
        type: String,
        default: null
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
    const totalSecs = Math.max(0, Math.floor(Number(seconds) || 0))
    const mins = Math.floor(totalSecs / 60)
    const secs = totalSecs % 60
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

const handleInput = (index, event) => {
    let val = event.target.value.replace(/[^0-9]/g, '')

    if (val.length > 1) {
        const code = val.slice(0, 6).split('')
        code.forEach((digit, digitIndex) => {
            digits.value[digitIndex] = digit
        })
        inputs.value[Math.min(code.length, 6) - 1]?.focus()
        return
    }

    digits.value[index] = val.substring(0, 1)

    if (val && index < 5) {
        inputs.value[index + 1]?.focus()
    }
}

const handleKeydown = (index, event) => {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        inputs.value[index - 1]?.focus()
    } else if (event.key === 'ArrowLeft' && index > 0) {
        inputs.value[index - 1]?.focus()
    } else if (event.key === 'ArrowRight' && index < 5) {
        inputs.value[index + 1]?.focus()
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
    <div class="flex min-h-[100dvh] items-start justify-center bg-[#253955] px-3 pb-6 pt-14 font-sans sm:items-center sm:px-4 sm:py-14">
        <div class="w-full max-w-md">
            <!-- Card -->
            <div class="relative bg-white px-4 pb-6 pt-10 text-center shadow-2xl sm:px-10 sm:pb-10 sm:pt-12">
                <!-- Icon top center -->
                <div class="absolute -top-8 left-1/2 flex h-16 w-16 -translate-x-1/2 items-center justify-center border-4 border-[#253955] bg-[#1b2b41] sm:-top-10 sm:h-[5.5rem] sm:w-[5.5rem] sm:border-[5px]">
                    <svg class="h-7 w-7 text-mustard sm:h-8 sm:w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>

                <h1 class="mb-2 mt-3 text-xl font-medium tracking-normal text-[#1b2b41] sm:mb-3 sm:mt-4 sm:text-[1.4rem]">Verify Your Identity</h1>
                <p class="mb-4 px-1 text-sm font-light leading-6 text-gray-400 sm:px-2 sm:text-[0.95rem]">
                    We've sent a 6-digit verification code to your email address. Enter the code below to continue.
                </p>

                <!-- Dev Mode OTP Display Banner -->
                <div v-if="devOtp" class="mb-4 flex items-center gap-2 border border-amber-300 bg-amber-50 p-3 text-left font-sans text-xs text-amber-900 shadow-sm sm:mb-6 sm:gap-3">
                    <span class="text-xl">🔑</span>
                    <div>
                        <div class="font-bold text-amber-900">Local Dev Verification Code</div>
                        <div class="text-[11px] text-amber-700">Code: <span class="font-mono font-extrabold text-sm text-navy-dark tracking-widest bg-amber-200/80 px-2 py-0.5 rounded select-all">{{ devOtp }}</span></div>
                    </div>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-5 sm:space-y-7">
                    <div class="grid grid-cols-6 gap-1.5 sm:gap-3 sm:px-1">
                        <input
                            v-for="(digit, index) in digits"
                            :key="index"
                            :ref="el => { if (el) inputs[index] = el }"
                            v-model="digits[index]"
                            @input="e => handleInput(index, e)"
                            @keydown="e => handleKeydown(index, e)"
                            @paste="handlePaste"
                            type="tel"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            :autocomplete="index === 0 ? 'one-time-code' : 'off'"
                            :enterkeyhint="index === 5 ? 'done' : 'next'"
                            :aria-label="`Verification code digit ${index + 1}`"
                            maxlength="1"
                            class="otp-digit h-12 min-w-0 w-full border border-gray-200 bg-white p-0 text-center text-2xl font-medium text-[#1b2b41] shadow-sm outline-none transition-all focus:border-mustard focus:ring-1 focus:ring-mustard/50 sm:h-[3.8rem] sm:text-3xl"
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
                    
                    <div class="mt-6 text-center text-sm leading-6 sm:mt-8 sm:text-[0.95rem]">
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
            
            <div class="mt-6 text-center text-xs font-light tracking-wide text-white/50 sm:mt-10 sm:text-[0.85rem]">
                &copy; {{ new Date().getFullYear() }} MMACI. All rights reserved.
            </div>
        </div>
    </div>
</template>
