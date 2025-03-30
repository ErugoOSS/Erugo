<script setup>
import { Folder, Trash, File } from 'lucide-vue-next'
import { niceFileSize, niceFileType, niceFileName } from '../utils'
import { ref } from 'vue'
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
  }
})

const openDirectories = ref([])

defineEmits(['remove-file'])

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
      <div class="upload-basket-item" v-for="file in structure.files" :key="file.fullPath || file.name">
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
          <div class="upload-basket-item" v-for="file in dirContent.files" :key="file.fullPath || file.name">
            
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
</style>
