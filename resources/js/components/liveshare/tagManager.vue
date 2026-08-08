<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { createLiveshareTag, updateLiveshareTag, deleteLiveshareTag } from '../../api'
import { Plus, Edit3, Trash2, X, Check, Loader2, ChevronDown } from 'lucide-vue-next'
import { useToast } from 'vue-toastification'
import { useConfirmDialog } from '../../composables/useConfirmDialog'

const props = defineProps({
  liveshareLongId: { type: String, required: true },
  tags: { type: Array, default: () => [] }
})

const emit = defineEmits(['tagsChanged'])
const toast = useToast()
const confirmDialog = useConfirmDialog()

const PRESET_COLORS = [
  '#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#1abc9c',
  '#3498db', '#9b59b6', '#e91e63', '#795548', '#607d8b'
]

// Create form
const showCreateForm = ref(false)
const newName = ref('')
const newColor = ref(PRESET_COLORS[0])
const creating = ref(false)

// Edit state
const editingTagId = ref(null)
const editName = ref('')
const editColor = ref('')
const savingEdit = ref(false)

// Color picker dropdown
const openColorPicker = ref(null) // 'create' or tag id

const toggleColorPicker = (id) => {
  openColorPicker.value = openColorPicker.value === id ? null : id
}

const selectNewColor = (color) => {
  newColor.value = color
  openColorPicker.value = null
}

const selectEditColor = (color) => {
  editColor.value = color
  openColorPicker.value = null
}

const handleGlobalClick = (e) => {
  if (openColorPicker.value !== null && !e.target.closest('.color-picker')) {
    openColorPicker.value = null
  }
}

onMounted(() => {
  document.addEventListener('click', handleGlobalClick, true)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleGlobalClick, true)
})

const customTags = computed(() => props.tags.filter(t => t.type === 'custom'))
const autoTags = computed(() => props.tags.filter(t => t.type === 'auto'))

const handleCreate = async () => {
  if (!newName.value.trim()) return
  creating.value = true
  try {
    await createLiveshareTag(props.liveshareLongId, newName.value.trim(), newColor.value)
    toast.success('Tag created')
    newName.value = ''
    newColor.value = PRESET_COLORS[0]
    showCreateForm.value = false
    emit('tagsChanged')
  } catch (err) {
    toast.error(err.message || 'Failed to create tag')
  }
  creating.value = false
}

const startEdit = (tag) => {
  editingTagId.value = tag.id
  editName.value = tag.name
  editColor.value = tag.color || PRESET_COLORS[0]
}

const cancelEdit = () => {
  editingTagId.value = null
}

const saveEdit = async (tag) => {
  if (!editName.value.trim()) return
  savingEdit.value = true
  try {
    const updates = {}
    if (editName.value.trim() !== tag.name) updates.name = editName.value.trim()
    if (editColor.value !== tag.color) updates.color = editColor.value

    if (Object.keys(updates).length > 0) {
      await updateLiveshareTag(props.liveshareLongId, tag.id, updates)
      toast.success('Tag updated')
      emit('tagsChanged')
    }
    editingTagId.value = null
  } catch (err) {
    toast.error(err.message || 'Failed to update tag')
  }
  savingEdit.value = false
}

const handleDelete = async (tag) => {
  const confirmed = await confirmDialog.show({
    title: 'Delete Tag',
    message: `Delete "${tag.name}"? This will remove it from all files.`,
    okText: 'Delete',
    cancelText: 'Cancel'
  })
  if (!confirmed) return
  try {
    await deleteLiveshareTag(props.liveshareLongId, tag.id)
    toast.success('Tag deleted')
    emit('tagsChanged')
  } catch (err) {
    toast.error(err.message || 'Failed to delete tag')
  }
}

const onCardMouseEnter = (e) => {
  const inner = e.currentTarget.querySelector('.tag-name-inner')
  const outer = e.currentTarget.querySelector('.tag-name')
  if (!inner || !outer) return
  if (inner.scrollWidth > outer.clientWidth) {
    const distance = inner.scrollWidth - outer.clientWidth
    inner.style.setProperty('--scroll-distance', `-${distance}px`)
    inner.classList.add('scrolling')
  }
}

