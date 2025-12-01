<script setup>
import { Mail, Loader2, CircleX } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  email: String
})

const emit = defineEmits(['update:show', 'update:email', 'submit'])
</script>

<template>
  <div class="auth-form-overlay" :class="{ active: show }" @click.self="emit('update:show', false)">
    <div class="auth-slide-form">
      <h2>
        <Mail />
        {{ $t('cloudConnect.auth.forgotPasswordTitle') || 'Reset Password' }}
      </h2>
      <p>
        {{
          $t('cloudConnect.auth.forgotPasswordDescription') ||
          "Enter your email address and we'll send you a link to reset your password."
        }}
      </p>
      <form @submit.prevent="emit('submit')">
        <div class="input-container">
          <label for="forgot-email">{{ $t('cloudConnect.auth.email') }}</label>
          <input
            type="email"
            id="forgot-email"
            :value="email"
            @input="emit('update:email', $event.target.value)"
            required
            :placeholder="$t('cloudConnect.auth.emailPlaceholder')"
          />
        </div>
        <div class="button-bar">
          <button type="submit" :disabled="loading">
            <Loader2 v-if="loading" class="spinner" />
            <Mail v-else />
            {{ $t('cloudConnect.auth.sendResetLink') || 'Send Reset Link' }}
          </button>
          <button type="button" class="secondary close-button" @click="emit('update:show', false)">
            <CircleX />
            {{ $t('settings.close') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import './modalStyles.scss';
</style>

