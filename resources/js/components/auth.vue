<script setup>
import { ref, onMounted } from 'vue'
import { getApiUrl } from '../utils'
import { useToast } from 'vue-toastification'
import { domData } from '../domData'
import { KeyRound, Fingerprint, UserPlus, Mail, ArrowLeft } from 'lucide-vue-next'
import { store } from '../store'
import {
  login,
  refresh,
  logout,
  forgotPassword,
  resetPassword,
  getAvailableAuthProviders,
  acceptReverseShareInvite,
  acceptReverseShareInviteById,
  getRegistrationSettings,
  registerUser,
  verifyEmail,
  resendVerificationCode
} from '../api'

import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()

const apiUrl = getApiUrl()
const toast = useToast()
const email = ref('')
const name = ref('')
const password = ref('')
const password_confirmation = ref('')
const verificationCode = ref('')
const passwordInput = ref(null)
const loginMessage = domData().login_message
const forgotPasswordMode = ref(false)
const haveResetToken = ref(false)
const resetToken = ref('')
const waitingForRedirect = ref(false)
const authProviders = ref([])
const reverseShareToken = ref('')

// Reverse share invite by ID (for existing users)
const pendingInviteId = ref(null)
const hasInviteIdPending = ref(false)

// Registration state
const registrationMode = ref(false)
const verificationMode = ref(false)
const selfRegistrationEnabled = ref(false)
const registrationEmail = ref('')
const isRegistering = ref(false)
const isVerifying = ref(false)
const isResending = ref(false)

onMounted(async () => {
  const token = domData().token
  if (token) {
    haveResetToken.value = true
    resetToken.value = token
  }

  // Parse URL parameters FIRST (before any async operations)
  // This ensures invite_id is set before attemptRefresh() completes
  const urlParams = new URLSearchParams(window.location.search)
  
  // Check for invite_id (for existing users who need to log in)
  const inviteId = urlParams.get('invite_id')
  if (inviteId) {
    pendingInviteId.value = inviteId
    hasInviteIdPending.value = true
    // Remove the invite_id from the URL but keep it stored
    window.history.replaceState({}, document.title, window.location.pathname)
  }

  // Now try to refresh (if user is already logged in, this will accept the pending invite)
  attemptRefresh()

  getAvailableAuthProviders().then((data) => {
    authProviders.value = data
  })

  // Check if self-registration is enabled
  try {
    const settings = await getRegistrationSettings()
    selfRegistrationEnabled.value = settings.self_registration_enabled
  } catch (error) {
    // Self-registration not available
    selfRegistrationEnabled.value = false
  }

  // Grab reverse share token from url (for guest users)
  reverseShareToken.value = urlParams.get('invite_token')
  if (reverseShareToken.value) {
    try {
      await acceptReverseShareInvite(reverseShareToken.value)
      toast.success(t.value('auth.invite_accepted'))
      //remove the invite token from the url
      window.history.replaceState({}, document.title, window.location.pathname)
      attemptRefresh()
    } catch (error) {
      //remove the invite token from the url
      window.history.replaceState({}, document.title, window.location.pathname)
      toast.error(t.value('auth.failed_to_accept_invite'))
    }
  }
})

const attemptLogin = async () => {
  if (email.value === '' || password.value === '') {
    toast.error(t.value('auth.please_enter_email_and_password'))
    return
  }

  try {
    const data = await login(email.value, password.value)
    store.authSuccess(data)
    toast.success(t.value('auth.login_successful'))

    // If there's a pending invite, accept it after successful login
    if (hasInviteIdPending.value && pendingInviteId.value) {
      try {
        await acceptReverseShareInviteById(pendingInviteId.value)
        toast.success(t.value('auth.invite_accepted'))
      } catch (inviteError) {
        toast.error(t.value('auth.failed_to_accept_invite'))
      } finally {
        hasInviteIdPending.value = false
        pendingInviteId.value = null
      }
    }
  } catch (error) {
    toast.error(t.value('auth.invalid_email_or_password'))
  }
}

const attemptRefresh = async () => {
  try {
    const data = await refresh()
    store.authSuccess(data)

    // If there's a pending invite and user is now logged in, accept it
    if (hasInviteIdPending.value && pendingInviteId.value) {
      try {
        await acceptReverseShareInviteById(pendingInviteId.value)
        toast.success(t.value('auth.invite_accepted'))
      } catch (inviteError) {
        toast.error(t.value('auth.failed_to_accept_invite'))
      } finally {
        hasInviteIdPending.value = false
        pendingInviteId.value = null
      }
    }
  } catch (error) {
    //noop
  }
}

