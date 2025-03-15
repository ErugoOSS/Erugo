<script setup>
import { ref, onMounted, defineExpose, inject, computed, nextTick, watch } from 'vue'
import {
  Settings,
  Tag,
  Share2,
  Send,
  AtSign,
  Fingerprint,
  MessageCircleQuestion,
  ShieldCheck,
  ShieldBan,
  Eye,
  EyeOff,
  Plus,
  Trash,
  ExternalLink
} from 'lucide-vue-next'
import {
  getSettingsByGroup,
  saveSettingsById,
  getAuthProviders,
  bulkUpdateAuthProviders,
  getAvailableProviderTypes,
  deleteAuthProvider,
  getCallbackUrl
} from '../../api'
import HelpTip from '../helpTip.vue'

import { useToast } from 'vue-toastification'
import { mapSettings } from '../../utils'

import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()

const showHelpTip = inject('showHelpTip')
const activateNewProviderForm = ref(false)
const newProviderType = ref(null)
const availableProviderTypes = ref([])
const toast = useToast()
const onLocalhost = ref(false)

const settings = ref({
  application_name: '',
  application_url: '',
  login_message: '',
  max_expiry_time: '',
  max_share_size: '',
  max_share_size_unit: '',
  clean_files_after_days: '',
  emails_share_downloaded_enabled: '',
  smtp_host: '',
  smtp_port: '',
  smtp_username: '',
  smtp_password: '',
  smtp_sender_name: '',
  smtp_sender_address: ''
})

const settingsLoaded = ref(false)
const saving = ref(false)
const authProviders = ref([])

const emit = defineEmits(['navItemClicked'])

onMounted(async () => {
  await loadSettings()
  await loadAuthProviders()
  onLocalhost.value = window.location.hostname === 'localhost'
})

const loadSettings = async () => {
  try {
    settings.value = {
      ...mapSettings(await getSettingsByGroup('system.*')),
      ...mapSettings(await getSettingsByGroup('ui.*'))
    }

    settingsLoaded.value = true
  } catch (error) {
    toast.error(t.value('settings.failedToLoadSettings'))
    console.error(error)
  }
}

const loadAuthProviders = async () => {
  try {
    authProviders.value = await getAuthProviders()
    authProviders.value.forEach((authProvider) => {
      Object.keys(authProvider.provider_config).forEach((configKey) => {
        hideSecrets.value[`${authProvider.id}_${configKey}`] = mightBeSecret(configKey)
      })
    })
  } catch (error) {
    console.error(error)
  }
}

const saveAuthProviders = async () => {
  console.log('saving auth providers')
  saving.value = true
  try {
    await bulkUpdateAuthProviders(authProviders.value)
    saving.value = false
    toast.success(t.value('settings.authProvidersSavedSuccessfully'))
    if (!onLocalhost.value) {
      await loadAuthProviders()
    }
  } catch (error) {
    saving.value = false
    toast.error(t.value('settings.failedToSaveAuthProviders'))
    console.error(error)
  }
}

const saveSettings = async () => {
  console.log('saving settings')

  if (!shareSettingsLookOk()) {
    return
  }

  //check if settings.application_url ends with a / and remove it if it does
  if (settings.value.application_url.endsWith('/')) {
    settings.value.application_url = settings.value.application_url.slice(0, -1)
  }

  saving.value = true
  try {
    await saveSettingsById({
      ...settings.value
    })

    await saveAuthProviders()

    saving.value = false
    toast.success(t.value('settings.settingsSavedSuccessfully'))
    await loadSettings()
  } catch (error) {
    saving.value = false
    toast.error(t.value('settings.failedToSaveSettings'))
    console.error(error)
  }
}

const shareSettingsLookOk = () => {
  if (settings.value.allow_chunked_uploads == false && settings.allow_direct_uploads == false) {
    toast.error(t.value('settings.system.atLeastOneUploadMode'))
    return false
  }

  //check that the selected upload mode is enabled
  if (settings.value.default_upload_mode == 'direct' && settings.value.allow_direct_uploads == false) {
    toast.error(t.value('settings.system.direct_uploads_disabled_but_default'))
    return false
  }

  if (settings.value.default_upload_mode == 'chunked' && settings.value.allow_chunked_uploads == false) {
    toast.error(t.value('settings.system.chunked_uploads_disabled_but_default'))
    return false
  }

  return true
}


