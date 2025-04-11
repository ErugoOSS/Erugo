import { fetchWithAuth } from './fetchWithAuth'
import { addJsonHeader } from './lib/addJsonHeader'
import { getApiUrl } from '../utils'

const apiUrl = getApiUrl()

const getEmailTemplates = async () => {
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

const updateEmailTemplates = async (templates) => {
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

export { getEmailTemplates, updateEmailTemplates }