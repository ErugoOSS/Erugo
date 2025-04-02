<script setup>
import { ref, onMounted, defineExpose, nextTick } from 'vue'
import { getMyProfile, updateMyProfile, getAvailableAuthProviders, unlinkProvider } from '../../api'
import { User, CircleX, UserRoundCheck, UserRoundPen, Fingerprint, Link, Unlink } from 'lucide-vue-next'
import { useToast } from 'vue-toastification'
import { store } from '../../store'

import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()

const toast = useToast()
const profile = ref(null)
const editUserFormActive = ref(false)
const editUser = ref({})
const errors = ref({})
const availableAuthProviders = ref([])
const onLocalhost = ref(false)

onMounted(async () => {
  await loadEverything()
  editUser.value = {
    ...profile.value,
    password: null,
    password_confirmation: null,
    current_password: null
  }
  if (store.autoShowProfileEdit) {
    editUserFormActive.value = true
    store.autoShowProfileEdit = false
  }
  onLocalhost.value = window.location.hostname === 'localhost'
})

const loadEverything = async () => {
  await loadProfile()
  nextTick(async () => {
    await loadAvailableAuthProviders()
  })
}

const loadProfile = async () => {
  profile.value = await getMyProfile()
}

const loadAvailableAuthProviders = async () => {
  let providers = await getAvailableAuthProviders()
  if (profile.value.linked_accounts.length > 0) {
    availableAuthProviders.value = providers.map((p) => {
      return {
        ...p,
        is_linked: profile.value.linked_accounts.some((ap) => ap.id === p.id)
      }
    })
  } else {
    availableAuthProviders.value = providers
  }
}

const editUserFormClickOutside = (e) => {
  if (!e.target.closest('.user-form')) {
    editUserFormActive.value = false
  }
}

const saveUser = async () => {
  errors.value = {}

  if (Object.keys(errors.value).length > 0) {
    toast.error(t.value('settings.users.profile_errors'))
    return
  }

  try {
    const profileData = {
      name: profile.value.name,
      email: profile.value.email
    }
    const updatedUser = await updateMyProfile(profileData)
    profile.value = updatedUser
    toast.success(t.value('settings.users.profile_updated'))
  } catch (error) {
    toast.error(t.value('settings.users.profile_update_failed'))
    errors.value = error.data.errors
  }
}

const savePassword = async () => {
  errors.value = {}

  if (!editUser.value.password) {
    errors.value.password = t.value('settings.users.password_required')
  }

  if (editUser.value.password.length < 8) {
    errors.value.password = t.value('settings.users.password_min_length', { length: 8 })
  }

  if (editUser.value.password !== editUser.value.password_confirmation) {
    errors.value.password_confirmation = t.value('settings.users.password_confirmation_mismatch')
  }

  if (editUser.value.password && editUser.value.current_password === null) {
    errors.value.current_password = t.value('settings.users.current_password_required')
  }

  if (Object.keys(errors.value).length > 0) {
    toast.error(t.value('settings.users.password_errors'))
    return
  }

  try {
    const passwordData = {
      password: editUser.value.password,
      password_confirmation: editUser.value.password_confirmation,
      current_password: editUser.value.current_password
    }
    const updatedUser = await updateMyProfile(passwordData)
    profile.value = updatedUser
    editUserFormActive.value = false
    toast.success(t.value('settings.users.password_updated'))
  } catch (error) {
    toast.error(t.value('settings.users.password_update_failed'))
    errors.value = error.data.errors
  }
}

//define exposed methods
defineExpose({
  saveUser
})

const emit = defineEmits(['navItemClicked'])
const handleNavItemClicked = (item) => {
  emit('navItemClicked', item)
}

const handleLinkProvider = (provider) => {
  const confirm = window.confirm(t.value('settings.users.link_provider_confirm'))
  if (confirm) {
    try {
      window.location.href = `/auth/provider/${provider.id}/link`
    } catch (error) {
      console.error('Failed to link provider', error)
    }
  }
}

const handleUnlinkProvider = async (provider) => {
  const confirm = window.confirm(t.value('settings.users.unlink_provider_confirm'))
  if (confirm) {
    try {
      await unlinkProvider(provider.id)
      toast.success(t.value('settings.users.provider_unlinked'))
      await loadEverything()
    } catch (error) {
      toast.error(t.value('settings.users.provider_unlink_failed'))
    }
  }
}
</script>

