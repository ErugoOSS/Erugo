<script setup>
import { Trash2, Loader2, CircleX } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  instance: Object
})

const emit = defineEmits(['update:show', 'confirm'])
</script>

<template>
  <div class="auth-form-overlay" :class="{ active: show }" @click.self="emit('update:show', false)">
    <div class="auth-slide-form danger-dialog">
      <h2>
        <Trash2 />
        {{ $t('cloudConnect.instances.deleteTitle') || 'Delete Instance' }}
      </h2>
      <p class="danger-message">
        {{
          $t('cloudConnect.instances.deleteWarning') ||
          'Are you sure you want to delete this instance? This action cannot be undone.'
        }}
      </p>
      <div v-if="instance" class="instance-to-delete">
        <strong>{{ instance.name }}</strong>
        <span>{{ instance.full_domain || `${instance.subdomain}.erugo.cloud` }}</span>
      </div>
      <div class="button-bar">
        <button type="button" class="danger" @click="emit('confirm')" :disabled="loading">
          <Loader2 v-if="loading" class="spinner" />
          <Trash2 v-else />
          {{ $t('cloudConnect.instances.confirmDelete') || 'Delete Instance' }}
        </button>
        <button type="button" class="secondary close-button" @click="emit('update:show', false)">
          <CircleX />
          {{ $t('settings.close') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import './modalStyles.scss';
</style>

