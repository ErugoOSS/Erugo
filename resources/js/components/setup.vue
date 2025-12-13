<script setup>
import { ref, computed, onMounted } from 'vue'
import { UserPlus, LogIn, Settings } from 'lucide-vue-next'
import { getApiUrl } from '../utils'
import { createFirstUser, saveFirstRunSettings, login } from '../api'
import { useToast } from 'vue-toastification'
import { store } from '../store'
import FileInput from './fileInput.vue'

import { useTranslate } from '@tolgee/vue'

const apiUrl = getApiUrl()
const toast = useToast()
const emailInput = ref(null)
const { t } = useTranslate()

// Step 1 = account creation, Step 2 = optional settings
const currentStep = ref(1)
const isLoggingIn = ref(false)

const newUser = ref({
  username: '',
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

// Get the current URL from the browser as default
const getCurrentUrl = () => {
  const url = new URL(window.location.href)
  // Remove any path, just keep scheme + host + port
  return `${url.protocol}//${url.host}`
}

const optionalSettings = ref({
  application_name: '',
  application_url: getCurrentUrl(),
  logo: null
})

const errors = ref({
  name: '',
  password: '',
  email: '',
  password_confirmation: '',
  application_name: '',
  application_url: '',
  logo: ''
})

const logoPreview = computed(() => {
  if (optionalSettings.value.logo instanceof File) {
    return { url: URL.createObjectURL(optionalSettings.value.logo) }
  }
  return null
})

onMounted(() => {
  emailInput.value.focus()
  //set the title
  document.title = 'Erugo First Run Setup'
})

// Step 1: Create the admin account
const createAccount = async () => {
  errors.value = {}
  if (newUser.value.password !== newUser.value.password_confirmation) {
    errors.value.password_confirmation = 'Password confirmation does not match'
  }

  if (Object.keys(errors.value).length > 0) {
    toast.error('Please fix the errors before saving')
    return
  }

  try {
    await createFirstUser(newUser.value)
    toast.success(t.value('setup.account_created'))
    // Move to step 2
    currentStep.value = 2
  } catch (error) {
    if (error.data && error.data.errors) {
      errors.value = error.data.errors
    }
    toast.error('Failed to create user')
  }
}

// Step 2: Save optional settings and login
const saveSettingsAndLogin = async () => {
  isLoggingIn.value = true
  errors.value = {}
  
  try {
    // First login to get auth token
    const data = await login(newUser.value.email, newUser.value.password)
    store.authSuccess(data)
    
    // Now save the optional settings (authenticated)
    const settingsToSave = {}
    
    if (optionalSettings.value.application_name) {
      settingsToSave.application_name = optionalSettings.value.application_name
    }
    if (optionalSettings.value.application_url) {
      settingsToSave.application_url = optionalSettings.value.application_url
    }
    if (optionalSettings.value.logo instanceof File) {
      settingsToSave.logo = optionalSettings.value.logo
    }
    
    // Only save if there are settings to save
    if (Object.keys(settingsToSave).length > 0) {
      await saveFirstRunSettings(settingsToSave)
    }
    
    toast.success(t.value('setup.settings_saved'))
    window.location.href = '/'
  } catch (error) {
    isLoggingIn.value = false
    if (error.data && error.data.errors) {
      errors.value = error.data.errors
    }
    toast.error('Failed to save settings')
  }
}

// Skip settings and just login
const skipAndLogin = async () => {
  isLoggingIn.value = true
  
  try {
    const data = await login(newUser.value.email, newUser.value.password)
    store.authSuccess(data)
    window.location.href = '/'
  } catch (error) {
    isLoggingIn.value = false
    toast.error('Failed to login')
  }
}
</script>

<template>
  <div class="setup-container">
    <div class="setup-inner">
      <div class="setup-logo-container">
        <img :src="'/images/logo.png'" alt="Erugo" class="setup-logo" />
      </div>

      <!-- Step indicator -->
      <div class="step-indicator">
        <div class="step" :class="{ active: currentStep === 1, completed: currentStep > 1 }">1</div>
        <div class="step-line" :class="{ completed: currentStep > 1 }"></div>
        <div class="step" :class="{ active: currentStep === 2 }">2</div>
      </div>

      <!-- Step 1: Create Admin Account -->
      <div v-if="currentStep === 1">
        <p>
          {{ t('setup.intro') }}
        </p>

        <div class="seperator"></div>

        <div class="setup-form">
          <!-- email -->
          <div class="input-container mt-2">
            <label for="email">{{ t('setup.first_user.email') }}</label>
            <input
              type="email"
              v-model="newUser.email"
              :placeholder="t('setup.first_user.email')"
              required
              id="email"
              :class="{ error: errors.email }"
              @keyup.enter="createAccount"
              ref="emailInput"
            />
            <div class="error-message" v-if="errors.email">
              {{ errors.email[0] }}
            </div>
          </div>

          <!-- full name -->
          <div class="input-container mt-2">
            <label for="name">{{ t('setup.first_user.name') }}</label>
            <input
              type="text"
              v-model="newUser.name"
              :placeholder="t('setup.first_user.name')"
              required
              id="name"
              :class="{ error: errors.name }"
              @keyup.enter="createAccount"
            />
            <div class="error-message" v-if="errors.name">
              {{ errors.name[0] }}
            </div>
          </div>

          <!-- password -->
          <div class="input-container mt-2">
            <label for="password">{{ t('setup.first_user.password') }}</label>
            <input
              type="password"
              v-model="newUser.password"
              :placeholder="t('setup.first_user.password')"
              required
              id="password"
              :class="{ error: errors.password }"
              @keyup.enter="createAccount"
            />
            <div class="error-message" v-if="errors.password">
              {{ errors.password[0] }}
            </div>
          </div>

          <!-- confirm password -->
          <div class="input-container mt-2">
            <label for="password_confirmation">{{ t('setup.first_user.password_confirmation') }}</label>
            <input
              type="password"
              v-model="newUser.password_confirmation"
              :placeholder="t('setup.first_user.password_confirmation')"
              required
              id="password_confirmation"
              :class="{ error: errors.password_confirmation }"
              @keyup.enter="createAccount"
            />
            <div class="error-message" v-if="errors.password_confirmation">
              {{ errors.password_confirmation[0] }}
            </div>
          </div>

          <div class="button-bar mt-3">
            <button @click="createAccount">
              <UserPlus />
              {{ t('setup.create_admin_account') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Step 2: Optional Settings -->
      <div v-if="currentStep === 2">
        <p>
          {{ t('setup.step2_intro') }}
        </p>

        <div class="seperator"></div>

        <div class="setup-form">
          <div class="input-container mt-2">
            <label for="application_name">{{ t('setup.application_name') }}</label>
            <input
              type="text"
              v-model="optionalSettings.application_name"
              :placeholder="t('setup.application_name_placeholder')"
              id="application_name"
              :class="{ error: errors.application_name }"
            />
            <div class="error-message" v-if="errors.application_name">
              {{ errors.application_name[0] }}
            </div>
          </div>

          <div class="input-container mt-2">
            <label for="application_url">{{ t('setup.application_url') }}</label>
            <input
              type="url"
              v-model="optionalSettings.application_url"
              :placeholder="t('setup.application_url_placeholder')"
              id="application_url"
              :class="{ error: errors.application_url }"
            />
            <div class="error-message" v-if="errors.application_url">
              {{ errors.application_url[0] }}
            </div>
          </div>

          <div class="input-container mt-2">
            <label for="logo">{{ t('setup.logo') }}</label>
            <FileInput 
              v-model="optionalSettings.logo"
              accept="image/png, image/svg+xml"
              :preview="logoPreview"
              :label="t('setup.logo_placeholder')"
            />
            <div class="error-message" v-if="errors.logo">
              {{ errors.logo[0] }}
            </div>
          </div>

          <div class="button-bar mt-3">
            <button @click="saveSettingsAndLogin" :disabled="isLoggingIn">
              <Settings />
              {{ t('setup.save_and_login') }}
            </button>
          </div>

          <div class="button-bar mt-2">
            <button class="skip-button" @click="skipAndLogin" :disabled="isLoggingIn">
              <LogIn />
              {{ t('setup.skip_and_login') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.setup-container {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 230;
  display: flex;
  justify-content: center;
  align-items: center;
  // backdrop-filter: blur(10px);
  pointer-events: none;
  overflow-y: auto;
  padding: 20px 0;

  .setup-inner {
    background: var(--panel-background-color);
    padding: 20px;
    border-radius: var(--panel-border-radius);
    width: 30%;
    min-width: 350px;
    max-width: 500px;
    pointer-events: auto;
    h1 {
      font-size: 24px;
      color: var(--panel-text-color);
      display: flex;
      align-items: center;
      gap: 10px;
      svg {
        font-size: 24px;
        margin-right: 5px;
        margin-top: -1px;
      }
    }
  }
}

button {
  display: block;
  width: 100%;
  
  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.setup-logo {
  width: 100px;
  margin-top: 10px;
  margin-bottom: 15px;
  display: block;
  margin-left: auto;
  margin-right: auto;
}

.step-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
  gap: 0;

  .step {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--panel-item-background-color);
    color: var(--panel-text-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85em;
    font-weight: 600;
    transition: all 0.3s ease;
    opacity: 0.5;

    &.active {
      background: var(--button-background-color);
      color: var(--button-text-color);
      opacity: 1;
    }

    &.completed {
      background: var(--button-background-color);
      color: var(--button-text-color);
      opacity: 0.7;
    }
  }

  .step-line {
    width: 40px;
    height: 2px;
    background: var(--panel-item-background-color);
    transition: background 0.3s ease;

    &.completed {
      background: var(--button-background-color);
    }
  }
}

.seperator {
  width: 100%;
  height: 1px;
  margin-top: 10px;
  margin-bottom: 20px;
  background: var(--panel-item-background-color);
}

.skip-button {
  background: transparent;
  border: 1px solid var(--input-border-color);
  color: var(--panel-text-color);
  opacity: 0.8;

  &:hover:not(:disabled) {
    opacity: 1;
    background: var(--panel-item-hover-background-color);
  }
}
</style>
