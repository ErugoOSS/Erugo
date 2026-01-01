import { getApiUrl, getTusdUrl } from './utils'
import { store, uploadController } from './store'
import { jwtDecode } from 'jwt-decode'
import { useToast } from 'vue-toastification'
import debounce from './debounce'
import * as tus from 'tus-js-client'
import { watch } from 'vue'
import JSZip from 'jszip'

// Bundling configuration for small files
const BUNDLE_CONFIG = {
  minFileCount: 50,        // Minimum number of files to trigger bundling
  maxFileSizeBytes: 102400, // Files under 100KB are considered "small"
  minSmallFileRatio: 0.7   // At least 70% of files must be small to trigger bundling
}

/**
 * Check if the files should be bundled based on count and size distribution
 */
const shouldBundleFiles = (files) => {
  if (files.length < BUNDLE_CONFIG.minFileCount) {
    return false
  }

  const smallFileCount = files.filter(f => f.size <= BUNDLE_CONFIG.maxFileSizeBytes).length
  const smallFileRatio = smallFileCount / files.length

  const shouldBundle = smallFileRatio >= BUNDLE_CONFIG.minSmallFileRatio

  console.log('[shouldBundleFiles]', {
    totalFiles: files.length,
    smallFileCount,
    smallFileRatio: (smallFileRatio * 100).toFixed(1) + '%',
    threshold: (BUNDLE_CONFIG.minSmallFileRatio * 100) + '%',
    willBundle: shouldBundle
  })

  return shouldBundle
}

/**
 * Bundle multiple files into a single zip file
 * Preserves file paths in the zip structure
 * @returns {Promise<{blob: Blob, manifest: Object}>} The zip blob and manifest of files
 */
const bundleFilesIntoZip = async (files, onProgress) => {
  const zip = new JSZip()
  const manifest = {
    version: 1,
    files: []
  }

  console.log('[bundleFilesIntoZip] Starting to bundle', files.length, 'files')

  for (let i = 0; i < files.length; i++) {
    const file = files[i]
    // Use fullPath if available (for folder uploads), otherwise just the name
    const filePath = file.fullPath || file.name

    // Add to zip - JSZip handles the folder structure automatically from the path
    zip.file(filePath, file)

    // Track in manifest
    manifest.files.push({
      path: filePath,
      originalName: file.name,
      size: file.size,
      type: file.type || 'application/octet-stream'
    })

    if (onProgress) {
      onProgress({
        phase: 'bundling',
        current: i + 1,
        total: files.length,
        percentage: Math.round(((i + 1) / files.length) * 100)
      })
    }
  }

  // Add manifest to the zip
  zip.file('__erugo_manifest__.json', JSON.stringify(manifest, null, 2))

  console.log('[bundleFilesIntoZip] Generating zip...')

  // Generate the zip blob
  const blob = await zip.generateAsync(
    { type: 'blob', compression: 'DEFLATE', compressionOptions: { level: 6 } },
    (metadata) => {
      if (onProgress) {
        onProgress({
          phase: 'compressing',
          percentage: Math.round(metadata.percent)
        })
      }
    }
  )

  console.log('[bundleFilesIntoZip] Bundle complete', {
    originalFileCount: files.length,
    bundleSize: blob.size,
    originalTotalSize: files.reduce((sum, f) => sum + f.size, 0)
  })

  return { blob, manifest }
}

/**
 * Upload files as a bundle (zipped together)
 * Used when there are many small files to avoid tusd hook overload
 */
