<script setup>
import { store } from '../store'
import {
  CircleX,
  Settings,
  Users as UsersIcon,
  UserPlus,
  Save,
  Palette,
  User,
  Boxes,
  Plus,
  EllipsisVertical,
  Bomb,
  Mail,
  HelpCircle,
  BarChart3,
  FolderOpen,
  RefreshCw,
  Cloud,
  LogOut,
  Wifi,
  WifiOff,
  Loader2
} from 'lucide-vue-next'
import { ref, onMounted } from 'vue'
import Users from './settings/users.vue'
import BrandingSettings from './settings/branding.vue'
import SystemSettings from './settings/system.vue'
import SystemStats from './settings/systemStats.vue'
import EmailTemplates from './settings/emailTemplates.vue'
import MyProfile from './settings/myProfile.vue'
import MyShares from './settings/myShares.vue'
import AllShares from './settings/allShares.vue'
import ErugoConnect from './settings/connect/ErugoConnectMain.vue'
import { getUsers } from '../api'
import ButtonWithMenu from './buttonWithMenu.vue'

import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()

//settings panels
const usersPanel = ref(null)
const mySharesPanel = ref(null)
const allSharesPanel = ref(null)
const brandingSettings = ref(null)
const systemSettings = ref(null)
const cloudConnectPanel = ref(null)
const cloudConnectLoggedIn = ref(false)
const cloudConnectConnected = ref(false)
const cloudConnectConnecting = ref(false)

const showDeletedShares = ref(false)
const showDeletedSharesAll = ref(false)
const allSharesUsers = ref([])
const selectedUserId = ref(null)
// Create refs for the tab contents
const tabContents = ref({
  stats: ref(null),
  branding: ref(null),
  system: ref(null),
  users: ref(null),
  myProfile: ref(null),
  myShares: ref(null),
  allShares: ref(null),
  cloudConnect: ref(null)
})

onMounted(() => {
  activeTab.value = getInitialTab()
  //do we have showDeletedShares in local storage?
  const showDeletedSharesSetting = localStorage.getItem('showDeletedShares')
  if (showDeletedSharesSetting) {
    showDeletedShares.value = showDeletedSharesSetting === 'true'
  }
  const showDeletedSharesAllSetting = localStorage.getItem('allSharesShowDeleted')
  if (showDeletedSharesAllSetting) {
    showDeletedSharesAll.value = showDeletedSharesAllSetting === 'true'
  }
})

const closeSettings = () => {
  store.setSettingsOpen(false)
}

const clickOutside = (e) => {
  if (e.target === e.currentTarget) {
    closeSettings()
  }
}

const setActiveTab = (tab) => {
  activeTab.value = tab
  if (tab === 'allShares' && allSharesUsers.value.length === 0) {
    loadAllSharesUsers()
  }
}

const getInitialTab = () => {
  return 'myShares'
}

// Track active tab
const activeTab = ref(null)

const createShare = () => {
  store.setSettingsOpen(false)
}

const handleNavItemClicked = (item) => {
  console.log('handleNavItemClicked', item)
  const scrollableElement = document.querySelector('.tab-content-body')
  const element = document.getElementById(item)
  if (element) {
    element.scrollIntoView({ behavior: 'smooth' })
    scrollableElement.scrollTo({
      top: element.offsetTop - 100,
      behavior: 'smooth'
    })
  }
}

defineExpose({
  setActiveTab,
  handleNavItemClicked
})

