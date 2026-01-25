<script setup>
import { ref, onMounted, inject, defineExpose } from 'vue'

import {
  SquareArrowOutUpRight,
  CalendarPlus,
  CalendarX2,
  HardDriveDownload,
  MessageCircleQuestion,
  Rocket,
  Lock,
  LockOpen,
  ArrowLeftRight
} from 'lucide-vue-next'
import { useToast } from 'vue-toastification'
import { niceFileSize, niceDate, niceFileName, niceNumber } from '../../utils'
import HelpTip from '../helpTip.vue'
import { useTranslate } from '@tolgee/vue'

import {
  getMyShares,
  expireShare,
  extendShare,
  setDownloadLimit,
  pruneExpiredShares,
  getShareRecipients,
  updateShareRecipients,
  resendShareRecipientEmails
} from '../../api'



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

const recipientsModalOpen = ref(false)
const recipientsLoading = ref(false)
const selectedShare = ref(null)
const recipientEmails = ref([{ name: '', email: '' }])
const recipientsError = ref('')

const openRecipientsModal = async (share) => {
  selectedShare.value = share
  recipientsError.value = ''
  recipientsLoading.value = true
  recipientsModalOpen.value = true

  try {
    const res = await getShareRecipients(share.id)
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


const closeRecipientsModal = () => {
  recipientsModalOpen.value = false
  selectedShare.value = null
  recipientEmails.value = [{ name: '', email: '' }]
  recipientsError.value = ''
}

const addRecipientRow = () => recipientEmails.value.push({ name: '', email: '' })
const removeRecipientRow = (idx) => {
  if (recipientEmails.value.length === 1) return
  recipientEmails.value.splice(idx, 1)
}

const saveRecipients = async () => {
  if (!selectedShare.value) return
  recipientsError.value = ''

  const payload = recipientEmails.value
    .map(r => ({
      name: (r?.name || '').trim(),
      email: (r?.email || '').trim().toLowerCase()
    }))
    .filter(r => r.email)
    .filter((r, idx, arr) => arr.findIndex(x => x.email === r.email) === idx)

  try {
    const res = await updateShareRecipients(selectedShare.value.id, payload)
    const json = await res.json().catch(() => ({}))

    if (!res.ok) {
      recipientsError.value = json?.message || `Unable to save recipients (${res.status})`
      return
    }

    // re-load from server to reflect canonical saved data
    recipientEmails.value = payload.length ? payload : [{ name: '', email: '' }]
    toast.success('Recipients saved')
  } catch (e) {
    recipientsError.value = 'Unable to save recipients'
  }
}


const resendRecipients = async () => {
  if (!selectedShare.value) return
  recipientsError.value = ''

  try {
    const res = await resendShareRecipientEmails(selectedShare.value.id)
    const json = await res.json().catch(() => ({}))

    if (!res.ok) {
      recipientsError.value = json?.message || `Unable to resend emails (${res.status})`
      return
    }

    toast.success('Emails resent')
  } catch (e) {
    recipientsError.value = 'Unable to resend emails'
  }
}



//const removeRecipientRow = (idx) => recipientsEmails.value.splice(idx, 1)



const loadShares = async () => {
  shares.value = await getMyShares(showDeletedShares.value)
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
    <table v-if="shares.length > 0">
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
        <tr v-for="share in shares" :key="share.id" :class="{ 'reverse-share': share.shared_with_me }">
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
      <p>{{ $t('settings.noShares') }}</p>
    </div>
    <div v-else class="center-message">
      <p>{{ $t('settings.loading') }}</p>
    </div>

  
    <div v-if="recipientsLoading">Loading…</div>
      <div class="recipients-modal-overlay" :class="{ active: recipientsModalOpen }" @click.self="closeRecipientsModal">
        <div class="recipients-modal-form">
          <div class="recipients-modal-header">
            <h3>Recipients</h3>
            <button class="close-btn icon-only secondary" @click="closeRecipientsModal">×</button>
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
            <button class="secondary" @click="closeRecipientsModal">Close</button>
            <button class="secondary" @click="saveRecipients" :disabled="recipientsLoading">Save</button>
            <button class="clear-button" @click="resendRecipients" :disabled="recipientsLoading">Resend</button>
          </div>
        </div>
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

.recipients-modal-overlay.active {
  opacity: 1;
  pointer-events: auto;
}

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

.recipients-modal-content {
  padding: 10px 0;
}

.recipient-row {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
}

.email-input {
  flex: 1;
  padding: 10px;
  border-radius: 8px;
  border: 1px solid rgba(0,0,0,0.15);
}

.button-bar {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 12px;
}

.error {
  padding: 10px;
  border-radius: 8px;
  margin-bottom: 10px;
  background: rgba(255, 0, 0, 0.08);
}


</style>
