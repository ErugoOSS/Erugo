import { fetchWithAuth } from './fetchWithAuth'
import { addJsonHeader } from './addJsonHeader'
import { getApiUrl } from '../utils'

const apiUrl = getApiUrl()

const sendReverseShareInvite = async (email, name, message) => {
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

const acceptReverseShareInvite = async token => {
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

export { sendReverseShareInvite, acceptReverseShareInvite }
