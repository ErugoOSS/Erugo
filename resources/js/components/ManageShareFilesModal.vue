<script setup>
import { ref, watch, computed } from 'vue'
import { uploadFileWithTus } from '../api'
import { addFilesToShare, replaceShareFile } from '../api'
import { useToast } from 'vue-toastification'
import { FilePlus2, RefreshCcw, X, Upload } from 'lucide-vue-next'
import { niceFileSize } from '../utils'

const props = defineProps({
  share: { type: Object, required: true },
  mode: { type: String, required: true }  // 'add' or 'replace'
})
const emit = defineEmits(['close', 'done'])

const toast = useToast()

const selectedFiles = ref([])   // Array of { file: File, path: string }
const uploading = ref(false)
const progress = ref(0)         // 0-100 overall percentage
const currentFileName = ref('')
const fileIndex = ref(0)
const error = ref(null)

// Replace-mode rename state
const keepOriginalName = ref(false)
const shareName = ref(props.share.name)

// Strip file extension to use as a share name suggestion
const nameWithoutExtension = (filename) => filename.replace(/\.[^.]+$/, '')

const canSubmit = computed(() => selectedFiles.value.length > 0 && !uploading.value)

const handleFileInput = (e) => {
  const raw = Array.from(e.target.files || [])
  selectedFiles.value = raw.map((f) => ({
    file: f,
    path: f.webkitRelativePath || f.name
  }))
  if (props.mode === 'replace' && raw.length > 0 && !keepOriginalName.value) {
    shareName.value = nameWithoutExtension(raw[0].name)
  }
  e.target.value = ''
}

watch(keepOriginalName, (keep) => {
  if (keep) {
    shareName.value = props.share.name
  } else if (selectedFiles.value.length > 0) {
    shareName.value = nameWithoutExtension(selectedFiles.value[0].file.name)
  }
})

const removeFile = (index) => {
  selectedFiles.value.splice(index, 1)
  if (props.mode === 'replace' && selectedFiles.value.length === 0 && !keepOriginalName.value) {
    shareName.value = props.share.name
  }
}

const handleUpload = async () => {
  if (!canSubmit.value) return
  uploading.value = true
  error.value = null

  const results = []
  const total = selectedFiles.value.length

  for (let i = 0; i < total; i++) {
    const { file, path } = selectedFiles.value[i]
    fileIndex.value = i + 1
    currentFileName.value = file.name

    try {
      const result = await new Promise((resolve, reject) => {
        uploadFileWithTus(
          file,
          (p) => {
            progress.value = Math.round(((i + p.percentage / 100) / total) * 100)
          },
          (r) => resolve({ uploadId: r.uploadId, path }),
          reject
        )
      })
      results.push(result)
    } catch (err) {
      error.value = err.message || 'Upload failed'
      uploading.value = false
      return
    }
  }

  progress.value = 100

  try {
    if (props.mode === 'replace') {
      const { uploadId, path: filePath } = results[0]
      const nameToSend = shareName.value.trim() || null
      await replaceShareFile(props.share.id, uploadId, filePath, nameToSend)
      toast.success('File replaced')
    } else {
      const uploadIds = results.map((r) => r.uploadId)
      const filePaths = Object.fromEntries(results.map((r) => [r.uploadId, r.path]))
      await addFilesToShare(props.share.id, uploadIds, filePaths)
      toast.success(`${results.length} file${results.length > 1 ? 's' : ''} added`)
    }
    emit('done')
    emit('close')
  } catch (err) {
    error.value = err.message || 'Failed to update share'
    uploading.value = false
  }
}
</script>

