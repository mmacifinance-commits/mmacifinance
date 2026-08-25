<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useOfflineQueue } from '@/composables/useOfflineQueue'

const page = usePage()
const { isOnline } = useOfflineQueue()
const props = defineProps({
    tutorial: {
        type: Object,
        default: () => ({ show: false, state: { status: 'pending' } }),
    },
})

const authRole = computed(() => page.props.auth?.user?.role || '')
const perms = computed(() => page.props.permissions || {})
const roleLabels = {
    super_admin: 'Head of Finance',
    budget_officer: 'Budget Monitoring Officer',
    disbursement_officer: 'Disbursement Officer',
    cashier: 'Cashier',
    auditor: 'Auditor',
}
const currentRoleLabel = computed(() => page.props.auth?.user?.role_label || roleLabels[authRole.value] || 'User')

const roleTutorials = computed(() => {
    const flow = [
        {
            id: 'dashboard-overview',
            title: 'Dashboard',
            body: 'This is the starting point of the system. It shows the fiscal snapshot, key totals, and whether your accounts are ready for the next step.',
            instruction: 'Review the summary cards first, then click Continue or the highlighted dashboard area to move forward.',
            expected: 'You understand the current fiscal status before entering data pages.',
            why: 'Every workflow in the system starts from the dashboard so you can see the full picture before acting.',
            actor: 'All roles',
            target: '[data-onboarding-target="dashboard-summary"]',
            clickTarget: '[data-onboarding-click="dashboard-summary"]',
            route: '/',
            advanceOnClick: true,
        },
        {
            id: 'income-open',
            title: 'Income',
            body: 'Income is the funding source. You need income on record before the system will allow you to create a budget.',
            instruction: 'Open the Income tab and check the income records for the current fiscal year.',
            expected: 'You arrive on the Income page and can see the Add Income button if your role allows it.',
            why: 'Without income, the budget workflow cannot continue because the app uses income as the source of allocation.',
            actor: 'Head of Finance records income; all roles can review it',
            target: '[data-onboarding-target="nav-income"]',
            clickTarget: '[data-onboarding-click="nav-income"]',
            route: '/income',
            advanceOnClick: true,
        },
        {
            id: 'income-create',
            title: 'Create Income',
            body: 'If you manage income, record the funding source here before moving to budget creation.',
            instruction: 'Click Add Income, enter the source, amount, date, and description, then save the record.',
            observerInstruction: 'Review the income records and note that only the Head of Finance can add or update income. Continue when you understand this prerequisite.',
            expected: 'A new income entry appears in the list and becomes available for the next workflow stage.',
            why: 'This is the prerequisite that unlocks budget creation.',
            actor: 'Head of Finance',
            requiredPermission: 'canManageIncome',
            target: '[data-onboarding-target="income-add"]',
            clickTarget: '[data-onboarding-click="income-add"]',
            route: '/income',
            advanceOnClick: true,
        },
        {
            id: 'budget-open',
            title: 'Budget',
            body: 'Budget is where income is converted into approved allocations. This page stays tied to your funding source.',
            instruction: 'Open the Budget tab to review the annual budget workspace.',
            expected: 'You land on Annual Budget with the table and budget actions visible.',
            why: 'This is where the system checks that income exists before allowing annual budget work.',
            actor: 'Head of Finance and Budget Monitoring Officer manage; all roles can review',
            target: '[data-onboarding-target="nav-budget"]',
            clickTarget: '[data-onboarding-click="nav-budget"]',
            route: '/annual-budgets',
            advanceOnClick: true,
        },
        {
            id: 'budget-create',
            title: 'Create Annual Budget',
            body: 'This step creates the annual allocation record that will later support expenses and disbursements.',
            instruction: 'Click New Annual Budget, choose the fiscal year and semester, then create the record.',
            observerInstruction: 'Review the annual budget records. The Head of Finance or Budget Monitoring Officer creates and maintains these allocations.',
            expected: 'The new budget appears in the table and can later accept monthly allocations or related details.',
            why: 'The system will not let you create this record unless funding already exists.',
            actor: 'Head of Finance or Budget Monitoring Officer',
            requiredPermission: 'canManageBudget',
            target: '[data-onboarding-target="budget-create"]',
            clickTarget: '[data-onboarding-click="budget-create"]',
            route: '/annual-budgets',
            advanceOnClick: true,
        },
        {
            id: 'expenditures-open',
            title: 'Expenditures',
            body: 'Expenditures is where actual spending is recorded. This is the page that captures real costs before they are released through disbursement.',
            instruction: 'Open the Expenditures tab and review the expense list, filters, and action buttons.',
            expected: 'You land on the Expenditures page and can see the Add Expense action for your role.',
            why: 'Expenses need to exist before a payment release can be created from them.',
            actor: 'Head of Finance, Cashier, and Disbursement Officer manage; all roles can review',
            target: '[data-onboarding-target="nav-expenditures"]',
            clickTarget: '[data-onboarding-click="nav-expenditures"]',
            route: '/expenses',
            advanceOnClick: true,
        },
        {
            id: 'expenditures-create',
            title: 'Create Expense',
            body: 'Record the official expenditure line here. This is the item that later gets linked to a payment release.',
            instruction: 'Click Add Expense, fill in the particulars, amount, and supporting details, then save or submit based on your role.',
            observerInstruction: 'Review the expenditure list and its workflow statuses. Authorized finance staff create these records before a payment release can begin.',
            expected: 'A new expense row appears and its status reflects the workflow stage required by the system.',
            why: 'Disbursements are created from approved expenses, not from blank records.',
            actor: 'Head of Finance, Cashier, or Disbursement Officer',
            requiredPermission: 'canManageExpenses',
            target: '[data-onboarding-target="expense-add"]',
            clickTarget: '[data-onboarding-click="expense-add"]',
            route: '/expenses',
            advanceOnClick: true,
        },
        {
            id: 'expenditures-save',
            title: 'Save Expense',
            body: 'After filling out the form, save the expense so it becomes part of the official expenditure list.',
            instruction: 'Click Create Expense or Update after entering the expense details.',
            observerInstruction: 'Observe how saved expenses appear in the list and move through workflow statuses. Your role has review access for this step.',
            expected: 'The expense is stored and can now move through approval or be linked to a disbursement.',
            why: 'You cannot create a payment release from an expense that was never saved.',
            actor: 'Head of Finance, Cashier, or Disbursement Officer',
            requiredPermission: 'canManageExpenses',
            target: '[data-onboarding-target="expense-save"]',
            clickTarget: '[data-onboarding-click="expense-save"]',
            route: '/expenses',
            advanceOnClick: true,
        },
        {
            id: 'disbursements-open',
            title: 'Disbursements',
            body: 'Disbursements is where a payment release is prepared from an approved expense. It converts spending into a release workflow.',
            instruction: 'Open the Disbursements tab to view the release board, filters, and workflow actions.',
            expected: 'You see the disbursement table and the Create Payment Release button if your role can use it.',
            why: 'This page handles the move from approved expense to payment release.',
            actor: 'Head of Finance, Cashier, and Disbursement Officer manage; all roles can review',
            target: '[data-onboarding-target="nav-disbursements"]',
            clickTarget: '[data-onboarding-click="nav-disbursements"]',
            route: '/disbursements',
            advanceOnClick: true,
        },
        {
            id: 'disbursement-create',
            title: 'Create Payment Release',
            body: 'Choose the approved expense that will be paid and start the release form.',
            instruction: 'Click Create Payment Release, select the approved expense, and enter the release details.',
            observerInstruction: 'Review the payment release list and statuses. Authorized finance staff create a release by linking an approved expense.',
            expected: 'A disbursement draft opens with the linked expense, payee, amount, and date fields ready to fill.',
            why: 'The system only allows approved expenses to become payment releases.',
            actor: 'Head of Finance, Cashier, or Disbursement Officer',
            requiredPermission: 'canManageDisbursements',
            target: '[data-onboarding-target="disbursement-create"]',
            clickTarget: '[data-onboarding-click="disbursement-create"]',
            route: '/disbursements',
            advanceOnClick: true,
        },
        {
            id: 'disbursement-save',
            title: 'Save Release',
            body: 'After filling out the release form, save it so the system can place it into the workflow queue.',
            instruction: 'Click the Save Record or Submit for Approval button in the modal after the details are complete.',
            observerInstruction: 'Observe that a saved release enters the workflow queue. Your role can review the record and its current status.',
            expected: 'The release is stored as a workflow record and appears in the disbursement table.',
            why: 'This is the handoff from preparation into the approval chain.',
            actor: 'Head of Finance, Cashier, or Disbursement Officer',
            requiredPermission: 'canManageDisbursements',
            target: '[data-onboarding-target="disbursement-save"]',
            clickTarget: '[data-onboarding-click="disbursement-save"]',
            route: '/disbursements',
            advanceOnClick: true,
        },
        {
            id: 'disbursement-submit',
            title: 'Submit Release',
            body: 'The release now moves to approval so the finance head can review it.',
            instruction: 'Click Submit Release on the matching disbursement row.',
            observerInstruction: 'Review the For Approval status. Authorized finance staff submit the release, then the Head of Finance reviews it.',
            expected: 'The workflow status changes to For Approval.',
            why: 'Submission sends the record into the approval stage before posting.',
            actor: 'Cashier, Disbursement Officer, or Head of Finance submits; Head of Finance approves and posts',
            requiredPermission: 'canManageDisbursements',
            target: '[data-onboarding-target="disbursement-submit"]',
            clickTarget: '[data-onboarding-click="disbursement-submit"]',
            route: '/disbursements',
            advanceOnClick: true,
        },
        {
            id: 'disbursement-approve',
            title: 'Approve Payment Release',
            body: 'After submission, the Head of Finance checks the linked expense, payee, amount, payment method, and supporting details before approval.',
            instruction: 'Open the submitted release, verify its information, then click Approve. Return or reject it when corrections are required.',
            observerInstruction: 'Review the approval status and note that only the Head of Finance can approve, return, or reject a submitted payment release.',
            expected: 'An accepted release changes from For Approval to Approved and becomes ready for posting.',
            why: 'Approval provides management control before a payment is treated as finalized in the financial records.',
            actor: 'Head of Finance',
            requiredPermission: 'isSuperAdmin',
            target: '[data-onboarding-target="disbursement-approve"]',
            clickTarget: '[data-onboarding-click="disbursement-approve"]',
            route: '/disbursements',
            advanceOnClick: true,
        },
        {
            id: 'disbursement-post',
            title: 'Post Payment Release',
            body: 'Posting is the final disbursement action. It confirms the approved payment release as part of the official financial record.',
            instruction: 'Review the approved release one final time, then click Post to finalize it.',
            observerInstruction: 'Review posted releases and note that only the Head of Finance can perform final posting after approval.',
            expected: 'The release status changes to Posted and its values are reflected in dashboard, IAEO, and financial report totals.',
            why: 'Only posted transactions should affect final utilization monitoring and reporting.',
            actor: 'Head of Finance',
            requiredPermission: 'isSuperAdmin',
            target: '[data-onboarding-target="disbursement-post"]',
            clickTarget: '[data-onboarding-click="disbursement-post"]',
            route: '/disbursements',
            advanceOnClick: true,
        },
        {
            id: 'iaeo-open',
            title: 'IAEO',
            body: 'IAEO shows the relationship between income, appropriation, and expense. It is where you verify whether the budget is healthy after transactions are entered.',
            instruction: 'Open the IAEO tab and review the summary cards and chart.',
            expected: 'You see income, appropriation, and expense in one consolidated view.',
            why: 'This page helps you confirm that the financial picture still balances after spending and release work.',
            actor: 'All roles',
            target: '[data-onboarding-target="nav-iaeo"]',
            clickTarget: '[data-onboarding-click="nav-iaeo"]',
            route: '/iaeo',
            advanceOnClick: true,
        },
        {
            id: 'reports-open',
            title: 'Financial Reports',
            body: 'Financial Reports is the final review layer. It gives you the monitoring and audit-friendly summary of everything that happened before.',
            instruction: 'Open Financial Reports and review the utilization and posted transaction summaries.',
            expected: 'The report page opens with totals, charts, and transaction breakdowns.',
            why: 'This is where the full workflow is summarized for oversight and reporting.',
            actor: 'All roles',
            target: '[data-onboarding-target="nav-reports"]',
            clickTarget: '[data-onboarding-click="nav-reports"]',
            route: '/reports',
            advanceOnClick: true,
        },
    ]

    return flow
})

