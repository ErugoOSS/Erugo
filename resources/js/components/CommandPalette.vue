<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import { Search, Settings, Palette, Users, User, Boxes, FolderOpen, BarChart3, Mail, Fingerprint, Share2, Send, AtSign, Database, Images, Image, Pipette } from 'lucide-vue-next'
import { useSettingsNavigation } from '../composables/useSettingsNavigation'
import { store } from '../store'

import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()
const { navigateTo } = useSettingsNavigation()

const isOpen = ref(false)
const searchQuery = ref('')
const selectedIndex = ref(0)
const searchInput = ref(null)

// Define all available commands
const commands = computed(() => {
  const isAdmin = store.isAdmin()
  
  const allCommands = [
    // Tabs (available to all users)
    { id: 'myShares', label: t.value('settings.title.myShares'), keywords: 'my shares files uploads', path: 'myShares', icon: Boxes, category: 'navigation' },
    { id: 'myProfile', label: t.value('settings.title.myProfile'), keywords: 'my profile account user settings', path: 'myProfile', icon: User, category: 'navigation' },
    
    // Admin-only tabs
    { id: 'stats', label: t.value('settings.title.stats') || 'System Stats', keywords: 'stats statistics dashboard analytics monitor', path: 'stats', icon: BarChart3, category: 'navigation', adminOnly: true },
    { id: 'branding', label: t.value('settings.title.branding'), keywords: 'branding theme logo colors colours appearance', path: 'branding', icon: Palette, category: 'navigation', adminOnly: true },
    { id: 'system', label: t.value('settings.title.system'), keywords: 'system settings configuration', path: 'system', icon: Settings, category: 'navigation', adminOnly: true },
    { id: 'emailTemplates', label: t.value('settings.title.emailTemplates'), keywords: 'email templates mail notifications', path: 'emailTemplates', icon: Mail, category: 'navigation', adminOnly: true },
    { id: 'users', label: t.value('settings.title.users'), keywords: 'users accounts members admin', path: 'users', icon: Users, category: 'navigation', adminOnly: true },
    { id: 'allShares', label: t.value('settings.title.allShares'), keywords: 'all shares files admin manage', path: 'allShares', icon: FolderOpen, category: 'navigation', adminOnly: true },
    
    // System Settings sections (admin only)
    { id: 'system.general', label: t.value('settings.system.general'), keywords: 'general application name url language', path: 'system.general', icon: Settings, category: 'system', adminOnly: true },
    { id: 'system.shares', label: t.value('settings.system.shares'), keywords: 'shares expiry size limits reverse', path: 'system.shares', icon: Share2, category: 'system', adminOnly: true },
    { id: 'system.emails', label: t.value('settings.system.emails'), keywords: 'emails notifications download expiry warning', path: 'system.emails', icon: Send, category: 'system', adminOnly: true },
    { id: 'system.smtp', label: t.value('settings.system.smtp'), keywords: 'smtp mail server host port encryption', path: 'system.smtp', icon: AtSign, category: 'system', adminOnly: true },
    { id: 'system.auth', label: t.value('settings.system.auth'), keywords: 'auth authentication providers login sso oauth', path: 'system.auth', icon: Fingerprint, category: 'system', adminOnly: true },
    { id: 'system.backups', label: t.value('settings.system.backups.title'), keywords: 'backups database backup restore', path: 'system.backups', icon: Database, category: 'system', adminOnly: true },
    
    // System sub-sections
    { id: 'system.auth.self_registration', label: t.value('settings.system.self_registration'), keywords: 'self registration signup sign up new users', path: 'system.auth.self_registration', icon: Fingerprint, category: 'system', adminOnly: true },
    { id: 'system.auth.auth_providers', label: t.value('settings.system.auth_providers') || 'Auth Providers', keywords: 'oauth sso google microsoft authentik oidc providers', path: 'system.auth.auth_providers', icon: Fingerprint, category: 'system', adminOnly: true },
    { id: 'system.shares.reverse_shares', label: t.value('settings.system.reverse_shares'), keywords: 'reverse shares upload invite guest', path: 'system.shares.reverse_shares', icon: Share2, category: 'system', adminOnly: true },
    { id: 'system.shares.share_url_generation', label: t.value('settings.system.share_url_generation'), keywords: 'url generation pattern haiku shortcode', path: 'system.shares.share_url_generation', icon: Share2, category: 'system', adminOnly: true },
    
    // Branding sections (admin only)
    { id: 'branding.background-images', label: t.value('settings.branding.background_images') || 'Background Images', keywords: 'background images wallpaper slideshow', path: 'branding.background-images', icon: Images, category: 'branding', adminOnly: true },
    { id: 'branding.logo-settings', label: t.value('settings.branding.logo') || 'Logo Settings', keywords: 'logo image brand icon favicon', path: 'branding.logo-settings', icon: Image, category: 'branding', adminOnly: true },
    { id: 'branding.ui-colours', label: t.value('settings.branding.colours') || 'UI Colours', keywords: 'colors colours theme primary secondary accent', path: 'branding.ui-colours', icon: Pipette, category: 'branding', adminOnly: true },
  ]
  
  // Filter out admin-only commands if user is not admin
  return allCommands.filter(cmd => !cmd.adminOnly || isAdmin)
})

