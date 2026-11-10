<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
defineProps({ histories: Object })

function details(row) {
  const changes = row.metadata?.changes
  if (!changes || typeof changes !== 'object') return row.remarks || '—'
  const fields = Object.entries(changes).map(([field, value]) => `${field}: ${typeof value === 'object' ? JSON.stringify(value) : value}`).join(' · ')
  return `${row.remarks || ''}${row.remarks ? ' ' : ''}${fields}`
}
</script>
<template>
  <AppLayout>
    <Head title="Log History" />
    <div class="mx-auto max-w-6xl space-y-4">
      <div><h2 class="text-xl font-bold text-gray-900">Log History</h2><p class="text-sm text-gray-500">All recorded actions performed in the system, including authentication and financial activity.</p></div>
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Date and time</th><th class="px-4 py-3">User</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">Record</th><th class="px-4 py-3">Details</th></tr></thead>
        <tbody><tr v-for="row in histories.data" :key="row.id" class="border-t align-top"><td class="px-4 py-3">{{ new Date(row.created_at).toLocaleString() }}</td><td class="px-4 py-3">{{ row.display_user_name }}</td><td class="px-4 py-3">{{ row.user_role || '—' }}</td><td class="px-4 py-3 font-semibold">{{ row.action }}</td><td class="px-4 py-3">{{ row.record_label }}</td><td class="max-w-xl px-4 py-3 break-words">{{ details(row) }}</td></tr><tr v-if="!histories.data?.length"><td colspan="6" class="px-4 py-8 text-center text-gray-500">No activity history yet.</td></tr></tbody></table>
      </div>
      <div class="flex gap-2"><Link v-for="link in histories.links" :key="link.label" :href="link.url || '#'" :class="['rounded border px-3 py-1 text-xs', link.active ? 'bg-navy text-white' : 'bg-white text-gray-600', !link.url && 'pointer-events-none opacity-50']" v-html="link.label" /></div>
    </div>
  </AppLayout>
</template>