const uploadBundledFiles = async (
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
  try {
    // Phase 1: Bundle the files
    onProgress({
      phase: 'bundling',
      percentage: 0,
      message: 'Preparing files...'
    })

    const { blob, manifest } = await bundleFilesIntoZip(files, (bundleProgress) => {
      onProgress({
        phase: bundleProgress.phase,
        percentage: bundleProgress.percentage,
        message: bundleProgress.phase === 'bundling'
          ? `Preparing files (${bundleProgress.current}/${bundleProgress.total})...`
          : `Compressing (${bundleProgress.percentage}%)...`
      })
    })

    // Create a File object from the blob for tus upload
    const bundleFile = new File([blob], '__erugo_bundle__.zip', {
      type: 'application/zip'
    })

    console.log('[uploadBundledFiles] Bundle created', {
      originalFiles: files.length,
      bundleSize: bundleFile.size
    })

    // Phase 2: Upload the bundle
    onProgress({
      phase: 'uploading',
      percentage: 0,
      uploadedBytes: 0,
      totalBytes: bundleFile.size,
      currentFile: 1,
      totalFiles: 1,
      currentFileName: `Bundle (${files.length} files)`
    })

    const uploadResult = await new Promise((resolve, reject) => {
      const upload = uploadFileWithTus(
        bundleFile,
        (progress) => {
          onProgress({
            phase: 'uploading',
            percentage: progress.percentage,
            uploadedBytes: progress.uploadedBytes,
            totalBytes: progress.totalBytes,
            currentFile: 1,
            totalFiles: 1,
            currentFileName: `Bundle (${files.length} files)`
          })
        },
        (result) => {
          resolve(result)
        },
        (error) => {
          reject(error)
        },
        // Pass bundle metadata to tus
        {
          isBundle: 'true',
          bundleFileCount: String(files.length)
        }
      )
    })

    console.log('[uploadBundledFiles] Bundle uploaded', uploadResult)

    // Phase 3: Create the share
    onProgress({
      phase: 'creating',
      percentage: 100,
      message: 'Creating share...'
    })

    const apiUrl = getApiUrl()
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
        uploadIds: [uploadResult.uploadId],
        filePaths: {}, // Not needed for bundles, paths are in the manifest
        expiry_date: expiryDate,
        password: password,
        password_confirm: passwordConfirm,
        isBundle: true
      })
    })

    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.message || 'Failed to create share from bundle')
    }

    const data = await response.json()
    console.log('[uploadBundledFiles] Share created successfully')
    onComplete(data)

  } catch (error) {
    console.error('[uploadBundledFiles] Error:', error)
    onError(error)
  }
}

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

