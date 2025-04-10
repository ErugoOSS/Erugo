import { fetchWithAuth } from './fetchWithAuth'
import { store } from '../store'
import { getApiUrl } from '../utils'

const apiUrl = getApiUrl()

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

export { createUploadSession }