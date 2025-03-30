<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import {
  CircleSlash2,
  FilePlus,
  FolderPlus,
  Upload,
  Trash,
  Copy,
  X,
  Loader,
  Check,
  Plus,
  Pause,
  Play,
  Boxes,
  Box,
  Clock9,
  Lock,
  LockOpen
} from 'lucide-vue-next'
import { niceFileSize, niceFileType, simpleUUID } from '../utils'
import { createShare, getHealth, getMyProfile, uploadFilesInChunks, logout } from '../api'
import Recipient from './recipient.vue'
import { uploadController } from '../store'
import { domData } from '../domData'
import { useTranslate } from '@tolgee/vue'
import { useToast } from 'vue-toastification'
import { store } from '../store'
import DirectoryItem from './directory-item.vue'
const { t } = useTranslate()
const toast = useToast()
const fileInput = ref(null)
const sharePanelVisible = ref(false)
const shareUrl = ref('')
const currentlyUploading = ref(false)
const uploadBasket = ref([])
const maxShareSize = ref(0)
const uploadProgress = ref(0)
const uploadedBytes = ref(0)
const totalBytes = ref(0)
const shareName = ref('')
const shareDescription = ref('')
const isPaused = ref(false)
const currentFileName = ref('')
const currentFileIndex = ref(0)
const totalFiles = ref(0)
const currentChunk = ref(0)
const totalChunks = ref(0)
const uploadSettings = ref({
  uploadMode: domData().default_upload_mode,
  allowDirectUploads: domData().allow_direct_uploads,
  allowChunkedUploads: domData().allow_chunked_uploads
})
const expiryValue = ref(domData().default_expiry_time)
const expiryUnit = ref('days')
const maxExpiryTime = ref(domData().max_expiry_time)
const errors = ref({
  shareName: null
})

const shareFormPassword = ref('')
const shareFormPasswordConfirm = ref('')

const sharePassword = ref('')
const sharePasswordConfirm = ref('')

const passwordFormErrors = ref({
  password: null,
  passwordConfirm: null
})

const recipients = ref([])

onMounted(async () => {
  //grab the max share size from the health check
  const health = await getHealth()
  maxShareSize.value = health.max_share_size

  //grab the upload mode from local storage
  const savedUploadMode = localStorage.getItem('uploadMode')
  if (savedUploadMode) {
    if (savedUploadMode === 'chunked') {
      if (uploadSettings.value.allowChunkedUploads) {
        uploadSettings.value.uploadMode = 'chunked'
      } else {
        uploadSettings.value.uploadMode = 'direct'
      }
    }

    if (savedUploadMode === 'direct') {
      if (uploadSettings.value.allowDirectUploads) {
        uploadSettings.value.uploadMode = 'direct'
      } else {
        uploadSettings.value.uploadMode = 'chunked'
      }
    }
  }
})

const showFilePicker = () => {
  fileInput.value.webkitdirectory = false
  fileInput.value.click()
}

const showFolderPicker = () => {
  fileInput.value.webkitdirectory = true
  fileInput.value.click()
}

const resetFileInput = () => {
  fileInput.value.value = null
}

const dropzone = ref(null)

const handleDrop = (e) => {
  e.preventDefault()
  e.stopPropagation()
  dropBoxRemoveActiveClass()

  const items = e.dataTransfer.items || []

  for (const item of items) {
    if (item.kind === 'file') {
      // For Chromium-based browsers
      const entry = item.webkitGetAsEntry?.()
      if (entry?.isDirectory) {
        console.log(`Got directory: ${entry.name}`)
        recurseDirectory(entry, entry.name)
        continue
      }

      const file = item.getAsFile()
      if (file) {
        pushFile(file, '')
      }
    }
  }
}

