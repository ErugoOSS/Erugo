<script setup>
import { ref, computed } from 'vue'
import { extendShare } from '../api'
import { useToast } from 'vue-toastification'
import { CalendarPlus, X } from 'lucide-vue-next'

const props = defineProps({
  share: {
    type: Object,
    required: true
  },
  isAdmin: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'extended'])

const toast = useToast()

// Modes: 'relative' | 'date' | 'unlimited'
const mode = ref('relative')

const amount = ref(7)
const unit = ref('days')
const specificDate = ref('')

const submitting = ref(false)

const currentExpiry = computed(() => {
  if (!props.share.expires_at) return 'No expiration'
  return new Date(props.share.expires_at).toLocaleDateString()
})

const minDate = computed(() => {
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  return tomorrow.toISOString().split('T')[0]
})

const handleSubmit = async () => {
  submitting.value = true
  try {
    let payload = {}

    if (mode.value === 'unlimited') {
      payload = { unlimited: true }
    } else if (mode.value === 'date') {
      if (!specificDate.value) {
        toast.error('Please select a date')
        submitting.value = false
        return
      }
      payload = { expires_at: specificDate.value }
    } else {
      const amt = parseInt(amount.value)
      if (isNaN(amt) || amt < 1) {
        toast.error('Please enter a valid amount')
        submitting.value = false
        return
      }
      payload = { amount: amt, unit: unit.value }
    }

    await extendShare(props.share.id, payload)
    toast.success('Share expiration updated')
    emit('extended')
    emit('close')
  } catch (err) {
    toast.error(err.message || 'Failed to update expiration')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <CalendarPlus />
        <h3>Extend Share</h3>
        <button class="close-btn" @click="emit('close')"><X /></button>
      </div>

      <div class="current-expiry">
        <span>Current expiry:</span>
        <strong>{{ currentExpiry }}</strong>
      </div>

      <div v-if="isAdmin" class="mode-tabs">
        <button :class="{ active: mode === 'relative' }" @click="mode = 'relative'">Extend by</button>
        <button :class="{ active: mode === 'date' }" @click="mode = 'date'">Set date</button>
        <button :class="{ active: mode === 'unlimited' }" @click="mode = 'unlimited'">No expiration</button>
      </div>

      <div class="modal-body">
        <!-- Relative extension (all users) -->
        <div v-if="mode === 'relative'" class="relative-picker">
          <input
            type="number"
            v-model="amount"
            min="1"
            max="3650"
            class="amount-input"
          />
          <select v-model="unit" class="unit-select">
            <option value="days">Days</option>
            <option value="weeks">Weeks</option>
            <option value="months">Months</option>
          </select>
        </div>

        <!-- Admin: specific date -->
        <div v-else-if="mode === 'date'" class="date-picker">
          <input
            type="date"
            v-model="specificDate"
            :min="minDate"
            class="date-input"
          />
        </div>

        <!-- Admin: no expiration -->
        <div v-else-if="mode === 'unlimited'" class="unlimited-info">
          <p>This share will never expire and will not be automatically cleaned up.</p>
        </div>
      </div>

      <div class="modal-footer">
        <button class="secondary" @click="emit('close')" :disabled="submitting">Cancel</button>
        <button @click="handleSubmit" :disabled="submitting">
          {{ submitting ? 'Saving...' : 'Save' }}
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
  min-width: 340px;
  max-width: 480px;
  width: 100%;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.modal-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;

  svg {
    width: 1.3rem;
    height: 1.3rem;
    color: var(--panel-section-text-color);
  }

  h3 {
    flex: 1;
    margin: 0;
    font-size: 1.1rem;
    color: var(--panel-section-text-color);
  }

  .close-btn {
    background: none;
    border: none;
    padding: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    color: var(--panel-section-text-color);
    opacity: 0.6;

    &:hover {
      opacity: 1;
    }

    svg {
      width: 1rem;
      height: 1rem;
    }
  }
}

.current-expiry {
  background: var(--panel-section-background-color-alt);
  border-radius: 6px;
  padding: 8px 12px;
  margin-bottom: 16px;
  font-size: 0.85rem;
  display: flex;
  gap: 8px;
  align-items: center;
  color: var(--panel-section-text-color);

  strong {
    font-weight: 600;
  }
}

.mode-tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 16px;

  button {
    flex: 1;
    padding: 6px 10px;
    font-size: 0.8rem;
    border-radius: 6px;
    background: var(--panel-section-background-color-alt);
    border: 2px solid transparent;
    color: var(--panel-section-text-color);
    cursor: pointer;
    transition: border-color 0.15s;

    &.active {
      border-color: var(--primary-color, #4f6ef7);
    }

    &:hover:not(.active) {
      opacity: 0.8;
    }
  }
}

.modal-body {
  margin-bottom: 20px;
}

.relative-picker {
  display: flex;
  gap: 10px;

  .amount-input {
    flex: 1;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid var(--panel-section-background-color-alt);
    background: var(--panel-section-background-color-alt);
    color: var(--panel-section-text-color);
    font-size: 1rem;
  }

  .unit-select {
    flex: 1.5;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid var(--panel-section-background-color-alt);
    background: var(--panel-section-background-color-alt);
    color: var(--panel-section-text-color);
    font-size: 1rem;
  }
}

.date-picker {
  .date-input {
    width: 100%;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid var(--panel-section-background-color-alt);
    background: var(--panel-section-background-color-alt);
    color: var(--panel-section-text-color);
    font-size: 1rem;
    box-sizing: border-box;
  }
}

.unlimited-info {
  background: var(--panel-section-background-color-alt);
  border-radius: 6px;
  padding: 12px;
  font-size: 0.85rem;
  color: var(--panel-section-text-color);

  p {
    margin: 0;
  }
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
