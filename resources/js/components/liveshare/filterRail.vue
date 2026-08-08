<script setup>
import { computed, ref } from 'vue'
import { Files, HardDrive, Search, Settings2, Tag, X } from 'lucide-vue-next'
import { niceFileSize } from '../../utils'
import { MEDIA_TYPES, countByAutoTag, countByTagId } from './fileHelpers'

const props = defineProps({
  files: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
  activeTypeFilter: { type: String, default: '' },
  activeTagFilters: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false }
})

const emit = defineEmits(['selectType', 'toggleTag', 'clearAll', 'manageTags'])

const tagSearch = ref('')

const typeCounts = computed(() =>
  MEDIA_TYPES.map((type) => ({ ...type, count: countByAutoTag(props.files, type.name) })).filter(
    (type) => type.count > 0 || props.activeTypeFilter === type.name
  )
)

const customTags = computed(() => props.tags.filter((t) => t.type === 'custom'))

const visibleTags = computed(() => {
  const query = tagSearch.value.trim().toLowerCase()
  const withCounts = customTags.value.map((tag) => ({
    ...tag,
    count: countByTagId(props.files, tag.id)
  }))
  if (!query) return withCounts
  return withCounts.filter((tag) => tag.name.toLowerCase().includes(query))
})

const showTagSearch = computed(() => customTags.value.length > 8)

const totalSize = computed(() => props.files.reduce((sum, file) => sum + (file.size || 0), 0))

const hasFilters = computed(() => !!props.activeTypeFilter || props.activeTagFilters.length > 0)
</script>

<template>
  <aside class="filter-rail">
    <div class="rail-scroll">
      <div class="rail-section">
        <div
          class="rail-row"
          :class="{ active: !hasFilters }"
          @click="emit('clearAll')"
        >
          <Files class="rail-row-icon" />
          <span class="rail-row-name">All files</span>
          <span class="rail-row-count">{{ files.length }}</span>
        </div>
      </div>

      <div class="rail-section" v-if="typeCounts.length > 0">
        <div class="rail-label">File types</div>
        <div
          v-for="type in typeCounts"
          :key="type.name"
          class="rail-row"
          :class="{ active: activeTypeFilter === type.name }"
          @click="emit('selectType', type.name)"
        >
          <component :is="type.icon" class="rail-row-icon" />
          <span class="rail-row-name">{{ type.label }}</span>
          <span class="rail-row-count">{{ type.count }}</span>
        </div>
      </div>

      <div class="rail-section">
        <div class="rail-label">
          <span>Tags</span>
          <button
            v-if="canManage"
            class="rail-label-action"
            @click="emit('manageTags')"
            title="Manage tags"
          >
            <Settings2 />
          </button>
        </div>

        <div class="rail-tag-search" v-if="showTagSearch">
          <Search class="rail-tag-search-icon" />
          <input v-model="tagSearch" type="text" placeholder="Find a tag..." />
          <button v-if="tagSearch" class="rail-tag-search-clear" @click="tagSearch = ''">
            <X />
          </button>
        </div>

        <div
          v-for="tag in visibleTags"
          :key="tag.id"
          class="rail-row"
          :class="{ active: activeTagFilters.includes(tag.id) }"
          @click="emit('toggleTag', tag.id)"
        >
          <span class="rail-dot" :style="tag.color ? { background: tag.color } : {}"></span>
          <span class="rail-row-name">{{ tag.name }}</span>
          <span class="rail-row-count">{{ tag.count }}</span>
        </div>

        <div class="rail-empty" v-if="customTags.length === 0">
          <Tag />
          <span>No tags yet</span>
        </div>
        <div class="rail-empty" v-else-if="visibleTags.length === 0">
          <span>No tags match</span>
        </div>
      </div>
    </div>

    <div class="rail-footer">
      <HardDrive />
      <span>{{ files.length }} files &middot; {{ niceFileSize(totalSize) }}</span>
    </div>
  </aside>
</template>

<style lang="scss" scoped>
.filter-rail {
  width: 210px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  min-height: 0;
  background: var(--panel-background-color);
  border-radius: 8px;
  padding: 14px 10px 0;
  box-sizing: border-box;
}

.rail-scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.rail-section {
  & + .rail-section {
    margin-top: 14px;
  }
}

.rail-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  padding: 4px 8px 6px;
  font-size: 0.68rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--panel-section-text-color);
  opacity: 0.45;
}

.rail-label-action {
  border: none;
  background: none;
  padding: 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  color: var(--panel-section-text-color);
  opacity: 0.7;

  &:hover {
    opacity: 1;
  }

  svg {
    width: 13px;
    height: 13px;
    margin: 0;
  }
}

.rail-row {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 7px 8px;
  border-radius: var(--panel-border-radius);
  cursor: pointer;
  transition: background 0.12s ease;

  &:hover {
    background: var(--panel-section-background-color);
  }

  &.active {
    background: var(--panel-section-background-color-alt);

    .rail-row-name {
      font-weight: 600;
    }

    .rail-row-icon {
      opacity: 1;
      color: var(--link-color);
    }
  }
}

.rail-row-icon {
  width: 15px;
  height: 15px;
  flex-shrink: 0;
  opacity: 0.55;
  color: var(--panel-section-text-color);
  margin: 0;
}

.rail-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
  margin: 0 3px;
  // Overridden inline when the tag has its own colour.
  background: var(--primary-button-background-color);
}

.rail-row-name {
  flex: 1;
  min-width: 0;
  font-size: 0.8rem;
  color: var(--panel-section-text-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rail-row-count {
  font-size: 0.68rem;
  color: var(--panel-section-text-color);
  opacity: 0.4;
  flex-shrink: 0;
}

.rail-tag-search {
  position: relative;
  display: flex;
  align-items: center;
  margin: 0 4px 6px;

  .rail-tag-search-icon {
    position: absolute;
    left: 8px;
    width: 13px;
    height: 13px;
    opacity: 0.4;
    pointer-events: none;
    color: var(--input-text-color);
    margin: 0;
  }

  input {
    width: 100%;
    height: 30px;
    margin: 0;
    padding: 0 26px;
    box-sizing: border-box;
    font-size: 0.78rem;
    border: 1px solid var(--input-border-color);
    background: var(--input-background-color);
    color: var(--input-text-color);
    border-radius: var(--panel-border-radius);
    outline: none;

    &:focus {
      border-color: var(--input-border-color-focus);
    }
  }

  .rail-tag-search-clear {
    position: absolute;
    right: 4px;
    border: none;
    background: none;
    padding: 0;
    cursor: pointer;
    display: flex;
    opacity: 0.4;
    color: var(--input-text-color);

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

.rail-empty {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 8px;
  font-size: 0.75rem;
  color: var(--panel-section-text-color);
  opacity: 0.35;

  svg {
    width: 14px;
    height: 14px;
    margin: 0;
  }
}

.rail-footer {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 12px 8px;
  margin-top: 10px;
  border-top: 1px solid var(--input-border-color);
  font-size: 0.7rem;
  color: var(--panel-section-text-color);
  opacity: 0.45;

  svg {
    width: 13px;
    height: 13px;
    margin: 0;
    flex-shrink: 0;
  }

  span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
