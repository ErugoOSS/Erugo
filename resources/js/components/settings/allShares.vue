<script setup>
import RecipientsModal from '../shares/recipients.vue'

import { ref, onMounted, inject, defineExpose, computed } from 'vue'
import { getAllShares, expireShare, extendShare, setDownloadLimit } from '../../api'
import {
  SquareArrowOutUpRight,
  CalendarPlus,
  CalendarX2,
  HardDriveDownload,
  MessageCircleQuestion,
  Rocket,
  Lock,
  LockOpen
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
const selectedUserId = ref(null)

const recipientsModalOpen = ref(false)
const selectedShare = ref(null)

const openRecipientsModal = (share) => {
  selectedShare.value = share
  recipientsModalOpen.value = true
}


onMounted(async () => {
  showDeletedShares.value = localStorage.getItem('allSharesShowDeleted') === 'true'
  loadShares()
})

const loadShares = async () => {
  shares.value = await getAllShares(showDeletedShares.value, selectedUserId.value)
  loadedShares.value = true
}

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

const setShowDeletedShares = (value) => {
  showDeletedShares.value = value
  localStorage.setItem('allSharesShowDeleted', value)
  loadShares()
}

const setUserFilter = (userId) => {
  selectedUserId.value = userId
  loadShares()
}

defineExpose({
  setShowDeletedShares,
  loadShares,
  setUserFilter
})
</script>

<template>
  <div>
    <HelpTip id="download-limit-help-tip-all" :header="$t('settings.help.downloadLimit.title')">
      <p>
        {{ $t('settings.help.downloadLimit.description') }}
      </p>
      <p>
        {{ $t('settings.help.downloadLimit.description2') }}
      </p>
    </HelpTip>

    <table v-if="shares.length > 0">
      <thead>
        <tr>
          <th>{{ $t('settings.table.name') }}</th>
          <th>{{ $t('settings.allShares.owner') }}</th>
          <th>{{ $t('settings.table.files') }}</th>
          <th>
            {{ $t('settings.table.downloads') }}
            <MessageCircleQuestion @click.stop="showHelpTip($event, '#download-limit-help-tip-all')" />
          </th>
          <th>{{ $t('settings.table.dates') }}</th>
          <th>{{ $t('settings.table.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="share in shares" :key="share.id">
          <td width="1" style="white-space: nowrap">
            <div class="slide-text">
              <strong class="content">{{ share.name }}</strong>
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
          <td width="1" style="white-space: nowrap">
            <div class="owner-info">
              <strong>{{ share.user_name }}</strong>
              <small>{{ share.user_email }}</small>
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

            <button class="secondary" @click="openRecipientsModal(share)">
              {{ ($t && $t('share.button.recipients')) || 'Recipients' }}
            </button>
            <button
              @click="downloadShare(share)"
              class="secondary icon-only"
              title="Download all files"
              :disabled="!enableDownloadButton(share)"
            >
              <HardDriveDownload style="margin-right: 0" />
            </button>
          </td>
        </tr>
      </tbody>
    </table>
    <div v-else-if="loadedShares" class="center-message">
      <Rocket />
      <p>{{ $t('settings.allShares.noShares') }}</p>
    </div>
    <div v-else class="center-message">
      <p>{{ $t('settings.loading') }}</p>
    </div>

 
    <RecipientsModal
      :modelValue="recipientsModalOpen"
      :share="selectedShare"
      @update:modelValue="recipientsModalOpen = $event"
    />



  </div>
</template>

<style lang="scss" scoped>
.owner-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  
  strong {
    font-size: 0.9rem;
    color: var(--panel-section-text-color);
  }
  
  small {
    font-size: 0.75rem;
    color: var(--panel-section-text-color);
    opacity: 0.7;
  }
}

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
</style>

