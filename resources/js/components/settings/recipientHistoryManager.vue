<script setup>
import { ref, onMounted } from 'vue'
import { getAllRecipientHistories, updateRecipientHistory, deleteRecipientHistory } from '../../api'
import { Pencil, Trash2, RefreshCw } from 'lucide-vue-next'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()
const toast = useToast()

const recipientHistories = ref([])
const loading = ref(false)
const editingId = ref(null)
const editForm = ref({
  email: '',
  name: ''
})

const loadRecipientHistories = async () => {
  loading.value = true
  try {
    recipientHistories.value = await getAllRecipientHistories()
  } catch (error) {
    toast.error(t.value('settings.error.loadRecipientHistories') || 'Failed to load recipient histories')
  } finally {
    loading.value = false
  }
}

const startEdit = (item) => {
  editingId.value = item.id
  editForm.value = {
    email: item.email,
    name: item.name || ''
  }
}

const cancelEdit = () => {
  editingId.value = null
  editForm.value = {
    email: '',
    name: ''
  }
}

const saveEdit = async (id) => {
  try {
    await updateRecipientHistory(id, editForm.value.email, editForm.value.name)
    toast.success(t.value('settings.success.recipientHistoryUpdated') || 'Recipient history updated')
    cancelEdit()
    await loadRecipientHistories()
  } catch (error) {
    toast.error(t.value('settings.error.updateRecipientHistory') || 'Failed to update recipient history')
  }
}

const deleteItem = async (id) => {
  if (!confirm(t.value('settings.confirm.deleteRecipientHistory') || 'Are you sure you want to delete this recipient?')) {
    return
  }
  try {
    await deleteRecipientHistory(id)
    toast.success(t.value('settings.success.recipientHistoryDeleted') || 'Recipient history deleted')
    await loadRecipientHistories()
  } catch (error) {
    toast.error(t.value('settings.error.deleteRecipientHistory') || 'Failed to delete recipient history')
  }
}

onMounted(() => {
  loadRecipientHistories()
})
</script>

<template>
  <div class="recipient-history-manager">
    <div class="header">
      <h3>{{ $t('settings.title.recipientHistory') || 'Recipient-list' }}</h3>
      <button @click="loadRecipientHistories" class="icon-button" :disabled="loading">
        <RefreshCw :class="{ spinning: loading }" />
      </button>
    </div>

    <div v-if="loading && recipientHistories.length === 0" class="loading">
      {{ $t('settings.loading') || 'Loading...' }}
    </div>

    <div v-else-if="recipientHistories.length === 0" class="empty">
      {{ $t('settings.noRecipientHistories') || 'No recipient histories found' }}
    </div>

    <table v-else class="recipient-table">
      <thead>
        <tr>
          <th>{{ $t('settings.table.name') || 'Name' }}</th>
          <th>{{ $t('settings.table.email') || 'Email' }}</th>
          <th>{{ $t('settings.table.lastUsed') || 'Last Used' }}</th>
          <th>{{ $t('settings.table.useCount') || 'Use Count' }}</th>
          <th>{{ $t('settings.table.actions') || 'Actions' }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in recipientHistories" :key="item.id">
          <td v-if="editingId === item.id">
            <input v-model="editForm.name" type="text" class="edit-input" />
          </td>
          <td v-else>{{ item.name || '-' }}</td>
          
          <td v-if="editingId === item.id">
            <input v-model="editForm.email" type="email" class="edit-input" />
          </td>
          <td v-else>{{ item.email }}</td>
          
          <td>{{ item.last_used_at ? new Date(item.last_used_at).toLocaleDateString() : '-' }}</td>
          <td>{{ item.use_count || 0 }}</td>
          
          <td class="actions">
            <template v-if="editingId === item.id">
              <button @click="saveEdit(item.id)" class="save-button">
                ✓
              </button>
              <button @click="cancelEdit" class="cancel-button">
                ✗
              </button>
            </template>
            <template v-else>
              <button @click="startEdit(item)" class="icon-button edit-button" :title="$t('settings.button.edit') || 'Edit'">
                <Pencil />
              </button>
              <button @click="deleteItem(item.id)" class="icon-button delete-button" :title="$t('settings.button.delete') || 'Delete'">
                <Trash2 />
              </button>
            </template>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped lang="scss">
.recipient-history-manager {
  padding: 20px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;

  h3 {
    margin: 0;
    font-size: 18px;
  }
}

.icon-button {
  background: var(--panel-section-background-color);
  border: 1px solid var(--panel-section-border-color);
  border-radius: 5px;
  padding: 5px 10px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  font-size: 14px;
  color: var(--panel-section-text-color);
  transition: all 0.2s;

  &:hover:not(:disabled) {
    background: var(--panel-section-background-color-alt);
  }

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  svg {
    width: 16px;
    height: 16px;
  }

  &.edit-button {
    color: var(--primary-button-text-color);
  }

  &.delete-button {
    color: #ef4444;
  }
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.loading,
.empty {
  text-align: center;
  padding: 40px;
  color: var(--panel-section-text-color);
}

.recipient-table {
  width: 100%;
  border-collapse: collapse;

  th,
  td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid var(--panel-section-border-color);
  }

  th {
    background: var(--panel-section-background-color-alt);
    font-weight: 600;
    color: var(--panel-section-text-color);
  }

  td {
    color: var(--panel-section-text-color);
  }

  .actions {
    display: flex;
    gap: 5px;
  }

  .edit-input {
    width: 100%;
    padding: 5px;
    border: 1px solid var(--panel-section-border-color);
    border-radius: 3px;
    background: var(--panel-section-background-color);
    color: var(--panel-section-text-color);
    font-size: 14px;

    &:focus {
      outline: none;
      border-color: var(--primary-button-background-color);
    }
  }

  .save-button,
  .cancel-button {
    padding: 5px 10px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
  }

  .save-button {
    background: #22c55e;
    color: white;

    &:hover {
      background: #16a34a;
    }
  }

  .cancel-button {
    background: #ef4444;
    color: white;

    &:hover {
      background: #dc2626;
    }
  }
}
</style>
