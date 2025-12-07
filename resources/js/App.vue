<script setup>
import { ref, onMounted, nextTick, watch, computed } from 'vue'


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
import ConfirmDialog from './components/ConfirmDialog.vue'
import MarkdownModal from './components/MarkdownModal.vue'

//3rd party
import { LogOut, Settings as SettingsIcon, MailPlus, AlertTriangle } from 'lucide-vue-next'
import { TolgeeProvider } from '@tolgee/vue'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'

//1st party
import { domData, domError, domSuccess } from './domData'
import { emitter, store } from './store'
import { logout } from './api'
import { checkUrlHash, clearUrlHash } from './composables/useSettingsNavigation'


//use
const { t } = useTranslate()

//static data
const logoTimestamp = ref(Date.now())
const logoUrl = computed(() => `/images/logo.png?t=${logoTimestamp.value}`)
const allowReverseShares = ref(false)
const logoWidth = ref(0)
const showPoweredBy = ref(false)
const setupNeeded = ref(false)
const tusdAvailable = ref(true)

//reactive data
const auth = ref(null)
const downloadShareCode = ref('')
const settingsPanel = ref(null)
const toast = useToast()
const reverseInvite = ref(null)
const tusdHelpModal = ref(null)

onMounted(() => {

  allowReverseShares.value = domData().allow_reverse_shares
  logoWidth.value = domData().logo_width
  showPoweredBy.value = domData().show_powered_by
  setupNeeded.value = domData().setup_needed
  tusdAvailable.value = domData().tusd_available !== false


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

  // Register settings navigation event listener
  emitter.on('settingsNavigate', handleSettingsNavigate)

  // Check for settings deep-link in URL hash
  // Delay slightly to ensure user is logged in and settings panel is available
  setTimeout(() => {
    checkSettingsDeepLink()
  }, 100)
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

/**
 * Handle settings navigation from the composable
 * @param {{ tab: string, section: string|null, subSection: string|null, skipUrlUpdate?: boolean }} navigation
 */
const handleSettingsNavigate = (navigation) => {
  const { tab, section, subSection, skipUrlUpdate = false } = navigation

  // Open settings panel
  store.setSettingsOpen(true)

  // Wait for settings panel to be ready, then navigate
  nextTick(() => {
    if (!settingsPanel.value) {
      console.warn('[App] Settings panel not available')
      return
    }

    // Set the active tab (skip URL update if requested)
    settingsPanel.value.setActiveTab(tab, { updateUrl: !skipUrlUpdate })

    // If there's a section or sub-section to scroll to, do it after a short delay
    // to allow the tab content to render
    if (section || subSection) {
      setTimeout(() => {
        // Prefer sub-section if available, otherwise use section
        const scrollTarget = subSection || section
        settingsPanel.value.handleNavItemClicked(scrollTarget, { skipUrlUpdate })
      }, 300)
    }
  })
}

/**
 * Track if we've already handled the initial deep-link
 */
const deepLinkHandled = ref(false)

/**
 * Check for settings deep-link in URL hash on page load
 */
const checkSettingsDeepLink = () => {
  if (deepLinkHandled.value) return
  
  const hashNav = checkUrlHash()
  if (hashNav && store.isLoggedIn()) {
    deepLinkHandled.value = true
    // Skip URL update since we're already at the correct URL
    handleSettingsNavigate({ ...hashNav, skipUrlUpdate: true })
  }
}

// Watch for login state changes to handle deep-links
watch(
  () => store.loggedIn,
  (isLoggedIn) => {
    if (isLoggedIn && !deepLinkHandled.value) {
      // Small delay to ensure settings panel is mounted
      setTimeout(() => {
        checkSettingsDeepLink()
      }, 100)
    }
  }
)
</script>

<template>
  <TolgeeProvider>
    <!-- tusd unavailable warning: shows if tusd service is not running and user is logged in -->
    <div class="tusd-warning" v-if="!tusdAvailable && store.isLoggedIn()">
      <AlertTriangle class="tusd-warning-icon" />
      <span>{{ $t('warnings.tusd_unavailable', 'Upload service unavailable. The tusd service is not running. File uploads will not work until this is resolved.') }}</span>
      <a href="#" class="tusd-warning-link" @click.prevent="tusdHelpModal.open()">{{ $t('warnings.how_to_fix', 'How to fix') }}</a>
    </div>
    
    <!-- tusd help modal -->
    <MarkdownModal 
      ref="tusdHelpModal" 
      topic="tusd-unavailable" 
      :title="$t('warnings.tusd_help_title', 'Upload Service Unavailable')" 
    />
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
        <a href="https://erugo.app"><img :src="'/icon.svg'" alt="Erugo" class="erugo-icon" /> Erugo</a>
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

    <!-- confirmation dialog: available globally -->
    <ConfirmDialog />
  </TolgeeProvider>
</template>


<style scoped>
.erugo-icon {
  width: 20px;
  height: 20px;
  margin-top: -5px;
  margin-left: -5px;
}

.tusd-warning {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 12px 20px;
  background-color: #dc2626;
  color: white;
  font-weight: 500;
  font-size: 14px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.tusd-warning-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.tusd-warning-link {
  color: white;
  text-decoration: underline;
  font-weight: 600;
  margin-left: 5px;
  
  &:hover {
    opacity: 0.9;
  }
}
</style>