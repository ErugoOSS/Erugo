<script setup>
import { LogIn, Loader2, CircleX } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  loginForm: Object
})

const emit = defineEmits(['update:show', 'submit', 'switchToRegister', 'forgotPassword', 'clickOutside'])

const handleClickOutside = (event) => {
  if (!event.target.closest('.auth-slide-form')) {
    emit('clickOutside')
  }
}
</script>

<template>
  <div class="auth-form-overlay" :class="{ active: show }" @click="handleClickOutside">
    <div class="auth-slide-form">
      <h2>
        <LogIn />
        {{ $t('cloudConnect.auth.login') }}
      </h2>
      <p>{{ $t('cloudConnect.auth.loginDescription') }}</p>
      <form @submit.prevent="emit('submit')">
        <div class="input-container">
          <label for="login-email">{{ $t('cloudConnect.auth.email') }}</label>
          <input
            type="email"
            id="login-email"
            :value="loginForm.email"
            @input="loginForm.email = $event.target.value"
            required
            :placeholder="$t('cloudConnect.auth.emailPlaceholder')"
          />
        </div>
        <div class="input-container">
          <label for="login-password">{{ $t('cloudConnect.auth.password') }}</label>
          <input
            type="password"
            id="login-password"
            :value="loginForm.password"
            @input="loginForm.password = $event.target.value"
            required
            :placeholder="$t('cloudConnect.auth.passwordPlaceholder')"
          />
        </div>
        <div class="button-bar">
          <button type="submit" :disabled="loading">
            <Loader2 v-if="loading" class="spinner" />
            <LogIn v-else />
            {{ $t('cloudConnect.auth.loginButton') }}
          </button>
          <button type="button" class="secondary close-button" @click="emit('update:show', false)">
            <CircleX />
            {{ $t('settings.close') }}
          </button>
        </div>
      </form>
      <p class="switch-form-text">
        {{ $t('cloudConnect.auth.noAccount') }}
        <button type="button" class="btn-text-inline" @click="emit('switchToRegister')">
          {{ $t('cloudConnect.auth.registerInstead') }}
        </button>
      </p>
      <p class="switch-form-text">
        <button type="button" class="btn-text-inline" @click="emit('forgotPassword')">
          {{ $t('cloudConnect.auth.forgotPassword') || 'Forgot your password?' }}
        </button>
      </p>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import './modalStyles.scss';
</style>