// Fuzzy search implementation
const fuzzyMatch = (text, query) => {
  if (!query) return { match: true, score: 0 }
  
  const textLower = text.toLowerCase()
  const queryLower = query.toLowerCase()
  
  // Exact match gets highest score
  if (textLower.includes(queryLower)) {
    return { match: true, score: 100 - textLower.indexOf(queryLower) }
  }
  
  // Fuzzy matching - check if all query chars appear in order
  let queryIndex = 0
  let score = 0
  let consecutiveBonus = 0
  
  for (let i = 0; i < textLower.length && queryIndex < queryLower.length; i++) {
    if (textLower[i] === queryLower[queryIndex]) {
      score += 10 + consecutiveBonus
      consecutiveBonus += 5
      queryIndex++
    } else {
      consecutiveBonus = 0
    }
  }
  
  return { 
    match: queryIndex === queryLower.length, 
    score 
  }
}

const filteredCommands = computed(() => {
  if (!searchQuery.value.trim()) {
    return commands.value
  }
  
  const query = searchQuery.value.trim()
  
  return commands.value
    .map(cmd => {
      const labelMatch = fuzzyMatch(cmd.label, query)
      const keywordMatch = fuzzyMatch(cmd.keywords, query)
      const bestScore = Math.max(labelMatch.score, keywordMatch.score)
      
      return {
        ...cmd,
        match: labelMatch.match || keywordMatch.match,
        score: bestScore
      }
    })
    .filter(cmd => cmd.match)
    .sort((a, b) => b.score - a.score)
})

// Reset selection when results change
watch(filteredCommands, () => {
  selectedIndex.value = 0
})

const open = () => {
  isOpen.value = true
  searchQuery.value = ''
  selectedIndex.value = 0
  nextTick(() => {
    searchInput.value?.focus()
  })
}

const close = () => {
  isOpen.value = false
  searchQuery.value = ''
}

const selectCommand = (command) => {
  navigateTo(command.path)
  close()
}

const handleKeydown = (e) => {
  if (!isOpen.value) return
  
  switch (e.key) {
    case 'ArrowDown':
      e.preventDefault()
      selectedIndex.value = Math.min(selectedIndex.value + 1, filteredCommands.value.length - 1)
      scrollSelectedIntoView()
      break
    case 'ArrowUp':
      e.preventDefault()
      selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
      scrollSelectedIntoView()
      break
    case 'Enter':
      e.preventDefault()
      if (filteredCommands.value[selectedIndex.value]) {
        selectCommand(filteredCommands.value[selectedIndex.value])
      }
      break
    case 'Escape':
      e.preventDefault()
      close()
      break
  }
}

const scrollSelectedIntoView = () => {
  nextTick(() => {
    const selected = document.querySelector('.command-item.selected')
    if (selected) {
      selected.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
    }
  })
}

const handleGlobalKeydown = (e) => {
  // Cmd+K (Mac) or Ctrl+K (Windows/Linux)
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault()
    if (store.isLoggedIn()) {
      if (isOpen.value) {
        close()
      } else {
        open()
      }
    }
  }
}

const clickOutside = (e) => {
  if (e.target === e.currentTarget) {
    close()
  }
}

const getCategoryLabel = (category) => {
  switch (category) {
    case 'navigation': return t.value('commandPalette.category.navigation') || 'Navigation'
    case 'system': return t.value('commandPalette.category.system') || 'System Settings'
    case 'branding': return t.value('commandPalette.category.branding') || 'Branding'
    default: return category
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleGlobalKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleGlobalKeydown)
})

