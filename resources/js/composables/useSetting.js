import { ref, onMounted, onUnmounted } from 'vue'
import { emitter } from '../store'
import { getSettingsByGroup } from '../api'
import { mapSettings } from '../utils'

// Shared cache of settings by group
const settingsCache = ref({})
const loadingGroups = ref({})

// Load settings for a group
const loadGroup = async (group) => {
  if (loadingGroups.value[group]) {
    // Already loading, wait for it
    return
  }
  
  loadingGroups.value[group] = true
  
  try {
    const settings = mapSettings(await getSettingsByGroup(group))
    settingsCache.value[group] = settings
  } catch (error) {
    console.error(`Failed to load settings for group ${group}`, error)
  } finally {
    loadingGroups.value[group] = false
  }
}

// Refresh a specific group or all cached groups
const refreshSettings = async (group = null) => {
  if (group) {
    await loadGroup(group)
  } else {
    // Refresh all cached groups
    const groups = Object.keys(settingsCache.value)
    await Promise.all(groups.map(g => loadGroup(g)))
  }
}

/**
 * Composable to subscribe to a setting value that auto-updates when settings change
 * 
 * @param {string} key - The setting key (e.g., 'self_registration_enabled')
 * @param {string} group - The settings group (e.g., 'system.auth')
 * @param {any} defaultValue - Default value if setting is not found
 * @returns {{ value: Ref, loading: Ref, refresh: Function }}
 */
export function useSetting(key, group, defaultValue = null) {
  const value = ref(defaultValue)
  const loading = ref(true)

  const updateValue = () => {
    if (settingsCache.value[group] && settingsCache.value[group][key] !== undefined) {
      value.value = settingsCache.value[group][key]
    }
  }

  const load = async () => {
    loading.value = true
    
    // Check if already cached
    if (settingsCache.value[group]) {
      updateValue()
      loading.value = false
    }
    
    // Load fresh data
    await loadGroup(group)
    updateValue()
    loading.value = false
  }

  const handleSettingsChanged = (payload) => {
    // If no group specified in event, or it matches our group, refresh
    if (!payload?.group || payload.group === group) {
      load()
    }
  }

  onMounted(() => {
    load()
    emitter.on('settingsChanged', handleSettingsChanged)
  })

  onUnmounted(() => {
    emitter.off('settingsChanged', handleSettingsChanged)
  })

  return {
    value,
    loading,
    refresh: load
  }
}

// Export helper to emit settings changed event
export const notifySettingsChanged = (group = null) => {
  emitter.emit('settingsChanged', group ? { group } : null)
}

