import { fetchWithAuth } from './fetchWithAuth'
import { getApiUrl } from '../utils'
import { addJsonHeader } from './lib/addJsonHeader'

const apiUrl = getApiUrl()

/**
 * Creates an upload session on the server
 */
const createUploadSession = async (file, uploadId, totalChunks) => {
  const response = await fetchWithAuth(`${apiUrl}/api/uploads/create-session`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
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