const handleFileSelect = (event) => {
  const files = event.target.files

  if (files.length > 0) {
    for (let i = 0; i < files.length; i++) {
      const file = files[i]

      // Check if it's a directory (webkitdirectory attribute adds a webkitRelativePath property)
      if (file.webkitRelativePath) {
        // Extract directory path from the webkitRelativePath
        const pathParts = file.webkitRelativePath.split('/')
        const directoryPath = pathParts.slice(0, -1).join('/')

        // Create file with path information
        const fileWithPath = new File([file], file.name, {
          type: file.type,
          lastModified: file.lastModified
        })

        fileWithPath.path = directoryPath
        fileWithPath.fullPath = file.webkitRelativePath
        pushFile(fileWithPath)
      } else {
        // Regular file
        pushFile(file)
      }
    }
  }

  resetFileInput()
}

const recurseDirectory = (entry, path) => {
  if (!entry || typeof entry.createReader !== 'function') {
    console.error('Invalid directory entry:', entry)
    return
  }

  console.log(`Recursing directory: ${path}`)
  const reader = entry.createReader()

  // Read entries in batches (the API might not return all entries at once)
  const readAllEntries = () => {
    reader.readEntries(
      (entries) => {
        if (entries.length === 0) {
          // No more entries, we're done with this directory
          return
        }

        for (const childEntry of entries) {
          if (childEntry.isDirectory) {
            recurseDirectory(childEntry, `${path}/${childEntry.name}`)
          } else if (childEntry.isFile) {
            childEntry.file(
              (file) => {
                // Create file with path information
                const fileWithPath = new File([file], file.name, {
                  type: file.type,
                  lastModified: file.lastModified
                })
                // Add path information
                fileWithPath.path = path
                fileWithPath.fullPath = `${path}/${file.name}`
                pushFile(fileWithPath)
              },
              (error) => {
                console.error('Error getting file:', error)
              }
            )
          }
        }

        // Continue reading more entries (the API might return entries in batches)
        readAllEntries()
      },
      (error) => {
        console.error('Error reading directory entries:', error)
      }
    )
  }

  readAllEntries()
}

const pushFile = (file) => {
  const ignore = ['.DS_Store', 'Thumbs.db', 'desktop.ini']

  if (ignore.includes(file.name)) {
    return
  }

  // Check if the file has path information
  const fileKey = file.fullPath || file.name

  // Check if the file is already in the upload basket using the full path if available
  if (!uploadBasket.value.some((item) => (item.fullPath || item.name) === fileKey)) {
    uploadBasket.value.push(file)

    // If the share name is empty, set it to the top-level directory name or file name
    if (shareName.value === '') {
      if (file.path) {
        // Use the top directory name
        const topDir = file.path.split('/')[0]
        shareName.value = topDir
      } else {
        shareName.value = file.name
      }
    }
  }
}

const removeFile = (file) => {
  uploadBasket.value = uploadBasket.value.filter((item) => item.fullPath !== file.fullPath)
}

const totalSize = computed(() => {
  return uploadBasket.value.reduce((acc, file) => acc + file.size, 0)
})

const uploadFiles = async () => {
  const uploadId = simpleUUID()
  currentlyUploading.value = true
  isPaused.value = false
  uploadController.resumeUpload()

  if (totalSize.value > maxShareSize.value) {
    alert(`Total size of files is greater than the max share size of ${niceFileSize(maxShareSize.value)}`)
    currentlyUploading.value = false
    return
  }

  //before we try uploading lets just check we're logged in still
  await getMyProfile()

  if (uploadSettings.value.uploadMode === 'chunked') {
    await doChunkedUpload(uploadId)
    return
  } else if (uploadSettings.value.uploadMode === 'direct') {
    await doDirectUpload(uploadId)
    return
  }

  alert(t.value('upload.upload_mode_not_supported'))
}

const calculateExpiryDate = () => {
  const now = new Date()
  const expiryDate = new Date(now)
  const currentTimestamp = now.getTime()
  const multiplier = multiplierFromUnit(expiryUnit.value)
  const expiryTimestamp = currentTimestamp + expiryValue.value * multiplier
  expiryDate.setTime(expiryTimestamp)
  return expiryDate
}

