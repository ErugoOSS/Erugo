import { addAuthHeader } from './addAuthHeader'

// Wrapper for fetch that handles auth refresh
export const fetchWithAuth = async (url, options = {}) => {
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