defineExpose({ open, close })
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="isOpen" class="command-palette-overlay" @click="clickOutside">
        <div class="command-palette">
          <div class="command-palette-header">
            <Search class="search-icon" />
            <input
              ref="searchInput"
              v-model="searchQuery"
              type="text"
              :placeholder="t('commandPalette.placeholder') || 'Search settings...'"
              @keydown="handleKeydown"
              class="command-search-input"
            />
            <kbd class="keyboard-hint">ESC</kbd>
          </div>
          
          <div class="command-palette-body">
            <div v-if="filteredCommands.length === 0" class="no-results">
              {{ t('commandPalette.noResults') || 'No results found' }}
            </div>
            
            <template v-else>
              <div
                v-for="(command, index) in filteredCommands"
                :key="command.id"
                class="command-item"
                :class="{ selected: index === selectedIndex }"
                @click="selectCommand(command)"
                @mouseenter="selectedIndex = index"
              >
                <component :is="command.icon" class="command-icon" />
                <div class="command-content">
                  <span class="command-label">{{ command.label }}</span>
                  <span class="command-category">{{ getCategoryLabel(command.category) }}</span>
                </div>
              </div>
            </template>
          </div>
          
          <div class="command-palette-footer">
            <div class="hint">
              <kbd>↑</kbd><kbd>↓</kbd> {{ t('commandPalette.navigate') || 'to navigate' }}
            </div>
            <div class="hint">
              <kbd>↵</kbd> {{ t('commandPalette.select') || 'to select' }}
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style lang="scss" scoped>
.command-palette-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: var(--overlay-background-color, rgba(0, 0, 0, 0.5));
  backdrop-filter: blur(4px);
  z-index: 999;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 15vh;
}

.command-palette {
  width: 100%;
  max-width: 560px;
  background: var(--panel-background-color, #1a1a1a);
  border-radius: 12px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  border: 1px solid var(--panel-section-background-color-alt, #333);
}

.command-palette-header {
  display: flex;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--panel-section-background-color-alt, #333);
  gap: 12px;
  
  .search-icon {
    width: 20px;
    height: 20px;
    color: var(--panel-text-color-alt, #888);
    flex-shrink: 0;
  }
  
  .command-search-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    font-size: 1.1rem;
    color: var(--panel-text-color, #fff);
    
    &::placeholder {
      color: var(--panel-text-color-alt, #666);
    }
  }
  
  .keyboard-hint {
    background: var(--panel-section-background-color-alt, #333);
    color: var(--panel-text-color-alt, #888);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-family: inherit;
    border: 1px solid var(--input-border-color, #444);
  }
}

.command-palette-body {
  max-height: 400px;
  overflow-y: auto;
  padding: 8px;
}

.no-results {
  padding: 40px 20px;
  text-align: center;
  color: var(--panel-text-color-alt, #666);
  font-size: 0.95rem;
}

.command-item {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  border-radius: 8px;
  cursor: pointer;
  gap: 14px;
  transition: background 0.15s ease;
  
  &:hover,
  &.selected {
    background: var(--panel-section-background-color-alt, #2a2a2a);
  }
  
  &.selected {
    background: var(--primary-button-background-color, #3b82f6);
    
    .command-label {
      color: var(--primary-button-text-color, #fff);
    }
    
    .command-category {
      color: var(--primary-button-text-color, #fff);
      opacity: 0.7;
    }
    
    .command-icon {
      color: var(--primary-button-text-color, #fff);
    }
  }
  
  .command-icon {
    width: 18px;
    height: 18px;
    color: var(--link-color, #3b82f6);
    flex-shrink: 0;
  }
  
  .command-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-width: 0;
  }
  
  .command-label {
    color: var(--panel-text-color, #fff);
    font-size: 0.95rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  
  .command-category {
    color: var(--panel-text-color-alt, #666);
    font-size: 0.8rem;
    white-space: nowrap;
    flex-shrink: 0;
  }
}

.command-palette-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 24px;
  padding: 12px 20px;
  border-top: 1px solid var(--panel-section-background-color-alt, #333);
  background: var(--panel-section-background-color-alt, #1f1f1f);
  
  .hint {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--panel-text-color-alt, #666);
    font-size: 0.8rem;
    
    kbd {
      background: var(--panel-background-color, #2a2a2a);
      color: var(--panel-text-color-alt, #888);
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 0.75rem;
      font-family: inherit;
      border: 1px solid var(--input-border-color, #444);
      min-width: 22px;
      text-align: center;
    }
  }
}

// Transition
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

// Scrollbar styling
.command-palette-body {
  &::-webkit-scrollbar {
    width: 8px;
  }
  
  &::-webkit-scrollbar-track {
    background: transparent;
  }
  
  &::-webkit-scrollbar-thumb {
    background: var(--panel-section-background-color-alt, #333);
    border-radius: 4px;
    
    &:hover {
      background: var(--input-border-color, #444);
    }
  }
}
</style>