const multiplierFromUnit = (unit) => {
  const baseMultiplier = 1000 * 60 * 60 * 24 //1 day in milliseconds
  switch (unit) {
    case 'days':
      return 1 * baseMultiplier
    case 'weeks':
      return 7 * baseMultiplier
    case 'months':
      return 30 * baseMultiplier
    case 'years':
      return 365 * baseMultiplier
    default:
      throw new Error('Invalid expiry unit')
  }
}

const doChunkedUpload = async (uploadId) => {
  let pageTitleAtStart = document.title

  try {
    await uploadFilesInChunks(
      uploadBasket.value,
      uploadId,
      shareName.value,
      shareDescription.value,
      recipients.value,
      calculateExpiryDate(),
      sharePassword.value,
      sharePasswordConfirm.value,
      (progress) => {
        uploadProgress.value = progress.percentage
        uploadedBytes.value = progress.uploadedBytes
        totalBytes.value = progress.totalBytes

        if (progress.currentFileName) {
          currentFileName.value = progress.currentFileName
        }

        if (progress.currentFile && progress.totalFiles) {
          currentFileIndex.value = progress.currentFile
          totalFiles.value = progress.totalFiles
        }

        if (progress.currentChunk && progress.totalChunks) {
          currentChunk.value = progress.currentChunk
          totalChunks.value = progress.totalChunks
        }

        //set the page title to the progress
        document.title = `${Math.round(progress.percentage)}% - ${currentFileName.value}`
      },
      (result) => {
        document.title = pageTitleAtStart
        if (store.isGuest()) {
          thankGuestForUpload()
        } else {
          showSharePanel(createShareURL(result.data.share.long_id))
        }
        uploadBasket.value = []
        shareName.value = ''
        shareDescription.value = ''
        currentlyUploading.value = false
        resetUploadState()
      },
      (error) => {
        console.error('Upload error:', error)
        alert(`Upload failed: ${error.message}`)
        currentlyUploading.value = false
        resetUploadState()
      }
    )
  } catch (error) {
    console.error('Upload error:', error)
    alert(`Upload failed: ${error.message}`)
    currentlyUploading.value = false
    resetUploadState()
  }
}

const doDirectUpload = async (uploadId) => {
  let pageTitleAtStart = document.title
  try {
    const share = await createShare(
      uploadBasket.value,
      shareName.value,
      shareDescription.value,
      recipients.value,
      uploadId,
      calculateExpiryDate(),
      sharePassword.value,
      sharePasswordConfirm.value,
      (progress) => {
        uploadProgress.value = progress.percentage
        uploadedBytes.value = progress.uploadedBytes
        totalBytes.value = progress.totalBytes
        document.title = `${Math.round(progress.percentage)}%`
      }
    )
    document.title = pageTitleAtStart
    if (store.isGuest()) {
      thankGuestForUpload()
    } else {
      showSharePanel(createShareURL(share.data.share.long_id))
    }
    uploadBasket.value = []
    shareName.value = ''
    shareDescription.value = ''
  } catch (error) {
    console.error('Upload error:', error)
    alert(`Upload failed: ${error.message}`)
  } finally {
    currentlyUploading.value = false
    setTimeout(() => {
      uploadProgress.value = 0
      uploadedBytes.value = 0
      totalBytes.value = 0
    }, 1000)
  }
}

const resetUploadState = () => {
  setTimeout(() => {
    uploadProgress.value = 0
    uploadedBytes.value = 0
    totalBytes.value = 0
    currentFileName.value = ''
    currentFileIndex.value = 0
    totalFiles.value = 0
    currentChunk.value = 0
    totalChunks.value = 0
  }, 1000)
}

const createShareURL = (longId) => {
  const currentUrl = window.location.href
  const baseUrl = currentUrl.split('/').slice(0, -1).join('/')
  return `${baseUrl}/shares/${longId}`
}

