<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
defineProps({ histories: Object })
</script>
<template>
  <AppLayout>
    <Head title="Login History" />
    <div class="mx-auto max-w-6xl space-y-4">
      <div><h2 class="text-xl font-bold text-gray-900">Login History</h2><p class="text-sm text-gray-500">Successful sign-ins recorded after two-factor verification.</p></div>
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Date and time</th><th class="px-4 py-3">User</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">IP address</th></tr></thead>
        <tbody><tr v-for="row in histories.data" :key="row.id" class="border-t"><td class="px-4 py-3">{{ new Date(row.logged_in_at).toLocaleString() }}</td><td class="px-4 py-3">{{ row.user?.name || 'Deleted user' }}</td><td class="px-4 py-3">{{ row.role || '—' }}</td><td class="px-4 py-3">{{ row.email }}</td><td class="px-4 py-3">{{ row.ip_address || '—' }}</td></tr><tr v-if="!histories.data?.length"><td colspan="5" class="px-4 py-8 text-center text-gray-500">No login history yet.</td></tr></tbody></table>
      </div>
      <div class="flex gap-2"><Link v-for="link in histories.links" :key="link.label" :href="link.url || '#'" :class="['rounded border px-3 py-1 text-xs', link.active ? 'bg-navy text-white' : 'bg-white text-gray-600', !link.url && 'pointer-events-none opacity-50']" v-html="link.label" /></div>
    </div>
  </AppLayout>
</template>