<template>
  <div class="modal-backdrop" @click.self="!uploading && emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <component :is="mode === 'replace' ? RefreshCcw : FilePlus2" />
        <h3>{{ mode === 'replace' ? 'Replace file' : 'Add files' }}</h3>
        <button class="close-btn" @click="emit('close')" :disabled="uploading"><X /></button>
      </div>

      <!-- File picker -->
      <div v-if="!uploading" class="picker-area">
        <label class="file-label">
          <input
            type="file"
            :multiple="mode === 'add'"
            @change="handleFileInput"
            style="display: none"
          />
          <span class="pick-btn">
            <component :is="mode === 'replace' ? RefreshCcw : FilePlus2" />
            {{ mode === 'replace' ? 'Choose replacement file' : 'Choose files to add' }}
          </span>
        </label>
      </div>

      <!-- Selected file list -->
      <div v-if="selectedFiles.length > 0 && !uploading" class="file-list">
        <div v-for="(item, idx) in selectedFiles" :key="idx" class="file-item">
          <span class="file-name" :title="item.path">{{ item.file.name }}</span>
          <span class="file-size">{{ niceFileSize(item.file.size) }}</span>
          <button class="remove-btn" @click="removeFile(idx)"><X /></button>
        </div>
      </div>

      <!-- Replace-mode: share rename -->
      <div v-if="mode === 'replace' && !uploading" class="rename-area">
        <label class="rename-label">Share name</label>
        <input
          v-model="shareName"
          type="text"
          class="rename-input"
          :disabled="keepOriginalName"
          placeholder="Share name"
        />
        <label class="keep-original-label">
          <input type="checkbox" v-model="keepOriginalName" />
          Keep original share name
        </label>
      </div>

      <!-- Upload progress -->
      <div v-if="uploading" class="progress-area">
        <div class="progress-info">
          <span>{{ currentFileName }}</span>
          <span>{{ fileIndex }} / {{ selectedFiles.length }}</span>
        </div>
        <div class="progress-bar-track">
          <div class="progress-bar-fill" :style="{ width: progress + '%' }"></div>
        </div>
        <div class="progress-pct">{{ progress }}%</div>
      </div>

      <!-- Error -->
      <div v-if="error" class="error-msg">{{ error }}</div>

      <div class="modal-footer">
        <button class="secondary" @click="emit('close')" :disabled="uploading">Cancel</button>
        <button @click="handleUpload" :disabled="!canSubmit">
          <Upload />
          {{ mode === 'replace' ? 'Replace' : 'Add files' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-box {
  background: var(--panel-section-background-color);
  border-radius: 10px;
  padding: 24px;
  min-width: 360px;
  max-width: 520px;
  width: 100%;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.modal-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;

  svg { width: 1.2rem; height: 1.2rem; color: var(--panel-section-text-color); }

  h3 { flex: 1; margin: 0; font-size: 1.1rem; color: var(--panel-section-text-color); }

  .close-btn {
    background: none; border: none; padding: 4px; cursor: pointer;
    display: flex; align-items: center; color: var(--panel-section-text-color); opacity: 0.6;
    &:hover { opacity: 1; }
    &:disabled { cursor: not-allowed; }
    svg { width: 1rem; height: 1rem; }
  }
}

.picker-area {
  margin-bottom: 16px;

  .file-label { display: block; cursor: pointer; }

  .pick-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 6px;
    background: var(--panel-section-background-color-alt);
    color: var(--panel-section-text-color);
    font-size: 0.9rem;
    border: 2px dashed rgba(128, 128, 128, 0.4);
    width: 100%;
    box-sizing: border-box;
    justify-content: center;
    transition: border-color 0.15s;

    &:hover { border-color: var(--primary-color, #4f6ef7); }

    svg { width: 1rem; height: 1rem; }
  }
}

.file-list {
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 200px;
  overflow-y: auto;
}

.file-item {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--panel-section-background-color-alt);
  border-radius: 6px;
  padding: 6px 10px;

  .file-name {
    flex: 1;
    font-size: 0.85rem;
    color: var(--panel-section-text-color);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .file-size {
    font-size: 0.75rem;
    color: var(--panel-section-text-color);
    opacity: 0.6;
    white-space: nowrap;
  }

  .remove-btn {
    background: none; border: none; padding: 2px; cursor: pointer;
    display: flex; align-items: center; color: var(--panel-section-text-color); opacity: 0.5;
    &:hover { opacity: 1; }
    svg { width: 0.85rem; height: 0.85rem; }
  }
}

.rename-area {
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;

  .rename-label {
    font-size: 0.85rem;
    color: var(--panel-section-text-color);
    opacity: 0.7;
  }

  .rename-input {
    width: 100%;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid rgba(128, 128, 128, 0.3);
    background: var(--panel-section-background-color-alt);
    color: var(--panel-section-text-color);
    font-size: 0.9rem;
    box-sizing: border-box;
    &:focus { outline: 2px solid var(--primary-color, #4f6ef7); border-color: transparent; }
    &:disabled { opacity: 0.5; cursor: not-allowed; }
  }

  .keep-original-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    color: var(--panel-section-text-color);
    opacity: 0.75;
    cursor: pointer;

    input[type="checkbox"] {
      cursor: pointer;
      width: auto;
      height: auto;
      margin-bottom: 0;
      flex-shrink: 0;
    }
  }
}

.progress-area {
  margin-bottom: 20px;

  .progress-info {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: var(--panel-section-text-color);
    margin-bottom: 8px;
    opacity: 0.8;
  }

  .progress-bar-track {
    height: 6px;
    background: var(--panel-section-background-color-alt);
    border-radius: 3px;
    overflow: hidden;
  }

  .progress-bar-fill {
    height: 100%;
    background: var(--primary-color, #4f6ef7);
    border-radius: 3px;
    transition: width 0.2s ease;
  }

  .progress-pct {
    text-align: right;
    font-size: 0.75rem;
    margin-top: 4px;
    color: var(--panel-section-text-color);
    opacity: 0.6;
  }
}

.error-msg {
  background: rgba(220, 53, 69, 0.1);
  border: 1px solid rgba(220, 53, 69, 0.3);
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 0.85rem;
  color: #dc3545;
  margin-bottom: 16px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
