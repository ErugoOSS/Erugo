<script setup>
import { CircleX, AlertTriangle } from 'lucide-vue-next'
import { useConfirmDialog } from '../composables/useConfirmDialog'

const { isActive, title, message, okText, cancelText, confirm, cancel } = useConfirmDialog()

const handleClickOutside = (event) => {
  if (!event.target.closest('.confirm-dialog-form')) {
    cancel()
  }
}
</script>

<template>
  <div class="confirm-dialog-overlay" :class="{ active: isActive }" @click="handleClickOutside">
    <div class="confirm-dialog-form">
      <h2>
        <AlertTriangle />
        {{ title }}
      </h2>
      <p v-if="message">{{ message }}</p>
      <div class="button-bar">
        <button @click="confirm">
          {{ okText }}
        </button>
        <button class="secondary close-button" @click="cancel">
          <CircleX />
          {{ cancelText }}
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.confirm-dialog-overlay {
  border-radius: 10px 10px 0 0;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: var(--overlay-background-color);
  backdrop-filter: blur(10px);
  z-index: 230;
  opacity: 0;
  pointer-events: none;
  transition: all 0.3s ease;

  h2 {
    margin-bottom: 10px;
    font-size: 24px;
    color: var(--panel-text-color);
    display: flex;
    align-items: center;
    justify-content: center;

    svg {
      width: 24px;
      height: 24px;
      margin-right: 10px;
    }
  }

  p {
    color: var(--panel-text-color);
    margin-bottom: 20px;
    text-align: left;
  }

  .confirm-dialog-form {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translate(-50%, 100%);
    width: min(500px, 100vw);
    background: var(--panel-background-color);
    color: var(--panel-text-color);
    padding: 20px;
    border-radius: 10px 10px 0 0;
    box-shadow: 0 0 100px 0 rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;
    gap: 10px;
    transition: all 0.3s ease;
    padding-bottom: 20px;

    button {
      display: block;
      width: 100%;
    }
  }

  &.active {
    opacity: 1;
    pointer-events: auto;
    .confirm-dialog-form {
      transform: translate(-50%, 0%);
    }
  }
}
</style>

