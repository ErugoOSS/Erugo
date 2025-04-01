<script setup>
import { ref, onMounted } from 'vue'
import { UserPlus, Hand } from 'lucide-vue-next'
import { getApiUrl } from '../utils'
import { createFirstUser, login } from '../api'
import { useToast } from 'vue-toastification'
import { store } from '../store'

import { useTranslate } from '@tolgee/vue'

const apiUrl = getApiUrl()
const toast = useToast()
const emailInput = ref(null)
const { t } = useTranslate()
const newUser = ref({
  username: '',
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

const errors = ref({
  name: '',
  password: '',
  email: '',
  password_confirmation: ''
})

onMounted(() => {
  emailInput.value.focus()
  //set the title
  document.title = 'Erugo First Run Setup'
})

const saveUser = async () => {
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
    toast.success('User created successfully! Logging you in...')
    setTimeout(async () => {
      try {
        const data = await login(newUser.value.email, newUser.value.password)
        store.authSuccess(data)
        window.location.href = '/'
      } catch (error) {
        toast.error('Failed to login')
      }
    }, 1000)
  } catch (error) {
    errors.value = error.data.errors
    toast.error('Failed to create user')
  }
}
</script>

<template>
  <div class="setup-container">
    <div class="setup-inner">
      <div class="setup-logo-container">
        <img src="../assets/images/erugo-logo.png" alt="Erugo" class="setup-logo" />
      </div>

      <p>
        {{ t('setup.intro') }}
      </p>

      <div class="seperator">
        
      </div>


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
            @keyup.enter="saveUser"
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
            @keyup.enter="saveUser"
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
            @keyup.enter="saveUser"
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
            @keyup.enter="saveUser"
          />
          <div class="error-message" v-if="errors.password_confirmation">
            {{ errors.password_confirmation[0] }}
          </div>
        </div>

        <div class="button-bar mt-3">
          <button @click="saveUser">
            <UserPlus />
            {{ t('setup.create_admin_account') }}
          </button>
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

  .setup-inner {
    background: var(--panel-background-color);
    padding: 20px;
    border-radius: var(--panel-border-radius);
    width: 30%;
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
}

.setup-logo {
  width: 100px;
  margin-top: 10px;
  margin-bottom: 15px;
  display: block;
  margin-left: auto;
  margin-right: auto;
}

.seperator {
  width: 100%;
  height: 1px;
  margin-top: 10px;
  margin-bottom: 20px;
  background: var(--panel-item-background-color);
}
</style>