const getSettingsTitle = () => {
  // Check if t.value exists and is a function
  if (!t.value) {
    // Fallback if translation function is not ready
    const fallbackTitles = {
      stats: 'System Stats',
      branding: 'Branding',
      system: 'System',
      users: 'Users',
      myProfile: 'My Profile',
      myShares: 'My Shares',
      allShares: 'All Shares',
      emailTemplates: 'Email Templates',
      cloudConnect: 'Cloud Connect'
    }
    return fallbackTitles[activeTab.value] || 'Erugo'
  }

  switch (activeTab.value) {
    case 'stats':
      return t.value('settings.title.stats') || 'System Stats'
    case 'branding':
      return t.value('settings.title.branding')
    case 'system':
      return t.value('settings.title.system')
    case 'users':
      return t.value('settings.title.users')
    case 'myProfile':
      return t.value('settings.title.myProfile')
    case 'myShares':
      return t.value('settings.title.myShares')
    case 'allShares':
      return t.value('settings.title.allShares')
    case 'emailTemplates':
      return t.value('settings.title.emailTemplates')
    case 'cloudConnect':
      return t.value('settings.title.cloudConnect') || 'Cloud Connect'
    default:
      return t.value('settings.title.erugo')
  }
}

const handlePruneExpiredShares = () => {
  mySharesPanel.value.handlePruneExpiredShares()
}

const setShowDeletedShares = (value) => {
  showDeletedShares.value = value
  localStorage.setItem('showDeletedShares', value)
  mySharesPanel.value.setShowDeletedShares(value)
}

const setShowDeletedSharesAll = (value) => {
  showDeletedSharesAll.value = value
  localStorage.setItem('allSharesShowDeleted', value)
  allSharesPanel.value.setShowDeletedShares(value)
}

const goToHelp = () => {
  window.open('https://new.erugo.app/docs/configuration/', '_blank')
}

const handleViewUserShares = (user) => {
  setActiveTab('allShares')
  // Use nextTick to ensure the component is mounted before setting the filter
  setTimeout(() => {
    if (allSharesPanel.value) {
      selectedUserId.value = user.id
      allSharesPanel.value.setUserFilter(user.id)
    }
  }, 100)
}

const loadAllSharesUsers = async () => {
  try {
    const data = await getUsers()
    allSharesUsers.value = data.users || []
  } catch (error) {
    console.error('Failed to load users', error)
  }
}

const handleUserFilterChange = (event) => {
  selectedUserId.value = event.target.value || null
  if (allSharesPanel.value) {
    allSharesPanel.value.setUserFilter(selectedUserId.value)
  }
}

const handleCloudConnectLogout = () => {
  if (cloudConnectPanel.value) {
    cloudConnectPanel.value.handleLogout()
  }
}

const updateCloudConnectLoginState = (isLoggedIn) => {
  cloudConnectLoggedIn.value = isLoggedIn
}

const updateCloudConnectConnectionState = (isConnected) => {
  cloudConnectConnected.value = isConnected
}

const updateCloudConnectConnectingState = (isConnecting) => {
  cloudConnectConnecting.value = isConnecting
}

const handleCloudConnectConnect = () => {
  if (cloudConnectPanel.value) {
    cloudConnectPanel.value.handleConnect()
  }
}

const handleCloudConnectDisconnect = () => {
  if (cloudConnectPanel.value) {
    cloudConnectPanel.value.handleDisconnect()
  }
}
</script>

