import { getApiUrl, getTusdUrl } from './utils'
import { store, uploadController } from './store'
import { jwtDecode } from 'jwt-decode'
import { useToast } from 'vue-toastification'
import debounce from './debounce'
import * as tus from 'tus-js-client'

const apiUrl = getApiUrl()
const toast = useToast()
const addAuthHeader = () => ({
  Authorization: `Bearer ${store.jwt}`
})

const addJsonHeader = () => ({
  'Content-Type': 'application/json',
  Accept: 'application/json'
})

// Wrapper for fetch that handles auth refresh
const fetchWithAuth = async (url, options = {}) => {
  // Add auth header if not present
  if (!options.headers?.Authorization) {
    options.headers = {
      ...options.headers,
      ...addAuthHeader()
    }
  }

  try {
    const response = await fetch(url, options)

    // If response is OK, return as-is
    if (response.ok) {
      return response
    }

    // Handle 401 or 403
    if (response.status === 401 || response.status === 403) {
      // Clone the response so we can read the body
      const clonedResponse = response.clone()
      const responseData = await clonedResponse.json()

      // Check for password change required in response body
      if (responseData?.message === 'Password change required') {
        store.setSettingsOpen(false)
        debouncedPasswordChangeRequired()
        throw new Error('PASSWORD_CHANGE_REQUIRED')
      }

      // For 401, try to refresh token
      if (response.status === 401) {
        try {
          const refreshData = await refresh()

          // Update auth header with new token
          options.headers = {
            ...options.headers,
            Authorization: `Bearer ${refreshData.jwt}`
          }

          // Retry original request with new token
          return await fetch(url, options)
        } catch (refreshError) {
          // If refresh fails, proceed to logout
        }
      }

      // If we reach here, either:
      // 1. It was a 403 without password change required
      // 2. It was a 401 and token refresh failed
      // In both cases, we log the user out
      store.setMultiple({
        admin: false,
        loggedIn: false,
        jwt: '',
        jwtExpires: null
      })
      throw new Error('Session expired. Please login again.')
    }

    // Handle other error status codes
    return response
  } catch (error) {
    // Rethrow password change required error
    if (error.message === 'PASSWORD_CHANGE_REQUIRED') {
      throw error
    }
    // Handle other errors
    throw error
  }
}

// Auth Methods (these don't use fetchWithAuth since they handle auth directly)