const thankGuestForUpload = () => {
  logout()
  store.setMode('thank_guest_for_upload')
}

const showSharePanel = (url) => {
  sharePanelVisible.value = true
  shareUrl.value = url
}

const showCopySuccess = ref(false)

const copyShareUrl = () => {
  navigator.clipboard.writeText(shareUrl.value)
  showCopySuccess.value = true
  setTimeout(() => {
    showCopySuccess.value = false
  }, 1000)
}

const removeRecipient = (recipient) => {
  console.log('remove recipient', recipient)
  recipients.value = recipients.value.filter((item) => item.email !== recipient.email)
}

const addRecipient = () => {
  const recipient = {
    email: null,
    name: null,
    showPopover: true
  }
  recipients.value.push(recipient)
}

const togglePause = () => {
  isPaused.value = !isPaused.value
  console.log(isPaused.value ? 'Upload paused' : 'Upload resumed')
  if (isPaused.value) {
    uploadController.pauseUpload()
  } else {
    uploadController.resumeUpload()
  }
}

const swapUploadMode = () => {
  uploadSettings.value.uploadMode = uploadSettings.value.uploadMode === 'chunked' ? 'direct' : 'chunked'
  localStorage.setItem('uploadMode', uploadSettings.value.uploadMode)
  toast.success(t.value('uploader.upload_mode_swapped', { value: uploadSettings.value.uploadMode }))
}

const showExpirySettings = ref(false)
const toggleExpirySettings = () => {
  showExpirySettings.value = !showExpirySettings.value
}

const expiryValueWatchEnabled = ref(true)
watch(expiryValue, () => {
  if (expiryValueWatchEnabled.value) {
    checkExpiryValue()
  }
})

const timeUnitConversions = {
  days: 1,
  weeks: 7,
  months: 30,
  years: 365
}

// Single watch function with cleaner conversion logic
watch(expiryUnit, (newUnit, oldUnit) => {
  // Skip if units are the same
  if (newUnit === oldUnit) return

  // Get the conversion factor between old and new units
  const oldUnitValue = timeUnitConversions[oldUnit]
  const newUnitValue = timeUnitConversions[newUnit]
  const conversionFactor = oldUnitValue / newUnitValue

  // Apply conversion with a minimum value of 1
  const newValue = Math.round(expiryValue.value * conversionFactor)
  expiryValue.value = Math.max(1, newValue)

  // Check if the new value exceeds maximum allowed time
  checkExpiryValue(false)
})

const checkExpiryValue = (showError = true) => {
  if (maxExpiryTime.value == null) {
    return
  }
  const currentTimestamp = new Date().getTime()
  const selectedUnitMultiplier = multiplierFromUnit(expiryUnit.value)
  const expiryTimestamp = currentTimestamp + expiryValue.value * selectedUnitMultiplier

  const maxExpiryTimeStamp = currentTimestamp + maxExpiryTime.value * 1000 * 60 * 60 * 24 //days in milliseconds (this setting is always in days)
  if (expiryTimestamp > maxExpiryTimeStamp) {
    if (showError) {
      toast.error(t.value('uploader.expiry_too_long', { value: maxExpiryTimeInSelectedUnit.value }))
    }
    console.log('max expiry time in selected unit', maxExpiryTimeInSelectedUnit.value)
    setNewExpiryValueWithoutLoop(maxExpiryTimeInSelectedUnit.value)
  }
}

const maxExpiryTimeInSelectedUnit = computed(() => {
  if (maxExpiryTime.value == null) {
    return null
  }
  const maxExpiryTimeInMilliseconds = maxExpiryTime.value * 1000 * 60 * 60 * 24
  const maxExpiryTimeInSelectedUnit = maxExpiryTimeInMilliseconds / multiplierFromUnit(expiryUnit.value)
  return maxExpiryTimeInSelectedUnit
})

