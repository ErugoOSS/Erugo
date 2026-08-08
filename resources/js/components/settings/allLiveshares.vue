<script setup>
import { ref, computed, onMounted, defineExpose } from 'vue'
import { getAllLiveshares, deleteLiveshare } from '../../api'
import {
  SquareArrowOutUpRight,
  Trash2,
  Rocket,
  FileText,
  User
} from 'lucide-vue-next'
import { useToast } from 'vue-toastification'
import { niceFileSize, niceDate } from '../../utils'
import { useTranslate } from '@tolgee/vue'

const emit = defineEmits(['ownersLoaded'])

const { t } = useTranslate()
const toast = useToast()
const liveshares = ref([])
const loaded = ref(false)
const selectedOwnerId = ref(null)

const visibleLiveshares = computed(() => {
  if (!selectedOwnerId.value) return liveshares.value
  return liveshares.value.filter((ls) => ls.owner?.id === selectedOwnerId.value)
})

onMounted(async () => {
  await loadLiveshares()
})

const loadLiveshares = async () => {
  try {
    liveshares.value = await getAllLiveshares()
    emit('ownersLoaded', collectOwners())
  } catch (error) {
    toast.error(t.value('settings.liveshares.error.load'))
  }
  loaded.value = true
}

const collectOwners = () => {
  const owners = new Map()
  liveshares.value.forEach((ls) => {
    if (ls.owner) {
      owners.set(ls.owner.id, ls.owner)
    }
  })
  return [...owners.values()].sort((a, b) => a.name.localeCompare(b.name))
}

const setOwnerFilter = (ownerId) => {
  selectedOwnerId.value = ownerId
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

defineExpose({
  setOwnerFilter
})
</script>

<template>
  <div>
    <table v-if="visibleLiveshares.length > 0">
      <thead>
        <tr>
          <th>{{ $t('settings.liveshares.table.name') }}</th>
          <th>{{ $t('settings.liveshares.table.owner') }}</th>
          <th>{{ $t('settings.liveshares.table.files') }}</th>
          <th>{{ $t('settings.liveshares.table.created') }}</th>
          <th>{{ $t('settings.liveshares.table.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="ls in visibleLiveshares" :key="ls.id">
          <td>
            <div class="liveshare-name">
              <strong>{{ ls.name }}</strong>
              <span class="liveshare-desc" v-if="ls.description">{{ ls.description }}</span>
            </div>
          </td>
          <td width="1" style="white-space: nowrap">
            <div class="owner-cell" v-if="ls.owner">
              <User class="owner-icon" />
              {{ ls.owner.name }}
            </div>
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
      <p>{{ $t('settings.liveshares.noneAll') }}</p>
    </div>
    <div v-else class="center-message">
      <p>{{ $t('settings.liveshares.loading') }}</p>
    </div>
  </div>
</template>

<style lang="scss" scoped>
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

.owner-cell {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.85rem;

  .owner-icon {
    width: 14px;
    height: 14px;
    opacity: 0.6;
  }
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
</style>
