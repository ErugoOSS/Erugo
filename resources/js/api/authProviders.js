import { fetchWithAuth } from './fetchWithAuth'
import { addJsonHeader } from './addJsonHeader'
import { getApiUrl } from '../utils'

const apiUrl = getApiUrl()

//public auth provider methods
const getAvailableAuthProviders = async () => {
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
const getAuthProviders = async () => {
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

const getCallbackUrl = async (uuid) => {
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

const bulkUpdateAuthProviders = async (providers) => {
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

const deleteAuthProvider = async (id) => {
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

const getAvailableProviderTypes = async () => {
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

const unlinkProvider = async (providerId) => {
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

export { getAvailableAuthProviders, getAuthProviders, getCallbackUrl, bulkUpdateAuthProviders, deleteAuthProvider, getAvailableProviderTypes, unlinkProvider }