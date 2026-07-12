<script setup>
import { ref, computed, onMounted, inject, defineExpose } from 'vue'
import { getMyShares, expireShare, extendShare, setDownloadLimit, pruneExpiredShares, requestShareDeletion, undoShareDeletion } from '../../api'
import {
  SquareArrowOutUpRight,
  CalendarPlus,
  CalendarX2,
  HardDriveDownload,
  MessageCircleQuestion,
  Rocket,
  Lock,
  LockOpen,
  ArrowLeftRight,
  Trash2,
  Undo2,
  Clock
} from 'lucide-vue-next'
import { useToast } from 'vue-toastification'
import { niceFileSize, niceDate, niceFileName, niceNumber } from '../../utils'
import HelpTip from '../helpTip.vue'
import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()

const showHelpTip = inject('showHelpTip')
const hideHelpTip = inject('hideHelpTip')

const toast = useToast()
const maxFilesToShow = 4
const loadedShares = ref(false)

const shares = ref([])
const showDeletedShares = ref(false)
onMounted(async () => {
  showDeletedShares.value = localStorage.getItem('showDeletedShares') === 'true'
  loadShares()
})

const loadShares = async () => {
  shares.value = await getMyShares(showDeletedShares.value)
  loadedShares.value = true
}

const activeShares = computed(() =>
  shares.value.filter(s => s.status !== 'pending_deletion')
)

const pendingDeletionShares = computed(() =>
  shares.value.filter(s => s.status === 'pending_deletion')
)

const handleExpireShareClick = async (share) => {
  expireShare(share.id)
    .then(() => {
      toast.success(t.value('settings.success.shareExpired'))
      loadShares()
    })
    .catch((error) => {
      toast.error(t.value('settings.error.shareExpired'))
    })
}

const handleExtendShareClick = async (share) => {
  extendShare(share.id)
    .then(() => {
      toast.success(t.value('settings.success.shareExtended'))
      loadShares()
    })
    .catch((error) => {
      toast.error(t.value('settings.error.shareExtended'))
    })
}

const handleDownloadLimitChange = async (share) => {
  let newLimit = null
  if (share.download_limit == '' || share.download_limit == null) {
    newLimit = -1
  } else {
    newLimit = parseInt(share.download_limit)
  }

  if (isNaN(newLimit)) {
    return
  }
  setDownloadLimit(share.id, newLimit)
    .then(() => {
      toast.success('Download limit changed')
      loadShares()
    })
    .catch((error) => {
      toast.error('Failed to change download limit')
    })
}

const downloadShare = async (share) => {
  window.location.href = `/api/shares/${share.long_id}/download`
}

const enableExpireShareButton = (share) => {
  return !share.expired && !share.deleted
}

const enableExtendShareButton = (share) => {
  return !share.deleted
}

const enableDownloadButton = (share) => {
  return !share.expired && !share.deleted
}

const enableRequestDeletionButton = (share) => {
  return !share.deleted && !share.pending_deletion
}

const handleRequestDeletionClick = async (share) => {
  const confirmed = confirm(t.value('settings.confirm.requestDeletion'))
  if (!confirmed) return
  requestShareDeletion(share.id)
    .then(() => {
      toast.success(t.value('settings.success.requestDeletion'))
      loadShares()
    })
    .catch(() => {
      toast.error(t.value('settings.error.requestDeletion'))
    })
}

const handleUndoDeletionClick = async (share) => {
  undoShareDeletion(share.id)
    .then(() => {
      toast.success(t.value('settings.success.undoDeletion'))
      loadShares()
    })
    .catch(() => {
      toast.error(t.value('settings.error.undoDeletion'))
    })
}

const handlePruneExpiredShares = async () => {
  const confirmed = confirm(t.value('settings.confirm.pruneExpiredShares'))
  if (!confirmed) {
    return
  }
  try {
    await pruneExpiredShares()
    toast.success(t.value('settings.success.pruneExpiredShares'))
    loadShares()
  } catch (error) {
    toast.error(t.value('settings.error.pruneExpiredShares'))
  }
}

