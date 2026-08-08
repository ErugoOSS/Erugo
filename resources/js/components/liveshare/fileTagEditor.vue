<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { addLiveshareFileTags, removeLiveshareFileTag } from '../../api'
import { Search, X } from 'lucide-vue-next'
import { useToast } from 'vue-toastification'

const props = defineProps({
  liveshareLongId: { type: String, required: true },
  file: { type: Object, required: true },
  tags: { type: Array, default: () => [] }
})

const emit = defineEmits(['close', 'tagsChanged'])
const toast = useToast()

const popoverRef = ref(null)
const searchInput = ref(null)
const busy = ref(false)
const searchQuery = ref('')

const customTags = computed(() => props.tags.filter(t => t.type === 'custom'))

const filteredTags = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return customTags.value
  return customTags.value.filter(t => t.name.toLowerCase().includes(q))
})

const fileTagIds = computed(() => {
  return new Set((props.file.tags || []).map(t => t.id))
})

const isTagged = (tagId) => fileTagIds.value.has(tagId)

const toggleTag = async (tag) => {
  if (busy.value) return
  busy.value = true
  try {
    if (isTagged(tag.id)) {
      await removeLiveshareFileTag(props.liveshareLongId, props.file.id, tag.id)
    } else {
      await addLiveshareFileTags(props.liveshareLongId, props.file.id, [tag.id])
    }
    emit('tagsChanged')
  } catch (err) {
    toast.error(err.message || 'Failed to update tag')
  }
  busy.value = false
}

const handleClickOutside = (e) => {
  if (popoverRef.value && !popoverRef.value.contains(e.target)) {
    emit('close')
  }
}

onMounted(() => {
  setTimeout(() => {
    document.addEventListener('click', handleClickOutside, true)
  }, 0)
  nextTick(() => {
    if (searchInput.value) {
      searchInput.value.focus()
    }
  })
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside, true)
})
</script>

<template>
  <div class="file-tag-editor" ref="popoverRef" @click.stop>
    <div class="editor-search">
      <Search class="search-icon" />
      <input
        ref="searchInput"
        type="text"
        v-model="searchQuery"
        placeholder="Search tags..."
        class="search-input"
      />
      <button class="editor-close" @click="$emit('close')">
        <X />
      </button>
    </div>
    <div class="editor-body" v-if="customTags.length > 0">
      <div
        v-for="tag in filteredTags"
        :key="tag.id"
        class="tag-row"
        :class="{ active: isTagged(tag.id), busy }"
        @click="toggleTag(tag)"
      >
        <span class="tag-dot" :style="isTagged(tag.id) ? { background: tag.color || '#999' } : {}"></span>
        <span class="tag-name">{{ tag.name }}</span>
      </div>
      <div class="no-results" v-if="filteredTags.length === 0">
        No tags match your search
      </div>
    </div>
    <div class="editor-empty" v-else>
      No custom tags yet
    </div>
  </div>
</template>

<style lang="scss" scoped>
.file-tag-editor {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  z-index: 50;
  width: 200px;
  background: var(--panel-background-color);
  border-radius: 5px;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}

.editor-search {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 8px;
  border-bottom: 1px solid var(--input-border-color);

  .search-icon {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
    color: var(--panel-text-color);
    opacity: 0.4;
  }

  .search-input {
    flex: 1;
    border: none;
    background: transparent;
    color: var(--panel-text-color);
    font-size: 0.8rem;
    padding: 2px 0;
    margin: 0;
    height: auto;
    outline: none;

    &::placeholder {
      color: var(--panel-text-color);
      opacity: 0.35;
    }
  }

  .editor-close {
    width: 20px;
    height: 20px;
    border: none;
    background: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--panel-text-color);
    opacity: 0.4;
    padding: 0;
    flex-shrink: 0;

    &:hover {
      opacity: 1;
    }

    svg {
      width: 12px;
      height: 12px;
      margin: 0;
    }
  }
}

.editor-body {
  padding: 4px;
  max-height: 180px;
  overflow-y: auto;
}

.tag-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.1s;
  margin-bottom: 4px;

  &:hover {
    background: var(--panel-section-background-color);
  }

  &.active {
    background: var(--panel-section-background-color-alt);

    .tag-name {
      font-weight: 600;
    }
  }

  &.busy {
    opacity: 0.5;
    pointer-events: none;
  }

  .tag-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
    background: var(--primary-button-text-color-disabled);
    transition: background 0.15s;
  }

  .tag-name {
    font-size: 0.8rem;
    color: var(--panel-text-color);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.no-results,
.editor-empty {
  padding: 14px 8px;
  text-align: center;
  font-size: 0.8rem;
  color: var(--panel-text-color);
  opacity: 0.4;
}
</style>
