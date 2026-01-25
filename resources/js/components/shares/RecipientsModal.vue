<script setup>
import { ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'
import { getShareRecipients, updateShareRecipients, resendShareRecipientEmails } from '../../api'

const props = defineProps({
  modelValue: { type: Boolean, default: false }, // v-model
  share: { type: Object, default: null },        // selected share
})

const emit = defineEmits(['update:modelValue'])

const toast = useToast()
const { t } = useTranslate()

const recipientsLoading = ref(false)
const recipientsError = ref('')
const recipientEmails = ref([{ name: '', email: '' }])

const close = () => {
  emit('update:modelValue', false)
}

watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    if (!props.share?.id) {
      recipientsError.value = 'No share selected'
      recipientEmails.value = [{ name: '', email: '' }]
      return
    }

    recipientsError.value = ''
    recipientsLoading.value = true

    try {
      const res = await getShareRecipients(props.share.id)
      const json = await res.json().catch(() => ({}))

      if (!res.ok) {
        recipientsError.value = json?.message || `Failed to load recipients (${res.status})`
        recipientEmails.value = [{ name: '', email: '' }]
        return
      }

      const data = json?.data?.recipients || []
      recipientEmails.value = data.length
        ? data.map(r => ({ name: r.name || '', email: r.email || '' }))
        : [{ name: '', email: '' }]
    } catch (e) {
      recipientsError.value = 'Failed to load recipients'
      recipientEmails.value = [{ name: '', email: '' }]
    } finally {
      recipientsLoading.value = false
    }
  }
)

const addRecipientRow = () => recipientEmails.value.push({ name: '', email: '' })

const removeRecipientRow = (idx) => {
  if (recipientEmails.value.length === 1) return
  recipientEmails.value.splice(idx, 1)
}

const saveRecipients = async () => {
  if (!props.share?.id) return
  recipientsError.value = ''

  const payload = recipientEmails.value
    .map(r => ({ name: (r?.name || '').trim(), email: (r?.email || '').trim().toLowerCase() }))
    .filter(r => r.email)
    .filter((r, idx, arr) => arr.findIndex(x => x.email === r.email) === idx)

  try {
    const res = await updateShareRecipients(props.share.id, payload)
    const json = await res.json().catch(() => ({}))

    if (!res.ok) {
      recipientsError.value = json?.message || `Unable to save recipients (${res.status})`
      return
    }

    toast.success(t.value('settings.success.shareRecipientsSaved') || 'Recipients saved')
  } catch (e) {
    recipientsError.value = 'Unable to save recipients'
  }
}

const resendRecipients = async () => {
  if (!props.share?.id) return
  recipientsError.value = ''

  try {
    const res = await resendShareRecipientEmails(props.share.id)
    const json = await res.json().catch(() => ({}))

    if (!res.ok) {
      recipientsError.value = json?.message || `Unable to resend emails (${res.status})`
      return
    }

    toast.success(t.value('settings.success.shareEmailsResent') || 'Emails resent')
  } catch (e) {
    recipientsError.value = 'Unable to resend emails'
  }
}
</script>

<template>
  <div class="recipients-modal-overlay" :class="{ active: modelValue }" @click.self="close">
    <div class="recipients-modal-form">
      <div class="recipients-modal-header">
        <h3>{{ t.value('share.button.recipients') || 'Recipients' }}</h3>
        <button class="close-btn icon-only secondary" @click="close">×</button>
      </div>

      <div class="recipients-modal-content">
        <div v-if="recipientsLoading">Loading…</div>
        <div v-else>
          <div v-if="recipientsError" class="error">{{ recipientsError }}</div>

          <div v-for="(r, idx) in recipientEmails" :key="idx" class="recipient-row">
            <input v-model="r.name" type="text" placeholder="Name (optional)" />
            <input v-model="r.email" type="email" placeholder="email@example.com" />
            <button class="secondary" @click="removeRecipientRow(idx)" :disabled="recipientEmails.length === 1">
              Remove
            </button>
          </div>

          <button class="secondary" @click="addRecipientRow">Add</button>
        </div>
      </div>

      <div class="button-bar">
        <button class="secondary" @click="close">Close</button>
        <button class="secondary" @click="saveRecipients" :disabled="recipientsLoading">Save</button>
        <button class="clear-button" @click="resendRecipients" :disabled="recipientsLoading">Resend</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.recipients-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.15s ease;
  z-index: 9999;
}
.recipients-modal-overlay.active { opacity: 1; pointer-events: auto; }
.recipients-modal-form {
  width: min(900px, 92vw);
  max-height: 80vh;
  overflow: auto;
  background: var(--panel-section-background-color);
  border-radius: 10px;
  padding: 16px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.25);
}
.recipients-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}
.recipient-row { display: flex; gap: 10px; margin-bottom: 10px; }
.button-bar { display: flex; gap: 10px; justify-content: flex-end; margin-top: 12px; }
.error {
  padding: 10px;
  border-radius: 8px;
  margin-bottom: 10px;
  background: rgba(255, 0, 0, 0.08);
}
</style>
