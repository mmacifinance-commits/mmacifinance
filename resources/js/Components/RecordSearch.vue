<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({ url: String, search: String, placeholder: String })
const query = ref(props.search || '')
watch(() => props.search, value => { query.value = value || '' })
function submit() {
    router.get(props.url, query.value.trim() ? { search: query.value.trim() } : {}, {
        preserveState: true, preserveScroll: true, replace: true,
    })
}
function reset() { query.value = ''; submit() }
</script>

<template>
    <form @submit.prevent="submit" class="mb-4 flex flex-wrap items-center gap-3">
        <input v-model="query" type="search" :aria-label="placeholder" :placeholder="placeholder" class="min-w-0 flex-1 rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm shadow-sm" />
        <button type="submit" class="rounded-md bg-navy-dark px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy">Search</button>
        <button v-if="query || search" type="button" @click="reset" class="rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700">Clear</button>
    </form>
</template>
