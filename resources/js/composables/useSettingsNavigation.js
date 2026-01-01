import { emitter } from '../store'

/**
 * Valid settings tabs that can be navigated to
 */
const VALID_TABS = [
  'stats',
  'branding',
  'system',
  'emailTemplates',
  'users',
  'allShares',
  'myShares',
  'myProfile',
  'about'
]

/**
 * Parse a settings path into its components
 * @param {string} path - Path like 'system.auth.self_registration'
 * @returns {{ tab: string, section: string|null, subSection: string|null }}
 */
export function parseSettingsPath(path) {
  if (!path || typeof path !== 'string') {
    return { tab: null, section: null, subSection: null }
  }

  const parts = path.split('.')
  const tab = parts[0] || null
  const section = parts[1] || null
  const subSection = parts[2] || null

  return { tab, section, subSection }
}

/**
 * Build a settings path from components
 * @param {string} tab - The main tab
 * @param {string|null} section - The section within the tab
 * @param {string|null} subSection - The sub-section within the section
 * @returns {string}
 */
export function buildSettingsPath(tab, section = null, subSection = null) {
  let path = tab
  if (section) {
    path += `.${section}`
    if (subSection) {
      path += `.${subSection}`
    }
  }
  return path
}

/**
 * Check URL hash for settings navigation path
 * @returns {{ tab: string, section: string|null, subSection: string|null }|null}
 */
export function checkUrlHash() {
  const hash = window.location.hash
  if (!hash || !hash.startsWith('#settings/')) {
    return null
  }

  const path = hash.replace('#settings/', '')
  const parsed = parseSettingsPath(path)

  // Validate that the tab is valid
  if (!parsed.tab || !VALID_TABS.includes(parsed.tab)) {
    return null
  }

  return parsed
}

/**
 * Update URL hash with settings path
 * @param {string} path - Path like 'system.auth.self_registration'
 * @param {boolean} replace - Whether to replace the current history entry
 */
export function updateUrlHash(path, replace = false) {
  const newHash = `#settings/${path}`
  
  if (replace) {
    window.history.replaceState(null, '', newHash)
  } else {
    window.history.pushState(null, '', newHash)
  }
}

/**
 * Clear the settings URL hash
 */
export function clearUrlHash() {
  window.history.pushState(null, '', window.location.pathname + window.location.search)
}

/**
 * Composable for settings navigation
 * @returns {{ navigateTo: (path: string, options?: { updateUrl?: boolean }) => void }}
 */
export function useSettingsNavigation() {
  /**
   * Navigate to a settings path
   * @param {string} path - Path like 'system.auth.self_registration'
   * @param {Object} options - Navigation options
   * @param {boolean} options.updateUrl - Whether to update the URL hash (default: true)
   */
  const navigateTo = (path, options = {}) => {
    const { updateUrl = true } = options

    const parsed = parseSettingsPath(path)

    if (!parsed.tab || !VALID_TABS.includes(parsed.tab)) {
      console.warn(`[useSettingsNavigation] Invalid tab: ${parsed.tab}`)
      return
    }

    // Emit navigation event for App.vue to handle
    emitter.emit('settingsNavigate', {
      tab: parsed.tab,
      section: parsed.section,
      subSection: parsed.subSection,
      path
    })

    // Update URL hash if requested
    if (updateUrl) {
      updateUrlHash(path)
    }
  }

  return {
    navigateTo
  }
}

export default useSettingsNavigation

