<script setup>
import { AlertTriangle, RefreshCw, Loader2, CircleX } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  instanceName: String,
  subdomain: String
})

const emit = defineEmits(['update:show', 'confirm', 'cancel'])
</script>

<template>
  <div class="auth-form-overlay" :class="{ active: show }">
    <div class="auth-slide-form reclaim-dialog">
      <h2>
        <AlertTriangle />
        {{ $t('cloudConnect.reclaimInstance.title') }}
      </h2>
      <p class="reclaim-message">
        {{ $t('cloudConnect.reclaimInstance.message', { name: instanceName || subdomain }) }}
      </p>
      <div class="button-bar">
        <button type="button" @click="emit('confirm')" :disabled="loading" class="secondary">
          <Loader2 v-if="loading" class="spinner" />
          <RefreshCw v-else />
          {{ $t('cloudConnect.reclaimInstance.confirm') }}
        </button>
        <button type="button" class="secondary close-button" @click="emit('cancel')">
          <CircleX />
          {{ $t('cloudConnect.reclaimInstance.cancel') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import './modalStyles.scss';
</style>

