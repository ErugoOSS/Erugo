<script setup>
import { CheckCircle, Copy, Check } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  token: String
})

const emit = defineEmits(['update:show', 'copyToken'])
</script>

<template>
  <div class="auth-form-overlay" :class="{ active: show }" @click.self="emit('update:show', false)">
    <div class="auth-slide-form">
      <h2>
        <CheckCircle />
        {{ $t('cloudConnect.instances.tokenRegeneratedTitle') || 'New Token Generated' }}
      </h2>
      <p>
        {{
          $t('cloudConnect.instances.tokenRegeneratedDescription') ||
          "Your new instance token is shown below. Make sure to copy it now - you won't be able to see it again!"
        }}
      </p>
      <div class="token-display">
        <code>{{ token }}</code>
        <button
          type="button"
          class="icon-only"
          @click="emit('copyToken')"
          :title="$t('cloudConnect.instances.copyToken') || 'Copy Token'"
        >
          <Copy />
        </button>
      </div>
      <div class="button-bar">
        <button type="button" @click="emit('update:show', false)">
          <Check />
          {{ $t('cloudConnect.instances.done') || 'Done' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import './modalStyles.scss';
</style>

