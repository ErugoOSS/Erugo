<script setup>
import { ref, onMounted, nextTick } from 'vue'

//components
import LanguageSelector from './components/languageSelector.vue'
import Uploader from './components/uploader.vue'
import Downloader from './components/downloader.vue'
import Auth from './components/auth.vue'
import Settings from './components/settings.vue'
import Setup from './components/setup.vue'
import ThankGuestForUpload from './components/thankGuestForUpload.vue'
import ReverseInvite from './components/reverseInvite.vue'
import Background from './components/layout/background.vue'

//3rd party
import { LogOut, Settings as SettingsIcon, MailPlus } from 'lucide-vue-next'
import { TolgeeProvider } from '@tolgee/vue'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'

//1st party
import { getApiUrl } from './utils'
import { domData, domError, domSuccess } from './domData'
import { emitter, store } from './store'
import { logout } from './api'


//use
const { t } = useTranslate()

//static data
const apiUrl = getApiUrl()
const logoUrl = `${apiUrl}/get-logo`
const allowReverseShares = ref(false)
const logoWidth = ref(0)
const showPoweredBy = ref(false)
const setupNeeded = ref(false)

//reactive data
const auth = ref(null)
const downloadShareCode = ref('')
const settingsPanel = ref(null)
const toast = useToast()
const reverseInvite = ref(null)

onMounted(() => {

  allowReverseShares.value = domData().allow_reverse_shares
  logoWidth.value = domData().logo_width
  showPoweredBy.value = domData().show_powered_by
  setupNeeded.value = domData().setup_needed


  if (domError().length > 0) {
    console.log('error', domError())
    nextTick(() => {
      toast.error(domError())
    })
  }

  if (domSuccess().length > 0) {
    nextTick(() => {
      console.log('domSuccess', domSuccess())
      toast.success(domSuccess())
      if (domSuccess() == 'Account linked successfully') {
        store.setSettingsOpen(true)
        settingsPanel.value.setActiveTab('myProfile')
        setTimeout(() => {
          settingsPanel.value.handleNavItemClicked('linked_accounts')
        }, 500)
      }
    })
  }

  if (setupNeeded.value) {
    store.setMode('setup')
    return
  }

  //figure out which mode the application is in
  setMode()

  //register events
  emitter.on('showPasswordResetForm', () => {
    settingsPanel.value.setActiveTab('myProfile')
    nextTick(() => {
      store.setSettingsOpen(true)
      nextTick(() => {
        emitter.emit('profileEditActive')
      })
    })
  })
})

const setMode = () => {
  if (window.location.pathname.includes('shares')) {
    store.setMode('download')
    downloadShareCode.value = window.location.pathname.split('/').pop()
    setPageTitle('Download Share')
  } else {
    store.setMode('upload')
    setPageTitle('Create Share')
  }
}

const setPageTitle = (title) => {
  let currentTitle = document.title
  document.title = `${currentTitle} - ${title}`
}

const handleLogoutClick = () => {
  if (store.isGuest()) {
    const confirm = window.confirm(t.value('auth.confirm_end_guest_session'))
    if (!confirm) {
      return
    }
  }

  logout()
}

const openSettings = () => {
  store.setSettingsOpen(true)
}

const openReverseShareInvite = () => {
  reverseInvite.value.showReverseInviteForm()
}
</script>

<template>
  <TolgeeProvider>
    <Background />
    <LanguageSelector />
    <div class="logo-container" v-if="store.mode !== 'setup'">
      <a href="/"><img :src="logoUrl" alt="Erugo" id="logo" :style="{ width: `${logoWidth}px` }" /></a>
    </div>
    <div class="main">
      <!-- auth: shows if user is not logged in and the mode is upload -->
      <Auth v-show="!store.isLoggedIn() && store.mode === 'upload'" ref="auth" />

      <!-- uploader: shows if user is logged in and mode is upload -->
      <Uploader v-if="store.mode === 'upload' && store.isLoggedIn()" />

      <!-- downloader -->
      <Downloader v-if="store.mode === 'download'" :downloadShareCode="downloadShareCode" />

      <!-- setup wizard: shows if mode is setup -->
      <Setup v-if="store.mode === 'setup'" />

      <!-- thank guest for upload: shows if mode is thank_guest_for_upload -->
      <ThankGuestForUpload v-if="store.mode === 'thank_guest_for_upload'" />
    </div>

    <footer>
      <!-- version info: shows if show_powered_by is true -->
      <div class="powered-by" v-if="showPoweredBy">
        {{ $t('Powered by') }}
        <a href="https://github.com/deanward/erugo">Erugo</a>
      </div>
      <!-- main menu: shows if user is logged in -->
      <div class="main-menu" v-if="store.isLoggedIn()">
        <button
          class="reverse-share-invite-button secondary icon-only"
          :title="t('button.reverse_share_invite')"
          @click="openReverseShareInvite"
          v-if="!store.isGuest() && allowReverseShares"
        >
          <MailPlus />
        </button>

        <button class="settings-button secondary icon-only" @click="openSettings" v-if="!store.isGuest()">
          <SettingsIcon />
        </button>

        <button
          class="logout icon-only secondary"
          @click="handleLogoutClick"
          :title="store.isGuest() ? t('auth.end_guest_session') : t('auth.logout')"
        >
          <LogOut />
        </button>
      </div>
    </footer>

    <!-- settings: load only if user is logged in -->
    <Settings ref="settingsPanel" v-if="store.isLoggedIn()" />

    <!-- reverse invite: load only if reverse shares are allowed and user is logged in and not a guest -->
    <ReverseInvite ref="reverseInvite" v-if="allowReverseShares && !store.isGuest() && store.isLoggedIn()" />
  </TolgeeProvider>
</template>
