<script setup>
import { Folder, Trash, File, Loader, Check, Clock } from 'lucide-vue-next'
import { niceFileSize, niceFileType, niceFileName, getApiUrl } from '../utils'
import { ref, computed } from 'vue'

const apiUrl = getApiUrl()

const props = defineProps({
  structure: {
    type: Object,
    required: true
  },
  isRoot: {
    type: Boolean,
    default: false
  },
  readOnly: {
    type: Boolean,
    default: false
  },
  shareCode: {
    type: String,
    default: null
  },
  password: {
    type: String,
    default: ''
  },
  currentPath: {
    type: String,
    default: ''
  },
  // Upload state props
  isUploading: {
    type: Boolean,
    default: false
  },
  currentUploadingFile: {
    type: String,
    default: ''
  },
  currentFileProgress: {
    type: Number,
    default: 0
  },
  completedFiles: {
    type: Array,
    default: () => []
  }
})

// Determine upload status for a file
const getFileStatus = (file) => {
  const filePath = file.fullPath || file.name
  if (props.completedFiles.includes(filePath)) {
    return 'completed'
  }
  if (props.currentUploadingFile === filePath) {
    return 'uploading'
  }
  if (props.isUploading) {
    return 'waiting'
  }
  return 'idle'
}

const openDirectories = ref([])

defineEmits(['remove-file'])

// Build the full path for a file and trigger download
function downloadFile(file, dirName = null) {
  if (!props.shareCode) return
  
  let filePath
  if (dirName) {
    // File is inside a directory shown in this component
    const basePath = props.currentPath ? `${props.currentPath}/${dirName}` : dirName
    filePath = `${basePath}/${file.name}`
  } else if (props.currentPath) {
    // File is at root of current path context
    filePath = `${props.currentPath}/${file.name}`
  } else {
    // File is at root level, use its full_path if available
    filePath = file.full_path ? `${file.full_path}/${file.name}` : file.name
  }
  
  const downloadUrl = `${apiUrl}/api/shares/${props.shareCode}/download/file/${encodeURIComponent(filePath)}`
  
  // If password is provided, use form POST to include it
  if (props.password) {
    const form = document.createElement('form')
    form.action = downloadUrl
    form.method = 'POST'
    
    const passwordInput = document.createElement('input')
    passwordInput.type = 'hidden'
    passwordInput.name = 'password'
    passwordInput.value = props.password
    form.appendChild(passwordInput)
    
    document.body.appendChild(form)
    form.submit()
    setTimeout(() => document.body.removeChild(form), 0)
  } else {
    window.location.href = downloadUrl
  }
}

// Helper function to get directories from structure
function getDirectories(structure) {
  const directories = {}

  // Filter out non-directory entries
  Object.entries(structure).forEach(([key, value]) => {
    if (key !== 'files' && typeof value === 'object') {
      directories[key] = value
    }
  })

  return directories
}
</script>