const activeIndex = ref(0)
const visible = ref(false)
const targetRect = ref(null)
const storageKey = computed(() => `budget_tutorial_state:${authRole.value || 'guest'}`)

function saveLocalState(nextState = {}) {
    if (typeof window === 'undefined') return

    const payload = {
        visible: visible.value,
        activeStepId: currentStep.value?.id || null,
        ...nextState,
    }

    window.localStorage.setItem(storageKey.value, JSON.stringify(payload))
}

function clearLocalState() {
    if (typeof window === 'undefined') return
    window.localStorage.removeItem(storageKey.value)
}

function readLocalState() {
    if (typeof window === 'undefined') return null

    try {
        const raw = window.localStorage.getItem(storageKey.value)
        return raw ? JSON.parse(raw) : null
    } catch {
        return null
    }
}

const currentStep = computed(() => roleTutorials.value[activeIndex.value] || roleTutorials.value[0])
const canPerformCurrentStep = computed(() => {
    const permission = currentStep.value?.requiredPermission
    return !permission || Boolean(perms.value[permission])
})
const currentInstruction = computed(() => (
    canPerformCurrentStep.value
        ? currentStep.value?.instruction
        : currentStep.value?.observerInstruction || currentStep.value?.instruction
))
const popoverStyle = computed(() => {
    const gap = 16
    const rect = targetRect.value

    if (!rect || typeof window === 'undefined') {
        return { right: `${gap}px`, bottom: `${gap}px` }
    }

    const targetCenterX = rect.left + (rect.width / 2)
    const targetCenterY = rect.top + (rect.height / 2)
    const horizontal = targetCenterX > window.innerWidth / 2
        ? { left: `${gap}px` }
        : { right: `${gap}px` }
    const vertical = targetCenterY > window.innerHeight / 2
        ? { top: `${gap}px` }
        : { bottom: `${gap}px` }

    return { ...horizontal, ...vertical }
})