<template>
  <div class="settings-overlay" :class="{ active: store.settingsOpen }" @click="clickOutside">
    <div class="settings-container">
      <div class="settings-header">
        <h1>
          <Settings />
          <span>
            {{ $t('settings.title.manage') }}
            <span v-html="getSettingsTitle()" />
          </span>
        </h1>
        <button class="settings-help-button icon-only" @click="goToHelp">
          <HelpCircle />
        </button>
        <button class="close-settings-button icon-only" @click="closeSettings">
          <CircleX />
        </button>
      </div>
      <div class="settings-tabs-wrapper">
        <div class="settings-tabs-container">
          <div
            class="settings-tab"
            :class="{ active: activeTab === 'stats' }"
            @click="setActiveTab('stats')"
            v-if="store.isAdmin()"
          >
            <h2>
              <BarChart3 />
              {{ $t('settings.title.stats') || 'Stats' }}
            </h2>
          </div>
          <div
            class="settings-tab"
            :class="{ active: activeTab === 'branding' }"
            @click="setActiveTab('branding')"
            v-if="store.isAdmin()"
          >
            <h2>
              <Palette />
              {{ $t('settings.title.branding') }}
            </h2>
          </div>
          <div
            class="settings-tab"
            :class="{ active: activeTab === 'system' }"
            @click="setActiveTab('system')"
            v-if="store.isAdmin()"
          >
            <h2>
              <Settings />
              {{ $t('settings.title.system') }}
            </h2>
          </div>
          <div
            class="settings-tab"
            :class="{ active: activeTab === 'emailTemplates' }"
            @click="setActiveTab('emailTemplates')"
            v-if="store.isAdmin()"
          >
            <h2>
              <Mail />
              {{ $t('settings.title.emailTemplates') }}
            </h2>
          </div>
          <div
            class="settings-tab"
            :class="{ active: activeTab === 'users' }"
            @click="setActiveTab('users')"
            v-if="store.isAdmin()"
          >
            <h2>
              <UsersIcon />
              {{ $t('settings.title.users') }}
            </h2>
          </div>
          <div
            class="settings-tab"
            :class="{ active: activeTab === 'allShares' }"
            @click="setActiveTab('allShares')"
            v-if="store.isAdmin()"
          >
            <h2>
              <FolderOpen />
              {{ $t('settings.title.allShares') }}
            </h2>
          </div>
          <div class="settings-tab" :class="{ active: activeTab === 'myShares' }" @click="setActiveTab('myShares')">
            <h2>
              <Boxes />
              {{ $t('settings.title.myShares') }}
            </h2>
          </div>
          <div class="settings-tab" :class="{ active: activeTab === 'myProfile' }" @click="setActiveTab('myProfile')">
            <h2>
              <User />
              {{ $t('settings.title.myProfile') }}
            </h2>
          </div>
          <div class="settings-tab-spacer" v-if="store.isAdmin()"></div>
          <div
            class="settings-tab"
            :class="{ active: activeTab === 'cloudConnect' }"
            @click="setActiveTab('cloudConnect')"
            v-if="store.isAdmin()"
          >
            <h2>
              <Cloud />
              {{ $t('settings.title.cloudConnect') || 'Cloud' }}
            </h2>
          </div>
        </div>
        <div class="settings-tabs-content-container">
          <Transition name="fade">
            <div v-if="activeTab === 'stats'" class="settings-tab-content" ref="tabContents.stats" key="stats">
              <div class="tab-content-header">
                <h2 class="d-none d-md-flex">
                  <BarChart3 />
                  <span>
                    {{ $t('settings.title.stats') || 'System Stats' }}
                    <small>{{ $t('settings.description.stats') || 'Monitor system usage and activity' }}</small>
                  </span>
                </h2>
                <div class="user-actions">
                  <button @click="$refs['systemStats'].refreshStats()">
                    <RefreshCw />
                    {{ $t('settings.stats.refresh') }}
                  </button>
                </div>
              </div>
              <div class="tab-content-body">
                <SystemStats
                  ref="systemStats"
                  v-if="store.settingsOpen"
                  @navItemClicked="handleNavItemClicked"
                />
              </div>
            </div>
            <div v-else-if="activeTab === 'branding'" class="settings-tab-content" ref="tabContents.branding" key="branding">
              <div class="tab-content-header">
                <h2 class="d-none d-md-flex">
                  <Palette />
                  <span>
                    {{ $t('settings.title.branding') }}
                    <small>{{ $t('settings.description.branding') }}</small>
                  </span>
                </h2>
                <div class="user-actions">
                  <button @click="$refs['brandingSettings'].saveSettings()">
                    <Save />
                    {{ $t('settings.button.branding.save') }}
                  </button>
                </div>
              </div>
              <div class="tab-content-body">
                <BrandingSettings
                  ref="brandingSettings"
                  v-if="store.settingsOpen"
                  @navItemClicked="handleNavItemClicked"
                />
              </div>
            </div>
            <div v-else-if="activeTab === 'system'" class="settings-tab-content" ref="tabContents.system" key="system">
              <div class="tab-content-header">
                <h2 class="d-none d-md-flex">
                  <Settings />
                  <span>
                    {{ $t('settings.title.system') }}
                    <small>{{ $t('settings.description.system') }}</small>
                  </span>
                </h2>
                <div class="user-actions">
                  <button @click="$refs['systemSettings'].saveSettings()">
                    <Save />
                    {{ $t('settings.button.system.save') }}
                  </button>
                </div>
              </div>
              <div class="tab-content-body">
                <SystemSettings ref="systemSettings" v-if="store.settingsOpen" @navItemClicked="handleNavItemClicked" />
              </div>
            </div>

            <div v-else-if="activeTab === 'emailTemplates'" class="settings-tab-content" ref="tabContents.emailTemplates" key="emailTemplates">
              <div class="tab-content-header">
                <h2 class="d-none d-md-flex">
                  <Settings />
                  <span>
                    {{ $t('settings.title.emailTemplates') }}
                    <small>{{ $t('settings.description.emailTemplates') }}</small>
                  </span>
                </h2>
                <div class="user-actions">
                  <button @click="$refs['emailTemplates'].saveEmailTemplates()">
                    <Save />
                    {{ $t('settings.button.emailTemplates.save') }}
                  </button>
                </div>
              </div>
              <div class="tab-content-body">
                <EmailTemplates ref="emailTemplates" v-if="store.settingsOpen" @navItemClicked="handleNavItemClicked" />
              </div>
            </div>

            <div v-else-if="activeTab === 'cloudConnect'" class="settings-tab-content" ref="tabContents.cloudConnect" key="cloudConnect">
              <div class="tab-content-header">
                <h2 class="d-none d-md-flex">
                  <Cloud />
                  <span>
                    {{ $t('settings.title.cloudConnect') || 'Erugo Connect' }}
                    <small>{{ $t('settings.description.cloudConnect') || 'Connect your instance to Erugo Connect' }}</small>
                  </span>
                </h2>
                <div class="user-actions">
                  <button v-if="!cloudConnectConnected" @click="handleCloudConnectConnect" :disabled="cloudConnectConnecting">
                    <Loader2 v-if="cloudConnectConnecting" class="spinner" />
                    <Wifi v-else />
                    {{ cloudConnectConnecting ? ($t('cloudConnect.ready.connecting') || 'Connecting...') : ($t('cloudConnect.ready.connect') || 'Connect') }}
                  </button>
                  <button v-else @click="handleCloudConnectDisconnect" class="secondary">
                    <WifiOff />
                    {{ $t('cloudConnect.connected.disconnect') || 'Disconnect' }}
                  </button>
                  <button v-if="cloudConnectLoggedIn" class="secondary" @click="handleCloudConnectLogout">
                    <LogOut />
                    {{ $t('cloudConnect.auth.logout') || 'Sign Out' }}
                  </button>
                </div>
              </div>
              <div class="tab-content-body">
                <ErugoConnect ref="cloudConnectPanel" v-if="store.settingsOpen" @loginStateChanged="updateCloudConnectLoginState" @connectionStateChanged="updateCloudConnectConnectionState" @connectingStateChanged="updateCloudConnectConnectingState" @navItemClicked="handleNavItemClicked" />
              </div>
            </div>

            <div v-else-if="activeTab === 'users'" class="settings-tab-content" ref="tabContents.users" key="users">
              <div class="tab-content-header">
                <h2 class="d-none d-md-flex">
                  <UsersIcon />
                  <span>
                    {{ $t('settings.title.users') }}
                    <small>{{ $t('settings.description.users') }}</small>
                  </span>
                </h2>
                <div class="user-actions">
                  <button @click="usersPanel.addUser">
                    <UserPlus />
                    {{ $t('settings.button.users.add') }}
                  </button>
                </div>
              </div>
              <div class="tab-content-body">
                <Users ref="usersPanel" v-if="store.settingsOpen" @viewUserShares="handleViewUserShares" />
              </div>
            </div>
            <div
              v-else-if="activeTab === 'allShares'"
              class="settings-tab-content"
              ref="tabContents.allShares"
              key="allShares"
            >
              <div class="tab-content-header">
                <h2 class="d-none d-md-flex">
                  <FolderOpen />
                  <span>
                    {{ $t('settings.title.allShares') }}
                    <small>{{ $t('settings.description.allShares') }}</small>
                  </span>
                </h2>
                <div class="user-actions">
                  <div class="row align-items-center">
                    <div class="col-auto">
                      <div class="checkbox-container pt-4">
                        <input type="checkbox" id="show_deleted_shares_all" :checked="showDeletedSharesAll" @change="setShowDeletedSharesAll($event.target.checked)" />
                        <label for="show_deleted_shares_all">{{ $t('settings.system.show_deleted_shares') }}</label>
                      </div>
                    </div>
                    <div class="col-auto">
                      <select id="user-filter-all" class="user-filter-select" @change="handleUserFilterChange" :value="selectedUserId || ''">
                        <option value="">{{ $t('settings.allShares.allUsers') }}</option>
                        <option v-for="user in allSharesUsers" :key="user.id" :value="user.id">
                          {{ user.name }} ({{ user.email }})
                        </option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="tab-content-body">
                <AllShares ref="allSharesPanel" v-if="store.settingsOpen" />
              </div>
            </div>
            <div
              v-else-if="activeTab === 'myProfile'"
              class="settings-tab-content"
              ref="tabContents.myProfile"
              key="myProfile"
            >
              <div class="tab-content-header">
                <h2 class="d-none d-md-flex">
                  <User />
                  <span>
                    {{ $t('settings.title.myProfile') }}
                    <small>{{ $t('settings.description.myProfile') }}</small>
                  </span>
                </h2>
                <div class="user-actions">
                  <button @click="$refs['myProfilePanel'].saveUser()">
                    <Save />
                    {{ $t('settings.button.myProfile.save') }}
                  </button>
                </div>
              </div>
              <div class="tab-content-body">
                <MyProfile ref="myProfilePanel" v-if="store.settingsOpen" @navItemClicked="handleNavItemClicked" />
              </div>
            </div>
            <div
              v-else-if="activeTab === 'myShares'"
              class="settings-tab-content"
              ref="tabContents.myShares"
              key="myShares"
            >
              <div class="tab-content-header">
                <h2 class="d-none d-md-flex">
                  <Boxes />
                  <span>
                    {{ $t('settings.title.myShares') }}
                    <small>{{ $t('settings.description.myShares') }}</small>
                  </span>
                </h2>
                <div class="user-actions">
                  <div class="row align-items-center">
                    <div class="col-auto">
                      <div class="checkbox-container pt-4">
                        <input type="checkbox" id="show_deleted_shares" :checked="showDeletedShares" @change="setShowDeletedShares($event.target.checked)" />
                        <label for="show_deleted_shares">{{ $t('settings.system.show_deleted_shares') }}</label>
                      </div>
                    </div>
                    <div class="col-auto pe-0">
                      <button @click="createShare">
                        <Plus />
                        {{ $t('settings.button.myShares.create') }}
                      </button>
                    </div>
                    <div class="col-auto pe-0">
                      <buttonWithMenu
                        :items="[
                          {
                            icon: Bomb,
                            label: t('settings.button.myShares.pruneExpired'),
                            action: handlePruneExpiredShares
                          }
                        ]"
                      >
                        <template #icon>
                          <EllipsisVertical />
                        </template>
                      </buttonWithMenu>
                    </div>
                  </div>
                </div>
              </div>
              <div class="tab-content-body">
                <MyShares ref="mySharesPanel" v-if="store.settingsOpen" />
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.settings-overlay {
  position: fixed;
  top: 0;
  left: 0;
  background-color: transparent;
  width: 100%;
  height: 100%;
  z-index: 210;
  pointer-events: none;
  transition: all 300ms ease-in-out;
  transition-delay: 300ms;

  .settings-container {
    position: absolute;
    bottom: 0;
    left: 0;
    transform: translateX(calc(50vw - var(--settings-width) / 2)) translateY(100%);

    width: var(--settings-width);
    height: var(--settings-height);

    transition: all 300ms ease-in-out;
    transition-delay: 0s;

    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;
  }

  &.active {
    background: var(--overlay-background-color);
    pointer-events: auto;
    transition-delay: 0s;
    backdrop-filter: blur(10px);

    .settings-container {
      transform: translateX(calc(50vw - var(--settings-width) / 2)) translateY(0);
      transition-delay: 100ms;
    }
  }
}

