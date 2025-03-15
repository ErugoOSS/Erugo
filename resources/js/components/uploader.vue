<script setup>
import { ref, computed, onMounted } from 'vue'
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
  Box
} from 'lucide-vue-next'
import { niceFileSize, niceFileType, simpleUUID } from '../utils'
import { createShare, getHealth, getMyProfile, uploadFilesInChunks } from '../api'
import Recipient from './recipient.vue'
import { uploadController } from '../store'
import { domData } from '../domData'
import { useTranslate } from '@tolgee/vue'
import { useToast } from 'vue-toastification'

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

const errors = ref({
  shareName: null
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

const pushFile = (file) => {
  //check if the file is already in the upload basket
  if (!uploadBasket.value.some((item) => item.name === file.name)) {
    uploadBasket.value.push(file)
    //if the share name is empty, set it to the file name
    if (shareName.value === '') {
      shareName.value = file.name
    }
  }
}

const handleFileSelect = (event) => {
  if (event.target.files.length > 1) {
    for (let i = 0; i < event.target.files.length; i++) {
      pushFile(event.target.files[i])
    }
  }
  if (event.target.files.length === 1) {
    pushFile(event.target.files[0])
  }
  resetFileInput()
}

const removeFile = (file) => {
  uploadBasket.value = uploadBasket.value.filter((item) => item.name !== file.name)
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
  const user = await getMyProfile()

  if (uploadSettings.value.uploadMode === 'chunked') {
    await doChunkedUpload(uploadId)
    return
  } else if (uploadSettings.value.uploadMode === 'direct') {
    await doDirectUpload(uploadId)
    return
  }

  alert(t.value('upload.upload_mode_not_supported'))
}

const doChunkedUpload = async (uploadId) => {
  try {
    await uploadFilesInChunks(
      uploadBasket.value,
      uploadId,
      shareName.value,
      shareDescription.value,
      recipients.value,
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
      },
      (result) => {
        showSharePanel(createShareURL(result.data.share.long_id))
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
  try {
    const share = await createShare(
      uploadBasket.value,
      shareName.value,
      shareDescription.value,
      recipients.value,
      uploadId,
      (progress) => {
        uploadProgress.value = progress.percentage
        uploadedBytes.value = progress.uploadedBytes
        totalBytes.value = progress.totalBytes
      }
    )

    showSharePanel(createShareURL(share.data.share.long_id))
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
        <div class="pause-button" @click="togglePause">
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

  <div class="upload-basket">
    <div class="basket-items">
      <div class="upload-basket-item" v-for="file in uploadBasket" :key="file.name" v-if="uploadBasket.length > 0">
        <div class="name">
          {{ file.name }}
        </div>
        <div class="meta">
          <div class="size">
            {{ niceFileSize(file.size) }}
          </div>
          <div class="type">
            {{ niceFileType(file.type) }}
          </div>
        </div>
        <div class="hover-actions">
          <button class="icon-only" @click="removeFile(file)">
            <Trash />
          </button>
        </div>
      </div>

      <div class="upload-basket-empty" v-else>
        <div class="upload-basket-empty-text">
          <CircleSlash2 />
          {{ $t('No files added yet') }}
        </div>
      </div>
    </div>

    <div class="upload-basket-details">
      <div class="recipients">
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
          rows="3"
          class="mt-0 mb-0"
        />
        <div class="error-message" v-if="errors.shareDescription">
          {{ errors.shareDescription }}
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
</template>

<style scoped lang="scss">
.progress-bar-container {
  margin-top: -20px;
  background: var(--panel-item-background-color);
  border-radius: 5px;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: flex-start;
  position: absolute;
  opacity: 0;
  transition: all 0.3s ease-in-out;
  left: 0;
  right: 0;
  top: 20px;
  bottom: 0;
  z-index: 1000;
  pointer-events: none;

  &.visible {
    opacity: 1;
    pointer-events: auto;
  }

  .progress-bar {
    height: 100%;
    width: 100%;
    background: var(--progress-bar-background-color);
    .progress-bar-fill {
      background: var(--progress-bar-fill-color);
      border-radius: 5px;
      transition: all 0.1s linear;
      height: 100%;
    }
  }
  .pause-button {
    position: absolute;
    right: 10px;
    top: 10px;
    cursor: pointer;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.1);
    border-radius: 50%;
    &:hover {
      background: rgba(0, 0, 0, 0.2);
    }
    svg {
      width: 16px;
      height: 16px;
    }
  }
  .progress-bar-text {
    font-size: 24px;
    color: var(--panel-text-color);
    font-weight: 600;
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    flex-direction: column;
    background: var(--panel-background-color);
    width: auto;
    padding: 10px;
    border-radius: 5px;
    .progress-bar-text-sub {
      font-size: 10px;
      color: var(--panel-text-color);
      opacity: 0.8;
      font-weight: 400;
    }
  }
}
.recipients {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: flex-start;
  gap: 10px;
  background: var(--input-background-color);
  border-radius: 5px;
  padding: 10px;
  margin-bottom: 10px;
  margin-left: 10px;
  margin-right: 10px;

  .recipient-list {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: 10px;
    flex-grow: 1;
    min-height: 30px;
  }

  .recipient-list-empty {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 0.7rem;
    color: var(--panel-text-color);
    width: 100%;
    flex-grow: 1;
    font-style: italic;
    svg {
      width: 13px;
      height: 13px;
    }
  }
}
</style>
