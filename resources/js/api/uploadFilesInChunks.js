import { fetchWithAuth } from './fetchWithAuth'
import { store } from '../store'
import { getApiUrl } from '../utils'
import { uploadFileInChunks } from './uploadFileInChunks'
const apiUrl = getApiUrl()

/**
 * Calculates the overall progress for multi-file uploads
 */
const calculateOverallProgress = (uploadedSize, fileTotalUploaded, totalSize, fileIndex, totalFiles, fileName) => {
  const overallPercentage = Math.round(((uploadedSize + fileTotalUploaded) / totalSize) * 100)
  
  return {
    percentage: overallPercentage,
    uploadedBytes: uploadedSize + fileTotalUploaded,
    totalBytes: totalSize,
    currentFile: fileIndex + 1,
    totalFiles,
    currentFileName: fileName
  }
}

/**
 * Creates a share from uploaded file chunks
 */
const createShareFromChunks = async (
  uploadId, 
  shareName, 
  shareDescription, 
  recipients, 
  results, 
  expiryDate, 
  password, 
  passwordConfirm
) => {
  const filePaths = {}
  results.forEach((r) => {
    filePaths[r.data.file.id] = r.fullPath
  })

  const response = await fetchWithAuth(`${apiUrl}/api/uploads/create-share-from-chunks`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${store.jwt}`
    },
    body: JSON.stringify({
      upload_id: uploadId,
      name: shareName,
      description: shareDescription,
      recipients,
      fileInfo: results.map((r) => r.data.file.id),
      filePaths,
      expiry_date: expiryDate,
      password,
      password_confirm: passwordConfirm
    })
  })

  if (!response.ok) {
    const data = await response.json()
    throw new Error(data.message || 'Failed to create share from chunks')
  }

  return await response.json()
}

/**
 * Uploads a single file with progress tracking
 */
const uploadSingleFile = async (
  file,
  fileUploadId,
  shareName,
  shareDescription,
  recipients,
  uploadedSize,
  totalSize,
  fileIndex,
  totalFiles,
  onProgress
) => {
  return new Promise((resolve, reject) => {
    uploadFileInChunks(
      file,
      fileUploadId,
      shareName,
      shareDescription,
      recipients,
      (progress) => {
        // Calculate file progress within overall progress
        const fileTotalUploaded = (progress.percentage / 100) * file.size
        const overallProgress = calculateOverallProgress(
          uploadedSize, 
          fileTotalUploaded, 
          totalSize, 
          fileIndex, 
          totalFiles, 
          file.name
        )
        onProgress(overallProgress)
      },
      (result) => {
        result.fullPath = file.fullPath
        resolve(result)
      },
      (error) => {
        reject(error)
      }
    )
  })
}

/**
 * Uploads multiple files in chunks
 * This is a wrapper for uploadFileInChunks that handles multiple files
 */
const uploadFilesInChunks = async (
  files,
  uploadId,
  shareName,
  shareDescription,
  recipients,
  expiryDate,
  password,
  passwordConfirm,
  onProgress,
  onComplete,
  onError
) => {
  const totalSize = files.reduce((total, file) => total + file.size, 0)
  let uploadedSize = 0
  const results = []

  try {
    // Process each file sequentially
    for (let i = 0; i < files.length; i++) {
      const file = files[i]
      const fileUploadId = `${uploadId}_file${i}`

      try {
        const result = await uploadSingleFile(
          file,
          fileUploadId,
          shareName,
          shareDescription,
          recipients,
          uploadedSize,
          totalSize,
          i,
          files.length,
          onProgress
        )
        
        results.push(result)
        uploadedSize += file.size
      } catch (error) {
        onError(error)
        // Continue with next file even if this one fails
      }
    }

    // All files have been uploaded, now create the share
    const shareData = await createShareFromChunks(
      uploadId,
      shareName,
      shareDescription,
      recipients,
      results,
      expiryDate,
      password,
      passwordConfirm
    )
    
    onComplete(shareData)
  } catch (error) {
    onError(error)
  }
}

export { uploadFilesInChunks }