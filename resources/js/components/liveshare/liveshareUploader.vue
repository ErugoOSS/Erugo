<script setup>
import { ref, computed } from 'vue'
import { uploadFileWithTus, addLiveshareFiles } from '../../api'
import { Plus, Loader2 } from 'lucide-vue-next'
import { useToast } from 'vue-toastification'

const props = defineProps({
  liveshareLongId: { type: String, required: true }
})

const emit = defineEmits(['filesAdded'])

const toast = useToast()
const fileInput = ref(null)
const uploading = ref(false)
const uploadProgress = ref(0)
const uploadStatus = ref('')
const currentFileName = ref('')
const currentFileIndex = ref(0)
const totalFiles = ref(0)

// SVG circle progress: circumference of r=24 circle
const circumference = 2 * Math.PI * 24
const progressOffset = computed(() => {
  return circumference - (uploadProgress.value / 100) * circumference
})

const triggerFileSelect = () => {
  fileInput.value.click()
}

const handleFilesSelected = async (event) => {
  const files = Array.from(event.target.files)
  if (files.length === 0) return

  uploading.value = true
  uploadProgress.value = 0
  totalFiles.value = files.length
  currentFileIndex.value = 0

  const uploadIds = []

  try {
    // Upload each file via TUS
    for (let i = 0; i < files.length; i++) {
      currentFileIndex.value = i + 1
      currentFileName.value = files[i].name
      uploadStatus.value = `Uploading ${i + 1} of ${files.length}...`

      const result = await new Promise((resolve, reject) => {
        uploadFileWithTus(
          files[i],
          (progress) => {
            // Calculate overall progress
            const fileProgress = progress.percentage / 100
            const overallProgress = ((i + fileProgress) / files.length) * 100
            uploadProgress.value = Math.round(overallProgress)
          },
          (result) => {
            resolve(result)
          },
          (error) => {
            reject(error)
          }
        )
      })

      uploadIds.push(result.uploadId)
    }

    // Now add the uploaded files to the liveshare
    uploadStatus.value = 'Adding files to liveshare...'
    uploadProgress.value = 100

    // Retry logic for race condition with tusd hooks
    const maxRetries = 5
    const baseDelayMs = 500

    for (let attempt = 0; attempt < maxRetries; attempt++) {
      try {
        await addLiveshareFiles(props.liveshareLongId, uploadIds)
        toast.success(`${files.length} file(s) added`)
        emit('filesAdded')
        break
      } catch (error) {
        if (error.message && error.message.includes('not found or not completed') && attempt < maxRetries - 1) {
          const delayMs = baseDelayMs * Math.pow(2, attempt)
          await new Promise((resolve) => setTimeout(resolve, delayMs))
          continue
        }
        throw error
      }
    }
  } catch (error) {
    toast.error(error.message || 'Failed to upload files')
  }

  uploading.value = false
  uploadProgress.value = 0
  uploadStatus.value = ''
  currentFileName.value = ''

  // Reset file input
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}
</script>

<template>
  <div class="liveshare-uploader">
    <input
      type="file"
      ref="fileInput"
      multiple
      @change="handleFilesSelected"
      style="display: none"
    />

    <!-- FAB button -->
    <button
      v-if="!uploading"
      class="fab-button"
      @click="triggerFileSelect"
      title="Upload files"
    >
      <Plus />
    </button>

    <!-- Floating progress indicator -->
    <div class="fab-progress" v-else>
      <div class="fab-progress-ring">
        <svg viewBox="0 0 56 56">
          <circle class="ring-bg" cx="28" cy="28" r="24" />
          <circle
            class="ring-fill"
            cx="28" cy="28" r="24"
            :style="{ strokeDashoffset: progressOffset }"
          />
        </svg>
        <span class="fab-progress-text">{{ uploadProgress }}%</span>
      </div>
      <div class="fab-progress-label">
        <span class="fab-progress-status">{{ uploadStatus }}</span>
        <span class="fab-progress-file" v-if="currentFileName">{{ currentFileName }}</span>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.liveshare-uploader {
  display: contents; // let parent flex handle children directly
}

.fab-button {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  margin: 0;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;

  svg {
    width: 24px;
    height: 24px;
    margin: 0;
  }

  &:hover {
    transform: scale(1.08);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
  }

  &:active {
    transform: scale(0.96);
  }
}

.fab-progress {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--panel-background-color);
  border-radius: 28px;
  padding: 6px 16px 6px 6px;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
}

.fab-progress-ring {
  position: relative;
  width: 44px;
  height: 44px;
  flex-shrink: 0;

  svg {
    width: 44px;
    height: 44px;
    transform: rotate(-90deg);
  }

  circle {
    fill: none;
    stroke-width: 4;
  }

  // stroke needs a solid colour; the themed surface variables are all gradients.
  .ring-bg {
    stroke: var(--input-border-color);
  }

  .ring-fill {
    stroke: var(--link-color);
    stroke-dasharray: 150.796; // 2 * PI * 24
    stroke-linecap: round;
    transition: stroke-dashoffset 0.3s ease;
  }
}

.fab-progress-text {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.6rem;
  font-weight: 600;
  color: var(--panel-section-text-color);
}

.fab-progress-label {
  display: flex;
  flex-direction: column;
  min-width: 0;
  max-width: 160px;
}

.fab-progress-status {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--panel-section-text-color);
  white-space: nowrap;
}

.fab-progress-file {
  font-size: 0.65rem;
  color: var(--panel-section-text-color);
  opacity: 0.5;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
