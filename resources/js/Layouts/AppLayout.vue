<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import OfflineBanner from '@/Components/OfflineBanner.vue'
import SystemAlert from '@/Components/SystemAlert.vue'
import LoadingOverlay from '@/Components/LoadingOverlay.vue'

const page = usePage()
const auth = computed(() => page.props.auth)
const flash = computed(() => page.props.flash)
const roleLabel = computed(() => auth.value?.user?.role_label || 'User')
const canViewReceipts = computed(() => auth.value?.user?.role !== 'auditor')

const mainNavItems = computed(() => [
    { href: '/', label: 'DASHBOARD', icon: '', section: 'dashboard' },
    { href: '/iaeo', label: 'IAEO', icon: '', section: 'iaeo' },
    { href: '/annual-budgets', label: 'BUDGET', icon: '', section: 'budget' },
    { href: '/income', label: 'INCOME', icon: '', section: 'income' },
    ...(canViewReceipts.value ? [{ href: '/receipts', label: 'RECEIPTS', icon: '', section: 'receipts' }] : []),
    { href: '/expenses', label: 'EXPENDITURES', icon: '', section: 'expenditures' },
    { href: '/disbursements', label: 'DISBURSEMENTS', icon: '', section: 'disbursements' },
    { href: '/reports', label: 'FINANCIAL REPORTS', icon: '', section: 'reports' },
])

const sidebarMenus = {
    budget: [
        { href: '/annual-budgets', label: 'Annual Budget', icon: '' },
        { href: '/departments', label: 'Responsibility Centers', icon: '' },
        { href: '/budget-categories', label: 'Budget Categories', icon: '' },
        { href: '/budget-particulars', label: 'Account Titles', icon: '' },
    ],
}

const mobileMenuOpen = ref(false)
const showFlash = ref(true)
const pageLoading = ref(false)
let loadingHideTimer = null

const currentPath = computed(() => page.url)

function getActiveSection() {
    const p = currentPath.value
    if (p === '/') return 'dashboard'
    if (p.startsWith('/annual-budgets') || p.startsWith('/budget-categories') || p.startsWith('/budget-particulars') || p.startsWith('/departments')) return 'budget'
    if (p.startsWith('/income')) return 'income'
    if (p.startsWith('/receipts')) return 'receipts'
    if (p.startsWith('/iaeo') || p.startsWith('/revenue')) return 'iaeo'
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

function handleGlobalLoading(event) {
    clearTimeout(loadingHideTimer)
    if (event.detail?.active) {
        pageLoading.value = true
        return
    }

    loadingHideTimer = setTimeout(() => {
        pageLoading.value = false
    }, 120)
}

onMounted(() => {
    window.addEventListener('app:loading', handleGlobalLoading)
})

onUnmounted(() => {
    window.removeEventListener('app:loading', handleGlobalLoading)
    clearTimeout(loadingHideTimer)
})


watch(flash, () => { showFlash.value = true; setTimeout(() => { showFlash.value = false }, 4000) }, { deep: true })
</script>

<template>
    <div class="flex min-h-screen flex-col bg-gray-100">
        <!-- Offline / Sync Banner -->
        <OfflineBanner />

        <!-- Header -->
        <header class="bg-navy-dark">
            <div class="flex items-center justify-between gap-3 px-3 py-3 sm:px-4 md:px-6">
                <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                    <img src="/images/logo.png" alt="MMAC Logo" class="h-9 w-9 flex-none object-contain sm:h-11 sm:w-11" />
                    <div class="min-w-0">
                        <h1 class="truncate text-sm font-bold leading-tight tracking-wide text-white sm:text-base">Budget Fund Utilization System</h1>
                        <p class="mt-0.5 truncate text-[10px] text-mustard sm:text-xs">Merchant Marine Academy of Caraga, Inc.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden md:flex items-center gap-2 rounded-md bg-white/10 px-3 py-1.5">
                        <span class="text-mustard text-sm"></span>
                        <span class="text-sm font-medium text-white">{{ roleLabel }}</span>
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
                <div class="flex items-center justify-between border-b border-navy-light px-4 py-3">
                    <span class="text-xs font-semibold text-mustard">{{ roleLabel }}</span>
                </div>
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
                    Logout
                </button>
            </div>
        </nav>

        <!-- Mobile secondary navigation for sections that use the desktop sidebar -->
        <nav v-if="hasSidebar" class="border-b border-gray-200 bg-white md:hidden" aria-label="Budget sub-navigation">
            <div class="flex snap-x gap-1 overflow-x-auto px-3 py-2">
                <Link
                    v-for="item in currentSidebar"
                    :key="item.href"
                    :href="item.href"
                    :class="[
                        'flex-none snap-start whitespace-nowrap border px-3 py-2 text-xs font-semibold',
                        isSideActive(item.href)
                            ? 'border-mustard bg-mustard/10 text-navy-dark'
                            : 'border-gray-200 text-gray-600'
                    ]"
                >
                    {{ item.label }}
                </Link>
            </div>
        </nav>

        <!-- Flash Messages -->
        <div v-if="showFlash && (flash?.success || flash?.error || flash?.warning)" class="fixed left-3 right-3 top-20 z-50 space-y-2 sm:left-auto sm:right-4 sm:w-96">
            <SystemAlert v-if="flash?.success" tone="success" title="Success" :messages="flash.success" dismissible @dismiss="showFlash = false" />
            <SystemAlert v-if="flash?.error" tone="error" title="Error" :messages="flash.error" dismissible @dismiss="showFlash = false" />
            <SystemAlert v-if="flash?.warning" tone="warning" title="Warning" :messages="flash.warning" dismissible @dismiss="showFlash = false" />
        </div>

        <!-- Body: Sidebar + Content -->
        <div class="relative flex flex-1 min-w-0">
            <LoadingOverlay
                :show="pageLoading"
                text="Loading..."
                subtext="Please wait while the page updates."
            />

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
        <main class="mobile-app-content min-w-0 flex-1 p-3 sm:p-4 md:p-6">
                <slot />
        </main>
        </div>

        <!-- Footer -->
        <footer class="bg-navy-dark px-6 py-3 text-center">
            <p class="text-xs text-mustard/70">Merchant Marine Academy of Caraga, Inc. &copy; {{ new Date().getFullYear() }}. All rights reserved.</p>
        </footer>
    </div>
</template>

