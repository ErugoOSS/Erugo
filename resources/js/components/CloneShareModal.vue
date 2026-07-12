<script setup>
import { ref } from 'vue'
import { cloneShare } from '../api'
import { useToast } from 'vue-toastification'
import { Copy, X } from 'lucide-vue-next'

const props = defineProps({
  share: { type: Object, required: true }
})
const emit = defineEmits(['close', 'done'])

const toast = useToast()
const newName = ref(props.share.name + ' (clone)')
const loading = ref(false)
const error = ref(null)

const handleClone = async () => {
  loading.value = true
  error.value = null
  try {
    await cloneShare(props.share.id, newName.value || null)
    toast.success('Share cloned successfully')
    emit('done')
    emit('close')
  } catch (e) {
    error.value = e.message || 'Clone failed'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="modal-backdrop" @click.self="!loading && emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <Copy />
        <h3>Clone share</h3>
        <button class="close-btn" @click="emit('close')" :disabled="loading"><X /></button>
      </div>

      <div class="clone-body">
        <label class="clone-label">Name for the clone</label>
        <input
          v-model="newName"
          type="text"
          class="clone-input"
          :disabled="loading"
          @keyup.enter="handleClone"
        />
      </div>

      <div v-if="error" class="error-msg">{{ error }}</div>

      <div class="modal-footer">
        <button class="secondary" @click="emit('close')" :disabled="loading">Cancel</button>
        <button @click="handleClone" :disabled="loading || !newName">
          <Copy />
          {{ loading ? 'Cloning…' : 'Clone' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-box {
  background: var(--panel-section-background-color);
  border-radius: 10px;
  padding: 24px;
  min-width: 360px;
  max-width: 480px;
  width: 100%;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.modal-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;

  svg { width: 1.2rem; height: 1.2rem; color: var(--panel-section-text-color); }
  h3 { flex: 1; margin: 0; font-size: 1.1rem; color: var(--panel-section-text-color); }

  .close-btn {
    background: none; border: none; padding: 4px; cursor: pointer;
    display: flex; align-items: center; color: var(--panel-section-text-color); opacity: 0.6;
    &:hover { opacity: 1; }
    &:disabled { cursor: not-allowed; }
    svg { width: 1rem; height: 1rem; }
  }
}

.clone-body {
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.clone-label {
  font-size: 0.85rem;
  color: var(--panel-section-text-color);
  opacity: 0.7;
}

.clone-input {
  width: 100%;
  padding: 10px 12px;
  border-radius: 6px;
  border: 1px solid rgba(128, 128, 128, 0.3);
  background: var(--panel-section-background-color-alt);
  color: var(--panel-section-text-color);
  font-size: 0.9rem;
  box-sizing: border-box;
  &:focus { outline: 2px solid var(--primary-color, #4f6ef7); border-color: transparent; }
  &:disabled { opacity: 0.5; }
}

.error-msg {
  background: rgba(220, 53, 69, 0.1);
  border: 1px solid rgba(220, 53, 69, 0.3);
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 0.85rem;
  color: #dc3545;
  margin-bottom: 16px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