const attemptLogout = async () => {
  await logout()
}

const moveToPassword = () => {
  passwordInput.value.focus()
}

const attemptForgotPassword = async () => {
  if (email.value === '') {
    toast.error(t.value('auth.please_enter_email'))
    return
  }
  try {
    await forgotPassword(email.value)
    toast.success(t.value('auth.password_reset_email_sent'))
    forgotPasswordMode.value = false
  } catch (error) {
    toast.error(t.value('auth.failed_to_send_password_reset_email'))
  }
}

const attemptResetPassword = async () => {
  if (password.value === '' || password_confirmation.value === '') {
    toast.error(t.value('auth.please_enter_password_and_confirm_password'))
    return
  }
  if (password.value !== password_confirmation.value) {
    toast.error(t.value('auth.passwords_do_not_match'))
    return
  }
  try {
    await resetPassword(resetToken.value, email.value, password.value, password_confirmation.value)
    toast.success(t.value('auth.password_reset_successfully'))
    haveResetToken.value = false
    waitingForRedirect.value = true
    setTimeout(() => {
      window.location.href = '/'
    }, 3000)
  } catch (error) {
    toast.error(t.value('auth.failed_to_reset_password'))
  }
}

const attemptAuthProviderLogin = (providerId) => {
  const newLocation = `/auth/provider/${providerId}/login`
  window.location.href = newLocation
}

// Registration methods
const attemptRegister = async () => {
  if (name.value === '' || email.value === '' || password.value === '' || password_confirmation.value === '') {
    toast.error(t.value('auth.please_fill_all_fields'))
    return
  }
  if (password.value !== password_confirmation.value) {
    toast.error(t.value('auth.passwords_do_not_match'))
    return
  }

  isRegistering.value = true
  try {
    const result = await registerUser(name.value, email.value, password.value, password_confirmation.value)
    toast.success(t.value('auth.registration_successful'))
    registrationEmail.value = email.value
    registrationMode.value = false
    verificationMode.value = true
    // Clear form
    name.value = ''
    password.value = ''
    password_confirmation.value = ''
  } catch (error) {
    toast.error(error.message || t.value('auth.registration_failed'))
  } finally {
    isRegistering.value = false
  }
}

const attemptVerifyEmail = async () => {
  if (verificationCode.value === '' || verificationCode.value.length !== 6) {
    toast.error(t.value('auth.please_enter_verification_code'))
    return
  }

  isVerifying.value = true
  try {
    await verifyEmail(registrationEmail.value, verificationCode.value)
    toast.success(t.value('auth.email_verified_successfully'))
    verificationMode.value = false
    verificationCode.value = ''
    email.value = registrationEmail.value
    registrationEmail.value = ''
  } catch (error) {
    toast.error(error.message || t.value('auth.verification_failed'))
  } finally {
    isVerifying.value = false
  }
}

const attemptResendCode = async () => {
  isResending.value = true
  try {
    await resendVerificationCode(registrationEmail.value)
    toast.success(t.value('auth.verification_code_resent'))
  } catch (error) {
    toast.error(error.message || t.value('auth.failed_to_resend_code'))
  } finally {
    isResending.value = false
  }
}

const switchToRegistration = () => {
  registrationMode.value = true
  forgotPasswordMode.value = false
}

const switchToLogin = () => {
  registrationMode.value = false
  verificationMode.value = false
  forgotPasswordMode.value = false
}
</script>