const onCardMouseLeave = (e) => {
  const inner = e.currentTarget.querySelector('.tag-name-inner')
  if (inner) {
    inner.classList.remove('scrolling')
  }
}

const openCreateForm = () => {
  showCreateForm.value = true
}

defineExpose({ openCreateForm })
</script>

<template>
  <div class="tag-manager">
    <!-- Custom tags section -->
    <div class="tag-section">
      <div class="section-header">
        <h3>Custom Tags</h3>
      </div>

      <!-- Create form -->
      <div class="tag-create-form" v-if="showCreateForm">
        <div class="create-row">
          <div class="color-picker" @click.stop>
            <button class="color-picker-trigger" @click="toggleColorPicker('create')">
              <span class="color-dot" :style="{ background: newColor }"></span>
              <ChevronDown class="chevron" />
            </button>
            <div class="color-picker-dropdown" v-if="openColorPicker === 'create'">
              <button
                v-for="color in PRESET_COLORS"
                :key="color"
                class="color-swatch"
                :class="{ active: newColor === color }"
                :style="{ background: color }"
                @click="selectNewColor(color)"
              ></button>
            </div>
          </div>
          <input
            type="text"
            v-model="newName"
            placeholder="Tag name"
            class="tag-name-input"
            maxlength="50"
            @keyup.enter="handleCreate"
          />
        </div>
        <div class="form-actions">
          <button @click="handleCreate" :disabled="creating || !newName.trim()">
            <Loader2 v-if="creating" class="spin" />
            <Plus v-else />
            Create
          </button>
          <button class="secondary" @click="showCreateForm = false" :disabled="creating">
            Cancel
          </button>
        </div>
      </div>

      <!-- Custom tags grid -->
      <div class="tag-grid" v-if="customTags.length > 0">
        <div
          class="tag-card"
          v-for="tag in customTags"
          :key="tag.id"
          :class="{ editing: editingTagId === tag.id }"
          @mouseenter="onCardMouseEnter"
          @mouseleave="onCardMouseLeave"
        >
          <!-- Edit mode -->
          <template v-if="editingTagId === tag.id">
            <div class="color-picker" @click.stop>
              <button class="color-picker-trigger" @click="toggleColorPicker(tag.id)">
                <span class="color-dot" :style="{ background: editColor }"></span>
                <ChevronDown class="chevron" />
              </button>
              <div class="color-picker-dropdown" v-if="openColorPicker === tag.id">
                <button
                  v-for="color in PRESET_COLORS"
                  :key="color"
                  class="color-swatch"
                  :class="{ active: editColor === color }"
                  :style="{ background: color }"
                  @click="selectEditColor(color)"
                ></button>
              </div>
            </div>
            <input
              type="text"
              v-model="editName"
              class="tag-name-input tag-name-input--card"
              maxlength="50"
              @keyup.enter="saveEdit(tag)"
            />
            <div class="card-actions">
              <button class="secondary icon-only" @click="saveEdit(tag)" :disabled="savingEdit || !editName.trim()">
                <Loader2 v-if="savingEdit" class="spin" />
                <Check v-else />
              </button>
              <button class="secondary icon-only" @click="cancelEdit" :disabled="savingEdit">
                <X />
              </button>
            </div>
          </template>
          <!-- Display mode -->
          <template v-else>
            <div class="card-top">
              <span class="tag-color-dot" :style="{ background: tag.color || '#999' }"></span>
              <span class="tag-name" :title="tag.name">
                <span class="tag-name-inner">{{ tag.name }}</span>
              </span>
            </div>
            <div class="card-bottom">
              <span class="tag-count">{{ tag.files_count }} file{{ tag.files_count === 1 ? '' : 's' }}</span>
              <div class="card-actions">
                <button class="secondary icon-only" @click="startEdit(tag)" title="Edit">
                  <Edit3 />
                </button>
                <button class="secondary icon-only" @click="handleDelete(tag)" title="Delete">
                  <Trash2 />
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>
      <p class="empty-text" v-else-if="!showCreateForm">No custom tags yet</p>
    </div>

    <!-- Auto tags section -->
    <div class="tag-section" v-if="autoTags.length > 0">
      <div class="section-header">
        <h3>Auto Tags</h3>
        <span class="section-hint">Generated automatically from file metadata</span>
      </div>
      <div class="tag-list">
        <div class="tag-item tag-item--auto" v-for="tag in autoTags" :key="tag.id">
          <div class="tag-display">
            <span class="tag-name">{{ tag.name }}</span>
            <span class="tag-count">{{ tag.files_count }} file{{ tag.files_count === 1 ? '' : 's' }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.tag-manager {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.tag-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;

  h3 {
    margin: 0;
    font-size: 1rem;
    color: var(--panel-text-color);
  }

  .section-hint {
    font-size: 0.75rem;
    opacity: 0.5;
  }
}

.tag-create-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  background: var(--panel-section-background-color);
  border-radius: 6px;
}

