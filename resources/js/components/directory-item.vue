<script setup>
import { Folder, Trash, File } from 'lucide-vue-next'
import { niceFileSize, niceFileType, niceFileName, getApiUrl } from '../utils'
import { ref } from 'vue'

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
  currentPath: {
    type: String,
    default: ''
  }
})

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
  window.location.href = downloadUrl
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
        :class="{ 'clickable': readOnly && shareCode }"
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
        <div class="hover-actions" v-if="!readOnly">
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
            :class="{ 'clickable': readOnly && shareCode }"
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
            <div class="hover-actions" v-if="!readOnly">
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
          :current-path="currentPath ? `${currentPath}/${dirName}` : dirName"
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
</style>