<template>
  <!-- Verification Code Screen -->
  <div class="auth-container" v-if="verificationMode">
    <div class="auth-container-inner">
      <h1>{{ $t('auth.verify_email') }}</h1>
      <p>{{ $t('auth.verification_code_sent', { email: registrationEmail }) }}</p>
      <div class="input-container">
        <label for="verification_code">{{ $t('auth.verification_code') }}</label>
        <input
          type="text"
          v-model="verificationCode"
          :placeholder="$t('auth.enter_6_digit_code')"
          @keyup.enter="attemptVerifyEmail"
          maxlength="6"
          class="verification-code-input"
        />
      </div>
      <div class="row mt-3 align-items-center w-100">
        <div class="col-12 ps-0 pe-0 mb-2">
          <button class="block" @click="attemptVerifyEmail" :disabled="isVerifying">
            <Mail />
            {{ isVerifying ? $t('auth.verifying') : $t('auth.verify_email') }}
          </button>
        </div>
      </div>
      <div class="row mt-2 align-items-center w-100">
        <div class="col-6 ps-0">
          <a href="" @click.prevent="attemptResendCode" :class="{ disabled: isResending }">
            {{ isResending ? $t('auth.sending') : $t('auth.resend_code') }}
          </a>
        </div>
        <div class="col-6 pe-0 text-end">
          <a href="" @click.prevent="switchToLogin">
            <ArrowLeft style="width: 14px; height: 14px; margin-top: -2px;" />
            {{ $t('auth.back_to_login') }}
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Auth Container -->
  <div class="auth-container" v-else-if="!haveResetToken && !waitingForRedirect">
    <div class="auth-container-inner">
      <!-- Login Mode -->
      <template v-if="!forgotPasswordMode && !registrationMode">
        <h1>{{ $t('auth.welcome') }}</h1>
        <p v-if="hasInviteIdPending">{{ $t('auth.login_to_accept_invite') }}</p>
        <p v-else>{{ loginMessage }}</p>
        <div class="input-container">
          <label for="email">{{ $t('auth.email') }}</label>
          <input type="text" v-model="email" :placeholder="$t('auth.email')" @keyup.enter="moveToPassword" />
        </div>
        <div class="input-container">
          <label for="password">{{ $t('auth.password') }}</label>
          <input
            type="password"
            v-model="password"
            :placeholder="$t('auth.password')"
            @keyup.enter="attemptLogin"
            ref="passwordInput"
          />
        </div>
        <div class="row mt-3 align-items-center w-100">
          <div class="col-6 ps-0">
            <button class="block" @click="attemptLogin">
              <KeyRound />
              {{ $t('auth.login') }}
            </button>
          </div>
          <div class="col-6 pe-0">
            <a href="" @click.prevent="forgotPasswordMode = true">{{ $t('auth.forgot_password') }}</a>
          </div>
        </div>

        <!-- Create Account Link -->
        <template v-if="selfRegistrationEnabled">
          <div class="row w-100 mt-4 mb-0 align-items-center">
            <div class="col">
              <hr />
            </div>
            <div class="col text-center pt-0 pb-0">
              <p class="m-0" style="font-size: 0.7rem; line-height: 0.8rem">{{ $t('auth.no_account') }}</p>
            </div>
            <div class="col">
              <hr />
            </div>
          </div>
          <div class="row mt-3 w-100">
            <div class="col ps-0 pe-0">
              <button class="block secondary" @click="switchToRegistration">
                <UserPlus />
                {{ $t('auth.create_account') }}
              </button>
            </div>
          </div>
        </template>
      </template>

      <!-- Forgot Password Mode -->
      <template v-else-if="forgotPasswordMode">
        <h1>{{ $t('auth.forgot_password') }}</h1>
        <p>{{ $t('auth.please_enter_email_to_reset_password') }}</p>
        <div class="input-container">
          <label for="email">{{ $t('auth.email') }}</label>
          <input type="text" v-model="email" :placeholder="$t('auth.email')" @keyup.enter="attemptForgotPassword" />
        </div>
        <div class="row mt-3 align-items-center w-100">
          <div class="col-6 ps-0">
            <button class="block" @click="attemptForgotPassword">
              <KeyRound />
              {{ $t('auth.request_reset') }}
            </button>
          </div>
          <div class="col-6 pe-0">
            <a href="" @click.prevent="switchToLogin">{{ $t('auth.back_to_login') }}</a>
          </div>
        </div>
      </template>

      <!-- Registration Mode -->
      <template v-else-if="registrationMode">
        <h1>{{ $t('auth.create_account') }}</h1>
        <p>{{ $t('auth.create_account_description') }}</p>
        <div class="input-container">
          <label for="name">{{ $t('auth.name') }}</label>
          <input type="text" v-model="name" :placeholder="$t('auth.name')" />
        </div>
        <div class="input-container">
          <label for="email">{{ $t('auth.email') }}</label>
          <input type="email" v-model="email" :placeholder="$t('auth.email')" />
        </div>
        <div class="input-container">
          <label for="password">{{ $t('auth.password') }}</label>
          <input type="password" v-model="password" :placeholder="$t('auth.password')" />
        </div>
        <div class="input-container">
          <label for="password_confirmation">{{ $t('auth.confirm_password') }}</label>
          <input
            type="password"
            v-model="password_confirmation"
            :placeholder="$t('auth.confirm_password')"
            @keyup.enter="attemptRegister"
          />
        </div>
        <div class="row mt-3 align-items-center w-100">
          <div class="col-6 ps-0">
            <button class="block" @click="attemptRegister" :disabled="isRegistering">
              <UserPlus />
              {{ isRegistering ? $t('auth.registering') : $t('auth.register') }}
            </button>
          </div>
          <div class="col-6 pe-0">
            <a href="" @click.prevent="switchToLogin">{{ $t('auth.back_to_login') }}</a>
          </div>
        </div>
      </template>

      <!-- Auth Providers -->
      <template v-if="authProviders.length > 0 && !waitingForRedirect && !forgotPasswordMode && !registrationMode">
        <div class="row w-100 mt-5 mb-0 align-items-center">
          <div class="col">
            <hr />
          </div>
          <div class="col text-center pt-0 pb-0">
            <p class="m-0" style="font-size: 0.7rem; line-height: 0.8rem">{{ $t('auth.or') }}</p>
            <p class="m-0" style="font-size: 0.7rem; line-height: 0.8rem">{{ $t('auth.login_with') }}</p>
          </div>
          <div class="col">
            <hr />
          </div>
        </div>

        <div class="row mt-4 w-100 gap-0">
          <div class="col-6 pe-1 ps-1 mb-2" v-for="provider in authProviders" :key="provider.id">
            <button class="block secondary provider-button" @click="attemptAuthProviderLogin(provider.id)">
              <Fingerprint v-if="!provider.icon" />
              <svg v-else v-html="provider.icon" class="custom"></svg>
              {{ provider.name }}
            </button>
          </div>
        </div>
      </template>
    </div>
    <svg id="gradientDefs">
      <linearGradient id="gradient">
        <stop offset="0%" style="stop-color: var(--link-color); stop-opacity: 1" />
        <stop offset="100%" style="stop-color: var(--link-color-hover); stop-opacity: 1" />
      </linearGradient>
    </svg>
  </div>

  <!-- Password Reset Token Screen -->
  <div class="auth-container" v-else-if="!waitingForRedirect">
    <div class="auth-container-inner">
      <h1>{{ t('auth.forgot_password_create_password') }}</h1>
      <p>{{ t('auth.forgot_password_create_password_description') }}</p>
      <div class="input-container">
        <label for="email">{{ t('auth.email') }}</label>
        <input type="text" v-model="email" :placeholder="t('auth.email')" @keyup.enter="moveToPassword" />
      </div>
      <div class="input-container">
        <label for="password">{{ t('auth.password') }}</label>
        <input
          type="password"
          v-model="password"
          :placeholder="t('auth.password')"
          @keyup.enter="attemptResetPassword"
        />
        <label for="password_confirmation">{{ t('auth.confirm_password') }}</label>
        <input
          type="password"
          v-model="password_confirmation"
          :placeholder="t('auth.confirm_password')"
          @keyup.enter="attemptResetPassword"
        />
      </div>
      <div class="row mt-3 align-items-center">
        <div class="col">
          <button class="block" @click="attemptResetPassword">
            <KeyRound />
            {{ t('auth.save_new_password') }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Waiting for Redirect Screen -->
  <div class="auth-container" v-else>
    <div class="auth-container-inner">
      <h1>{{ t('auth.password_set') }}</h1>
      <p>{{ t('auth.password_set_description') }}</p>
    </div>
  </div>
</template>

<style scoped lang="scss">
.provider-button {
  svg {
    stroke: url(#gradient);
    &.custom {
      fill: url(#gradient);
    }
  }
}

#gradientDefs {
  opacity: 0;
  position: absolute;
  top: 0;
  left: 0;
  width: 0;
  height: 0;
}

.verification-code-input {
  font-size: 1.5rem;
  letter-spacing: 0.5rem;
  text-align: center;
  font-family: monospace;
}

a.disabled {
  opacity: 0.5;
  pointer-events: none;
}
</style>
