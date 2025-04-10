<script setup>
import { ref, onMounted } from 'vue'
import { getApiUrl } from '../utils'
import { useToast } from 'vue-toastification'
import { domData } from '../domData'
import { KeyRound, Fingerprint } from 'lucide-vue-next'
import { store } from '../store'
import {
  login,
  refresh,
  logout,
  forgotPassword,
  resetPassword,
  getAvailableAuthProviders,
  acceptReverseShareInvite
} from '../api'

import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()

const apiUrl = getApiUrl()
const toast = useToast()
const email = ref('')
const password = ref('')
const password_confirmation = ref('')
const passwordInput = ref(null)
const loginMessage = domData().login_message
const forgotPasswordMode = ref(false)
const haveResetToken = ref(false)
const resetToken = ref('')
const waitingForRedirect = ref(false)
const authProviders = ref([])
const reverseShareToken = ref('')

onMounted(async () => {
  attemptRefresh()

  const token = domData().token
  if (token) {
    haveResetToken.value = true
    resetToken.value = token
  }
  getAvailableAuthProviders().then((data) => {
    authProviders.value = data
  })

  //grab reverse share token from url
  const urlParams = new URLSearchParams(window.location.search)
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
  } catch (error) {
    console.log(error)
    toast.error(t.value('auth.invalid_email_or_password'))
  }
}

const attemptRefresh = () => {
  refresh()
    .then((data) => {
      store.authSuccess(data)
    })
    .catch((error) => {
      //noop
    })
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
</script>

<template>
  <div class="auth-container" v-if="!haveResetToken && !waitingForRedirect">
    <div class="auth-container-inner">
      <template v-if="!forgotPasswordMode">
        <h1>{{ $t('auth.welcome') }}</h1>
        <p>{{ loginMessage }}</p>
      </template>
      <template v-else>
        <h1>{{ $t('auth.forgot_password') }}</h1>
        <p>{{ $t('auth.please_enter_email_to_reset_password') }}</p>
      </template>
      <div class="input-container">
        <label for="email">{{ $t('auth.email') }}</label>
        <input type="text" v-model="email" :placeholder="$t('auth.email')" @keyup.enter="moveToPassword" />
      </div>
      <div class="input-container" v-if="!forgotPasswordMode">
        <label for="password">{{ $t('auth.password') }}</label>
        <input
          type="password"
          v-model="password"
          :placeholder="$t('auth.password')"
          @keyup.enter="attemptLogin"
          ref="passwordInput"
        />
      </div>
      <div class="row mt-3 align-items-center w-100" v-if="!forgotPasswordMode">
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
      <div class="row mt-3 align-items-center" v-if="forgotPasswordMode">
        <div class="col">
          <button class="block" @click="attemptForgotPassword">
            <KeyRound />
            {{ $t('auth.request_reset') }}
          </button>
        </div>
        <div class="col">
          <a href="" @click.prevent="forgotPasswordMode = false">{{ $t('auth.back_to_login') }}</a>
        </div>
      </div>

      <template v-if="authProviders.length > 0 && !waitingForRedirect && !forgotPasswordMode">
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
</style>
