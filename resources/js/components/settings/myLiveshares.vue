<script setup>
import { ref, onMounted, defineExpose } from 'vue'
import { getLiveshares, createLiveshare, deleteLiveshare } from '../../api'
import {
  SquareArrowOutUpRight,
  Plus,
  Trash2,
  Rocket,
  CircleX,
  FileText,
  Loader2
} from 'lucide-vue-next'
import { useToast } from 'vue-toastification'
import { niceFileSize, niceDate } from '../../utils'
import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()
const toast = useToast()
const liveshares = ref([])
const loaded = ref(false)
const creating = ref(false)

const showCreateForm = ref(false)
const newName = ref('')
const newDescription = ref('')

onMounted(async () => {
  await loadLiveshares()
})

const loadLiveshares = async () => {
  try {
    liveshares.value = await getLiveshares()
  } catch (error) {
    toast.error(t.value('settings.liveshares.error.load'))
  }
  loaded.value = true
}

const handleCreateLiveshare = async () => {
  if (!newName.value.trim()) {
    toast.error(t.value('settings.liveshares.error.nameRequired'))
    return
  }

  creating.value = true
  try {
    await createLiveshare(newName.value.trim(), newDescription.value.trim() || null)
    toast.success(t.value('settings.liveshares.success.created'))
    creating.value = false
    closeCreateForm()
    await loadLiveshares()
  } catch (error) {
    toast.error(error.message || t.value('settings.liveshares.error.create'))
  }
  creating.value = false
}

const handleDeleteLiveshare = async (liveshare) => {
  const confirmed = confirm(t.value('settings.liveshares.confirmDelete', { name: liveshare.name }))
  if (!confirmed) return

  try {
    await deleteLiveshare(liveshare.long_id)
    toast.success(t.value('settings.liveshares.success.deleted'))
    await loadLiveshares()
  } catch (error) {
    toast.error(error.message || t.value('settings.liveshares.error.delete'))
  }
}

const openWorkspace = (liveshare) => {
  window.open(`/liveshares/${liveshare.long_id}`, '_blank')
}

const openCreateForm = () => {
  showCreateForm.value = true
}

const closeCreateForm = () => {
  if (creating.value) return
  showCreateForm.value = false
  newName.value = ''
  newDescription.value = ''
}

const createFormClickOutside = (event) => {
  if (!event.target.closest('.liveshare-form')) {
    closeCreateForm()
  }
}

const roleBadgeClass = (role) => {
  return {
    'role-badge': true,
    'role-owner': role === 'owner',
    'role-manager': role === 'manager',
    'role-collaborator': role === 'collaborator',
    'role-viewer': role === 'viewer'
  }
}

defineExpose({
  openCreateForm
})
</script>

