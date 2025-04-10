import { store } from '../store'
import { getApiUrl } from '../utils'

const apiUrl = getApiUrl()

/**
 * Uploads a single chunk to the server
 */
const uploadChunk = (chunk, uploadId, chunkIndex, totalChunks, filename, onChunkProgress) => {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    const formData = new FormData()
    formData.append('chunk', chunk, filename)
    formData.append('upload_id', uploadId)
    formData.append('chunk_index', chunkIndex)
    formData.append('total_chunks', totalChunks)

    // Track progress within this chunk
    xhr.upload.onprogress = event => {
      if (event.lengthComputable) {
        // Calculate progress for this specific chunk
        const chunkPercentage = Math.round((event.loaded * 100) / event.total)

        // Pass both the chunk-specific progress and the raw event data
        onChunkProgress({
          chunkPercentage,
          chunkLoaded: event.loaded,
          chunkTotal: event.total,
          chunkIndex,
          totalChunks
        })
      }
    }

    xhr.open('POST', `${apiUrl}/api/uploads/chunk`, true)
    xhr.setRequestHeader('Authorization', `Bearer ${store.jwt}`)
    xhr.setRequestHeader('Accept', 'application/json')

    xhr.onload = () => {
      if (xhr.status === 200) {
        try {
          const response = JSON.parse(xhr.responseText)
          resolve(response)
        } catch (error) {
          reject(new Error('Invalid JSON response'))
        }
      } else {
        try {
          const errorData = JSON.parse(xhr.responseText)
          reject(new Error(errorData.message || 'Failed to upload chunk'))
        } catch (error) {
          reject(new Error(`Failed with status ${xhr.status}`))
        }
      }
    }

    xhr.onerror = () => {
      reject(new Error('Network error during chunk upload'))
    }

    xhr.send(formData)
  })
}

export { uploadChunk }