const handleNavItemClicked = (item) => {
  emit('navItemClicked', item)
}

//define exposed methods
defineExpose({
  saveSettings
})

const mightBeSecret = (key) => {
  return /secret|token|password|key/.test(key)
}

const hideSecrets = ref({})

const togglePasswordVisibility = (authProvider, configKey) => {
  hideSecrets.value[`${authProvider.id}_${configKey}`] = !hideSecrets.value[`${authProvider.id}_${configKey}`]
}

const newProviderButton = ref(null)

const handleNewProviderButtonClicked = async () => {
  //if the new provider type is not set, show the form
  if (!newProviderType.value) {
    availableProviderTypes.value = await getAvailableProviderTypes()
    activateNewProviderForm.value = true
  } else {
    const uuid = generateUUID()
    const newProvider = {
      name: newProviderType.value.name,
      description: newProviderType.value.description,
      icon: newProviderType.value.icon,
      class: newProviderType.value.class,
      provider_config: newProviderType.value.provider_config,
      uuid: uuid,
      enabled: false,
      editing: true,
      callback_url: await handleGetCallbackUrl(uuid)
    }
    authProviders.value.push(newProvider)
    newProviderType.value = null
    activateNewProviderForm.value = false
    await nextTick()
    handleNavItemClicked('new-provider')
  }
}

const handleGetCallbackUrl = async (uuid) => {
  const callbackUrl = await getCallbackUrl(uuid)
  return callbackUrl
}

const generateUUID = () => {
  //are we in a secure context?
  if (typeof window !== 'undefined' && window.crypto) {
    return window.crypto.randomUUID()
  }
  //fallback to a simple uuid
  return uuidv4()
}

