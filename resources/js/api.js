import { getApiUrl } from './utils'
import { store, uploadController } from './store'
import { jwtDecode } from 'jwt-decode'
import { useToast } from 'vue-toastification'
import debounce from './debounce'

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
export const createShare = async (files, name, description, recipients, uploadId, expiryDate, password, passwordConfirm, onProgress) => {
  const formData = new FormData()
  files.forEach((file) => {
    formData.append('files[]', file)
    formData.append('file_paths[]', file.fullPath)
  })
  formData.append('name', name)
  formData.append('description', description)
  formData.append('upload_id', uploadId)
  formData.append('expiry_date', expiryDate.toISOString())
  if (password) {
    formData.append('password', password)
  }
  if (passwordConfirm) {
    formData.append('password_confirm', passwordConfirm)
  }
  if (recipients.length > 0) {
    recipients.forEach((recipient, index) => {
      formData.append(`recipients[${index}][name]`, recipient.name)
      formData.append(`recipients[${index}][email]`, recipient.email)
    })
  }

  const xhr = new XMLHttpRequest()

  xhr.upload.onprogress = (event) => {
    if (event.lengthComputable) {
      const percentageComplete = Math.round((event.loaded * 100) / event.total)
      onProgress({
        percentage: percentageComplete,
        uploadedBytes: event.loaded,
        totalBytes: event.total
      })
    }
  }

  xhr.open('POST', `${apiUrl}/api/shares`, true)
  xhr.setRequestHeader('Accept', 'application/json')
  xhr.setRequestHeader('Authorization', `Bearer ${store.jwt}`)

  xhr.onload = () => {
    if (xhr.status === 200) {
      const response = JSON.parse(xhr.responseText)
    }
  }

  xhr.send(formData)

  return new Promise((resolve, reject) => {
    xhr.onload = () => {
      if (xhr.status === 200) {
        resolve(JSON.parse(xhr.responseText))
      } else {
        reject(new Error(xhr.responseText))
      }
    }
    xhr.onerror = () => reject(new Error('Network Error'))
  })
}

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
 * Uploads a file in chunks to the server
 * @param {File} file - The file to upload
 * @param {string} uploadId - Unique ID for this upload
 * @param {string} shareName - Name of the share
 * @param {string} shareDescription - Description of the share
 * @param {Array} recipients - Recipients for the share
 * @param {Function} onProgress - Progress callback function
 * @param {Function} onComplete - Complete callback function
 * @param {Function} onError - Error callback function
 */
export const uploadFileInChunks = async (
  file,
  uploadId,
  shareName,
  shareDescription,
  recipients,
  onProgress,
  onComplete,
  onError
) => {
  // Configuration
  const chunkSize = 1024 * 1024 * 20 // 20MB chunks
  const totalChunks = Math.ceil(file.size / chunkSize)
  let currentChunk = 0
  let totalUploaded = 0

  // Create upload session first
  try {
    await createUploadSession(file, uploadId, totalChunks)
  } catch (error) {
    onError(error)
    return
  }

  // Process chunks
  const processChunk = async () => {
    if (currentChunk >= totalChunks) {
      // All chunks uploaded, finalize the upload
      try {
        const result = await finalizeUpload(uploadId, file.name, shareName, shareDescription, recipients)
        onComplete(result)
      } catch (error) {
        onError(error)
      }
      return
    }

    if (uploadController.pause) {
      //we're paused so hold fire for 1 second and try again
      setTimeout(processChunk, 1000)
      return
    }

    const start = currentChunk * chunkSize
    const end = Math.min(file.size, start + chunkSize)
    const chunk = file.slice(start, end)

    try {
      await uploadChunk(chunk, uploadId, currentChunk, totalChunks, file.name)

      // Update progress
      totalUploaded += chunk.size
      onProgress({
        percentage: Math.round((totalUploaded / file.size) * 100),
        uploadedBytes: totalUploaded,
        totalBytes: file.size,
        currentChunk,
        totalChunks
      })

      // Process next chunk
      currentChunk++
      processChunk()
    } catch (error) {
      // Retry logic could be implemented here
      onError(error)
    }
  }

  // Start processing chunks
  processChunk()
}

/**
 * Creates an upload session on the server
 */
const createUploadSession = async (file, uploadId, totalChunks) => {
  const response = await fetchWithAuth(`${apiUrl}/api/uploads/create-session`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${store.jwt}`
    },
    body: JSON.stringify({
      upload_id: uploadId,
      filename: file.name,
      filesize: file.size,
      filetype: file.type,
      total_chunks: totalChunks
    })
  })

  if (!response.ok) {
    const data = await response.json()
    throw new Error(data.message || 'Failed to create upload session')
  }

  return await response.json()
}

/**
 * Uploads a single chunk to the server
 */
const uploadChunk = async (chunk, uploadId, chunkIndex, totalChunks, filename) => {
  const formData = new FormData()
  formData.append('chunk', chunk, filename)
  formData.append('upload_id', uploadId)
  formData.append('chunk_index', chunkIndex)
  formData.append('total_chunks', totalChunks)

  const response = await fetchWithAuth(`${apiUrl}/api/uploads/chunk`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${store.jwt}`
    },
    body: formData
  })

  if (!response.ok) {
    const data = await response.json()
    throw new Error(data.message || 'Failed to upload chunk')
  }

  return await response.json()
}

/**
 * Finalizes a chunked upload on the server
 */
const finalizeUpload = async (uploadId, filename, shareName, shareDescription, recipients) => {
  const response = await fetchWithAuth(`${apiUrl}/api/uploads/finalize`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${store.jwt}`
    },
    body: JSON.stringify({
      upload_id: uploadId,
      filename: filename,
      name: shareName,
      description: shareDescription,
      recipients: recipients
    })
  })

  if (!response.ok) {
    const data = await response.json()
    throw new Error(data.message || 'Failed to finalize upload')
  }

  return await response.json()
}

/**
 * Uploads multiple files in chunks
 * This is a wrapper for uploadFileInChunks that handles multiple files
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

  // Process each file sequentially
  for (let i = 0; i < files.length; i++) {
    const file = files[i]
    const fileUploadId = `${uploadId}_file${i}`

    await new Promise((resolve) => {
      uploadFileInChunks(
        file,
        fileUploadId,
        shareName,
        shareDescription,
        recipients,
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
        (result) => {
          result.fullPath = file.fullPath
          results.push(result)
          console.log('result', results)
          uploadedSize += file.size
          resolve()
        },
        (error) => {
          onError(error)
          resolve() // Continue with next file even if this one fails
        }
      )
    })
  }

  // All files have been uploaded, now create the share
  try {

    const filePaths = {}
    results.forEach((r) => {
      console.log('r', r)
      filePaths[r.data.file.id] = r.fullPath
    })
    console.log('filePaths', filePaths)

    const response = await fetchWithAuth(`${apiUrl}/api/uploads/create-share-from-chunks`, {
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
        fileInfo: results.map((r) => r.data.file.id),
        filePaths: filePaths,
        expiry_date: expiryDate,
        password: password,
        password_confirm: passwordConfirm
      })
    })

    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.message || 'Failed to create share from chunks')
    }

    const data = await response.json()
    onComplete(data)
  } catch (error) {
    onError(error)
  }
}