const RoundedMaxExpiryTimeInSelectedUnit = computed(() => {
  return Math.max(1, Math.floor(maxExpiryTimeInSelectedUnit.value))
})

const setNewExpiryValueWithoutLoop = (value) => {
  expiryValueWatchEnabled.value = false
  nextTick(() => {
    expiryValue.value = Math.floor(value)
    expiryValueWatchEnabled.value = true
  })
}

const canExpireInWeeks = computed(() => {
  if (maxExpiryTime.value == null) {
    return true
  }
  return maxExpiryTime.value >= 7
})

const canExpireInMonths = computed(() => {
  if (maxExpiryTime.value == null) {
    return true
  }
  return maxExpiryTime.value >= 30
})

const canExpireInYears = computed(() => {
  if (maxExpiryTime.value == null) {
    return true
  }
  return maxExpiryTime.value >= 365
})

const showPasswordForm = ref(false)
const passwordFormClickOutside = (e) => {
  if (!e.target.closest('.user-form')) {
    showPasswordForm.value = false
  }
}

const passwordProtected = computed(() => {
  return (
    sharePassword.value.length > 0 &&
    sharePasswordConfirm.value.length > 0 &&
    sharePassword.value === sharePasswordConfirm.value
  )
})

const setPassword = () => {
  passwordFormErrors.value.password = null
  passwordFormErrors.value.passwordConfirm = null

  if (shareFormPassword.value.length === 0) {
    passwordFormErrors.value.password = t.value('uploader.password_required')
    return
  }

  if (shareFormPasswordConfirm.value.length === 0) {
    passwordFormErrors.value.passwordConfirm = t.value('uploader.password_confirmation_required')
    return
  }

  if (shareFormPassword.value !== shareFormPasswordConfirm.value) {
    passwordFormErrors.value.passwordConfirm = t.value('uploader.password_mismatch')
    return
  }

  sharePassword.value = shareFormPassword.value
  sharePasswordConfirm.value = shareFormPasswordConfirm.value
  showPasswordForm.value = false
}

const removePassword = () => {
  sharePassword.value = ''
  sharePasswordConfirm.value = ''
  passwordFormErrors.value.password = null
  passwordFormErrors.value.passwordConfirm = null
  shareFormPassword.value = ''
  shareFormPasswordConfirm.value = ''
  showPasswordForm.value = false
}

const passwordInput = ref(null)
watch(showPasswordForm, (newVal) => {
  if (newVal) {
    passwordInput.value.focus()
  }
})

const handleDragOver = (e) => {
  e.preventDefault()
  e.stopPropagation()
  dropBoxAddActiveClass()
}

const handleDragLeave = (e) => {
  e.preventDefault()
  e.stopPropagation()
  dropBoxRemoveActiveClass(e)
}

const dropBoxAddActiveClass = () => {
  dropzone.value.classList.add('active')
}

const dropBoxRemoveActiveClass = (e) => {
  if (e == null) {
    dropzone.value.classList.remove('active')
  } else if (!dropzone.value.contains(e.relatedTarget)) {
    dropzone.value.classList.remove('active')
  }
}

const handleDropzoneClick = (e) => {
  console.log(e)
  if (e.target === dropzone.value) {
    showFilePicker()
  }
}

// Add this computed property to organize files by directory
const filesByDirectory = computed(() => {
  const structure = {}

  uploadBasket.value.forEach((file) => {
    const path = file.path || ''
    const dirs = path ? path.split('/') : ['']

    // Create nested structure
    let current = structure
    for (const dir of dirs) {
      if (dir) {
        if (!current[dir]) {
          current[dir] = { files: [], directories: {} }
        }
        current = current[dir].directories
      }
    }

    // Add file to its directory
    if (path) {
      const parentDir = dirs.reduce((acc, dir, index) => {
        if (index < dirs.length - 1 && dir) {
          return acc[dir].directories
        }
        return acc
      }, structure)

      const lastDir = dirs[dirs.length - 1]
      if (lastDir) {
        parentDir[lastDir].files.push(file)
      }
    } else {
      // Root files
      if (!structure.files) {
        structure.files = []
      }
      structure.files.push(file)
    }
  })

  return structure
})
</script>

