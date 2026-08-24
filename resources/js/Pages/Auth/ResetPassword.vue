<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    verified: {
        type: Boolean,
        default: false,
    },
})

const verifyForm = useForm({
    email: props.email,
    code: '',
})

const resetForm = useForm({
    email: props.email,
    verified: props.verified ? 1 : 0,
    code: '',
    password: '',
    password_confirmation: '',
})

const resendForm = useForm({
    email: props.email,
})

function onCodeInput(e, target) {
    const value = e.target.value.replace(/\D/g, '').slice(0, 6)
    if (target === 'verify') {
        verifyForm.code = value
    } else {
        resetForm.code = value
    }
}

function verifyCode() {
    verifyForm.post('/reset-password/verify', {
        preserveScroll: true,
    })
}

function submitPassword() {
    resetForm.post('/reset-password', {
        preserveScroll: true,
        onFinish: () => resetForm.reset('password', 'password_confirmation'),
    })
}

function resendCode() {
    resendForm.email = props.email
    resendForm.post('/forgot-password', {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Reset Password" />
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-navy-dark via-navy to-navy-light px-4">
        <div class="w-full max-w-md">
            <div class="rounded-2xl border border-white/20 bg-white/10 p-8 shadow-2xl backdrop-blur-lg">
                <div class="mb-6 text-center">
                    <img src="/images/logo.png" alt="MMAC Logo" class="mx-auto h-20 w-20 object-contain" />
                    <h1 class="mt-4 text-xl font-bold text-white font-sans">Reset Password</h1>
                    <p class="mt-1 text-sm leading-relaxed text-white/60">
                        {{ verified ? 'Create your new password below.' : 'Enter the 6-digit code sent to ' }}
                        <strong v-if="!verified" class="text-white">{{ email }}</strong>
                    </p>
                </div>

                <div v-if="$page.props.flash?.message || $page.props.flash?.success" class="mb-4 rounded-lg border border-green-500/30 bg-green-500/20 p-3 text-center text-sm text-green-200">
                    {{ $page.props.flash.message || $page.props.flash.success }}
                </div>

                <div v-if="!verified">
                    <form @submit.prevent="verifyCode" class="space-y-5">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-white/80">Verification Code</label>
                            <input
                                v-model="verifyForm.code"
                                @input="e => onCodeInput(e, 'verify')"
                                type="text"
                                inputmode="numeric"
                                maxlength="6"
                                required
                                autofocus
                                class="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-center text-lg tracking-[0.5em] font-semibold text-white placeholder-white/20 transition focus:border-mustard focus:ring-2 focus:ring-mustard/40"
                                placeholder="000000"
                            />
                            <p v-if="verifyForm.errors.email" class="mt-1 text-xs text-red-300">{{ verifyForm.errors.email }}</p>
                            <p v-if="verifyForm.errors.code" class="mt-1 text-xs text-red-300">{{ verifyForm.errors.code }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="verifyForm.processing"
                            class="w-full rounded-lg bg-mustard py-2.5 font-semibold text-navy-dark transition hover:bg-mustard-light disabled:opacity-50"
                        >
                            {{ verifyForm.processing ? 'Verifying...' : 'Verify Code' }}
                        </button>
                    </form>

                    <div class="mt-4 flex items-center justify-between px-1">
                        <button
                            type="button"
                            @click="resendCode"
                            :disabled="resendForm.processing"
                            class="text-xs text-white/60 underline transition hover:text-white disabled:opacity-50"
                        >
                            {{ resendForm.processing ? 'Resending...' : 'Resend Code' }}
                        </button>
                        <Link href="/login" class="text-xs font-medium text-mustard transition hover:text-mustard-light">
                            Back to Login
                        </Link>
                    </div>
                </div>

                <div v-else>
                    <form @submit.prevent="submitPassword" class="space-y-5">
                        <input v-model="resetForm.email" type="hidden" />
                        <input v-model="resetForm.verified" type="hidden" />
                        <input v-model="resetForm.code" type="hidden" />

                        <div>
                            <label class="mb-1 block text-sm font-medium text-white/80">New Password</label>
                            <input
                                v-model="resetForm.password"
                                type="password"
                                required
                                autofocus
                                class="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-white placeholder-white/40 transition focus:border-mustard focus:ring-2 focus:ring-mustard/40"
                                placeholder="••••••••"
                            />
                            <p v-if="resetForm.errors.password" class="mt-1 text-xs text-red-300">{{ resetForm.errors.password }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-white/80">Confirm New Password</label>
                            <input
                                v-model="resetForm.password_confirmation"
                                type="password"
                                required
                                class="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-white placeholder-white/40 transition focus:border-mustard focus:ring-2 focus:ring-mustard/40"
                                placeholder="••••••••"
                            />
                            <p v-if="resetForm.errors.password_confirmation" class="mt-1 text-xs text-red-300">{{ resetForm.errors.password_confirmation }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="resetForm.processing"
                            class="w-full rounded-lg bg-mustard py-2.5 font-semibold text-navy-dark transition hover:bg-mustard-light disabled:opacity-50"
                        >
                            {{ resetForm.processing ? 'Resetting password...' : 'Reset Password' }}
                        </button>
                    </form>

                    <div class="mt-4 flex items-center justify-between px-1">
                        <button
                            type="button"
                            @click="resendCode"
                            :disabled="resendForm.processing"
                            class="text-xs text-white/60 underline transition hover:text-white disabled:opacity-50"
                        >
                            {{ resendForm.processing ? 'Resending...' : 'Resend Code' }}
                        </button>
                        <Link href="/login" class="text-xs font-medium text-mustard transition hover:text-mustard-light">
                            Back to Login
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
