<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'

const form = useForm({
    email: '',
})

function submit() {
    form.email = form.email.trim().toLowerCase()
    form.post('/forgot-password')
}
</script>

<template>
    <Head title="Forgot Password" />
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-navy-dark via-navy to-navy-light px-4">
        <div class="w-full max-w-md">
            <div class="rounded-2xl bg-white/10 backdrop-blur-lg border border-white/20 p-8 shadow-2xl">
                <!-- Header -->
                <div class="mb-6 text-center">
                    <img src="/images/logo.png" alt="MMAC Logo" class="mx-auto h-20 w-20 object-contain" />
                    <h1 class="mt-4 text-xl font-bold text-white font-sans">Forgot Password</h1>
                    <p class="text-sm text-white/60 mt-2 leading-relaxed">
                        Enter your email address and we'll send you a 6-digit verification code to reset your password.
                    </p>
                </div>

                <!-- Success / Error Flash Message -->
                <div v-if="$page.props.flash?.message || $page.props.flash?.success" class="mb-4 rounded-lg bg-green-500/20 border border-green-500/30 p-3 text-sm text-green-200 text-center">
                    {{ $page.props.flash.message || $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.error" class="mb-4 rounded-lg bg-red-500/20 border border-red-500/30 p-3 text-sm text-red-200 text-center">
                    {{ $page.props.flash.error }}
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            class="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-white placeholder-white/40 focus:border-mustard focus:ring-2 focus:ring-mustard/40 transition"
                            placeholder="admin@mmac.edu.ph"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-300">{{ form.errors.email }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-lg bg-mustard py-2.5 font-semibold text-navy-dark hover:bg-mustard-light transition disabled:opacity-50"
                    >
                        {{ form.processing ? 'Sending code...' : 'Send Verification Code' }}
                    </button>

                    <div class="text-center mt-4">
                        <Link href="/login" class="text-sm text-mustard hover:text-mustard-light transition font-medium">
                            Back to Login
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
