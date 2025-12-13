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

// Self-registration methods
export const getRegistrationSettings = async () => {
  const response = await fetch(`${apiUrl}/api/auth/registration-settings`, {
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

export const registerUser = async (name, email, password, password_confirmation) => {
  const response = await fetch(`${apiUrl}/api/auth/register`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({
      name,
      email,
      password,
      password_confirmation
    })
  })
  const data = await response.json()
  if (!response.ok) {
    const error = new Error(data.message)
    error.errors = data.data?.errors
    throw error
  }
  return data
}

export const verifyEmail = async (email, code) => {
  const response = await fetch(`${apiUrl}/api/auth/verify-email`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({
      email,
      code
    })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data
}

export const resendVerificationCode = async (email) => {
  const response = await fetch(`${apiUrl}/api/auth/resend-verification`, {
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

export const acceptReverseShareInviteById = async (inviteId) => {
  const response = await fetchWithAuth(`${apiUrl}/api/reverse-shares/accept-by-id`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({
      invite_id: inviteId
    })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data
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
    const error = new Error(data.message)
    error.code = data.error_code || 'unknown_error'
    throw error
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

// Save settings during first-run setup (after authentication)
export const saveFirstRunSettings = async (settings) => {
  // Save text-based settings first
  const settingsToSave = []
  
  if (settings.application_name) {
    settingsToSave.push({ key: 'application_name', value: settings.application_name })
  }
  if (settings.application_url) {
    settingsToSave.push({ key: 'application_url', value: settings.application_url })
  }
  
  // Save text settings if any
  if (settingsToSave.length > 0) {
    await saveSettingsById(
      Object.fromEntries(settingsToSave.map(s => [s.key, s.value]))
    )
  }
  
  // Upload logo if provided
  if (settings.logo instanceof File) {
    await saveLogo(settings.logo)
  }
  
  return true
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
    const error = new Error(data.message)
    error.code = data.error_code || 'unknown_error'
    throw error
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

export const resetFavicon = async () => {
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
    const tusdEndpoint = getTusdUrl()
    console.log('[uploadFileWithTus] Creating tus.Upload with endpoint:', tusdEndpoint)
    const upload = new tus.Upload(file, {
      endpoint: tusdEndpoint,
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
        console.log('[uploadFileWithTus] Found previous uploads:', previousUploads.length, previousUploads.map(u => u.uploadUrl))
        
        // Filter out uploads with mismatched protocol (e.g., http:// when we're on https://)
        const currentProtocol = window.location.protocol
        const validPreviousUploads = previousUploads.filter(u => {
          const uploadProtocol = new URL(u.uploadUrl).protocol
          if (uploadProtocol !== currentProtocol) {
            console.log('[uploadFileWithTus] Skipping previous upload with mismatched protocol:', u.uploadUrl, '(current:', currentProtocol, ')')
            return false
          }
          return true
        })
        
        if (validPreviousUploads.length > 0) {
          const previousUpload = validPreviousUploads[0]
          console.log('[uploadFileWithTus] Attempting to resume from:', previousUpload.uploadUrl)
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
              console.log('[uploadFileWithTus] Resuming previous upload:', previousUploadId, 'URL:', previousUpload.uploadUrl)
              upload.resumeFromPreviousUpload(previousUpload)
            } else {
              // Upload session doesn't exist (file was already shared), start fresh
              console.log('[uploadFileWithTus] Previous upload no longer valid, starting fresh with endpoint:', tusdEndpoint)
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
