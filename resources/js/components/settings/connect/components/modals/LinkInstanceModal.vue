<script setup>
import { Link, Loader2, CircleX } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  instance: Object
})

const emit = defineEmits(['update:show', 'confirm'])
</script>

<template>
  <div class="auth-form-overlay" :class="{ active: show }" @click.self="emit('update:show', false)">
    <div class="auth-slide-form">
      <h2>
        <Link />
        {{ $t('cloudConnect.instances.linkTitle') || 'Link Instance' }}
      </h2>
      <p>
        {{
          $t('cloudConnect.instances.linkDescription') ||
          'Link this instance to your current Erugo installation. This will allow you to connect to the tunnel using this instance.'
        }}
      </p>
      <div v-if="instance" class="instance-to-link">
        <strong>{{ instance.name }}</strong>
        <span>{{ instance.full_domain || `${instance.subdomain}.erugo.cloud` }}</span>
      </div>
      <p class="info-message">
        {{
          $t('cloudConnect.instances.linkNote') ||
          'Note: A new instance token will be generated. Any other Erugo installations using this instance will need to be re-linked.'
        }}
      </p>
      <div class="button-bar">
        <button type="button" @click="emit('confirm')" :disabled="loading">
          <Loader2 v-if="loading" class="spinner" />
          <Link v-else />
          {{ $t('cloudConnect.instances.confirmLink') || 'Link Instance' }}
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

.instance-to-link {
  background: var(--panel-section-background-color-alt);
  border: 1px solid var(--panel-border-color);
  border-radius: 8px;
  padding: 16px;
  margin: 16px 0;
  text-align: center;

  strong {
    display: block;
    font-size: 1.1rem;
    margin-bottom: 4px;
  }

  span {
    font-size: 0.9rem;
    opacity: 0.7;
  }
}

.info-message {
  font-size: 0.875rem;
  opacity: 0.8;
  background: color-mix(in srgb, var(--color-info, #3b82f6) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-info, #3b82f6) 30%, transparent);
  border-radius: 8px;
  padding: 12px;
  margin: 16px 0;
}
</style>