function closeTutorial() {
    visible.value = false
    clearLocalState()
}

async function persist(endpoint, payload = {}) {
    await fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    })
}

function refreshTarget() {
    const el = [...document.querySelectorAll(currentStep.value.target)].find((candidate) => {
        const rect = candidate.getBoundingClientRect()
        return rect.width > 0 && rect.height > 0
    })
    if (!el) {
        targetRect.value = null
        return
    }

    const r = el.getBoundingClientRect()
    targetRect.value = {
        top: r.top,
        left: r.left,
        width: r.width,
        height: r.height,
    }
}

function syncFromServer() {
    const current = props.tutorial?.state?.current_step
    const idx = roleTutorials.value.findIndex(step => step.id === current)
    activeIndex.value = idx >= 0 ? idx : 0
    requestAnimationFrame(refreshTarget)
}

function isOnStepRoute(stepRoute) {
    if (!stepRoute) return true
    if (stepRoute === '/') return page.url === '/'
    return page.url.startsWith(stepRoute)
}

function hydrateTutorialState(forceVisible = false) {
    const local = readLocalState()

    if (local?.activeStepId) {
        const localIndex = roleTutorials.value.findIndex(step => step.id === local.activeStepId)
        if (localIndex >= 0) {
            activeIndex.value = localIndex
        }
    } else {
        syncFromServer()
    }

    visible.value = forceVisible ? true : Boolean(local?.visible)
    requestAnimationFrame(refreshTarget)
}

