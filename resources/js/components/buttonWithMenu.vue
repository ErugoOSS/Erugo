<script setup>
import { ref, computed, useSlots, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  items: {
    type: Array,
    required: true
  },
  secondary: {
    type: Boolean,
    default: true
  }
})

// Get access to the slots
const slots = useSlots()

// Check if the label slot exists
const iconOnly = computed(() => {
  return !slots.label
})

const visible = ref(false)

const toggleMenu = () => {
  visible.value = !visible.value
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

const handleClickOutside = (event) => {
  if (!event.target.closest('.button-with-menu')) {
    visible.value = false
  }
}

const handleItemClick = (item) => {
  if (typeof item.action === 'function') {
    item.action()
  }
  visible.value = false
}
</script>

<template>
  <div class="button-with-menu">
    <button :class="{ 'icon-only': iconOnly, 'secondary': secondary }" @click="toggleMenu">
      <slot name="icon" />
      <slot name="label" />
    </button>
    <div class="button-with-menu-dropdown" :class="{ visible: visible }">
      <div class="button-with-menu-dropdown-item" v-for="item in items" :key="item.label" @click="handleItemClick(item)">
        <Component :is="item.icon" />
        {{ item.label }}
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.button-with-menu {
  display: inline-block;
  position: relative;
}

.button-with-menu-dropdown {
  position: absolute;
  top: calc(100% + 5px);
  right: 0;
  background: var(--panel-item-background-color);
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 5px;
  border-radius: 5px;
  z-index: 100;
  opacity: 0;
  filter: blur(10px);
  transition: all 0.3s ease-in-out;
  transform: translateY(-10px);
  pointer-events: none;

  &.visible {
    opacity: 1;
    filter: blur(0px);
    transform: translateY(0px);
    pointer-events: auto;
  }

  .button-with-menu-dropdown-item {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    gap: 5px;
    cursor: pointer;
    font-size: 14px;
    padding: 10px 10px;
    border-radius: 5px;
    margin: 0;
    color: var(--panel-text-color);
    white-space: nowrap;

    svg {
      width: 15px;
      height: 15px;
      margin-top: 0px;
    }

    &.active {
      background: var(--primary-button-background-color);
      color: var(--primary-button-text-color);
    }

    &:hover {
      background: var(--primary-button-background-color-hover);
      color: var(--primary-button-text-color-hover);
    }

    span.fi {
      border-radius: 5px;
      width: 15px;
      margin-top: -1px;
    }
  }
}
</style>
