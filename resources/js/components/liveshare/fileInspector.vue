<script setup>
import { computed, ref, watch } from 'vue'
import { Download, Info, Loader2, Plus, Trash2, X } from 'lucide-vue-next'
import { useToast } from 'vue-toastification'
import { niceFileSize } from '../../utils'
import { removeLiveshareFileTag } from '../../api'
import FileTagEditor from './fileTagEditor.vue'
import {
  autoTagsOf,
  customTagsOf,
  fileDisplayName,
  fileExtension,
  getFileIcon,
  niceDateTime,
  niceType,
  thumbnailSrc
} from './fileHelpers'

const props = defineProps({
  liveshareLongId: { type: String, required: true },
  file: { type: Object, default: null },
  selectedFiles: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
  canRemoveFiles: { type: Boolean, default: false },
  canTagFiles: { type: Boolean, default: false },
  downloading: { type: Boolean, default: false }
})

const emit = defineEmits([
  'close',
  'download',
  'remove',
  'bulkDownload',
  'bulkRemove',
  'tagsChanged',
  'filterByTag'
])

const toast = useToast()

const multi = computed(() => props.selectedFiles.length > 1)

const selectionSize = computed(() =>
  props.selectedFiles.reduce((sum, file) => sum + (file.size || 0), 0)
)

const previewFailed = ref(false)

watch(
  () => props.file?.id,
  () => {
    previewFailed.value = false
    tagEditorOpen.value = false
  }
)

const previewSrc = computed(() => thumbnailSrc(props.file, 'medium'))

const showPreview = computed(() => !!previewSrc.value && !previewFailed.value)

const tagEditorOpen = ref(false)

const removingTagId = ref(null)

const removeTag = async (tag) => {
  removingTagId.value = tag.id
  try {
    await removeLiveshareFileTag(props.liveshareLongId, props.file.id, tag.id)
    emit('tagsChanged')
  } catch (err) {
    toast.error(err.message || 'Failed to remove tag')
  }
  removingTagId.value = null
}
</script>

<template>
  <aside class="file-inspector">
    <div class="inspector-head">
      <h3><Info /> Details</h3>
      <button class="inspector-close" @click="emit('close')" title="Close details">
        <X />
      </button>
    </div>

    <!-- Multiple files selected -->
    <div class="inspector-body" v-if="multi">
      <div class="multi-summary">
        <span class="multi-count">{{ selectedFiles.length }}</span>
        <span class="multi-label">files selected</span>
      </div>
      <dl class="meta">
        <div class="meta-row">
          <dt>Total size</dt>
          <dd>{{ niceFileSize(selectionSize) }}</dd>
        </div>
      </dl>
      <div class="inspector-actions">
        <button @click="emit('bulkDownload')" :disabled="downloading">
          <Loader2 v-if="downloading" class="spin" />
          <Download v-else />
          Download all
        </button>
        <button class="secondary danger" v-if="canRemoveFiles" @click="emit('bulkRemove')">
          <Trash2 />
          Remove all
        </button>
      </div>
    </div>

    <!-- Single file -->
    <div class="inspector-body" v-else-if="file">
      <div class="preview">
        <img
          v-if="showPreview"
          :src="previewSrc"
          :alt="fileDisplayName(file)"
          @error="previewFailed = true"
        />
        <div class="preview-icon" v-else>
          <component :is="getFileIcon(file.type)" />
          <span v-if="fileExtension(file)">{{ fileExtension(file) }}</span>
        </div>
      </div>

      <h4 class="file-name" :title="fileDisplayName(file)">{{ fileDisplayName(file) }}</h4>

      <dl class="meta">
        <div class="meta-row">
          <dt>Size</dt>
          <dd>{{ niceFileSize(file.size) }}</dd>
        </div>
        <div class="meta-row">
          <dt>Kind</dt>
          <dd>{{ niceType(file) }}</dd>
        </div>
        <div class="meta-row">
          <dt>Added</dt>
          <dd>{{ niceDateTime(file.created_at) }}</dd>
        </div>
        <div class="meta-row">
          <dt>Added by</dt>
          <dd>{{ file.uploader?.name || 'Unknown' }}</dd>
        </div>
      </dl>

      <div class="section">
        <div class="section-head">
          <span>Tags</span>
          <div class="tag-add-anchor" v-if="canTagFiles">
            <button class="tag-add" @click.stop="tagEditorOpen = !tagEditorOpen" title="Add tag">
              <Plus />
            </button>
            <FileTagEditor
              v-if="tagEditorOpen"
              :liveshare-long-id="liveshareLongId"
              :file="file"
              :tags="tags"
              @close="tagEditorOpen = false"
              @tagsChanged="emit('tagsChanged')"
            />
          </div>
        </div>
        <div class="tag-cloud">
          <span
            v-for="tag in customTagsOf(file)"
            :key="tag.id"
            class="pill"
            :class="{ busy: removingTagId === tag.id }"
            :style="tag.color ? { background: tag.color, color: '#fff' } : {}"
          >
            <span class="pill-name" @click="emit('filterByTag', tag.id)" :title="`Filter by ${tag.name}`">
              {{ tag.name }}
            </span>
            <button v-if="canTagFiles" class="pill-remove" @click="removeTag(tag)" title="Remove tag">
              <X />
            </button>
          </span>
          <span class="tag-none" v-if="customTagsOf(file).length === 0">No tags</span>
        </div>
      </div>

      <div class="section" v-if="autoTagsOf(file).length > 0">
        <div class="section-head"><span>Detected</span></div>
        <div class="tag-cloud">
          <span v-for="tag in autoTagsOf(file)" :key="tag.id" class="pill pill--auto">
            {{ tag.name }}
          </span>
        </div>
      </div>

      <div class="inspector-actions">
        <button @click="emit('download', file)">
          <Download />
          Download
        </button>
        <button class="secondary danger" v-if="canRemoveFiles" @click="emit('remove', file)">
          <Trash2 />
          Remove
        </button>
      </div>
    </div>

    <!-- Nothing selected -->
    <div class="inspector-empty" v-else>
      <Info />
      <p>Select a file to see its details</p>
    </div>
  </aside>