.settings-header {
  background: var(--panel-header-background-color);
  // border-radius: 5px 5px 0 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 80px;
  width: 100%;
  h1 {
    font-size: 20px;
    font-weight: 600;
    color: var(--panel-header-text-color);
    padding-left: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    svg {
      width: 20px;
      height: 20px;
    }
  }
}

.settings-tabs-wrapper {
  width: 100%;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: flex-start;
}

.settings-tabs-container {
  display: flex;
  gap: 5px;
  padding-left: 20px;
  padding-right: 20px;
  background: var(--tabs-bar-background-color);
  width: 100%;
  
  .settings-tab-spacer {
    flex-grow: 1;
  }
  
  .settings-tab {
    background: var(--tabs-tab-background-color);
    margin-top: 10px;
    padding: 10px;
    border-radius: var(--tabs-border-radius);
    cursor: pointer;
    transition: all 300ms ease-in-out;

    h2 {
      font-size: 16px;
      font-weight: 400;
      color: var(--tabs-tab-text-color);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: all 100ms ease-in-out;
      svg {
        width: 20px;
        height: 20px;
        display: none;
        @media (min-width: 768px) {
          display: block;
        }
      }
    }

    &.active {
      background: var(--tabs-tab-background-color-active);
      h2 {
        color: var(--tabs-tab-text-color-active);
      }
    }

    &:hover {
      background: var(--tabs-tab-background-color-hover);
      h2 {
        color: var(--tabs-tab-text-color-hover);
      }
    }
  }
}

