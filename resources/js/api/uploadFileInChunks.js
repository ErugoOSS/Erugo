import { uploadChunk } from './uploadChunk'
import { finalizeUpload } from './finalizeUpload'
import { createUploadSession } from './createUploadSession'
import { uploadController } from '../store'
/**
 * Uploads a file in chunks to the server
 * @param {File} file - The file to upload
 * @param {string} uploadId - Unique ID for this upload
 * @param {string} shareName - Name of the share
 * @param {string} shareDescription - Description of the share
 * @param {Array} recipients - Recipients for the share
 * @param {Function} onProgress - Progress callback function
 * @param {Function} onComplete - Complete callback function
 * @param {Function} onError - Error callback function
 */
const uploadFileInChunks = async (
  file,
  uploadId,
  shareName,
  shareDescription,
  recipients,
  onProgress,
  onComplete,
  onError
) => {
  // Configuration
  const chunkSize = 1024 * 1024 * 3 // 3MB chunks
  const totalChunks = Math.ceil(file.size / chunkSize)
  let currentChunk = 0
  let totalUploaded = 0

  // Track the last reported progress to avoid too many updates
  let lastReportedProgress = 0

  // Create upload session first
  try {
    await createUploadSession(file, uploadId, totalChunks)
  } catch (error) {
    onError(error)
    return
  }

  // Process chunks
  const processChunk = async () => {
    if (currentChunk >= totalChunks) {
      // All chunks uploaded, finalize the upload
      try {
        const result = await finalizeUpload(uploadId, file.name, shareName, shareDescription, recipients)
        onComplete(result)
      } catch (error) {
        onError(error)
      }
      return
    }

    if (uploadController.pause) {
      //we're paused so hold fire for 1 second and try again
      setTimeout(processChunk, 1000)
      return
    }

    const start = currentChunk * chunkSize
    const end = Math.min(file.size, start + chunkSize)
    const chunk = file.slice(start, end)

    try {
      await uploadChunk(
        chunk,
        uploadId,
        currentChunk,
        totalChunks,
        file.name,
        // Progress handler for this specific chunk
        (chunkProgress) => {

          const overallBytesUploaded = currentChunk * chunkSize + chunkProgress.chunkLoaded
          const overallPercentage = Math.round((overallBytesUploaded / file.size) * 100)


          if (overallPercentage !== lastReportedProgress) {
            lastReportedProgress = overallPercentage

            onProgress({
              percentage: overallPercentage,
              uploadedBytes: overallBytesUploaded,
              totalBytes: file.size,
              currentChunk,
              totalChunks,
              chunkProgress: chunkProgress.chunkPercentage
            })
          }
        }
      )

      // Update the counter for the next chunk
      totalUploaded += chunk.size
      currentChunk++

      // Process next chunk
      processChunk()
    } catch (error) {
      // Retry logic could be implemented here
      onError(error)
    }
  }

  // Start processing chunks
  processChunk()
}

export {uploadFileInChunks}