<template>
  <div class="container-fluid" v-if="profile">
    <div class="row mb-5">
      <div class="col-2 d-none d-md-block">
        <ul class="settings-nav pt-5">
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('my_account')">
              <User />
              {{ $t('settings.title.myProfile') }}
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('linked_accounts')">
              <Link />
              {{ $t('settings.title.linked_accounts') }}
            </a>
          </li>
        </ul>
      </div>
      <div class="col-12 col-md-8 pt-5">
        <div class="row mb-5">
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="my_account">
              <div class="setting-group-header">
                <h3>
                  <User />
                  {{ $t('settings.title.myProfile') }}
                </h3>
              </div>

              <div class="setting-group-body">
                <div class="setting-group-body-item">
                  <label for="email">{{ $t('settings.account.email') }}</label>
                  <input type="text" id="email" v-model="profile.email" />
                </div>

                <div class="setting-group-body-item">
                  <label for="name">{{ $t('settings.account.name') }}</label>
                  <input type="text" id="name" v-model="profile.name" />
                </div>

                <div class="setting-group-body-item mt-3">
                  <button class="secondary" @click="editUserFormActive = true">
                    <UserRoundPen />
                    {{ $t('settings.account.change_password') }}
                  </button>
                </div>

                <!--
                <div class="setting-group-body-item">
                  <label for="default_language">{{ $t('settings.system.default_language') }}</label>
                  <select id="default_language" v-model="settings.default_language">
                    <option value="en">{{ t('settings.system.languages.english') }}</option>
                    <option value="de">{{ t('settings.system.languages.german') }}</option>
                    <option value="fr">{{ t('settings.system.languages.french') }}</option>
                    <option value="it">{{ t('settings.system.languages.italian') }}</option>
                    <option value="nl">{{ t('settings.system.languages.dutch') }}</option>
                  </select>
                </div>
                -->
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{ $t('settings.title.myProfile') }}</h6>
              <p>{{ $t('settings.account.myProfile_description') }}</p>
            </div>
          </div>
        </div>

        <div class="row mb-5">
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="linked_accounts">
              <div class="setting-group-header">
                <h3>
                  <Link />
                  {{ $t('settings.title.linked_accounts') }}
                </h3>
              </div>
              <div class="setting-group-body" v-if="availableAuthProviders.length > 0 && profile">
                <svg id="gradientDefs">
                  <linearGradient id="icon-gradient">
                    <stop offset="0%" style="stop-color: var(--link-color); stop-opacity: 1" />
                    <stop offset="100%" style="stop-color: var(--link-color-hover); stop-opacity: 1" />
                  </linearGradient>
                </svg>
                <div class="provider" v-for="provider in availableAuthProviders" :key="provider.id">
                  <div class="row w-100 align-items-center">
                    <div class="col-auto">
                      <div class="icon">
                        <Fingerprint v-if="!provider.icon" />
                        <svg v-else v-html="provider.icon" class="custom"></svg>
                      </div>
                    </div>
                    <div class="col">
                      <small>
                        {{ provider.is_linked ? t('settings.users.linked') : t('settings.users.not_linked') }}
                      </small>
                      <h6>
                        {{ provider.name }}
                      </h6>
                    </div>
                    <div class="col-5">
                      <button class="block" @click="handleLinkProvider(provider)" v-if="!provider.is_linked">
                        <Link />
                        {{ $t('settings.account.create_link') }}
                      </button>
                      <button class="secondary block" @click="handleUnlinkProvider(provider)" v-if="provider.is_linked">
                        <Unlink />
                        {{ $t('settings.account.unlink') }}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{ $t('settings.title.linked_accounts') }}</h6>
              <p>{{ $t('settings.account.linked_accounts_description') }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="user-form-overlay" :class="{ active: editUserFormActive }" @click="editUserFormClickOutside">
    <div class="user-form">
      <h2>
        <UserRoundPen />
        {{ $t('settings.account.change_password') }}
      </h2>

      <div class="input-container">
        <label for="current_password">{{ $t('settings.users.current_password') }}</label>
        <input
          type="password"
          v-model="editUser.current_password"
          :placeholder="$t('settings.users.current_password')"
          required
          :class="{ error: errors.current_password }"
        />
        <div class="error-message" v-if="errors.current_password">
          {{ errors.current_password }}
        </div>
      </div>
      <div class="input-container">
        <label for="password">{{ $t('settings.users.password') }}</label>
        <input
          type="password"
          v-model="editUser.password"
          :placeholder="$t('settings.users.password')"
          required
          :class="{ error: errors.password }"
        />
        <div class="error-message" v-if="errors.password">
          {{ errors.password }}
        </div>
      </div>
      <div class="input-container">
        <label for="password_confirmation">{{ $t('settings.users.password_confirmation') }}</label>
        <input
          type="password"
          v-model="editUser.password_confirmation"
          :placeholder="$t('settings.users.password_confirmation')"
          required
          :class="{ error: errors.password_confirmation }"
        />
        <div class="error-message" v-if="errors.password_confirmation">
          {{ errors.password_confirmation }}
        </div>
      </div>

      <div class="button-bar">
        <button @click="savePassword">
          <UserRoundCheck />
          {{ $t('settings.users.save_changes') }}
        </button>
        <button class="secondary close-button" @click="editUserFormActive = false">
          <CircleX />
          {{ $t('settings.users.close') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.profile-card {
  width: 450px;
  border-radius: 10px;
  background: var(--panel-item-background-color);

  .profile-card-header {
    background: var(--panel-header-background-color);
    border-radius: 8px 8px 0 0;
    padding-left: 20px;
    padding-right: 20px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    h1 {
      font-size: 24px;
      font-weight: 600;
      color: var(--panel-text-color);
      display: flex;
      align-items: center;
      gap: 10px;
      svg {
        width: 24px;
        height: 24px;
        color: var(--panel-text-color);
      }
    }
  }

  .profile-card-tags {
    padding: 20px;
    background: var(--panel-item-background-color);
    border-radius: 8px 8px 0 0;
    margin-bottom: 0px;
    margin-right: 5px;
    margin-left: 5px;
    margin-top: -10px;
    display: flex;
    align-items: center;
    gap: 10px;
    .profile-card-tag {
      font-size: 14px;
      font-weight: 600;
      color: var(--panel-text-color);
      background: var(--panel-background-color);
      padding: 5px 10px;
      border-radius: 5px;
    }
  }

  .profile-card-profile-item {
    padding: 10px 20px;
    background: var(--panel-background-color);
    h2 {
      font-size: 16px;
      font-weight: 600;
      color: var(--panel-text-color);
    }
    p {
      font-size: 19px;
      font-weight: 400;
      color: var(--panel-text-color);
      padding: 0;
      margin: 0;
    }

    &:last-child {
      border-radius: 0 0 8px 8px;
      border-bottom: none;
    }
  }

  .profile-card-footer {
    padding: 10px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    button {
      display: block;
      width: 100%;
    }
  }
}

.user-form-overlay {
  border-radius: 10px 10px 0 0;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: var(--overlay-background-color);
  backdrop-filter: blur(10px);
  z-index: 230;
  opacity: 0;
  pointer-events: none;
  transition: all 0.3s ease;

  h2 {
    margin-bottom: 10px;
    font-size: 24px;
    color: var(--panel-text-color);
    display: flex;
    align-items: center;
    justify-content: center;

    svg {
      width: 24px;
      height: 24px;
      margin-right: 10px;
    }
  }
  .user-form {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translate(-50%, 100%);
    width: min(500px, 100vw);
    background: var(--panel-background-color);
    color: var(--panel-text-color);
    padding: 20px;
    border-radius: 10px 10px 0 0;
    box-shadow: 0 0 100px 0 rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;
    gap: 10px;
    transition: all 0.3s ease;
    padding-bottom: 20px;
    button {
      display: block;
      width: 100%;
    }
  }

  &.active {
    opacity: 1;
    pointer-events: auto;
    .user-form {
      transform: translate(-50%, 0%);
    }
  }
}

.provider {
  margin-bottom: 8px;
  .icon {
    svg {
      width: 2rem;
      height: 2rem;
      stroke: url(#icon-gradient);
      &.custom {
        fill: url(#icon-gradient);
      }
    }
  }
}

.provider {
  background: var(--panel-section-background-color-alt);
  height: 80px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-radius: var(--panel-border-radius);

  h6 {
    margin-top: -4px;
  }

  small {
    font-size: 0.7rem;
    color: var(--panel-text-color-alt);
    margin-bottom: 0;
  }

  .checkbox-container {
    margin-top: 25px;
    label {
      margin-bottom: 0;
      font-weight: 400;
    }
  }

  &.open {
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
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
