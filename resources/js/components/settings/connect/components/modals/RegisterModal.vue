<script setup>
import { UserPlus, Loader2, CircleX } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  registerForm: Object
})

const emit = defineEmits(['update:show', 'submit', 'switchToLogin', 'clickOutside'])

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
        <UserPlus />
        {{ $t('cloudConnect.auth.register') }}
      </h2>
      <p>{{ $t('cloudConnect.auth.registerDescription') }}</p>
      <form @submit.prevent="emit('submit')">
        <div class="input-container">
          <label for="register-name">{{ $t('cloudConnect.auth.name') }}</label>
          <input
            type="text"
            id="register-name"
            :value="registerForm.name"
            @input="registerForm.name = $event.target.value"
            required
            :placeholder="$t('cloudConnect.auth.namePlaceholder')"
          />
        </div>
        <div class="input-container">
          <label for="register-email">{{ $t('cloudConnect.auth.email') }}</label>
          <input
            type="email"
            id="register-email"
            :value="registerForm.email"
            @input="registerForm.email = $event.target.value"
            required
            :placeholder="$t('cloudConnect.auth.emailPlaceholder')"
          />
        </div>
        <div class="input-container">
          <label for="register-password">{{ $t('cloudConnect.auth.password') }}</label>
          <input
            type="password"
            id="register-password"
            :value="registerForm.password"
            @input="registerForm.password = $event.target.value"
            required
            minlength="8"
            :placeholder="$t('cloudConnect.auth.passwordPlaceholder')"
          />
        </div>
        <div class="input-container">
          <label for="register-password-confirm">{{ $t('cloudConnect.auth.confirmPassword') }}</label>
          <input
            type="password"
            id="register-password-confirm"
            :value="registerForm.password_confirmation"
            @input="registerForm.password_confirmation = $event.target.value"
            required
            :placeholder="$t('cloudConnect.auth.confirmPasswordPlaceholder')"
          />
        </div>
        <div class="checkbox-container">
          <input 
            type="checkbox" 
            id="accept-terms" 
            :checked="registerForm.accept_terms"
            @change="registerForm.accept_terms = $event.target.checked"
            required 
          />
          <label for="accept-terms">
            {{ $t('cloudConnect.auth.acceptTerms') }}
            <a href="https://erugo.cloud/terms" target="_blank">{{ $t('cloudConnect.auth.termsLink') }}</a>
          </label>
        </div>
        <div class="checkbox-container">
          <input 
            type="checkbox" 
            id="accept-privacy" 
            :checked="registerForm.accept_privacy"
            @change="registerForm.accept_privacy = $event.target.checked"
            required 
          />
          <label for="accept-privacy">
            {{ $t('cloudConnect.auth.acceptPrivacy') }}
            <a href="https://erugo.cloud/privacy" target="_blank">{{ $t('cloudConnect.auth.privacyLink') }}</a>
          </label>
        </div>
        <div class="checkbox-container">
          <input 
            type="checkbox" 
            id="accept-marketing" 
            :checked="registerForm.accept_marketing"
            @change="registerForm.accept_marketing = $event.target.checked"
          />
          <label for="accept-marketing">{{ $t('cloudConnect.auth.acceptMarketing') }}</label>
        </div>
        <div class="button-bar">
          <button type="submit" :disabled="loading">
            <Loader2 v-if="loading" class="spinner" />
            <UserPlus v-else />
            {{ $t('cloudConnect.auth.registerButton') }}
          </button>
          <button type="button" class="secondary close-button" @click="emit('update:show', false)">
            <CircleX />
            {{ $t('settings.close') }}
          </button>
        </div>
      </form>
      <p class="switch-form-text">
        {{ $t('cloudConnect.auth.haveAccount') }}
        <button type="button" class="btn-text-inline" @click="emit('switchToLogin')">
          {{ $t('cloudConnect.auth.loginInstead') }}
        </button>
      </p>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import './modalStyles.scss';
</style>