export const getSystemInfo = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/stats/system-info`, {
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

// Database Backup Methods
export const getBackups = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/backups`, {
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

export const createBackup = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/backups`, {
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

export const downloadBackup = async (filename) => {
  const response = await fetchWithAuth(`${apiUrl}/api/backups/${encodeURIComponent(filename)}/download`, {
    method: 'GET'
  })
  if (!response.ok) {
    const data = await response.json()
    throw new Error(data.message)
  }
  // Return the response blob for download
  const blob = await response.blob()
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  window.URL.revokeObjectURL(url)
  document.body.removeChild(a)
  return true
}

export const deleteBackup = async (filename) => {
  const response = await fetchWithAuth(`${apiUrl}/api/backups/${encodeURIComponent(filename)}`, {
    method: 'DELETE',
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
 * @param {Object} extraMetadata - Optional additional metadata to include
 * @returns {tus.Upload} - The tus upload instance (can be used for pause/resume/abort)
 */
export const uploadFileWithTus = (file, onProgress, onComplete, onError, extraMetadata = {}) => {
  const startUpload = (skipResume = false) => {
    const tusdEndpoint = getTusdUrl()
    console.log('[uploadFileWithTus] Creating tus.Upload with endpoint:', tusdEndpoint)
    const upload = new tus.Upload(file, {
      endpoint: tusdEndpoint,
      retryDelays: [0, 1000, 3000, 5000],
      chunkSize: 20 * 1024 * 1024, // 20MB chunks
      removeFingerprintOnSuccess: true, // Clean up fingerprint after successful upload
      metadata: {
        filename: file.name,
        filetype: file.type || 'application/octet-stream',
        ...extraMetadata
      },
      headers: {
        Authorization: `Bearer ${store.jwt}`
      },
      // Refresh token before each request if it's about to expire
      // This prevents long uploads from failing due to token expiration
      onBeforeRequest: function (req) {
        const now = new Date()
        const fiveMinutesFromNow = new Date(now.getTime() + 5 * 60 * 1000)

        // Only attempt refresh if token is expiring soon
        if (store.jwtExpires && store.jwtExpires < fiveMinutesFromNow) {
          // Return a Promise so tus waits for the refresh to complete
          return refresh()
            .then(refreshData => {
              store.authSuccess(refreshData)
              console.log('[uploadFileWithTus] Token refreshed successfully')
              // Update header with new token
              req.setHeader('Authorization', `Bearer ${store.jwt}`)
            })
            .catch(e => {
              console.error('[uploadFileWithTus] Failed to refresh token:', e)
              // Don't modify header on failure, use existing token
            })
        }
        // No refresh needed - don't touch headers, let static config apply
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
        
        // Filter out uploads with mismatched protocol or host (including port)
        // This prevents resume attempts when the site is accessed from a different URL
        const currentProtocol = window.location.protocol
        const currentHost = window.location.host
        const validPreviousUploads = previousUploads.filter(u => {
          const uploadUrl = new URL(u.uploadUrl)
          if (uploadUrl.protocol !== currentProtocol) {
            console.log('[uploadFileWithTus] Skipping previous upload with mismatched protocol:', u.uploadUrl, '(current:', currentProtocol, ')')
            return false
          }
          if (uploadUrl.host !== currentHost) {
            console.log('[uploadFileWithTus] Skipping previous upload with mismatched host:', u.uploadUrl, '(current:', currentHost, ')')
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
  // Check if we should bundle the files (many small files)
  const useBundle = shouldBundleFiles(files)

  if (useBundle) {
    console.log('[uploadFilesInChunks] Using bundled upload for', files.length, 'files')
    return uploadBundledFiles(
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
    )
  }

  // Standard upload flow for normal file counts/sizes
  const totalSize = files.reduce((total, file) => total + file.size, 0)
  let uploadedSize = 0
  const results = []

  // Track the currently active upload for pause/resume
  let currentUpload = null
  let stopWatcher = null

  // Set up a watcher on uploadController.pause to handle pause/resume
  const setupPauseWatcher = () => {
    stopWatcher = watch(
      () => uploadController.pause,
      (isPaused) => {
        if (currentUpload) {
          if (isPaused) {
            console.log('[uploadFilesInChunks] Pause detected, aborting current upload')
            currentUpload.abort()
          } else {
            console.log('[uploadFilesInChunks] Resume detected, starting upload')
            currentUpload.start()
          }
        }
      }
    )
  }

  // Clean up the watcher when done
  const cleanupWatcher = () => {
    if (stopWatcher) {
      stopWatcher()
      stopWatcher = null
    }
  }

  setupPauseWatcher()

  // Process each file sequentially
  for (let i = 0; i < files.length; i++) {
    const file = files[i]

    try {
      const result = await new Promise((resolve, reject) => {
        // Wait for unpause before starting if currently paused
        const waitForUnpauseAndStart = () => {
          if (uploadController.pause) {
            // Check again in a bit
            setTimeout(waitForUnpauseAndStart, 500)
            return
          }
          startUpload()
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
                currentFileName: file.name,
                currentFilePath: file.fullPath || file.name,
                currentFileProgress: progress.percentage,
                currentFileUploadedBytes: progress.uploadedBytes,
                currentFileTotalBytes: progress.totalBytes
              })
            },
            (uploadResult) => {
              uploadResult.fullPath = file.fullPath
              currentUpload = null
              resolve(uploadResult)
            },
            (error) => {
              currentUpload = null
              reject(error)
            }
          )
          currentUpload = upload
        }

        waitForUnpauseAndStart()
      })

      results.push(result)
      uploadedSize += file.size
    } catch (error) {
      cleanupWatcher()
      onError(error)
      return // Stop on first error
    }
  }

  cleanupWatcher()

  // All files have been uploaded, now create the share
  // Use retry logic to handle race condition where backend post-finish hooks
  // may still be processing (especially with many small files)
  const filePaths = {}
  results.forEach((r) => {
    filePaths[r.uploadId] = r.fullPath
  })

  const uploadIds = results.map((r) => r.uploadId)
  const maxRetries = 5
  const baseDelayMs = 500

  console.log('[createShare] Starting share creation', {
    fileCount: results.length,
    uploadIds: uploadIds,
    totalSize: results.reduce((sum, r) => sum + (r.filesize || 0), 0)
  })

  for (let attempt = 0; attempt < maxRetries; attempt++) {
    try {
      console.log(`[createShare] Attempt ${attempt + 1}/${maxRetries}`)

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
          uploadIds: uploadIds,
          filePaths: filePaths,
          expiry_date: expiryDate,
          password: password,
          password_confirm: passwordConfirm
        })
      })

      if (response.ok) {
        const data = await response.json()
        console.log('[createShare] Success on attempt', attempt + 1)
        onComplete(data)
        return
      }

      const data = await response.json()
      const errorMessage = data.message || 'Failed to create share from uploads'

      // Check if this is the "not completed" error - worth retrying
      if (errorMessage.includes('not found or not completed') && attempt < maxRetries - 1) {
        const delayMs = baseDelayMs * Math.pow(2, attempt) // 500ms, 1000ms, 2000ms, 4000ms
        console.log(`[createShare] Upload sessions not ready, retrying in ${delayMs}ms...`, {
          attempt: attempt + 1,
          maxRetries,
          error: errorMessage
        })
        await new Promise(resolve => setTimeout(resolve, delayMs))
        continue
      }

      // Non-retryable error or out of retries
      console.error('[createShare] Failed after retries or non-retryable error', {
        attempt: attempt + 1,
        error: errorMessage
      })
      throw new Error(errorMessage)

    } catch (error) {
      // Network errors or thrown errors from above
      if (attempt === maxRetries - 1) {
        console.error('[createShare] Final attempt failed', { error: error.message })
        onError(error)
        return
      }

      // If it's a network error, might be worth retrying
      if (!error.message.includes('not found or not completed')) {
        console.error('[createShare] Unexpected error, not retrying', { error: error.message })
        onError(error)
        return
      }
    }
  }
}