.create-row {
  display: flex;
  gap: 8px;
  align-items: flex-start;
}

.tag-name-input {
  font-size: 0.85rem;
  height: 36px;

  &--card {
    width: 100%;
    height: 32px;
    font-size: 0.8rem;
  }
}

// Color picker dropdown
.color-picker {
  position: relative;
  flex-shrink: 0;
  width: 20px;
  height: 20px;
}

.color-picker-trigger {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 2px solid transparent;
  padding: 0;
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s;
  background: none;
  display: block;

  &:hover {
    transform: scale(1.15);
  }

  .color-dot {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    display: block;
  }

  .chevron {
    display: none;
  }
}

.color-picker-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  z-index: 20;
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 4px;
  padding: 8px;
  background: var(--panel-background-color);
  border: 1px solid var(--input-border-color);
  border-radius: 6px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
}

.color-swatch {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  transition: all 0.15s ease;
  padding: 0;

  &:hover {
    transform: scale(1.15);
  }

  &.active {
    border-color: white;
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.3);
  }
}

.form-actions {
  display: flex;
  gap: 8px;
  margin-top: 0px;
}

// Custom tags grid
.tag-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 6px;
}

.tag-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 10px;
  border-radius: 6px;
  background: var(--panel-section-background-color);
  text-align: center;
  min-height: 100px;
  overflow: hidden;

  &.editing {
    justify-content: flex-start;
    gap: 6px;
    padding: 10px;
  }
}

.card-top {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  width: 100%;
  min-width: 0;
}

.card-bottom {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.tag-color-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  flex-shrink: 0;
}

.tag-name {
  display: block;
  overflow: hidden;
  max-width: 100%;
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--panel-text-color);
}

.tag-name-inner {
  display: inline-block;
  white-space: nowrap;

  &.scrolling {
    animation: scroll-tag-name 3s ease-in-out infinite alternate;
  }
}

@keyframes scroll-tag-name {
  0%, 15% { transform: translateX(0); }
  85%, 100% { transform: translateX(var(--scroll-distance, 0)); }
}

.tag-count {
  font-size: 0.7rem;
  opacity: 0.5;
  white-space: nowrap;
}

.card-actions {
  display: flex;
  gap: 4px;

  button {
    width: 28px;
    height: 28px;
    padding: 0;

    svg {
      width: 13px;
      height: 13px;
      margin: 0;
    }
  }
}

// Auto tags list (keep as simple list)
.tag-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.tag-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 6px;
  background: var(--panel-section-background-color);

  &--auto {
    opacity: 0.7;
  }

  .tag-display {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
  }

  .tag-count {
    margin-left: auto;
  }
}

.empty-text {
  font-size: 0.85rem;
  opacity: 0.5;
  margin: 0;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
