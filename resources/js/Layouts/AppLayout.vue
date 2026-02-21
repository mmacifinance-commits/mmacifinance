<script setup>
import { ref, computed, watch } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

const page = usePage()
const auth = computed(() => page.props.auth)
const flash = computed(() => page.props.flash)

const mainNavItems = [
    { href: '/', label: 'DASHBOARD', icon: '📊', section: 'dashboard' },
    { href: '/annual-budgets', label: 'BUDGET', icon: '💰', section: 'budget' },
    { href: '/expenses', label: 'EXPENDITURES', icon: '🧾', section: 'expenditures' },
    { href: '/disbursements', label: 'DISBURSEMENTS', icon: '💳', section: 'disbursements' },
    { href: '/reports', label: 'FINANCIAL REPORTS', icon: '📈', section: 'reports' },
]

const sidebarMenus = {
    budget: [
        { href: '/annual-budgets', label: 'Annual Budget', icon: '📋' },
        { href: '/budget-categories', label: 'Budget Categories', icon: '🏷️' },
        { href: '/budget-particulars', label: 'Budget Particulars', icon: '📄' },
    ],
}

const mobileMenuOpen = ref(false)
const showFlash = ref(true)

const currentPath = computed(() => page.url)

function getActiveSection() {
    const p = currentPath.value
    if (p === '/') return 'dashboard'
    if (p.startsWith('/annual-budgets') || p.startsWith('/budget-categories') || p.startsWith('/budget-particulars') || p.startsWith('/departments')) return 'budget'
    if (p.startsWith('/expenses')) return 'expenditures'
    if (p.startsWith('/disbursements')) return 'disbursements'
    if (p.startsWith('/reports')) return 'reports'
    return 'dashboard'
}

const activeSection = computed(() => getActiveSection())
const hasSidebar = computed(() => !!sidebarMenus[activeSection.value])
const currentSidebar = computed(() => sidebarMenus[activeSection.value] || [])

function isMainActive(item) {
    return activeSection.value === item.section
}

function isSideActive(href) {
    if (href === currentPath.value) return true
    if (currentPath.value.startsWith(href) && href !== '/') return true
    return false
}

function logout() {
    router.post('/logout')
}

watch(flash, () => { showFlash.value = true; setTimeout(() => { showFlash.value = false }, 4000) }, { deep: true })
</script>

<template>
    <div class="flex min-h-screen flex-col bg-gray-100">
        <!-- Header -->
        <header class="bg-navy-dark">
            <div class="flex items-center justify-between px-4 py-3 md:px-6">
                <div class="flex items-center gap-3">
                    <img src="/images/logo.jpg" alt="MMAC Logo" class="h-11 w-11 rounded-full object-cover border-2 border-mustard/60 shadow" />
                    <div>
                        <h1 class="text-base font-bold text-white leading-tight tracking-wide">ACCOUNTING INFORMATION SYSTEM</h1>
                        <p class="text-xs text-mustard mt-0.5">Merchant Marine Academy of Caraga, Inc.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden md:flex items-center gap-2 rounded-md bg-white/10 px-3 py-1.5">
                        <span class="text-mustard text-sm">👤</span>
                        <span class="text-sm font-medium text-white">{{ auth.user?.role_label || auth.user?.name }}</span>
                    </div>
                    <button @click="logout" class="rounded-md bg-red-500/20 px-3 py-1.5 text-xs font-medium text-red-300 hover:bg-red-500/30 transition hidden md:block">
                        Logout
                    </button>
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-white md:hidden">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Navigation Bar -->
        <nav class="bg-navy border-b-2 border-mustard">
            <div class="hidden md:flex">
                <Link
                    v-for="item in mainNavItems"
                    :key="item.section"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-2 px-5 py-2.5 text-xs font-bold uppercase tracking-wider transition-all',
                        isMainActive(item)
                            ? 'bg-mustard text-navy-dark rounded-t-lg'
                            : 'text-white/70 hover:text-white hover:bg-white/5'
                    ]"
                >
                    <span>{{ item.icon }}</span>
                    {{ item.label }}
                </Link>
            </div>
            <!-- Mobile nav -->
            <div v-if="mobileMenuOpen" class="md:hidden">
                <Link
                    v-for="item in mainNavItems"
                    :key="item.section"
                    :href="item.href"
                    @click="mobileMenuOpen = false"
                    :class="[
                        'block px-4 py-3 text-xs font-bold uppercase tracking-wider border-b border-navy-light',
                        isMainActive(item) ? 'bg-mustard text-navy-dark' : 'text-white/70'
                    ]"
                >
                    <span class="mr-2">{{ item.icon }}</span>{{ item.label }}
                </Link>
                <button @click="logout" class="w-full px-4 py-3 text-left text-xs font-bold uppercase text-red-300 border-b border-navy-light">
                    🚪 Logout
                </button>
            </div>
        </nav>

        <!-- Flash Messages -->
        <div v-if="showFlash && (flash?.success || flash?.error)" class="fixed top-20 right-4 z-50 w-80">
            <div v-if="flash?.success" class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 shadow-lg flex justify-between items-center">
                <span>✅ {{ flash.success }}</span>
                <button @click="showFlash = false" class="text-green-500 hover:text-green-700 ml-2">&times;</button>
            </div>
            <div v-if="flash?.error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 shadow-lg flex justify-between items-center">
                <span>❌ {{ flash.error }}</span>
                <button @click="showFlash = false" class="text-red-500 hover:text-red-700 ml-2">&times;</button>
            </div>
        </div>

        <!-- Body: Sidebar + Content -->
        <div class="flex flex-1 min-w-0">
            <!-- Sidebar -->
            <aside v-if="hasSidebar" class="hidden px-4 mt-2 md:block w-64 bg-white border-r border-gray-200 flex-shrink-0">
                <div class="border-t-4 border-mustard bg-navy-dark px-4 py-2">
                    <h3 class="text-[11px] font-bold uppercase tracking-widest text-white">Sub Menu</h3>
                </div>
                <nav class="py-1">
                    <Link
                        v-for="item in currentSidebar"
                        :key="item.href"
                        :href="item.href"
                        :class="[
                            'flex items-center gap-2 px-3 py-2 text-[13px] transition-colors border-l-[3px]',
                            isSideActive(item.href)
                                ? 'text-navy-dark font-bold border-mustard bg-mustard/5'
                                : 'text-gray-500 hover:text-navy-dark border-transparent hover:bg-gray-50'
                        ]"
                    >
                        <span class="text-[11px]">{{ item.icon }}</span>
                        {{ item.label }}
                    </Link>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 p-6 min-w-0">
                <slot />
            </main>
        </div>

        <!-- Footer -->
        <footer class="bg-navy-dark px-6 py-3 text-center">
            <p class="text-xs text-mustard/70">Merchant Marine Academy of Caraga, Inc. &copy; {{ new Date().getFullYear() }}. All rights reserved.</p>
        </footer>
    </div>
</template>