<template>
  <div class="directory-structure" :class="{ 'is-root': isRoot }">
    <!-- Root-level files -->
    <div v-if="structure.files && structure.files.length" class="root-files">
      <div 
        class="upload-basket-item" 
        :class="{ 
          'clickable': readOnly && shareCode,
          'is-uploading': getFileStatus(file) === 'uploading',
          'is-completed': getFileStatus(file) === 'completed',
          'is-waiting': getFileStatus(file) === 'waiting'
        }"
        v-for="file in structure.files" 
        :key="file.fullPath || file.name"
        @click="readOnly && shareCode ? downloadFile(file) : null"
      >
        <div class="name">
          {{ niceFileName(file.name) }}
        </div>
        <div class="meta">
          <div class="size">
            {{ niceFileSize(file.size) }}
          </div>
          <div class="type">
            {{ niceFileType(file.type) }}
          </div>
        </div>
        <!-- Upload status indicator -->
        <div class="upload-status" v-if="isUploading">
          <template v-if="getFileStatus(file) === 'waiting'">
            <Clock class="status-icon waiting" />
          </template>
          <template v-else-if="getFileStatus(file) === 'uploading'">
            <Loader class="status-icon uploading spin" />
            <span class="progress-text">{{ Math.round(currentFileProgress) }}%</span>
          </template>
          <template v-else-if="getFileStatus(file) === 'completed'">
            <Check class="status-icon completed" />
          </template>
        </div>
        <div class="hover-actions" v-if="!readOnly && !isUploading">
          <button class="icon-only" @click="$emit('remove-file', file)">
            <Trash />
          </button>
        </div>
      </div>
    </div>

    <!-- Directories -->
    <template v-for="(dirContent, dirName) in getDirectories(structure)" :key="dirName">
      <div class="upload-basket-folder">
        <div class="directory-header" @click="toggleDirectory(dirName)">
          <Folder />
          <span>{{ dirName }}</span>
        </div>

        <!-- Files in this directory -->
        <div class="directory-files" v-if="dirContent.files && dirContent.files.length">
          <div 
            class="upload-basket-item" 
            :class="{ 
              'clickable': readOnly && shareCode,
              'is-uploading': getFileStatus(file) === 'uploading',
              'is-completed': getFileStatus(file) === 'completed',
              'is-waiting': getFileStatus(file) === 'waiting'
            }"
            v-for="file in dirContent.files" 
            :key="file.fullPath || file.name"
            @click="readOnly && shareCode ? downloadFile(file, dirName) : null"
          >
            <div class="name">
              <div class="icon">
                <File />
              </div>
              {{ niceFileName(file.name) }}
            </div>
            <div class="meta">
              <div class="size">
                {{ niceFileSize(file.size) }}
              </div>
              <div class="type">
                {{ niceFileType(file.type) }}
              </div>
            </div>
            <!-- Upload status indicator -->
            <div class="upload-status" v-if="isUploading">
              <template v-if="getFileStatus(file) === 'waiting'">
                <Clock class="status-icon waiting" />
              </template>
              <template v-else-if="getFileStatus(file) === 'uploading'">
                <Loader class="status-icon uploading spin" />
                <span class="progress-text">{{ Math.round(currentFileProgress) }}%</span>
              </template>
              <template v-else-if="getFileStatus(file) === 'completed'">
                <Check class="status-icon completed" />
              </template>
            </div>
            <div class="hover-actions" v-if="!readOnly && !isUploading">
              <button class="icon-only" @click="$emit('remove-file', file)">
                <Trash />
              </button>
            </div>
          </div>
        </div>

        <!-- Subdirectories (recursive) -->
        <directory-item
          :structure="dirContent.directories"
          :is-root="false"
          @remove-file="$emit('remove-file', $event)"
          class="subdirectory"
          :read-only="readOnly"
          :share-code="shareCode"
          :password="password"
          :current-path="currentPath ? `${currentPath}/${dirName}` : dirName"
          :is-uploading="isUploading"
          :current-uploading-file="currentUploadingFile"
          :current-file-progress="currentFileProgress"
          :completed-files="completedFiles"
        />
      </div>
    </template>
  </div>
</template>

<style scoped lang="scss">
.directory-structure {
  width: 100%;
  position: relative;
}

.subdirectory {
  padding-left: 0px;
}

.directory-header {
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
}

.directory-files {
  margin-left: 16px;
}

.upload-basket-item.clickable {
  cursor: pointer;
  transition: background-color 0.15s ease;
  
  &:hover {
    background-color: var(--panel-section-background-color-alt, rgba(255, 255, 255, 0.05));
  }
}

// Upload status styles
.upload-status {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-left: auto;
  padding-left: 12px;
  font-size: 0.85em;
  
  .status-icon {
    width: 16px;
    height: 16px;
    
    &.waiting {
      opacity: 0.5;
    }
    
    &.uploading {
      color: var(--accent-color, #3b82f6);
    }
    
    &.completed {
      color: var(--success-color, #22c55e);
    }
    
    &.spin {
      animation: spin 1s linear infinite;
    }
  }
  
  .progress-text {
    min-width: 40px;
    text-align: right;
    font-variant-numeric: tabular-nums;
    color: var(--accent-color, #3b82f6);
  }
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.upload-basket-item {
  &.is-uploading {
    background-color: rgba(59, 130, 246, 0.1);
  }
  
  &.is-completed {
    opacity: 0.7;
  }
  
  &.is-waiting {
    opacity: 0.6;
  }
}
</style>
