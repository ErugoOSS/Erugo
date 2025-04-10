import { fetchWithAuth } from './fetchWithAuth'
import { addJsonHeader } from './addJsonHeader'
import { getApiUrl } from '../utils'

const apiUrl = getApiUrl()

const getSettingsByGroup = async (group) => {
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

const getSettingById = async (id) => {
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

const saveSettingsById = async (settings) => {
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

const saveLogo = async (logoFile) => {
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

const installCustomTheme = async (name, file) => {
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

const getBackgroundImages = async () => {
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

const saveBackgroundImage = async (backgroundImage) => {
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

const deleteBackgroundImage = async (file) => {
  const response = await fetchWithAuth(`${apiUrl}/api/settings/backgrounds/${file}`, {
    method: 'DELETE'
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export { getSettingsByGroup, getSettingById, saveSettingsById, saveLogo, installCustomTheme, getBackgroundImages, saveBackgroundImage, deleteBackgroundImage }