const setShowDeletedShares = (value) => {
  showDeletedShares.value = value
  loadShares()
}

defineExpose({
  handlePruneExpiredShares,
  setShowDeletedShares
})
</script>

<template>
  <div>
    <HelpTip id="download-limit-help-tip" :header="$t('settings.help.downloadLimit.title')">
      <p>
        {{ $t('settings.help.downloadLimit.description') }}
      </p>
      <p>
        {{ $t('settings.help.downloadLimit.description2') }}
      </p>
    </HelpTip>
    <table v-if="activeShares.length > 0">
      <thead>
        <tr>
          <th>{{ $t('settings.table.name') }}</th>
          <th>{{ $t('settings.table.files') }}</th>
          <th>
            {{ $t('settings.table.downloads') }}
            <MessageCircleQuestion @click.stop="showHelpTip($event, '#download-limit-help-tip')" />
          </th>
          <th>{{ $t('settings.table.dates') }}</th>
          <th>{{ $t('settings.table.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="share in activeShares" :key="share.id" :class="{ 'reverse-share': share.shared_with_me }">
          <td width="1" style="white-space: nowrap">
            <div class="slide-text">
              <strong class="content">
                <ArrowLeftRight v-if="share.shared_with_me" class="reverse-share-icon" />
                {{ share.name }}
              </strong>
            </div>
            <a :href="`/shares/${share.long_id}`" target="_blank" class="share_long_id">
              <SquareArrowOutUpRight />
              {{ share.long_id }}
            </a>
            <div class="protection-status">
              <template v-if="share.password_protected">
                <Lock />
                {{ $t('share.passwordProtected') }}
              </template>
              <template v-else>
                <LockOpen />
                {{ $t('share.passwordNotProtected') }}
              </template>
            </div>
          </td>
          <td style="vertical-align: top">
            <h6 class="file-count">
              {{ $t('share.files.count', { count: share.files.length, value: share.files.length }) }}
              <template v-if="share.files.length > maxFilesToShow">
                {{ $t('share.files.including') }}
              </template>
            </h6>
            <div class="files-container pt-1">
              <div class="file" v-for="file in share.files.slice(0, maxFilesToShow)" :key="file.id">
                <div class="file-name" :title="file.name">{{ niceFileName(file.name) }}</div>
                <div class="file-size">
                  {{ niceFileSize(file.size) }}
                </div>
              </div>
              <div class="some-more" v-if="share.files.length > maxFilesToShow">
                <span>And {{ share.files.length - maxFilesToShow }} more</span>
              </div>
            </div>
          </td>
          <td width="1" style="white-space: nowrap" class="text-center">
            <div class="download_limit_manager">
              <div class="limit-label">{{ $t('limit') }}</div>
              <div class="download_count">
                <label class="count_label">{{ $t('settings.table.downloads') }}</label>
                {{ niceNumber(share.download_count) }}
                <span>/</span>
              </div>
              <input
                class="download_limit_input"
                v-model="share.download_limit"
                @change="handleDownloadLimitChange(share)"
                placeholder="∞"
              />
            </div>
          </td>
          <td width="1" style="white-space: nowrap">
            <div class="date-container">
              <div class="date">
                <span>{{ $t('share.created') }}:</span>
                {{ niceDate(share.created_at) }}
              </div>
              <div class="date">
                <span>{{ $t('share.expires') }}:</span>
                <template v-if="share.expired">
                  <strong class="ps-1 text-danger">{{ $t('share.expired') }}</strong>
                </template>
                <template v-else>
                  {{ niceDate(share.expires_at) }}
                </template>
              </div>
              <div class="date">
                <span>{{ $t('share.deletes') }}:</span>
                <template v-if="share.deleted">
                  <strong class="ps-1 text-danger">{{ $t('share.deleted') }}</strong>
                </template>
                <template v-else>
                  {{ niceDate(share.deletes_at) }}
                </template>
              </div>
            </div>
          </td>
          <td width="1" style="white-space: nowrap">
            <button
              @click="handleExpireShareClick(share)"
              class="clear-button"
              :disabled="!enableExpireShareButton(share)"
            >
              <CalendarX2 />
              {{ $t('share.button.expireNow') }}
            </button>
            <button
              @click="handleExtendShareClick(share)"
              class="secondary"
              :disabled="!enableExtendShareButton(share)"
            >
              <CalendarPlus />
              {{ $t('share.button.extend') }}
            </button>
            <button
              @click="downloadShare(share)"
              class="secondary icon-only"
              title="Download all files"
              :disabled="!enableDownloadButton(share)"
            >
              <HardDriveDownload style="margin-right: 0" />
            </button>
            <button
              @click="handleRequestDeletionClick(share)"
              class="danger"
              :disabled="!enableRequestDeletionButton(share)"
              :title="$t('share.button.requestDeletion')"
            >
              <Trash2 />
              {{ $t('share.button.requestDeletion') }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
    <div v-else-if="loadedShares && pendingDeletionShares.length === 0" class="center-message">
      <Rocket />
      <p>{{ $t('settings.noShares') }}</p>
    </div>
    <div v-else-if="!loadedShares" class="center-message">
      <p>{{ $t('settings.loading') }}</p>
    </div>

    <!-- Pending Deletion Section -->
    <div v-if="pendingDeletionShares.length > 0" class="pending-deletion-section">
      <h4 class="pending-deletion-header">
        <Clock />
        {{ $t('settings.pendingDeletion.title') }}
      </h4>
      <p class="pending-deletion-description">{{ $t('settings.pendingDeletion.description') }}</p>
      <table>
        <thead>
          <tr>
            <th>{{ $t('settings.table.name') }}</th>
            <th>{{ $t('settings.table.files') }}</th>
            <th>{{ $t('settings.pendingDeletion.requestedOn') }}</th>
            <th>{{ $t('settings.table.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="share in pendingDeletionShares" :key="share.id" class="pending-deletion-row">
            <td width="1" style="white-space: nowrap">
              <div class="slide-text">
                <strong class="content">{{ share.name }}</strong>
              </div>
              <span class="pending-deletion-badge">
                <Clock />
                {{ $t('share.status.pendingDeletion') }}
              </span>
            </td>
            <td style="vertical-align: top">
              <h6 class="file-count">
                {{ $t('share.files.count', { count: share.files.length, value: share.files.length }) }}
              </h6>
            </td>
            <td width="1" style="white-space: nowrap">
              <div class="date">{{ niceDate(share.deletion_requested_at) }}</div>
            </td>
            <td width="1" style="white-space: nowrap">
              <button @click="handleUndoDeletionClick(share)" class="secondary">
                <Undo2 />
                {{ $t('share.button.undoDeletion') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.files-container {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 10px;
  .file {
    display: flex;
    flex-direction: column;
    background: var(--panel-section-background-color-alt);
    border-radius: 5px;
    padding: 5px 10px;
    gap: 1px;
    .file-name {
      font-size: 0.85rem;
      font-weight: bold;
      color: var(--panel-section-text-color);
    }
    .file-size {
      font-size: 0.7rem;
      color: var(--panel-section-text-color);
    }
  }
  .some-more {
    font-size: 0.7rem;
    color: var(--panel-section-text-color);
    margin-left: 10px;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
  }
}

.date-container {
  display: flex;
  flex-direction: column;
  gap: 5px;
  .date {
    background: var(--panel-section-background-color);
    border-radius: 5px;
    padding: 5px 10px;
    gap: 5px;
    span {
      display: inline-block;
      font-weight: bold;
      background: var(--panel-section-background-color-alt);
      border-radius: 5px;
      padding: 5px 10px;
      margin-left: -10px;
      margin-bottom: -5px;
      margin-top: -5px;
      height: calc(100% + 10px);
      min-width: 100px;
      margin-right: 10px;
    }
  }
}

.share_long_id {
  display: block;
  font-size: 1rem;
  color: var(--panel-section-text-color);
  text-decoration: none;
  font-weight: bold;

  svg {
    width: 1rem;
    height: 1rem;
    margin-right: 5px;
    margin-top: -2px;
    color: var(--panel-section-text-color);
  }
}

.file-count {
  background: var(--panel-section-background-color-alt);
  margin-left: -10px;
  margin-top: -10px;
  margin-right: -10px;
  padding: 5px 10px;

  color: var(--panel-section-text-color-alt);
  font-weight: 500;
}

td {
  a {
    color: var(--panel-section-text-color);
    text-decoration: none;
    cursor: pointer;
    font-size: 0.75rem;
    margin-top: 10px;
    display: block;
    &:hover {
      text-decoration: underline;
    }
  }
}

.download_limit_manager {
  position: relative;
  --height: 40px;
  display: flex;
  flex-direction: row;
  align-items: center;
  background: var(--panel-section-background-color-alt);
  height: var(--height);
  border-radius: 5px;
  .limit-label {
    position: absolute;
    left: 90px;
    width: 90px;
    top: 0;
    bottom: 0;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    opacity: 0.3;
    font-size: 0.5rem;
    font-weight: normal;
    padding-bottom: 1.5px;
    color: var(--panel-section-text-color);
    z-index: 1;
    pointer-events: none;
  }
  .download_count {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding-left: 10px;
    padding-right: 10px;
    border-radius: 3px;
    border: none;
    color: var(--panel-section-text-color);
    outline: none;
    height: var(--height);
    background: var(--panel-section-background-color-alt);
    font-weight: bold;
    width: 90px;
    padding-bottom: 6px !important;
    span {
      position: absolute;
      left: 86.5px;
      opacity: 0.3;
      z-index: 10;
    }
    .count_label {
      position: absolute;
      left: 0;
      right: 0;
      top: 0;
      bottom: 0;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      opacity: 0.3;
      font-size: 0.5rem;
      font-weight: normal;
      padding-bottom: 1.5px;
    }
  }
  .download_limit_input {
    position: relative !important;
    background: var(--panel-section-background-color-alt);
    height: var(--height);
    border: none;
    border-radius: 0 3px 3px 0;
    text-align: center;
    margin: 0;
    width: 90px;
    padding-bottom: 16px !important;
    &:focus {
      outline: none;
    }
  }
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

.protection-status {
  margin-top: 10px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 5px;
  font-size: 0.6rem;
  color: var(--panel-section-text-color);
  svg {
    width: 1rem;
    height: 1rem;
    margin-top: -2px;
  }
}

.reverse-share {
  background: var(--panel-section-background-color-alt);
  border-radius: 5px;
  padding: 5px 10px;
  gap: 5px;
}

.reverse-share-icon {
  width: 1rem;
  height: 1rem;
  margin-right: 5px;
  vertical-align: middle;
  opacity: 0.7;
}

.pending-deletion-section {
  margin-top: 2rem;
  border-top: 2px solid var(--panel-section-background-color-alt);
  padding-top: 1rem;
}

.pending-deletion-header {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--panel-section-text-color);
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.25rem;

  svg {
    width: 1.1rem;
    height: 1.1rem;
    opacity: 0.7;
  }
}

.pending-deletion-description {
  font-size: 0.8rem;
  color: var(--panel-section-text-color);
  opacity: 0.7;
  margin-bottom: 0.75rem;
}

.pending-deletion-row {
  opacity: 0.8;
}

.pending-deletion-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.65rem;
  color: var(--panel-section-text-color);
  background: var(--panel-section-background-color-alt);
  border-radius: 4px;
  padding: 2px 6px;
  margin-top: 4px;
  opacity: 0.8;

  svg {
    width: 0.75rem;
    height: 0.75rem;
  }
}
</style>