const uuidv4 = () => {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
    var r = (Math.random() * 16) | 0,
      v = c == 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

const disableNewProviderButton = computed(() => {
  return activateNewProviderForm.value && !newProviderType.value
})

const handleDeleteAuthProvider = async (id) => {
  if (!confirm(t.value('settings.system.delete_auth_provider_confirmation'))) {
    return
  }

  try {
    await deleteAuthProvider(id)
    toast.success('Auth provider deleted successfully')
    await loadAuthProviders()
  } catch (error) {
    toast.error('Failed to delete auth provider')
    console.error(error)
  }
}
</script>
<template>
  <div class="container-fluid">
    <div class="row mb-5">
      <div class="col-2 d-none d-md-block">
        <ul class="settings-nav pt-5">
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('general')">
              <Settings />
              {{ $t('settings.system.general') }}
            </a>
          </li>
          <li>
            <a href="" @click.prevent="handleNavItemClicked('shares')">
              <Share2 />
              {{ $t('settings.system.shares') }}
            </a>
          </li>
          <li>
            <a href="" @click.prevent="handleNavItemClicked('emails')">
              <Send />
              {{ $t('settings.system.emails') }}
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('smtp')">
              <AtSign />
              {{ $t('settings.system.smtp') }}
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('auth')">
              <Fingerprint />
              {{ $t('settings.system.auth') }}
            </a>
          </li>
        </ul>
      </div>
      <div class="col-12 col-md-8 pt-5">
        <div class="row mb-5">
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="general">
              <div class="setting-group-header">
                <h3>
                  <Settings />
                  {{ $t('settings.system.general') }}
                </h3>
              </div>

              <div class="setting-group-body">
                <div class="setting-group-body-item">
                  <label for="application_name">{{ $t('settings.system.application_name') }}</label>
                  <input type="text" id="application_name" v-model="settings.application_name" />
                </div>

                <div class="setting-group-body-item">
                  <label for="application_url">{{ $t('settings.system.application_url') }}</label>
                  <input type="text" id="application_url" v-model="settings.application_url" />
                </div>

                <div class="setting-group-body-item">
                  <label for="login_message">{{ $t('settings.system.login_message') }}</label>
                  <input
                    type="text"
                    id="login_message"
                    v-model="settings.login_message"
                    placeholder="Login to your account to upload files."
                  />
                </div>

                <div class="setting-group-body-item">
                  <label for="default_language">{{ $t('settings.system.default_language') }}</label>
                  <select id="default_language" v-model="settings.default_language">
                    <!-- English-->
                    <option value="en">{{ t('settings.system.languages.english') }}</option>
                    <!-- German-->
                    <option value="de">{{ t('settings.system.languages.german') }}</option>
                    <!-- French-->
                    <option value="fr">{{ t('settings.system.languages.french') }}</option>
                    <!-- Italian-->
                    <option value="it">{{ t('settings.system.languages.italian') }}</option>
                    <!-- Dutch-->
                    <option value="nl">{{ t('settings.system.languages.dutch') }}</option>
                    <!-- Portuguese-->
                    <option value="pt">{{ t('settings.system.languages.portuguese') }}</option>
                  </select>
                </div>

                <div class="setting-group-body-item mt-3">
                  <div class="checkbox-container">
                    <input type="checkbox" id="show_language_selector" v-model="settings.show_language_selector" />
                    <label for="show_language_selector">{{ $t('settings.system.show_language_selector') }}</label>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{ $t('settings.system.application_name') }}</h6>
              <p>{{ $t('settings.system.application_name_description') }}</p>

              <h6>{{ $t('settings.system.application_url') }}</h6>
              <p>{{ $t('settings.system.application_url_description') }}</p>

              <h6>{{ $t('settings.system.login_message') }}</h6>
              <p>{{ $t('settings.system.login_message_description') }}</p>

              <h6>{{ $t('settings.system.default_language') }}</h6>
              <p>{{ $t('settings.system.default_language_description') }}</p>

              <h6>{{ $t('settings.system.show_language_selector') }}</h6>
              <p>{{ $t('settings.system.show_language_selector_description') }}</p>
            </div>
          </div>
        </div>

        <div class="row mb-5">
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="shares">
              <div class="setting-group-header">
                <h3>
                  <Share2 />
                  {{ $t('settings.system.shares') }}
                </h3>
              </div>

              <div class="setting-group-body">
                <div class="setting-group-body-item">
                  <label for="max_expiry_time">
                    {{ $t('settings.system.max_expiry_time') }}
                    <small>({{ $t('settings.system.days') }})</small>
                  </label>
                  <input type="number" id="max_expiry_time" v-model="settings.max_expiry_time" placeholder="∞" />
                </div>
                <div class="setting-group-body-item">
                  <div class="row">
                    <div class="col pe-0">
                      <label for="max_share_size">{{ $t('settings.system.max_share_size') }}</label>
                      <input type="number" id="max_share_size" v-model="settings.max_share_size" />
                    </div>
                    <div class="col-auto ps-1">
                      <label for="max_share_size_unit">&nbsp;</label>
                      <select
                        name="max_share_size_unit"
                        id="max_share_size_unit"
                        v-model="settings.max_share_size_unit"
                      >
                        <option value="MB">MB</option>
                        <option value="GB">GB</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="setting-group-body-item">
                  <label for="clean_files_after_days">
                    {{ $t('settings.system.clean_files_after') }}
                    <small>({{ $t('settings.system.days') }})</small>
                  </label>
                  <input
                    type="number"
                    id="clean_files_after_days"
                    v-model="settings.clean_files_after_days"
                    placeholder="30"
                  />
                </div>
                <h6 class="mt-3 mb-3">{{ $t('settings.system.upload_modes') }}</h6>
                <div class="setting-group-body-item">
                  <div class="checkbox-container">
                    <input type="checkbox" id="allow_direct_uploads" v-model="settings.allow_direct_uploads" />
                    <label for="allow_direct_uploads">{{ $t('settings.system.allow_direct_uploads') }}</label>
                  </div>
                </div>

                <div class="setting-group-body-item">
                  <div class="checkbox-container">
                    <input type="checkbox" id="allow_chunked_uploads" v-model="settings.allow_chunked_uploads" />
                    <label for="allow_chunked_uploads">{{ $t('settings.system.allow_chunked_uploads') }}</label>
                  </div>
                </div>

                <div class="setting-group-body-item">
                  <label for="default_upload_mode">{{ $t('settings.system.default_upload_mode') }}</label>
                  <select id="default_upload_mode" v-model="settings.default_upload_mode">
                    <option value="direct">{{ $t('settings.system.direct') }}</option>
                    <option value="chunked">{{ $t('settings.system.chunked') }}</option>
                  </select>
                </div>

              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{ $t('settings.system.max_expiry_time') }}</h6>
              <p>{{ $t('settings.system.max_expiry_time_description') }}</p>
              <h6>{{ $t('settings.system.max_share_size') }}</h6>
              <p>{{ $t('settings.system.max_share_size_description') }}</p>
              <h6>{{ $t('settings.system.clean_files_after') }}</h6>
              <p>{{ $t('settings.system.clean_files_after_description') }}</p>
              <h6>{{ $t('settings.system.allow_direct_uploads') }}</h6>
              <p>{{ $t('settings.system.allow_direct_uploads_description') }}</p>
              <h6>{{ $t('settings.system.allow_chunked_uploads') }}</h6>
              <p>{{ $t('settings.system.allow_chunked_uploads_description') }}</p>
              <h6>{{ $t('settings.system.default_upload_mode') }}</h6>
              <p>{{ $t('settings.system.default_upload_mode_description') }}</p>
            </div>
          </div>
        </div>

        <div class="row mb-5">
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="emails">
              <div class="setting-group-header">
                <h3>
                  <Send />
                  {{ $t('settings.system.emails') }}
                </h3>
              </div>

              <div class="setting-group-body">
                <div class="setting-group-body-item">
                  <div class="checkbox-container">
                    <input
                      type="checkbox"
                      id="emails_share_downloaded_enabled"
                      v-model="settings.emails_share_downloaded_enabled"
                    />
                    <label for="emails_share_downloaded_enabled">
                      {{ $t('settings.system.enable_share_downloaded_emails') }}
                    </label>
                  </div>
                </div>

                <div class="setting-group-body-item">
                  <div class="checkbox-container">
                    <input
                      type="checkbox"
                      id="emails_share_expiry_warning_enabled"
                      v-model="settings.emails_share_expiry_warning_enabled"
                    />
                    <label for="emails_share_expiry_warning_enabled">
                      {{ $t('settings.system.enable_share_expiry_warning_emails') }}
                    </label>
                  </div>
                </div>

                <div class="setting-group-body-item">
                  <div class="checkbox-container">
                    <input
                      type="checkbox"
                      id="emails_share_expired_warning_enabled"
                      v-model="settings.emails_share_expired_warning_enabled"
                    />
                    <label for="emails_share_expired_warning_enabled">
                      {{ $t('settings.system.enable_share_expired_warning_emails') }}
                    </label>
                  </div>
                </div>

                <div class="setting-group-body-item">
                  <div class="checkbox-container">
                    <input
                      type="checkbox"
                      id="emails_share_deletion_warning_enabled"
                      v-model="settings.emails_share_deletion_warning_enabled"
                    />
                    <label for="emails_share_deletion_warning_enabled">
                      {{ $t('settings.system.enable_share_deletion_warning_emails') }}
                    </label>
                  </div>
                </div>

                <div class="setting-group-body-item">
                  <div class="checkbox-container">
                    <input
                      type="checkbox"
                      id="emails_share_deleted_enabled"
                      v-model="settings.emails_share_deleted_enabled"
                    />
                    <label for="emails_share_deleted_enabled">
                      {{ $t('settings.system.enable_share_deleted_emails') }}
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{ $t('settings.system.enable_share_downloaded_emails') }}</h6>
              <p>{{ $t('settings.system.share_downloaded_emails_description') }}</p>

              <h6>{{ $t('settings.system.enable_share_expiry_warning_emails') }}</h6>
              <p>{{ $t('settings.system.share_expiry_warning_emails_description') }}</p>

              <h6>{{ $t('settings.system.enable_share_expired_warning_emails') }}</h6>
              <p>{{ $t('settings.system.share_expired_warning_emails_description') }}</p>

              <h6>{{ $t('settings.system.enable_share_deletion_warning_emails') }}</h6>
              <p>{{ $t('settings.system.share_deletion_warning_emails_description') }}</p>

              <h6>{{ $t('settings.system.enable_share_deleted_emails') }}</h6>
              <p>{{ $t('settings.system.share_deleted_emails_description') }}</p>
            </div>
          </div>
        </div>

        <div class="row mb-5">
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="smtp">
              <div class="setting-group-header">
                <h3>
                  <AtSign />
                  {{ $t('settings.system.smtp') }}
                </h3>
              </div>

              <div class="setting-group-body">
                <div class="setting-group-body-item">
                  <label for="smtp_host">{{ $t('settings.system.smtp_host') }}</label>
                  <input type="text" id="smtp_host" v-model="settings.smtp_host" />
                </div>
                <div class="setting-group-body-item">
                  <label for="smtp_port">{{ $t('settings.system.smtp_port') }}</label>
                  <input type="number" id="smtp_port" v-model="settings.smtp_port" />
                </div>
                <div class="setting-group-body-item">
                  <label for="smtp_username">{{ $t('settings.system.smtp_username') }}</label>
                  <input type="text" id="smtp_username" v-model="settings.smtp_username" />
                </div>
                <div class="setting-group-body-item">
                  <label for="smtp_password">{{ $t('settings.system.smtp_password') }}</label>
                  <input type="password" id="smtp_password" v-model="settings.smtp_password" />
                </div>
                <div class="setting-group-body-item">
                  <label for="smtp_sender_name">{{ $t('settings.system.smtp_sender_name') }}</label>
                  <input type="text" id="smtp_sender_name" v-model="settings.smtp_sender_name" />
                </div>
                <div class="setting-group-body-item">
                  <label for="smtp_sender_address">{{ $t('settings.system.smtp_sender_address') }}</label>
                  <input type="text" id="smtp_sender_address" v-model="settings.smtp_sender_address" />
                </div>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{ $t('settings.system.smtp_host') }}</h6>
              <p>{{ $t('settings.system.smtp_host_description') }}</p>

              <h6>{{ $t('settings.system.smtp_port') }}</h6>
              <p>{{ $t('settings.system.smtp_port_description') }}</p>

              <h6>{{ $t('settings.system.smtp_username') }}</h6>
              <p>{{ $t('settings.system.smtp_username_description') }}</p>

              <h6>{{ $t('settings.system.smtp_password') }}</h6>
              <p>{{ $t('settings.system.smtp_password_description') }}</p>

              <h6>{{ $t('settings.system.smtp_sender_name') }}</h6>
              <p>{{ $t('settings.system.smtp_sender_name_description') }}</p>

              <h6>{{ $t('settings.system.smtp_sender_address') }}</h6>
              <p>{{ $t('settings.system.smtp_sender_address_description') }}</p>
            </div>
          </div>
        </div>

        <div class="row mb-5">
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="auth">
              <div class="setting-group-header">
                <h3>
                  <Fingerprint />
                  {{ $t('settings.system.auth') }}
                </h3>
              </div>

              <svg id="gradientDefs">
                <linearGradient id="icon-gradient">
                  <stop offset="0%" style="stop-color: var(--link-color); stop-opacity: 1" />
                  <stop offset="100%" style="stop-color: var(--link-color-hover); stop-opacity: 1" />
                </linearGradient>
              </svg>

              <div class="setting-group-body">
                <h5 class="mb-4">{{ $t('settings.system.your_auth_providers') }}</h5>
                <div
                  class="setting-group-body-item auth-provider"
                  v-for="authProvider in authProviders"
                  :key="authProvider.id"
                >
                  <HelpTip
                    :id="`auth-provider-${authProvider.id}-help-tip`"
                    :header="$t('settings.help.auth_provider.title')"
                  >
                    <p>{{ authProvider.provider_description }}</p>
                  </HelpTip>

                  <div class="provider-type" :class="{ open: authProvider.editing }">
                    <div class="row w-100 align-items-center">
                      <div class="col-auto">
                        <div class="icon">
                          <Fingerprint v-if="!authProvider.icon" />
                          <svg v-else v-html="authProvider.icon" class="custom"></svg>
                        </div>
                      </div>
                      <div class="col">
                        <small>{{ authProvider.provider_name }}</small>
                        <h6 @click.stop="showHelpTip($event, `#auth-provider-${authProvider.id}-help-tip`)">
                          {{ authProvider.name }}
                        </h6>
                      </div>
                      <div class="col pe-0" style="font-size: 0.8rem; font-weight: 300">
                        <ShieldCheck v-if="authProvider.enabled" style="margin-top: -2px; width: 15px; height: 15px" />
                        <ShieldBan v-else style="margin-top: -2px; width: 15px; height: 15px" />
                        {{ authProvider.enabled ? $t('settings.system.enabled') : $t('settings.system.disabled') }}
                      </div>
                      <div class="col-auto">
                        <button @click="authProvider.editing = !authProvider.editing">
                          <template v-if="!onLocalhost">
                            {{ $t('settings.provider.edit') }}
                          </template>
                          <template v-else>
                            {{ $t('settings.provider.view') }}
                          </template>
                        </button>
                      </div>
                    </div>
                  </div>
                  <div class="provider-settings" :class="{ open: authProvider.editing }">
                    <div class="setting-group-body">
                      <div class="row align-items-start mb-0">
                        <div class="col">
                          <div class="checkbox-container">
                            <input
                              type="checkbox"
                              :id="`auth_provider_enabled_${authProvider.id}`"
                              v-model="authProvider.enabled"
                              :disabled="onLocalhost"
                            />
                            <label :for="`auth_provider_enabled_${authProvider.id}`">
                              {{ $t('settings.system.auth_provider_enabled') }}
                            </label>
                          </div>
                        </div>
                        <div class="col-auto" v-if="authProvider.information_url">
                          <a :href="authProvider.information_url" target="_blank" class="provider-info-link">
                            <ExternalLink />
                            {{ t('settings.system_auth_provider_info_link', { name: authProvider.provider_name }) }}
                          </a>
                        </div>
                      </div>

                      <div class="setting-group-body-item">
                        <label :for="`auth_provider_name_${authProvider.id}`">
                          {{ $t('settings.system.auth_provider_name') }}
                        </label>
                        <input
                          type="text"
                          :id="`auth_provider_name_${authProvider.id}`"
                          v-model="authProvider.name"
                          :readonly="onLocalhost"
                        />
                      </div>

                      <div
                        class="setting-group-body-item"
                        v-for="(configValue, configKey) in authProvider.provider_config"
                        :key="configKey"
                      >
                        <label :for="`auth_provider_config_${authProvider.id}_${configKey}`">
                          {{ $t(`settings.system.auth_provider_config_${configKey}`) }}
                        </label>
                        <div class="input-group">
                          <input
                            :type="hideSecrets[`${authProvider.id}_${configKey}`] ? 'password' : 'text'"
                            :id="`auth_provider_config_${authProvider.id}_${configKey}`"
                            v-model="authProvider.provider_config[configKey]"
                            :readonly="onLocalhost"
                          />

                          <button
                            class="icon-only"
                            @click="togglePasswordVisibility(authProvider, configKey)"
                            v-if="mightBeSecret(configKey)"
                          >
                            <Eye v-if="hideSecrets[`${authProvider.id}_${configKey}`]" />
                            <EyeOff v-else />
                          </button>
                        </div>
                      </div>
                      <hr v-if="!onLocalhost" />
                      <div class="setting-group-body-item" v-if="!onLocalhost">
                        <label for="callback_url">{{ $t('settings.system.callback_url') }}</label>
                        <textarea
                          :id="`callback_url_${authProvider.id}`"
                          :value="authProvider.callback_url"
                          readonly
                        ></textarea>
                        <p class="help-text">{{ $t('settings.system.callback_url_description') }}</p>
                      </div>
                      <hr v-if="!onLocalhost" />
                      <a
                        href="#"
                        class="delete-auth-provider"
                        @click.prevent="handleDeleteAuthProvider(authProvider.id)"
                        v-if="authProvider.id && !onLocalhost"
                      >
                        <Trash />
                        {{ $t('settings.system.delete_auth_provider') }}
                      </a>
                    </div>
                  </div>
                </div>
                <div class="setting-group-body-item auth-provider" id="new-provider" v-if="!onLocalhost">
                  <div class="provider-type">
                    <div class="new-provider-form" :class="{ active: activateNewProviderForm }">
                      <select v-model="newProviderType">
                        <option :value="null">{{ $t('settings.system.select_auth_provider') }}</option>
                        <option v-for="provider in availableProviderTypes" :value="provider">
                          {{ provider.name }}
                        </option>
                      </select>
                      <button
                        class="new-provider-button icon-only"
                        @click="handleNewProviderButtonClicked"
                        ref="newProviderButton"
                        :disabled="disableNewProviderButton"
                      >
                        <Plus />
                      </button>
                    </div>
                  </div>
                </div>
                <div class="setting-group-body-item p-3 help-text text-small" id="new-provider" v-else>
                  <p style="font-size: 0.8rem; opacity: 0.5">
                    {{ $t('settings.system.auth_providers_description_localhost') }}
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{ $t('settings.system.auth_providers') }}</h6>
              <p>{{ $t('settings.system.auth_providers_description') }}</p>
              <h6>{{ $t('settings.system.provider_trust_warning') }}</h6>
              <p>{{ $t('settings.system.provider_trust_warning_description') }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.auth-provider {
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

.provider-type {
  background: var(--panel-section-background-color-alt);
  height: 80px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-radius: var(--panel-border-radius);

  h6 {
    cursor: pointer;
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

.provider-settings {
  background: var(--panel-section-background-color-alt);
  border-radius: var(--panel-border-radius);
  border-top: 1px solid var(--input-border-color);
  border-top-left-radius: 0;
  border-top-right-radius: 0;
  padding: 1rem;
  padding-top: 0px;
  padding-bottom: 0px;
  margin-bottom: 0px;
  opacity: 0;
  max-height: 0;
  overflow: hidden;
  transition: all 0.5s ease;
  &.open {
    margin-bottom: 10px;
    opacity: 1;
    max-height: 800px;
    padding: 1rem;
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

.input-group {
  position: relative;

  button {
    position: absolute;
    right: 5px;
    top: 5px;
    bottom: 0;
    height: 40px;
    width: 40px;
    border-radius: 100% !important;
    svg {
      margin-top: 1px;
    }
  }
}

.new-provider-form {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding-left: 15px;
  padding-right: 15px;
  select {
    opacity: 0;
    transition: all 0.3s ease;
    margin-top: 10px;
    width: calc(100% - 70px);
    pointer-events: none;
  }
  .new-provider-button {
    position: absolute;
    right: 50%;
    top: 50%;
    transform: translateX(50%) translateY(-50%);
    transition: all 0.3s ease;
    filter: grayscale(100%);
    opacity: 0.4;
  }
  &:hover {
    .new-provider-button {
      filter: grayscale(0%);
      opacity: 1;
    }
  }
  &.active {
    select {
      opacity: 1;
      pointer-events: auto;
    }
    .new-provider-button {
      left: unset;
      right: 12px;
      transform: translateX(0) translateY(-50%);
      filter: grayscale(0%);
      opacity: 1;
    }
  }
}

.delete-auth-provider {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-top: 10px;
  font-size: 0.8rem;
  color: var(--panel-text-color);
  &:hover {
    color: var(--danger-color);
  }
  svg {
    width: 15px;
    height: 15px;
    margin-top: -2px;
  }
}

.provider-info-link {
  display: flex;
  align-items: center;
  gap: 5px;
  svg {
    width: 15px;
    height: 15px;
    margin-top: -2px;
  }
}
</style>