.settings-tabs-content-container {
  position: relative;
  flex-grow: 1;
  width: 100%;
  border-radius: 5px;
  background: var(--panel-background-color);

  .settings-tab-content {
    position: absolute;
    width: 100%;
    height: 100%;
    padding: 0px;

    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;

    background: var(--panel-background-color);

    .tab-content-header {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      background: var(--panel-subheader-background-color);
      padding: 20px;
      width: 100%;

      @media (min-width: 768px) {
        justify-content: space-between;
      }

      h2 {
        font-size: 1.4rem;
        color: var(--panel-subheader-text-color);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        svg {
          width: 20px;
          height: 20px;
        }
        small {
          display: block;
          font-size: 0.8rem;
          color: var(--panel-subheader-text-color);
          margin: 0;
        }
      }
      p {
        font-size: 1rem;
        color: var(--panel-subheader-text-color);
        margin: 0;
      }
    }

    .tab-content-body {
      display: block;
      padding: 0px;
      overflow-y: auto;
      flex-grow: 1;
      width: 100%;
    }
  }
}

// Cross-fade transition
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.fade-enter-active {
  z-index: 1;
}

.spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.user-filter-select {
  padding: 8px 12px;
  border-radius: 5px;
  border: 1px solid var(--panel-section-background-color-alt);
  background: var(--panel-section-background-color-alt);
  color: var(--panel-section-text-color);
  font-size: 0.9rem;
  min-width: 200px;
  cursor: pointer;
  margin-top: 16px;
  
  &:focus {
    outline: none;
    border-color: var(--button-primary-background-color);
  }
}
</style>