</template>

<style lang="scss" scoped>
.file-inspector {
  width: 290px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  min-height: 0;
  background: var(--panel-background-color);
  border-radius: 8px;
  box-sizing: border-box;
}

.inspector-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 14px 10px;
  flex-shrink: 0;

  h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--panel-text-color);

    svg {
      width: 15px;
      height: 15px;
      margin: 0;
      opacity: 0.5;
    }
  }
}

.inspector-close {
  border: none;
  background: none;
  padding: 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  color: var(--panel-text-color);
  opacity: 0.4;

  &:hover {
    opacity: 1;
  }

  svg {
    width: 15px;
    height: 15px;
    margin: 0;
  }
}

.inspector-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 0 14px 14px;
}

.preview {
  width: 100%;
  aspect-ratio: 4 / 3;
  border-radius: var(--panel-border-radius);
  overflow: hidden;
  background: var(--panel-section-background-color-alt);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;

  img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
  }
}

.preview-icon {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: var(--panel-section-text-color);

  svg {
    width: 46px;
    height: 46px;
    opacity: 0.3;
    margin: 0;
  }

  span {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    opacity: 0.45;
  }
}

.file-name {
  margin: 0 0 12px;
  font-size: 0.88rem;
  font-weight: 600;
  line-height: 1.35;
  color: var(--panel-text-color);
  word-break: break-word;
}

.meta {
  margin: 0 0 14px;
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.meta-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;

  dt {
    flex-shrink: 0;
    font-size: 0.72rem;
    color: var(--panel-section-text-color);
    opacity: 0.45;
  }

  dd {
    margin: 0;
    font-size: 0.76rem;
    text-align: right;
    color: var(--panel-text-color);
    word-break: break-word;
  }
}

.section {
  margin-bottom: 14px;
  padding-top: 12px;
  border-top: 1px solid var(--input-border-color);
}

.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
  font-size: 0.68rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--panel-section-text-color);
  opacity: 0.45;
}

.tag-add-anchor {
  position: relative;
}

.tag-add {
  width: 20px;
  height: 20px;
  padding: 0;
  border: none;
  border-radius: 50%;
  background: var(--panel-section-background-color-alt);
  color: var(--panel-text-color);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;

  &:hover {
    background: var(--primary-button-background-color);
    color: var(--primary-button-text-color);
  }

  svg {
    width: 12px;
    height: 12px;
    margin: 0;
  }
}

.tag-cloud {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.pill {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 2px 4px 2px 8px;
  border-radius: 11px;
  font-size: 0.7rem;
  font-weight: 500;
  max-width: 100%;
  // Both overridden inline when the tag has its own colour, where white text is
  // the only safe contrast against an arbitrary user-picked background.
  background: var(--primary-button-background-color);
  color: var(--primary-button-text-color);

  &.busy {
    opacity: 0.5;
    pointer-events: none;
  }

  &--auto {
    padding: 2px 8px;
    background: var(--panel-section-background-color-alt);
    color: var(--panel-section-text-color);
    opacity: 0.75;
  }
}

.pill-name {
  cursor: pointer;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;

  &:hover {
    text-decoration: underline;
  }
}

.pill-remove {
  border: none;
  background: none;
  padding: 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  color: inherit;
  opacity: 0.6;

  &:hover {
    opacity: 1;
  }

  svg {
    width: 11px;
    height: 11px;
    margin: 0;
  }
}

.tag-none {
  font-size: 0.74rem;
  color: var(--panel-section-text-color);
  opacity: 0.35;
}

.inspector-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--input-border-color);

  button {
    width: 100%;
    height: 38px;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.82rem;

    svg {
      width: 15px;
      height: 15px;
      margin: 0;
    }

    &.danger:hover:not(:disabled) {
      background: var(--color-danger);
      color: white;
    }
  }
}

.multi-summary {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 24px 0;

  .multi-count {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    color: var(--link-color);
  }

  .multi-label {
    font-size: 0.78rem;
    color: var(--panel-section-text-color);
    opacity: 0.55;
  }
}

.inspector-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 20px;
  text-align: center;
  color: var(--panel-section-text-color);
  opacity: 0.35;

  svg {
    width: 30px;
    height: 30px;
    margin: 0;
  }

  p {
    margin: 0;
    font-size: 0.8rem;
    line-height: 1.4;
  }
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
