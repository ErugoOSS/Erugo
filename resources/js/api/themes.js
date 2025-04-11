import { fetchWithAuth } from './fetchWithAuth'
import { addJsonHeader } from './lib/addJsonHeader'
import { getApiUrl } from '../utils'

const apiUrl = getApiUrl()

const getThemes = async () => {
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

const saveTheme = async (theme) => {
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

const deleteTheme = async (name) => {
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

const setActiveTheme = async (name) => {
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

const getActiveTheme = async () => {
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

export { getThemes, saveTheme, deleteTheme, setActiveTheme, getActiveTheme }