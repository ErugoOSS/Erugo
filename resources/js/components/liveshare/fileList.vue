<script setup>
import { ref } from 'vue'
import { ArrowDown, ArrowUp, Check, FileQuestion } from 'lucide-vue-next'
import { niceFileSize } from '../../utils'
import { customTagsOf, fileDisplayName, getFileIcon, niceShortDate, niceType, thumbnailSrc } from './fileHelpers'

const props = defineProps({
  files: { type: Array, default: () => [] },
  selectMode: { type: Boolean, default: false },
  selectedIds: { type: Array, default: () => [] },
  activeId: { type: [Number, String], default: null },
  sortKey: { type: String, default: 'name' },
  sortDir: { type: String, default: 'asc' },
  filtered: { type: Boolean, default: false }
})

const emit = defineEmits(['select', 'open', 'contextmenu', 'sort'])

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'size', label: 'Size' },
  { key: 'type', label: 'Type' },
  { key: 'tags', label: 'Tags', sortable: false },
  { key: 'uploader', label: 'Added by' },
  { key: 'created_at', label: 'Added' }
]

const failedThumbs = ref(new Set())

const onThumbError = (fileId) => {
  failedThumbs.value = new Set([...failedThumbs.value, fileId])
}

const showThumbnail = (file) => !!file.thumbnail_url && !failedThumbs.value.has(file.id)

const isSelected = (fileId) => props.selectedIds.includes(fileId)

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
  <div class="file-list" v-if="files.length > 0">
    <div class="list-head">
      <div class="cell cell-check"></div>
      <div
        v-for="col in columns"
        :key="col.key"
        class="cell"
        :class="[`cell-${col.key}`, { sortable: col.sortable !== false, active: sortKey === col.key }]"
        @click="col.sortable !== false && emit('sort', col.key)"
      >
        <span>{{ col.label }}</span>
        <ArrowUp v-if="sortKey === col.key && sortDir === 'asc'" class="sort-icon" />
        <ArrowDown v-else-if="sortKey === col.key && sortDir === 'desc'" class="sort-icon" />
      </div>
    </div>

    <div class="list-body">
      <div
        class="list-row"
        v-for="file in files"
        :key="file.id"
        :class="{
          'is-selected': isSelected(file.id),
          'is-active': activeId === file.id
        }"
        @click="handleClick(file, $event)"
        @dblclick="$emit('open', file)"
        @contextmenu="handleContextMenu(file, $event)"
      >
        <div class="cell cell-check">
          <button
            class="row-check"
            :class="{ checked: isSelected(file.id) }"
            @click.stop="emit('select', { file, additive: true, range: false })"
          >
            <Check v-if="isSelected(file.id)" />
          </button>
        </div>

        <div class="cell cell-name">
          <div class="row-thumb">
            <img
              v-if="showThumbnail(file)"
              :src="thumbnailSrc(file, 'small')"
              :alt="fileDisplayName(file)"
              loading="lazy"
              @error="onThumbError(file.id)"
            />
            <component :is="getFileIcon(file.type)" v-else class="row-icon" />
          </div>
          <span class="row-name" :title="fileDisplayName(file)">{{ fileDisplayName(file) }}</span>
        </div>

        <div class="cell cell-size">{{ niceFileSize(file.size) }}</div>
        <div class="cell cell-type">{{ niceType(file) }}</div>

        <div class="cell cell-tags">
          <span
            v-for="tag in customTagsOf(file)"
            :key="tag.id"
            class="row-tag"
            :style="tag.color ? { background: tag.color, color: '#fff' } : {}"
            :title="tag.name"
          >
            {{ tag.name }}
          </span>
          <span class="row-tag-empty" v-if="customTagsOf(file).length === 0">--</span>
        </div>

        <div class="cell cell-uploader">{{ file.uploader?.name || '--' }}</div>
        <div class="cell cell-created_at">{{ niceShortDate(file.created_at) }}</div>
      </div>
    </div>
  </div>

  <div class="list-empty" v-else>
    <FileQuestion />
    <p v-if="filtered">No files match your filters</p>
    <p v-else>No files yet</p>
  </div>
</template>