export const resetPassword = async (token, email, password, password_confirmation) => {
  const response = await fetch(`${apiUrl}/api/auth/reset-password`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({
      token,
      email,
      password,
      password_confirmation
    })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data
}

export const forgotPassword = async (email) => {
  const response = await fetch(`${apiUrl}/api/auth/forgot-password`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({
      email
    })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data
}
export const login = async (email, password) => {
  const response = await fetch(`${apiUrl}/api/auth/login`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({
      email,
      password
    })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return buildAuthSuccessData(data)
}

export const sendReverseShareInvite = async (email, name, message) => {
  const response = await fetchWithAuth(`${apiUrl}/api/reverse-shares/invite`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({
      recipient_name: name,
      recipient_email: email,
      message: message
    })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data
}

export const acceptReverseShareInvite = async (token) => {
  const response = await fetch(`${apiUrl}/api/reverse-shares/accept?token=${token}`, {
    method: 'GET',
    credentials: 'include'
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return buildAuthSuccessData(data)
}

export const refresh = async () => {
  const response = await fetch(`${apiUrl}/api/auth/refresh`, {
    method: 'POST',
    credentials: 'include'
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return buildAuthSuccessData(data)
}

export const logout = async () => {
  try {
    await fetch(`${apiUrl}/api/auth/logout`, {
      method: 'POST',
      credentials: 'include'
    })
  } catch (error) {
    // ignore
  }

  store.setMultiple({
    admin: false,
    loggedIn: false,
    jwt: '',
    jwtExpires: null
  })

  return true
}

// User Methods
export const getUsers = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/users`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const createUser = async (user) => {
  const response = await fetchWithAuth(`${apiUrl}/api/users`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify(user)
  })
  const data = await response.json()
  if (!response.ok) {
    return Promise.reject(data)
  }
  return data.data
}

export const updateUser = async (user) => {
  const response = await fetchWithAuth(`${apiUrl}/api/users/${user.id}`, {
    method: 'PUT',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify(user)
  })
  const data = await response.json()
  if (!response.ok) {
    return Promise.reject(data)
  }
  return data.data
}

export const updateMyProfile = async (user) => {
  //unset empty fields
  Object.keys(user).forEach((key) => {
    if (user[key] === '' || user[key] === null) {
      delete user[key]
    }
  })

  const response = await fetchWithAuth(`${apiUrl}/api/users/me`, {
    method: 'PUT',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify(user)
  })
  const data = await response.json()
  if (!response.ok) {
    return Promise.reject(data)
  }
  return data.data.user
}

export const deleteUser = async (id) => {
  const response = await fetchWithAuth(`${apiUrl}/api/users/${id}`, {
    method: 'DELETE',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    return Promise.reject(data)
  }
  return data.data
}

export const forceResetPassword = async (id) => {
  const response = await fetchWithAuth(`${apiUrl}/api/users/${id}/force-reset-password`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    return Promise.reject(data)
  }
  return data
}

// Settings Methods
export const getSettingsByGroup = async (group) => {
  const response = await fetchWithAuth(`${apiUrl}/api/settings/group/${group}`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.settings
}

export const getSettingById = async (id) => {
  const response = await fetchWithAuth(`${apiUrl}/api/settings/${id}`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.setting
}

export const saveSettingsById = async (settings) => {
  console.log('save settings', settings)
  const settingsArray = []
  const keys = Object.keys(settings)
  for (const key of keys) {
    //if the value is a file, convert it to a string
    if (settings[key] instanceof File) {
      settings[key] = settings[key].name
    }

    //if it's an array, convert it to a string
    if (Array.isArray(settings[key])) {
      settings[key] = settings[key].join(',')
    }

    //if it's an object, convert it to a string
    if (typeof settings[key] === 'object') {
      settings[key] = JSON.stringify(settings[key])
    }

    settingsArray.push({
      key: key,
      value: settings[key] + ''
    })
  }

  const response = await fetchWithAuth(`${apiUrl}/api/settings`, {
    method: 'PUT',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({ settings: settingsArray })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const saveLogo = async (logoFile) => {
  const formData = new FormData()
  formData.append('logo', logoFile)

  const response = await fetchWithAuth(`${apiUrl}/api/settings/logo`, {
    method: 'POST',
    body: formData
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const resetLogo = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/settings/logo`, {
    method: 'DELETE'
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data
}

export const saveFavicon = async (faviconFile) => {
  const formData = new FormData()
  formData.append('favicon', faviconFile)

  const response = await fetchWithAuth(`${apiUrl}/api/settings/favicon`, {
    method: 'POST',
    body: formData
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const deleteFavicon = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/settings/favicon`, {
    method: 'DELETE'
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data
}

export const getFaviconStatus = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/settings/favicon/status`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const installCustomTheme = async (name, file) => {
  const formData = new FormData()
  formData.append('name', name)
  formData.append('file', file)

  const response = await fetchWithAuth(`${apiUrl}/api/themes/install`, {
    method: 'POST',
    body: formData
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.theme
}

export const getBackgroundImages = async () => {
  const response = await fetch(`${apiUrl}/api/backgrounds`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const saveBackgroundImage = async (backgroundImage) => {
  const formData = new FormData()
  formData.append('background_image', backgroundImage)

  const response = await fetchWithAuth(`${apiUrl}/api/settings/backgrounds`, {
    method: 'POST',
    body: formData
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const deleteBackgroundImage = async (file) => {
  const response = await fetchWithAuth(`${apiUrl}/api/settings/backgrounds/${file}`, {
    method: 'DELETE'
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

// Share Methods
export const getMyShares = async (showDeletedShares = false) => {
  const response = await fetchWithAuth(`${apiUrl}/api/shares?show_deleted=${showDeletedShares}`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.shares
}

export const getAllShares = async (showDeletedShares = false, userId = null) => {
  let url = `${apiUrl}/api/shares/all?show_deleted=${showDeletedShares}`
  if (userId) {
    url += `&user_id=${userId}`
  }
  const response = await fetchWithAuth(url, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.shares
}

export const expireShare = async (id) => {
  const response = await fetchWithAuth(`${apiUrl}/api/shares/${id}/expire`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.share
}

export const extendShare = async (id) => {
  const response = await fetchWithAuth(`${apiUrl}/api/shares/${id}/extend`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.share
}

export const setDownloadLimit = async (id, amount) => {
  const response = await fetchWithAuth(`${apiUrl}/api/shares/${id}/set-download-limit`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({
      amount
    })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.share
}

export const pruneExpiredShares = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/shares/prune-expired`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.shares
}

export const getShare = async (id) => {
  const response = await fetchWithAuth(`${apiUrl}/api/shares/${id}`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.share
}

// Theme Methods
export const getThemes = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/themes`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.themes
}

export const saveTheme = async (theme) => {
  const response = await fetchWithAuth(`${apiUrl}/api/themes`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify(theme)
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.theme
}

export const deleteTheme = async (name) => {
  const response = await fetchWithAuth(`${apiUrl}/api/themes/`, {
    method: 'DELETE',
    body: JSON.stringify({
      name
    }),
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const setActiveTheme = async (name) => {
  const response = await fetchWithAuth(`${apiUrl}/api/themes/set-active`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({
      name
    })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return true
}

export const getActiveTheme = async () => {
  const response = await fetch(`${apiUrl}/api/themes/active`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.theme
}

//public auth provider methods
export const getAvailableAuthProviders = async () => {
  const response = await fetch(`${apiUrl}/api/available-auth-providers`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.authProviders
}

//private auth provider methods
export const getAuthProviders = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/auth-providers`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.authProviders
}

export const getCallbackUrl = async (uuid) => {
  const response = await fetchWithAuth(`${apiUrl}/api/auth-providers/${uuid}/callback-url`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.callbackUrl
}

export const bulkUpdateAuthProviders = async (providers) => {
  const payload = {
    providers: providers.map((provider) => ({
      id: provider.id,
      name: provider.name,
      provider_config: provider.provider_config,
      class: provider.class,
      enabled: provider.enabled,
      allow_registration: provider.allow_registration,
      uuid: provider.uuid
    }))
  }
  const response = await fetchWithAuth(`${apiUrl}/api/auth-providers/bulk-update`, {
    method: 'PUT',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify(payload)
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const deleteAuthProvider = async (id) => {
  const response = await fetchWithAuth(`${apiUrl}/api/auth-providers/${id}`, {
    method: 'DELETE',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const getAvailableProviderTypes = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/auth-providers/available-types`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.providers
}

export const unlinkProvider = async (providerId) => {
  const response = await fetchWithAuth(`${apiUrl}/api/users/me/providers/${providerId}`, {
    method: 'DELETE',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

//misc methods
export const getHealth = async () => {
  const response = await fetch(`${apiUrl}/api/health`)
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const getMyProfile = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/users/me`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.user
}

export const createFirstUser = async (user) => {
  const response = await fetch(`${apiUrl}/api/setup`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify(user)
  })
  const data = await response.json()
  if (!response.ok) {
    return Promise.reject(data)
  }
  return data.data
}


export const getEmailTemplates = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/email-templates`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.templates
}

// System Stats Methods
export const getSystemStats = async (days = 30) => {
  const response = await fetchWithAuth(`${apiUrl}/api/stats?days=${days}`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const updateEmailTemplates = async (templates) => {
  const response = await fetchWithAuth(`${apiUrl}/api/email-templates`, {
    method: 'PUT',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify(templates)
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

// Cloud Connect Methods
export const getCloudConnectStatus = async (refresh = false) => {
  const url = refresh
    ? `${apiUrl}/api/cloud-connect/status?refresh=true`
    : `${apiUrl}/api/cloud-connect/status`
  const response = await fetchWithAuth(url, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const cloudConnectRegister = async (userData) => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/register`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify(userData)
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data
}

export const cloudConnectLogin = async (email, password) => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/login`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({ email, password })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data
}

export const cloudConnectLogout = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/logout`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data
}

export const getCloudConnectSubscription = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/subscription`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const getCloudConnectPlans = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/plans`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const createCloudConnectCheckout = async (plan) => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/checkout`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({ plan })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const checkCloudConnectSubdomain = async (subdomain) => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/subdomains/check?subdomain=${encodeURIComponent(subdomain)}`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const getCloudConnectInstances = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/instances`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const createCloudConnectInstance = async (name, subdomain, confirmReclaim = false) => {
  const body = { name, subdomain }
  if (confirmReclaim) {
    body.confirm_reclaim = true
  }
  
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/instances`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify(body)
  })
  const data = await response.json()
  if (!response.ok) {
    // For SUBDOMAIN_OWNED_BY_USER errors, throw an error with the full data
    if (data.code === 'SUBDOMAIN_OWNED_BY_USER') {
      const error = new Error(data.message)
      error.code = data.code
      error.data = data.data
      throw error
    }
    throw new Error(data.message)
  }
  return data.data
}

export const connectCloudConnect = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/connect`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const disconnectCloudConnect = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/disconnect`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const resendCloudConnectVerification = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/resend-verification`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const getCloudConnectTunnelStatus = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/tunnel-status`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const getCloudConnectUsage = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/usage`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const updateCloudConnectUser = async (name) => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/user`, {
    method: 'PATCH',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({ name })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const getCloudConnectInstance = async (instanceId) => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/instances/${instanceId}`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const updateCloudConnectInstance = async (instanceId, updateData) => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/instances/${instanceId}`, {
    method: 'PATCH',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify(updateData)
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const deleteCloudConnectInstance = async (instanceId) => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/instances/${instanceId}`, {
    method: 'DELETE',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const regenerateCloudConnectInstanceToken = async (instanceId) => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/instances/${instanceId}/regenerate-token`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const createCloudConnectBillingPortal = async (returnUrl = null) => {
  const body = returnUrl ? { return_url: returnUrl } : {}
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/billing/portal`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify(body)
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export const cloudConnectForgotPassword = async (email) => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/forgot-password`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({ email })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data
}

export const cloudConnectResetPassword = async (token, password, passwordConfirmation) => {
  const response = await fetchWithAuth(`${apiUrl}/api/cloud-connect/reset-password`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({
      token,
      password,
      password_confirmation: passwordConfirmation
    })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data
}

// Private functions
const buildAuthSuccessData = (data) => {
  const decoded = jwtDecode(data.data.access_token)
  return {
    userId: decoded.sub,
    admin: decoded.admin,
    loggedIn: true,
    jwtExpires: decoded.exp,
    jwt: data.data.access_token,
    mustChangePassword: decoded.must_change_password,
    guest: decoded.guest == 1 ? true : false
  }
}

const passwordChangeRequired = () => {
  toast.error('You must change your password to continue')
  store.showPasswordResetForm()
}

const debouncedPasswordChangeRequired = debounce(passwordChangeRequired, 100)

/**
 * Upload a single file using tus protocol
 * @param {File} file - The file to upload
 * @param {Function} onProgress - Progress callback function
 * @param {Function} onComplete - Complete callback function (receives upload URL)
 * @param {Function} onError - Error callback function
 * @returns {tus.Upload} - The tus upload instance (can be used for pause/resume/abort)
 */
export const uploadFileWithTus = (file, onProgress, onComplete, onError) => {
  const startUpload = (skipResume = false) => {
    const upload = new tus.Upload(file, {
      endpoint: getTusdUrl(),
      retryDelays: [0, 1000, 3000, 5000],
      chunkSize: 20 * 1024 * 1024, // 20MB chunks
      metadata: {
        filename: file.name,
        filetype: file.type || 'application/octet-stream'
      },
      headers: {
        Authorization: `Bearer ${store.jwt}`
      },
      onError: (error) => {
        console.error('tus upload error:', error)
        onError(error)
      },
      onProgress: (bytesUploaded, bytesTotal) => {
        const percentage = Math.round((bytesUploaded / bytesTotal) * 100)
        onProgress({
          percentage,
          uploadedBytes: bytesUploaded,
          totalBytes: bytesTotal
        })
      },
      onSuccess: () => {
        // Extract upload ID from the URL (last part of the path)
        const uploadUrl = upload.url
        const uploadId = uploadUrl.split('/').pop()
        onComplete({
          uploadId,
          uploadUrl,
          filename: file.name,
          filesize: file.size,
          filetype: file.type
        })
      }
    })

    if (skipResume) {
      // Start fresh without checking for previous uploads
      upload.start()
    } else {
      // Check for previous uploads to resume
      upload.findPreviousUploads().then(async (previousUploads) => {
        if (previousUploads.length > 0) {
          const previousUpload = previousUploads[0]
          // Extract upload ID from the previous upload URL
          const previousUploadId = previousUpload.uploadUrl.split('/').pop()

          // Verify with our backend that this upload session still exists
          // If the file was already used to create a share, the session will be gone
          try {
            const response = await fetch(`${apiUrl}/api/uploads/verify/${previousUploadId}`, {
              method: 'GET',
              headers: {
                Authorization: `Bearer ${store.jwt}`
              }
            })

            if (response.ok) {
              // Upload session exists in our backend, safe to resume
              console.log('Resuming previous upload:', previousUploadId)
              upload.resumeFromPreviousUpload(previousUpload)
            } else {
              // Upload session doesn't exist (file was already shared), start fresh
              console.log('Previous upload no longer valid, starting fresh')
              // Clear the stale fingerprint from localStorage
              clearTusFingerprint(previousUpload.uploadUrl)
            }
          } catch (e) {
            // If verification fails, be safe and start fresh
            console.warn('Could not verify previous upload, starting fresh:', e)
            clearTusFingerprint(previousUpload.uploadUrl)
          }
        }
        upload.start()
      })
    }

    return upload
  }

  return startUpload(false)
}

/**
 * Clear a stale tus fingerprint from localStorage
 */
const clearTusFingerprint = (uploadUrl) => {
  try {
    for (let i = localStorage.length - 1; i >= 0; i--) {
      const key = localStorage.key(i)
      if (key && key.startsWith('tus::')) {
        const value = localStorage.getItem(key)
        // The value is a JSON string containing the uploadUrl
        if (value && value.includes(uploadUrl)) {
          localStorage.removeItem(key)
          console.log('Cleared stale tus fingerprint:', key)
        }
      }
    }
  } catch (e) {
    console.warn('Could not clear stale tus fingerprint:', e)
  }
}

/**
 * Uploads multiple files using tus protocol
 * @param {Array} files - Array of files to upload
 * @param {string} uploadId - Unique ID for this upload batch
 * @param {string} shareName - Name of the share
 * @param {string} shareDescription - Description of the share
 * @param {Array} recipients - Recipients for the share
 * @param {Date} expiryDate - Expiry date for the share
 * @param {string} password - Optional password for the share
 * @param {string} passwordConfirm - Password confirmation
 * @param {Function} onProgress - Progress callback function
 * @param {Function} onComplete - Complete callback function
 * @param {Function} onError - Error callback function
 */
export const uploadFilesInChunks = async (
  files,
  uploadId,
  shareName,
  shareDescription,
  recipients,
  expiryDate,
  password,
  passwordConfirm,
  onProgress,
  onComplete,
  onError
) => {
  const totalSize = files.reduce((total, file) => total + file.size, 0)
  let uploadedSize = 0
  const results = []
  const uploads = [] // Store upload instances for pause/resume

  // Process each file sequentially
  for (let i = 0; i < files.length; i++) {
    const file = files[i]

    try {
      const result = await new Promise((resolve, reject) => {
        const checkPause = () => {
          if (uploadController.pause) {
            // Pause all active uploads
            uploads.forEach((u) => u.abort())
            setTimeout(checkPause, 1000)
            return true
          }
          return false
        }

        if (checkPause()) {
          // Wait for unpause before starting
          const waitForUnpause = setInterval(() => {
            if (!uploadController.pause) {
              clearInterval(waitForUnpause)
              startUpload()
            }
          }, 1000)
          return
        }

        const startUpload = () => {
          const upload = uploadFileWithTus(
            file,
            (progress) => {
              // Calculate overall progress
              const fileTotalUploaded = (progress.percentage / 100) * file.size
              const overallPercentage = Math.round(((uploadedSize + fileTotalUploaded) / totalSize) * 100)

              onProgress({
                percentage: overallPercentage,
                uploadedBytes: uploadedSize + progress.uploadedBytes,
                totalBytes: totalSize,
                currentFile: i + 1,
                totalFiles: files.length,
                currentFileName: file.name
              })
            },
            (uploadResult) => {
              uploadResult.fullPath = file.fullPath
              resolve(uploadResult)
            },
            (error) => {
              reject(error)
            }
          )
          uploads.push(upload)
        }

        startUpload()
      })

      results.push(result)
      uploadedSize += file.size
    } catch (error) {
      onError(error)
      return // Stop on first error
    }
  }

  // All files have been uploaded, now create the share
  try {
    const filePaths = {}
    results.forEach((r) => {
      filePaths[r.uploadId] = r.fullPath
    })

    const response = await fetchWithAuth(`${apiUrl}/api/uploads/create-share-from-uploads`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${store.jwt}`
      },
      body: JSON.stringify({
        upload_id: uploadId,
        name: shareName,
        description: shareDescription,
        recipients: recipients,
        uploadIds: results.map((r) => r.uploadId),
        filePaths: filePaths,
        expiry_date: expiryDate,
        password: password,
        password_confirm: passwordConfirm
      })
    })

    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.message || 'Failed to create share from uploads')
    }

    const data = await response.json()
    onComplete(data)
  } catch (error) {
    onError(error)
  }
}