<template>
  <div>
    <table v-if="liveshares.length > 0">
      <thead>
        <tr>
          <th>{{ $t('settings.liveshares.table.name') }}</th>
          <th>{{ $t('settings.liveshares.table.role') }}</th>
          <th>{{ $t('settings.liveshares.table.files') }}</th>
          <th>{{ $t('settings.liveshares.table.created') }}</th>
          <th>{{ $t('settings.liveshares.table.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="ls in liveshares" :key="ls.id">
          <td>
            <div class="liveshare-name">
              <strong>{{ ls.name }}</strong>
              <span class="liveshare-desc" v-if="ls.description">{{ ls.description }}</span>
            </div>
          </td>
          <td width="1" style="white-space: nowrap">
            <span :class="roleBadgeClass(ls.my_role)">{{ ls.my_role }}</span>
          </td>
          <td width="1" style="white-space: nowrap">
            <div class="stat-cell">
              <FileText class="stat-icon" />
              {{ $t('settings.liveshares.fileCount', { count: ls.file_count }) }}
              <span class="stat-size">({{ niceFileSize(ls.size) }})</span>
            </div>
          </td>
          <td width="1" style="white-space: nowrap">{{ niceDate(ls.created_at) }}</td>
          <td width="1" style="white-space: nowrap">
            <div class="action-buttons">
              <button class="secondary" @click="openWorkspace(ls)" :title="$t('settings.liveshares.openWorkspace')">
                <SquareArrowOutUpRight />
                {{ $t('settings.liveshares.open') }}
              </button>
              <button
                class="clear-button icon-only"
                @click="handleDeleteLiveshare(ls)"
                :title="$t('settings.liveshares.delete')"
                v-if="ls.my_role === 'owner'"
              >
                <Trash2 />
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-else-if="loaded" class="center-message">
      <Rocket />
      <p>{{ $t('settings.liveshares.noneMine') }}</p>
    </div>
    <div v-else class="center-message">
      <p>{{ $t('settings.liveshares.loading') }}</p>
    </div>
  </div>

  <div class="liveshare-form-overlay" :class="{ active: showCreateForm }" @click="createFormClickOutside">
    <div class="liveshare-form">
      <h2>
        <Plus />
        {{ $t('settings.liveshares.create.title') }}
      </h2>
      <p>{{ $t('settings.liveshares.create.description') }}</p>
      <div class="input-container">
        <label for="new_liveshare_name">{{ $t('settings.liveshares.create.nameLabel') }}</label>
        <input
          type="text"
          id="new_liveshare_name"
          v-model="newName"
          :placeholder="$t('settings.liveshares.create.namePlaceholder')"
          maxlength="255"
          required
          @keyup.enter="handleCreateLiveshare"
        />
      </div>
      <div class="input-container">
        <label for="new_liveshare_description">{{ $t('settings.liveshares.create.descriptionLabel') }}</label>
        <textarea
          id="new_liveshare_description"
          v-model="newDescription"
          :placeholder="$t('settings.liveshares.create.descriptionPlaceholder')"
          maxlength="1000"
          rows="3"
        ></textarea>
      </div>
      <div class="button-bar">
        <button @click="handleCreateLiveshare" :disabled="creating || !newName.trim()">
          <Loader2 v-if="creating" class="spin" />
          <Plus v-else />
          {{ $t('settings.liveshares.create.submit') }}
        </button>
        <button class="secondary close-button" @click="closeCreateForm" :disabled="creating">
          <CircleX />
          {{ $t('settings.close') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.liveshare-form-overlay {
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

  .liveshare-form {
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

    textarea {
      resize: vertical;
    }

    button {
      display: block;
      width: 100%;
    }
  }

  &.active {
    opacity: 1;
    pointer-events: auto;
    .liveshare-form {
      transform: translate(-50%, 0%);
    }
  }
}

.liveshare-name {
  display: flex;
  flex-direction: column;
  gap: 2px;

  strong {
    font-size: 0.95rem;
  }

  .liveshare-desc {
    font-size: 0.75rem;
    color: var(--panel-section-text-color);
    opacity: 0.7;
  }
}

.role-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
}

.role-owner {
  background: var(--primary-button-background-color);
  color: var(--primary-button-text-color);
}

.role-manager {
  background: var(--panel-section-background-color-alt);
  color: var(--panel-section-text-color);
}

.role-collaborator {
  background: var(--panel-section-background-color-alt);
  color: var(--panel-section-text-color);
}

.role-viewer {
  background: var(--panel-section-background-color);
  color: var(--panel-section-text-color);
  opacity: 0.8;
}

.stat-cell {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.85rem;

  .stat-icon {
    width: 14px;
    height: 14px;
    opacity: 0.6;
  }

  .stat-size {
    font-size: 0.75rem;
    opacity: 0.6;
  }
}

.action-buttons {
  display: flex;
  gap: 5px;
  align-items: center;
}

.center-message {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  width: 100%;
  min-height: 300px;
  font-size: 1.5rem;
  color: var(--panel-section-text-color);
  svg {
    width: 4rem;
    height: 4rem;
    margin-right: 10px;
    margin-top: -20px;
  }
}

.spin {
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
</style>
