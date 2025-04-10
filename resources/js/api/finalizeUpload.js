import { fetchWithAuth } from './fetchWithAuth'
import { store } from '../store'
import { getApiUrl } from '../utils'

const apiUrl = getApiUrl()

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

export { finalizeUpload }