<template>
  <div class="upload-form">
    <div class="buttons">
      <button class="upload-files block text-large" @click="showFilePicker">
        <FilePlus />
        {{ $t('Add Files') }}
      </button>
      <button class="upload-folder block text-large secondary" @click="showFolderPicker">
        <FolderPlus />
        {{ $t('Add Folders') }}
      </button>
    </div>
    <div class="max-size-label">{{ niceFileSize(totalSize) }} / {{ niceFileSize(maxShareSize) }}</div>

    <div>
      <div class="progress-bar-container" :class="{ visible: currentlyUploading }">
        <div class="progress-bar">
          <div class="progress-bar-fill" :style="{ width: `${uploadProgress}%` }"></div>
        </div>
        <div class="pause-button" @click="togglePause" v-if="uploadSettings.uploadMode === 'chunked'">
          <Pause v-if="!isPaused" />
          <Play v-else />
        </div>
        <div class="progress-bar-text">
          <template v-if="uploadProgress < 100">
            {{ Math.round(uploadProgress) }}%
            <div class="progress-bar-text-sub">
              {{ niceFileSize(uploadedBytes) }} /
              {{ niceFileSize(totalBytes) }}
            </div>
            <div v-if="currentFileName" class="progress-bar-text-sub">
              {{ $t('File') }}: {{ currentFileIndex }} / {{ totalFiles }} - {{ currentFileName }}
            </div>
            <div v-if="totalChunks > 0" class="progress-bar-text-sub">
              {{ $t('Chunk') }}: {{ currentChunk }} / {{ totalChunks }}
            </div>
          </template>
          <template v-else>
            {{ $t('Processing uploaded files') }}
            <div class="progress-bar-text-sub">
              {{ niceFileSize(uploadedBytes) }} /
              {{ niceFileSize(totalBytes) }}
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
  <div class="expiry-settings-container">
    <span class="expiry-label" @click="toggleExpirySettings">
      <Clock9 />
      {{ $t('uploader.expiry_label', { value: expiryValue, unit: t('uploader.expiry_unit.' + expiryUnit) }) }}
    </span>
    <div class="expiry-settings" :class="{ visible: showExpirySettings }">
      <input type="number" v-model="expiryValue" />
      <span class="maxValueOverlay" v-if="maxExpiryTimeInSelectedUnit != null">
        {{ t('uploader.expiry_max_value', { value: RoundedMaxExpiryTimeInSelectedUnit }) }}
      </span>
      <select v-model="expiryUnit">
        <option value="days">{{ $t('uploader.expiry_unit.days') }}</option>
        <option value="weeks" v-if="canExpireInWeeks">{{ $t('uploader.expiry_unit.weeks') }}</option>
        <option value="months" v-if="canExpireInMonths">{{ $t('uploader.expiry_unit.months') }}</option>
        <option value="years" v-if="canExpireInYears">{{ $t('uploader.expiry_unit.years') }}</option>
      </select>
    </div>
  </div>
  <div class="upload-basket-details pt-2">
    <div class="recipients" v-if="!store.isGuest()">
      <div class="button-outside-label uploader-add-recipient">
        <button class="icon-only round" @click="addRecipient">
          <Plus />
        </button>
        <div class="button-outside-label-text">{{ $t('Add Recipient') }}</div>
      </div>
      <div class="recipient-list">
        <template v-if="recipients.length > 0">
          <Recipient
            v-for="recipient in recipients"
            :key="recipient.id"
            :recipient="recipient"
            @remove="removeRecipient"
            :ref="`recipient-${recipient.email}`"
          />
        </template>
        <template v-else>
          <div class="recipient-list-empty">
            {{ $t('No recipients') }}
            <br />
            <small>{{ $t("We'll provide you with a link instead.") }}</small>
          </div>
        </template>
      </div>
    </div>

    <div class="input-container mb-0">
      <input
        type="text"
        v-model="shareName"
        :placeholder="$t('Share Name')"
        required
        :class="{ error: errors.shareName }"
        class="mb-0"
        id="share-name"
        name="share-name"
        autocomplete="share-name"
        v-if="!store.isGuest()"
      />
      <div class="error-message" v-if="errors.shareName">
        {{ errors.shareName }}
      </div>
    </div>

    <div class="input-container mt-0 mb-0">
      <textarea
        v-model="shareDescription"
        :placeholder="$t('Message to share recipients (optional)')"
        required
        :class="{ error: errors.shareDescription }"
        rows="1"
        class="mt-0 mb-0"
        v-if="!store.isGuest()"
      />
      <div class="error-message" v-if="errors.shareDescription">
        {{ errors.shareDescription }}
      </div>
    </div>
  </div>
  <div class="upload-basket">
    <div
      class="basket-items dropzone"
      @drop="handleDrop"
      @dragenter="handleDragOver"
      @dragover.prevent.stop
      @dragleave="handleDragLeave($event)"
      @click="handleDropzoneClick"
      ref="dropzone"
    >
      <template v-if="uploadBasket.length > 0">
        <directory-item :structure="filesByDirectory" :is-root="true" @remove-file="removeFile" />
      </template>

      <div class="upload-basket-empty" v-else>
        <div class="upload-basket-empty-text">
          <CircleSlash2 />
          {{ $t('No files added yet') }}
        </div>
      </div>
    </div>
  </div>

  <div class="upload-button-container">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col d-flex align-items-center justify-content-end">
          <button
            class="upload-button block"
            :disabled="uploadBasket.length === 0 || currentlyUploading"
            @click="uploadFiles"
            :class="{ uploading: currentlyUploading }"
          >
            <div class="loader" v-if="currentlyUploading">
              <Loader />
            </div>
            <Upload v-else />
            <template v-if="uploadBasket.length > 0 && currentlyUploading">
              {{ $t('uploading.files', 'Uploading {value} files', { value: uploadBasket.length }) }}
            </template>
            <template v-if="uploadBasket.length > 0 && !currentlyUploading">
              {{ $t('upload.files', 'Upload {value} files', { value: uploadBasket.length }) }}
            </template>
            <template v-if="uploadBasket.length === 0">{{ $t('No files added yet') }}</template>
          </button>
        </div>

        <div class="col-auto" v-if="uploadSettings.allowChunkedUploads && uploadSettings.allowDirectUploads">
          <div class="upload-modes">
            <div class="upload-mode" v-if="uploadSettings.uploadMode === 'chunked'">
              <button class="icon-only secondary" :title="$t('uploader.current_mode.chunked')" @click="swapUploadMode">
                <Boxes />
              </button>
            </div>
            <div class="upload-mode" v-if="uploadSettings.uploadMode === 'direct'">
              <button class="icon-only secondary" :title="$t('uploader.current_mode.direct')" @click="swapUploadMode">
                <Box />
              </button>
            </div>
          </div>
        </div>
        <div class="col-auto" v-else>
          <div class="upload-mode">
            <div
              class="svg-container"
              :title="$t('uploader.current_mode.chunked')"
              v-if="uploadSettings.uploadMode === 'chunked'"
            >
              <Boxes />
            </div>
            <div class="svg-container" :title="$t('uploader.current_mode.direct')" v-else>
              <Box />
            </div>
          </div>
        </div>

        <div class="ps-0 col-auto">
          <button class="icon-only secondary" @click="showPasswordForm = !showPasswordForm">
            <Lock v-if="passwordProtected" />
            <LockOpen v-else />
          </button>
        </div>
      </div>
    </div>
  </div>

  <input
    type="file"
    @change="handleFileSelect"
    style="display: none"
    webkitdirectory
    directory
    ref="fileInput"
    multiple
  />
  <div class="sharePanel" :class="{ visible: sharePanelVisible }">
    <div class="sharePanel-content">
      <div class="sharePanel-close" @click="sharePanelVisible = false">
        <X />
      </div>
      <div class="sharePanel-title">{{ $t('Share URL') }}</div>
      <div class="sharePanel-url">
        {{ shareUrl }}
        <button class="sharePanel-copy-button icon-only" @click="copyShareUrl">
          <Check v-if="showCopySuccess" />
          <Copy v-else />
        </button>
      </div>
    </div>
  </div>

  <div class="user-form-overlay" :class="{ active: showPasswordForm }" @click="passwordFormClickOutside">
    <div class="user-form">
      <h2>
        <Lock />
        {{ $t('settings.title.share.password_protect') }}
      </h2>
      <p>{{ $t('settings.share.password_protect_description') }}</p>
      <div class="input-container">
        <label for="edit_share_password">{{ $t('settings.share.password') }}</label>
        <input
          type="password"
          v-model="shareFormPassword"
          id="edit_share_password"
          :placeholder="$t('settings.share.password')"
          required
          :class="{ error: passwordFormErrors.password }"
          @keyup.enter="setPassword"
          ref="passwordInput"
        />
        <div class="error-message" v-if="passwordFormErrors.password">
          {{ passwordFormErrors.password }}
        </div>
      </div>

      <div class="input-container">
        <label for="edit_share_password_confirm">{{ $t('settings.share.password_confirm') }}</label>
        <input
          type="password"
          v-model="shareFormPasswordConfirm"
          id="edit_share_password_confirm"
          :placeholder="$t('settings.share.password_confirm')"
          required
          :class="{ error: passwordFormErrors.passwordConfirm }"
          @keyup.enter="setPassword"
        />

        <div class="error-message" v-if="passwordFormErrors.passwordConfirm">
          {{ passwordFormErrors.passwordConfirm }}
        </div>
      </div>

      <div class="button-bar">
        <button @click="setPassword">
          <Lock />
          {{ $t('button.share.password_protect') }}
        </button>
        <button class="secondary close-button" @click="removePassword">
          <LockOpen />
          {{ $t('settings.share.remove_password') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">

@mixin blur-background($blur-amount) {
  backdrop-filter: blur($blur-amount);
}

@mixin flex-row {
  display: flex;
  flex-direction: row;
  align-items: center;
}

@mixin flex-column {
  display: flex;
  flex-direction: column;
  align-items: center;
}


.user-form-overlay {
  border-radius: var(--panel-border-radius) var(--panel-border-radius) 0 0;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: var(--overlay-background-color);
  @include blur-background(10px);
  z-index: 230;
  opacity: 0;
  pointer-events: none;
  transition: all 0.3s ease;

  h2 {
    margin-bottom: 10px;
    font-size: 24px;
    color: var(--panel-text-color);
    display: flex;
    align-items: center;
    justify-content: center;

    svg {
      width: 24px;
      height: 24px;
      margin-right: 10px;
    }
  }

  .user-form {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translate(-50%, 100%);
    width: 500px;
    background: var(--panel-background-color);
    color: var(--panel-text-color);
    padding: 20px;
    border-radius: var(--panel-border-radius) var(--panel-border-radius) 0 0;
    box-shadow: 0 0 100px 0 rgba(0, 0, 0, 0.5);
    @include flex-column;
    align-items: flex-start;
    justify-content: flex-start;
    gap: 10px;
    transition: all 0.3s ease;

    button {
      display: block;
      width: 100%;
    }
  }

  &.active {
    opacity: 1;
    pointer-events: auto;

    .user-form {
      transform: translate(-50%, 0%);
    }
  }
}
</style>