function openTutorial() {
    if (!isOnline.value) return
    hydrateTutorialState(true)
    saveLocalState({ visible: true, activeStepId: currentStep.value?.id || roleTutorials.value[0]?.id || null })
}

defineExpose({
    openTutorial,
})

async function skip() {
    await persist('/tutorial/skip')
    visible.value = false
    clearLocalState()
    router.reload({ preserveScroll: true, only: [] })
}

async function complete() {
    await persist('/tutorial/complete')
    visible.value = false
    clearLocalState()
    router.reload({ preserveScroll: true, only: [] })
}

async function advance() {
    const step = currentStep.value
    await persist('/tutorial/step', { step: step.id })

    if (activeIndex.value < roleTutorials.value.length - 1) {
        const nextIndex = activeIndex.value + 1
        const nextStep = roleTutorials.value[nextIndex]
        saveLocalState({ visible: true, activeStepId: nextStep?.id || null })
    }

    if (step.route && !isOnStepRoute(step.route)) {
        router.visit(step.route, {
            preserveScroll: true,
            onFinish: () => {
                activeIndex.value = Math.min(activeIndex.value + 1, roleTutorials.value.length - 1)
                requestAnimationFrame(refreshTarget)
                saveLocalState({ visible: true, activeStepId: currentStep.value?.id || null })
            },
        })
        return
    }

    if (activeIndex.value < roleTutorials.value.length - 1) {
        activeIndex.value += 1
        requestAnimationFrame(refreshTarget)
        saveLocalState({ visible: true, activeStepId: currentStep.value?.id || null })
        return
    }

    await complete()
}

function matchesTutorialTarget(event) {
    const selector = currentStep.value.clickTarget || currentStep.value.target
    const target = event.target instanceof Element ? event.target : null
    return !!selector && !!target && !!target.closest(selector)
}

function onDocumentClick(event) {
    if (!visible.value) return
    if (!matchesTutorialTarget(event)) return

    event.preventDefault()
    event.stopPropagation()
    if (currentStep.value.advanceOnClick) {
        advance()
    }
}

function onResize() {
    if (visible.value) refreshTarget()
}

