import { fetchWithAuth } from './fetchWithAuth'
import { addJsonHeader } from './lib/addJsonHeader'
import { getApiUrl } from '../utils'
import { store } from '../store'

const apiUrl = getApiUrl()

const createShare = async (
  files,
  name,
  description,
  recipients,
  uploadId,
  expiryDate,
  password,
  passwordConfirm,
  onProgress
) => {
  const formData = new FormData()
  files.forEach((file) => {
    formData.append('files[]', file)
    formData.append('file_paths[]', file.fullPath)
  })
  formData.append('name', name)
  formData.append('description', description)
  formData.append('upload_id', uploadId)
  formData.append('expiry_date', expiryDate.toISOString())
  if (password) {
    formData.append('password', password)
  }
  if (passwordConfirm) {
    formData.append('password_confirm', passwordConfirm)
  }
  if (recipients.length > 0) {
    recipients.forEach((recipient, index) => {
      formData.append(`recipients[${index}][name]`, recipient.name)
      formData.append(`recipients[${index}][email]`, recipient.email)
    })
  }

  const xhr = new XMLHttpRequest()

  xhr.upload.onprogress = (event) => {
    if (event.lengthComputable) {
      const percentageComplete = Math.round((event.loaded * 100) / event.total)
      onProgress({
        percentage: percentageComplete,
        uploadedBytes: event.loaded,
        totalBytes: event.total
      })
    }
  }

  xhr.open('POST', `${apiUrl}/api/shares`, true)
  xhr.setRequestHeader('Accept', 'application/json')
  xhr.setRequestHeader('Authorization', `Bearer ${store.jwt}`)

  xhr.onload = () => {
    if (xhr.status === 200) {
      const response = JSON.parse(xhr.responseText)
    }
  }

  xhr.send(formData)

  return new Promise((resolve, reject) => {
    xhr.onload = () => {
      if (xhr.status === 200) {
        resolve(JSON.parse(xhr.responseText))
      } else {
        reject(new Error(xhr.responseText))
      }
    }
    xhr.onerror = () => reject(new Error('Network Error'))
  })
}

const getMyShares = async (showDeletedShares = false) => {
  const response = await fetchWithAuth(`${apiUrl}/api/shares?show_deleted=${showDeletedShares}`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.shares
}

const expireShare = async (id) => {
  const response = await fetchWithAuth(`${apiUrl}/api/shares/${id}/expire`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.share
}

const extendShare = async (id) => {
  const response = await fetchWithAuth(`${apiUrl}/api/shares/${id}/extend`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.share
}

const setDownloadLimit = async (id, amount) => {
  const response = await fetchWithAuth(`${apiUrl}/api/shares/${id}/set-download-limit`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    },
    body: JSON.stringify({
      amount
    })
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.share
}

const pruneExpiredShares = async () => {
  const response = await fetchWithAuth(`${apiUrl}/api/shares/prune-expired`, {
    method: 'POST',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.shares
}

const getShare = async (id) => {
  const response = await fetchWithAuth(`${apiUrl}/api/shares/${id}`, {
    method: 'GET',
    headers: {
      ...addJsonHeader()
    }
  })
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data.share
}

export { createShare, getMyShares, expireShare, extendShare, setDownloadLimit, pruneExpiredShares, getShare }
