<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
const props = defineProps({ histories: Object, filters: Object, actions: Array })
const form = reactive({ search: props.filters?.search || '', action: props.filters?.action || '', from: props.filters?.from || '', to: props.filters?.to || '' })
function applyFilters() {
  router.get('/login-history', form, { preserveState: true, replace: true })
}
function clearFilters() {
  Object.assign(form, { search: '', action: '', from: '', to: '' })
  applyFilters()
}

const actionLabels = { created: 'Created', modified: 'Updated', deleted: 'Deleted', imported: 'Imported', approved: 'Approved', rejected: 'Rejected', posted: 'Posted', submitted: 'Submitted', login: 'Logged in', logout: 'Logged out', login_failed: 'Failed login', login_failed_otp: 'Failed 2FA login', otp_resent: '2FA code resent' }
function actionLabel(action) { return actionLabels[action] || action.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase()) }
function formatValue(value) { return value === null || value === undefined || value === '' ? 'blank' : typeof value === 'boolean' ? (value ? 'Yes' : 'No') : typeof value === 'object' ? JSON.stringify(value) : String(value) }

function details(row) {
  const changes = row.metadata?.changes || row.metadata
  if (!changes || typeof changes !== 'object' || !Object.keys(changes).length) return row.remarks || '—'
  const fields = Object.entries(changes).filter(([field]) => field !== 'ip_address').map(([field, value]) => `${field.replaceAll('_', ' ')}: ${formatValue(value)}`).join(' · ')
  return `${row.remarks || ''}${row.remarks && fields ? ' ' : ''}${fields}`
}
</script>
<template>
  <AppLayout>
    <Head title="Log History" />
    <div class="mx-auto max-w-6xl space-y-4">
      <div><h2 class="text-xl font-bold text-gray-900">Log History</h2><p class="text-sm text-gray-500">All recorded actions performed in the system, including authentication and financial activity.</p></div>
      <form class="grid gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-5" @submit.prevent="applyFilters">
        <label class="text-xs font-semibold text-gray-600 md:col-span-2">Search activity<input v-model="form.search" type="search" placeholder="User, action, record, or details" class="mt-1 w-full rounded-lg border-gray-300 text-sm" /></label>
        <label class="text-xs font-semibold text-gray-600">Action<select v-model="form.action" class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="">All actions</option><option v-for="action in actions" :key="action" :value="action">{{ actionLabel(action) }}</option></select></label>
        <label class="text-xs font-semibold text-gray-600">From date<input v-model="form.from" type="date" class="mt-1 w-full rounded-lg border-gray-300 text-sm" /></label>
        <label class="text-xs font-semibold text-gray-600">To date<input v-model="form.to" type="date" class="mt-1 w-full rounded-lg border-gray-300 text-sm" /></label>
        <div class="flex gap-2 md:col-span-5"><button type="submit" class="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white">Search</button><button type="button" @click="clearFilters" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Clear</button></div>
      </form>
      <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full table-fixed text-left text-sm"><colgroup><col class="w-[15%]" /><col class="w-[13%]" /><col class="w-[12%]" /><col class="w-[11%]" /><col class="w-[14%]" /><col class="w-[35%]" /></colgroup><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Date and time</th><th class="px-4 py-3">User</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">Record</th><th class="px-4 py-3">Details</th></tr></thead>
        <tbody><tr v-for="row in histories.data" :key="row.id" class="border-t align-top"><td class="px-4 py-3">{{ new Date(row.created_at).toLocaleString() }}</td><td class="break-words px-4 py-3">{{ row.display_user_name }}</td><td class="break-words px-4 py-3">{{ row.user_role || '—' }}</td><td class="break-words px-4 py-3 font-semibold">{{ actionLabel(row.action) }}</td><td class="break-words px-4 py-3">{{ row.record_label }}</td><td class="break-all px-4 py-3 leading-6">{{ details(row) }}</td></tr><tr v-if="!histories.data?.length"><td colspan="6" class="px-4 py-8 text-center text-gray-500">No activity history yet.</td></tr></tbody></table>
      </div>
      <div class="flex gap-2"><Link v-for="link in histories.links" :key="link.label" :href="link.url || '#'" :class="['rounded border px-3 py-1 text-xs', link.active ? 'bg-navy text-white' : 'bg-white text-gray-600', !link.url && 'pointer-events-none opacity-50']" v-html="link.label" /></div>
    </div>
  </AppLayout>
</template>
