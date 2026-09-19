<script setup>
import SystemAlert from './SystemAlert.vue'
import { requestError } from '../support/requestErrors'
</script>

<template>
    <Teleport to="body">
        <div v-if="requestError" data-global-request-error class="fixed left-4 right-4 top-4 z-[10000] mx-auto max-w-xl print:hidden">
            <SystemAlert title="Unable to complete request" :messages="requestError" dismissible @dismiss="requestError = ''" />
        </div>
    </Teleport>
</template>
