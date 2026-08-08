<script setup>
import { ref } from 'vue'
import { Check, FileQuestion } from 'lucide-vue-next'
import { niceFileSize } from '../../utils'
import { customTagsOf, fileDisplayName, getFileIcon, thumbnailSrc } from './fileHelpers'

const props = defineProps({
  files: { type: Array, default: () => [] },
  selectMode: { type: Boolean, default: false },
  selectedIds: { type: Array, default: () => [] },
  activeId: { type: [Number, String], default: null },
  filtered: { type: Boolean, default: false }
})

const emit = defineEmits(['select', 'open', 'contextmenu'])

const failedThumbs = ref(new Set())

const onThumbError = (fileId) => {
  failedThumbs.value = new Set([...failedThumbs.value, fileId])
}

const showThumbnail = (file) => !!file.thumbnail_url && !failedThumbs.value.has(file.id)

const isSelected = (fileId) => props.selectedIds.includes(fileId)

const MAX_VISIBLE_DOTS = 4

const tagDots = (file) => customTagsOf(file).slice(0, MAX_VISIBLE_DOTS)

const extraTagCount = (file) => Math.max(0, customTagsOf(file).length - MAX_VISIBLE_DOTS)

const handleClick = (file, event) => {
  emit('select', {
    file,
    additive: event.metaKey || event.ctrlKey,
    range: event.shiftKey
  })
}

const handleContextMenu = (file, event) => {
  event.preventDefault()
  emit('contextmenu', { file, x: event.clientX, y: event.clientY })
}
</script>

<template>
  <div class="file-grid" v-if="files.length > 0">
    <div
      class="file-tile"
      v-for="file in files"
      :key="file.id"
      :class="{
        'is-selected': isSelected(file.id),
        'is-active': activeId === file.id,
        'is-select-mode': selectMode
      }"
      @click="handleClick(file, $event)"
      @dblclick="$emit('open', file)"
      @contextmenu="handleContextMenu(file, $event)"
    >
      <div class="tile-preview">
        <img
          v-if="showThumbnail(file)"
          :src="thumbnailSrc(file, 'small')"
          :alt="fileDisplayName(file)"
          class="tile-thumb"
          loading="lazy"
          @error="onThumbError(file.id)"
        />
        <div class="tile-icon" v-else>
          <component :is="getFileIcon(file.type)" />
        </div>

        <button
          class="tile-check"
          :class="{ checked: isSelected(file.id) }"
          @click.stop="emit('select', { file, additive: true, range: false })"
          :title="isSelected(file.id) ? 'Deselect' : 'Select'"
        >
          <Check v-if="isSelected(file.id)" />
        </button>
      </div>

      <div class="tile-label">
        <span class="tile-name" :title="fileDisplayName(file)">{{ fileDisplayName(file) }}</span>
        <span class="tile-meta">
          <span class="tile-size">{{ niceFileSize(file.size) }}</span>
          <span class="tile-dots" v-if="tagDots(file).length > 0">
            <span
              v-for="tag in tagDots(file)"
              :key="tag.id"
              class="tile-dot"
              :style="tag.color ? { background: tag.color } : {}"
              :title="tag.name"
            ></span>
            <span class="tile-dot-more" v-if="extraTagCount(file) > 0">+{{ extraTagCount(file) }}</span>
          </span>
        </span>
      </div>
    </div>
  </div>

  <div class="grid-empty" v-else>
    <FileQuestion />
    <p v-if="filtered">No files match your filters</p>
    <p v-else>No files yet</p>
  </div>
</template>

<style lang="scss" scoped>
.file-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 6px;
  width: 100%;
  align-content: start;
}

.file-tile {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 8px;
  border-radius: var(--panel-border-radius);
  border: 1px solid transparent;
  cursor: pointer;
  user-select: none;
  transition: background 0.15s ease, border-color 0.15s ease;

  &:hover {
    background: var(--panel-section-background-color);

    .tile-check {
      opacity: 1;
    }
  }

  &.is-selected {
    background: var(--panel-section-background-color-alt);

    .tile-check {
      opacity: 1;
    }
  }

  &.is-active {
    border-color: var(--input-border-color-focus);
    background: var(--panel-section-background-color-alt);
  }

  &.is-select-mode .tile-check {
    opacity: 1;
  }
}

.tile-preview {
  position: relative;
  width: 100%;
  aspect-ratio: 1 / 1;
  border-radius: calc(var(--panel-border-radius) - 2px);
  overflow: hidden;
  background: var(--panel-section-background-color-alt);
}

.tile-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.tile-icon {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;

  svg {
    width: 40%;
    height: 40%;
    opacity: 0.3;
    color: var(--panel-section-text-color);
    margin: 0;
  }
}

.tile-check {
  position: absolute;
  top: 6px;
  left: 6px;
  width: 22px;
  height: 22px;
  padding: 0;
  border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, 0.9);
  background: rgba(0, 0, 0, 0.35);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.15s ease, background 0.15s ease;

  svg {
    width: 12px;
    height: 12px;
    color: white;
    margin: 0;
  }

  // The white ring is kept when checked -- this sits on top of an arbitrary
  // thumbnail, so it needs contrast against the image rather than the theme.
  &.checked {
    background: var(--primary-button-background-color);
  }
}

.tile-label {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  padding: 0 2px;
}

.tile-name {
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--panel-section-text-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tile-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  min-width: 0;
}

.tile-size {
  font-size: 0.68rem;
  color: var(--panel-section-text-color);
  opacity: 0.5;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.tile-dots {
  display: flex;
  align-items: center;
  gap: 3px;
  flex-shrink: 0;
}

.tile-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  // Overridden inline when the tag has its own colour.
  background: var(--primary-button-background-color);
}

.tile-dot-more {
  font-size: 0.62rem;
  color: var(--panel-section-text-color);
  opacity: 0.5;
}

.grid-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 80px 20px;
  color: var(--panel-section-text-color);
  opacity: 0.4;

  svg {
    width: 44px;
    height: 44px;
    margin: 0;
  }

  p {
    margin: 0;
    font-size: 0.95rem;
  }
}
</style>
