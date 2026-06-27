<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'

const props = defineProps({
    email: {
        type: String,
        required: true,
    }
})

const form = useForm({
    code: '',
    password: '',
    password_confirmation: '',
})

function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Reset Password" />
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-navy-dark via-navy to-navy-light px-4">
        <div class="w-full max-w-md">
            <div class="rounded-2xl bg-white/10 backdrop-blur-lg border border-white/20 p-8 shadow-2xl">
                <!-- Header -->
                <div class="mb-6 text-center">
                    <img src="/images/logo.png" alt="MMAC Logo" class="mx-auto h-20 w-20 object-contain" />
                    <h1 class="mt-4 text-xl font-bold text-white font-sans">Reset Password</h1>
                    <p class="text-sm text-white/60 mt-1 leading-relaxed">
                        Enter the 6-digit code sent to <strong class="text-white">{{ email }}</strong> and choose your new password.
                    </p>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Verification Code</label>
                        <input
                            v-model="form.code"
                            type="text"
                            inputmode="numeric"
                            maxlength="6"
                            required
                            autofocus
                            class="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-center text-lg tracking-[0.5em] font-semibold text-white placeholder-white/20 focus:border-mustard focus:ring-2 focus:ring-mustard/40 transition"
                            placeholder="000000"
                        />
                        <p v-if="form.errors.code" class="mt-1 text-xs text-red-300">{{ form.errors.code }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">New Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            class="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-white placeholder-white/40 focus:border-mustard focus:ring-2 focus:ring-mustard/40 transition"
                            placeholder="••••••••"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-300">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Confirm Password</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            class="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-white placeholder-white/40 focus:border-mustard focus:ring-2 focus:ring-mustard/40 transition"
                            placeholder="••••••••"
                        />
                        <p v-if="form.errors.password_confirmation" class="mt-1 text-xs text-red-300">{{ form.errors.password_confirmation }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-lg bg-mustard py-2.5 font-semibold text-navy-dark hover:bg-mustard-light transition disabled:opacity-50"
                    >
                        {{ form.processing ? 'Resetting password...' : 'Reset Password' }}
                    </button>

                    <div class="text-center mt-4 flex justify-between items-center px-1">
                        <Link href="/forgot-password" class="text-xs text-white/60 hover:text-white transition">
                            Resend Code
                        </Link>
                        <Link href="/login" class="text-xs text-mustard hover:text-mustard-light transition font-medium">
                            Back to Login
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