<style lang="scss" scoped>
.file-list {
  display: flex;
  flex-direction: column;
  min-height: 0;
  height: 100%;
  container-type: inline-size;
}

.list-head,
.list-row {
  display: grid;
  grid-template-columns: 30px minmax(120px, 3fr) 80px 72px minmax(0, 1.2fr) 105px 100px;
  align-items: center;
  gap: 6px;
}

.list-head {
  padding: 0 16px 8px 8px;
  border-bottom: 1px solid var(--input-border-color);
  flex-shrink: 0;

  .cell {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--panel-section-text-color);
    opacity: 0.5;

    &.sortable {
      cursor: pointer;

      &:hover {
        opacity: 0.9;
      }
    }

    &.active {
      opacity: 1;
      color: var(--link-color);
    }
  }

  .sort-icon {
    width: 12px;
    height: 12px;
    margin: 0;
  }
}

.list-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  scrollbar-gutter: stable;
  padding-top: 4px;
}

.list-row {
  padding: 6px 8px;
  border-radius: var(--panel-border-radius);
  border: 1px solid transparent;
  cursor: pointer;
  user-select: none;
  transition: background 0.12s ease, border-color 0.12s ease;

  &:hover {
    background: var(--panel-section-background-color);

    .row-check {
      opacity: 1;
    }
  }

  &.is-selected {
    background: var(--panel-section-background-color-alt);

    .row-check {
      opacity: 1;
    }
  }

  &.is-active {
    border-color: var(--input-border-color-focus);
    background: var(--panel-section-background-color-alt);
  }
}

.cell {
  min-width: 0;
  font-size: 0.8rem;
  color: var(--panel-section-text-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cell-check {
  display: flex;
  align-items: center;
  justify-content: center;
}

.row-check {
  width: 20px;
  height: 20px;
  padding: 0;
  border-radius: 50%;
  border: 2px solid var(--panel-section-text-color);
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  opacity: 0.25;
  transition: opacity 0.12s ease, background 0.12s ease;

  svg {
    width: 11px;
    height: 11px;
    color: var(--primary-button-text-color);
    margin: 0;
  }

  &.checked {
    opacity: 1;
    background: var(--primary-button-background-color);
    border-color: var(--input-border-color-focus);
  }
}

.cell-name {
  display: flex;
  align-items: center;
  gap: 10px;
}

.row-thumb {
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  border-radius: 5px;
  overflow: hidden;
  background: var(--panel-section-background-color-alt);
  display: flex;
  align-items: center;
  justify-content: center;

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .row-icon {
    width: 15px;
    height: 15px;
    opacity: 0.4;
    margin: 0;
  }
}

.row-name {
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cell-size,
.cell-type,
.cell-uploader,
.cell-created_at {
  opacity: 0.6;
  font-size: 0.75rem;
}

.cell-tags {
  display: flex;
  align-items: center;
  gap: 4px;
  overflow: hidden;
}

.row-tag {
  flex-shrink: 0;
  max-width: 80px;
  padding: 1px 7px;
  border-radius: 10px;
  font-size: 0.65rem;
  font-weight: 500;
  // Both overridden inline when the tag has its own colour, where white text is
  // the only safe contrast against an arbitrary user-picked background.
  background: var(--primary-button-background-color);
  color: var(--primary-button-text-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.row-tag-empty {
  font-size: 0.75rem;
  opacity: 0.35;
}

.list-empty {
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

// The panel width changes with the rail and details sidebar, so columns respond
// to the list's own width rather than the viewport. These come last so they win
// over the base .list-head .cell rules they override.
@container (max-width: 720px) {
  .list-head,
  .list-row {
    grid-template-columns: 30px minmax(110px, 3fr) 80px 72px minmax(0, 1.2fr) 100px;
  }

  .list-head .cell-uploader,
  .list-row .cell-uploader {
    display: none;
  }
}

@container (max-width: 560px) {
  .list-head,
  .list-row {
    grid-template-columns: 30px minmax(100px, 3fr) 80px 100px;
  }

  .list-head .cell-uploader,
  .list-row .cell-uploader,
  .list-head .cell-type,
  .list-row .cell-type,
  .list-head .cell-tags,
  .list-row .cell-tags {
    display: none;
  }
}
</style>
