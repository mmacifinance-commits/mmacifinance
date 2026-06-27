<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
import { ref } from 'vue'

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Login" />
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-navy-dark via-navy to-navy-light px-4">
        <div class="w-full max-w-md">
            <div class="rounded-2xl bg-white/10 backdrop-blur-lg border border-white/20 p-8 shadow-2xl">
                <!-- Logo -->
                <div class="mb-6 text-center">
                    <img src="/images/logo.png" alt="MMAC Logo" class="mx-auto h-20 w-20 object-contain" />
                    <h1 class="mt-4 text-xl font-bold text-white">Budget Fund Utilization & Tracking</h1>
                    <p class="text-sm text-mustard/80 mt-1">Merchant Marine Academy of Caraga, Inc.</p>
                </div>

                <!-- Success Flash Message -->
                <div v-if="$page.props.flash?.message || $page.props.flash?.success" class="mb-4 rounded-lg bg-green-500/20 border border-green-500/30 p-3 text-sm text-green-200 text-center">
                    {{ $page.props.flash.message || $page.props.flash.success }}
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

                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            class="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-white placeholder-white/40 focus:border-mustard focus:ring-2 focus:ring-mustard/40 transition"
                            placeholder="••••••••"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-300">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <input v-model="form.remember" type="checkbox" id="remember" class="rounded border-white/30 bg-white/10 text-mustard focus:ring-mustard/40" />
                            <label for="remember" class="text-sm text-white/70">Remember me</label>
                        </div>
                        <Link href="/forgot-password" class="text-sm text-mustard hover:text-mustard-light transition font-medium">
                            Forgot Password?
                        </Link>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-lg bg-mustard py-2.5 font-semibold text-navy-dark hover:bg-mustard-light transition disabled:opacity-50"
                    >
                        {{ form.processing ? 'Signing in...' : 'Sign In' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
