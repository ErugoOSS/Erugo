<script setup>
import { Key, Loader2, CircleX } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  instance: Object
})

const emit = defineEmits(['update:show', 'confirm'])
</script>

<template>
  <div class="auth-form-overlay" :class="{ active: show }" @click.self="emit('update:show', false)">
    <div class="auth-slide-form warning-dialog">
      <h2>
        <Key />
        {{ $t('cloudConnect.instances.regenerateTokenTitle') || 'Regenerate Token' }}
      </h2>
      <p class="warning-message">
        {{
          $t('cloudConnect.instances.regenerateTokenWarning') ||
          'Regenerating the token will invalidate the current token. You will need to reconnect this instance.'
        }}
      </p>
      <div v-if="instance" class="instance-to-delete">
        <strong>{{ instance.name }}</strong>
        <span>{{ instance.full_domain || `${instance.subdomain}.erugo.cloud` }}</span>
      </div>
      <div class="button-bar">
        <button type="button" @click="emit('confirm')" :disabled="loading">
          <Loader2 v-if="loading" class="spinner" />
          <Key v-else />
          {{ $t('cloudConnect.instances.confirmRegenerate') || 'Regenerate Token' }}
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

