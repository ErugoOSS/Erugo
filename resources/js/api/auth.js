import { getApiUrl } from '../utils'
import { addJsonHeader } from './addJsonHeader'
import { jwtDecode } from 'jwt-decode'
import { store } from '../store'
import debounce from '../debounce'
const apiUrl = getApiUrl()

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

const resetPassword = async (token, email, password, password_confirmation) => {
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

const forgotPassword = async email => {
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

const login = async (email, password) => {
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

const refresh = async () => {
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

const logout = async () => {
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


const passwordChangeRequired = () => {
  toast.error('You must change your password to continue')
  store.showPasswordResetForm()
}

const debouncedPasswordChangeRequired = debounce(passwordChangeRequired, 100)


export {
  resetPassword,
  forgotPassword,
  login,
  refresh,
  logout,
  debouncedPasswordChangeRequired
}