<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Check, Copy, Download, Info, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  x: { type: Number, required: true },
  y: { type: Number, required: true },
  file: { type: Object, required: true },
  selectedCount: { type: Number, default: 0 },
  isSelected: { type: Boolean, default: false },
  canRemoveFiles: { type: Boolean, default: false }
})

const emit = defineEmits(['close', 'download', 'remove', 'details', 'toggleSelect', 'copyName'])

const menuRef = ref(null)
const position = ref({ left: props.x, top: props.y })

const MENU_WIDTH = 200
const MENU_HEIGHT = 190
const EDGE_GAP = 8

const clampToViewport = () => {
  const left = Math.min(props.x, window.innerWidth - MENU_WIDTH - EDGE_GAP)
  const top = Math.min(props.y, window.innerHeight - MENU_HEIGHT - EDGE_GAP)
  position.value = { left: Math.max(EDGE_GAP, left), top: Math.max(EDGE_GAP, top) }
}

const downloadLabel = computed(() =>
  props.selectedCount > 1 && props.isSelected ? `Download ${props.selectedCount} files` : 'Download'
)

const removeLabel = computed(() =>
  props.selectedCount > 1 && props.isSelected ? `Remove ${props.selectedCount} files` : 'Remove'
)

const handleClickOutside = (e) => {
  if (menuRef.value && !menuRef.value.contains(e.target)) {
    emit('close')
  }
}

const handleKeydown = (e) => {
  if (e.key === 'Escape') emit('close')
}

onMounted(() => {
  clampToViewport()
  setTimeout(() => {
    document.addEventListener('click', handleClickOutside, true)
    document.addEventListener('contextmenu', handleClickOutside, true)
  }, 0)
  document.addEventListener('keydown', handleKeydown)
  window.addEventListener('resize', clampToViewport)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside, true)
  document.removeEventListener('contextmenu', handleClickOutside, true)
  document.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('resize', clampToViewport)
})
</script>

<template>
  <Teleport to="body">
    <div
      class="context-menu"
      ref="menuRef"
      :style="{ left: position.left + 'px', top: position.top + 'px' }"
    >
      <button class="menu-item" @click="emit('download')">
        <Download />
        {{ downloadLabel }}
      </button>
      <button class="menu-item" @click="emit('details')">
        <Info />
        Details
      </button>
      <button class="menu-item" @click="emit('toggleSelect')">
        <Check />
        {{ isSelected ? 'Deselect' : 'Select' }}
      </button>
      <button class="menu-item" @click="emit('copyName')">
        <Copy />
        Copy name
      </button>
      <template v-if="canRemoveFiles">
        <div class="menu-divider"></div>
        <button class="menu-item menu-item--danger" @click="emit('remove')">
          <Trash2 />
          {{ removeLabel }}
        </button>
      </template>
    </div>
  </Teleport>
</template>

<style lang="scss" scoped>
.context-menu {
  position: fixed;
  z-index: 300;
  width: 200px;
  padding: 5px;
  background: var(--panel-background-color);
  border-radius: var(--panel-border-radius);
  box-shadow: 0 8px 28px rgba(0, 0, 0, 0.3);
}

.menu-item {
  width: 100%;
  height: auto;
  margin: 0;
  padding: 8px 10px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 9px;
  border: none;
  border-radius: calc(var(--panel-border-radius) - 4px);
  background: none;
  color: var(--panel-text-color);
  font-size: 0.8rem;
  text-align: left;
  cursor: pointer;

  svg {
    width: 14px;
    height: 14px;
    margin: 0;
    flex-shrink: 0;
    opacity: 0.6;
  }

  &:hover {
    background: var(--panel-section-background-color);
  }

  &--danger:hover {
    background: var(--color-danger);
    color: white;

    svg {
      opacity: 1;
    }
  }
}

.menu-divider {
  height: 1px;
  margin: 4px 6px;
  background: var(--panel-section-background-color-alt);
}
</style>