onMounted(() => {
    window.addEventListener('resize', onResize)
    window.addEventListener('scroll', onResize, true)
    document.addEventListener('click', onDocumentClick, true)
    window.addEventListener('tutorial:open', openTutorial)
    window.addEventListener('tutorial:close', closeTutorial)
    hydrateTutorialState()
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize)
    window.removeEventListener('scroll', onResize, true)
    document.removeEventListener('click', onDocumentClick, true)
    window.removeEventListener('tutorial:open', openTutorial)
    window.removeEventListener('tutorial:close', closeTutorial)
})

watch(() => page.url, () => {
    if (!visible.value) return
    requestAnimationFrame(refreshTarget)
    saveLocalState({ visible: true, activeStepId: currentStep.value?.id || null })
})

watch(roleTutorials, () => {
    if (!visible.value) return
    hydrateTutorialState()
})

watch(activeIndex, () => {
    if (!visible.value) return
    saveLocalState({ visible: true, activeStepId: currentStep.value?.id || null })
})

watch(visible, (next) => {
    if (!next) {
        clearLocalState()
        return
    }

    saveLocalState({ visible: true, activeStepId: currentStep.value?.id || null })
})

watch(isOnline, (nextOnline) => {
    if (!nextOnline) {
        closeTutorial()
    }
})
</script>

<template>
    <teleport to="body">
        <div v-if="visible" class="fixed inset-0 z-[100] pointer-events-none">
            <div class="absolute inset-0 bg-slate-950/70"></div>
            <div
                v-if="targetRect"
                class="pointer-events-none absolute rounded-2xl ring-4 ring-mustard/70 shadow-[0_0_0_9999px_rgba(15,23,42,0.72)] transition-all duration-300"
                :style="{
                    top: `${Math.max(12, targetRect.top - 10)}px`,
                    left: `${Math.max(12, targetRect.left - 10)}px`,
                    width: `${targetRect.width + 20}px`,
                    height: `${targetRect.height + 20}px`,
                }"
            ></div>

            <div
                class="pointer-events-auto absolute max-h-[68vh] w-[min(28rem,calc(100vw-2rem))] overflow-y-auto rounded-xl border border-white/10 bg-white p-5 shadow-2xl"
                :style="popoverStyle"
            >
                <div class="mb-2 flex items-center justify-between gap-4">
                    <div class="text-[11px] font-bold uppercase tracking-[0.3em] text-navy-dark">
                        Step {{ activeIndex + 1 }} of {{ roleTutorials.length }}
                    </div>
                    <button class="text-xs font-semibold text-rose-600 hover:text-rose-700" @click="skip">Skip Tutorial</button>
                </div>
                <h3 class="text-lg font-bold text-slate-900">{{ currentStep.title }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ currentStep.body }}</p>
                <div class="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Performed by</p>
                        <p class="mt-1 text-xs font-semibold leading-5 text-slate-800">{{ currentStep.actor }}</p>
                    </div>
                    <div
                        class="rounded-lg border px-3 py-2"
                        :class="canPerformCurrentStep ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'"
                    >
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Your role: {{ currentRoleLabel }}</p>
                        <p class="mt-1 text-xs font-semibold" :class="canPerformCurrentStep ? 'text-emerald-800' : 'text-amber-800'">
                            {{ canPerformCurrentStep ? 'You can perform this step.' : 'Review-only for this step.' }}
                        </p>
                    </div>
                </div>
                <div class="mt-3 rounded-lg bg-slate-50 p-3 text-xs leading-5 text-slate-700">
                    <p class="font-semibold text-slate-900">What to do</p>
                    <p>{{ currentInstruction }}</p>
                    <p class="mt-2 font-semibold text-slate-900">What you should see</p>
                    <p>{{ currentStep.expected }}</p>
                    <p class="mt-2 font-semibold text-slate-900">Why this step matters</p>
                    <p>{{ currentStep.why }}</p>
                </div>
                <p class="mt-2 text-[11px] font-semibold uppercase tracking-[0.25em] text-emerald-700">
                    {{ canPerformCurrentStep ? 'Click the highlighted area or Continue' : 'Review this step, then click Continue' }}
                </p>
                <div class="mt-4 flex items-center justify-between gap-3">
                    <div class="flex gap-1.5">
                        <span
                            v-for="(_, idx) in roleTutorials"
                            :key="idx"
                            class="h-2.5 w-2.5 rounded-full"
                            :class="idx <= activeIndex ? 'bg-mustard' : 'bg-slate-300'"
                        ></span>
                    </div>
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-navy-dark px-4 py-2 text-sm font-semibold text-white hover:bg-navy" @click="advance">
                            {{ activeIndex === roleTutorials.length - 1 ? 'Done' : 'Continue' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>
