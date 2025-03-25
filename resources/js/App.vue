<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { LogOut, Settings as SettingsIcon, Info, MailPlus } from 'lucide-vue-next'
import Uploader from './components/uploader.vue'
import Downloader from './components/downloader.vue'
import Auth from './components/auth.vue'
import Settings from './components/settings.vue'
import Setup from './components/setup.vue'
import { unsplashImages } from './unsplashImages'
import { getApiUrl } from './utils'
import { domData, domError, domSuccess } from './domData'
import { emitter, store } from './store'
import { logout, getBackgroundImages } from './api'
import { TolgeeProvider } from '@tolgee/vue'
import LanguageSelector from './components/languageSelector.vue'
import { useToast } from 'vue-toastification'
import ThankGuestForUpload from './components/thankGuestForUpload.vue'
import { useTranslate } from '@tolgee/vue'
import ReverseInvite from './components/reverseInvite.vue'
const { t } = useTranslate()

const apiUrl = getApiUrl()

const logoUrl = `${apiUrl}/get-logo`
const version = ref()
const logoWidth = ref(100)
const useMyBackgrounds = ref(false)
const backgroundImages = ref([])
const showPoweredBy = ref(false)

const auth = ref(null)
const downloadShareCode = ref('')
const settingsPanel = ref(null)
const setupNeeded = ref(false)
const toast = useToast()
const slideshowSpeed = ref(180)
const reverseInvite = ref(null)
const allowReverseShares = domData().allow_reverse_shares

onMounted(() => {
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

  setupNeeded.value = domData().setup_needed

  if (setupNeeded.value) {
    store.setMode('setup')
    return
  }

  setMode()

  version.value = domData().version
  logoWidth.value = domData().logo_width
  useMyBackgrounds.value = domData().use_my_backgrounds
  showPoweredBy.value = domData().show_powered_by
  slideshowSpeed.value = domData().background_slideshow_speed
  setTimeout(changeBackground, slideshowSpeed.value * 1000)
  getBackgroundImages().then((data) => {
    backgroundImages.value = data.files
    nextTick(() => {
      changeBackground()
    })
  })
  emitter.on('showPasswordResetForm', () => {
    settingsPanel.value.setActiveTab('myProfile')
    nextTick(() => {
      store.setSettingsOpen(true)
      nextTick(() => {
        emitter.emit('profileEditActive')
      })
    })
  })

  //next tick change background
  nextTick(() => {
    // changeBackground()
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

const changeBackground = async () => {
  let backgrounds = document.querySelectorAll('.backgrounds-item')
  if (backgrounds.length === 0) {
    return
  }
  backgrounds.forEach((background) => {
    background.classList.remove('active')
  })
  backgrounds[Math.floor(Math.random() * backgrounds.length)].classList.add('active')
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
    <LanguageSelector />
    <div class="backgrounds" v-if="!useMyBackgrounds">
      <div
        class="backgrounds-item"
        v-for="image in unsplashImages"
        :key="image"
        :style="{
          backgroundImage: `url(https://images.unsplash.com/${image.id}?q=80&w=1920&auto=format)`
        }"
      >
        <div class="backgrounds-item-credit" v-html="image.credit"></div>
      </div>
    </div>

    <div class="backgrounds" v-else>
      <div
        class="backgrounds-item"
        v-for="image in backgroundImages"
        :key="image"
        :style="{ backgroundImage: `url(/api/backgrounds/${image})` }"
      ></div>
    </div>
    <template v-if="store.isLoggedIn()">
      <div class="main-menu">
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
    </template>

    <div class="wrapper">
      <div class="left-panel">
        

        <div class="ui-container">
          <template v-if="store.mode === 'upload'">
            <Uploader v-if="store.isLoggedIn()" />
            <Auth v-show="!store.isLoggedIn()" ref="auth" />
          </template>
          <Downloader v-if="store.mode === 'download'" :downloadShareCode="downloadShareCode" />
          <template v-if="store.mode === 'setup'">
            <Setup />
          </template>
          <template v-if="store.mode === 'thank_guest_for_upload'">
            <ThankGuestForUpload />
          </template>
        </div>
      </div>
      <div class="right-panel d-none d-md-flex">
        <div class="right-panel-content" v-if="store.isGuest() && store.mode === 'upload' && store.isLoggedIn()">
          <div class="right-panel-content-item">
            <div class="right-panel-content-item-title">
              <h5>{{ t('Reverse Share') }}</h5>
              <p>
                <Info />
                {{ t('auth.guest_warning') }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="version-info" v-if="showPoweredBy">
      <div class="version-info-text">
        {{ $t('Powered by') }}
        <a href="https://github.com/deanward/erugo">Erugo</a>
        {{ version }}
      </div>
    </div>
    <Settings ref="settingsPanel" />
    <ReverseInvite ref="reverseInvite" v-if="allowReverseShares && !store.isGuest() && store.isLoggedIn()" />
  </TolgeeProvider>
</template>
