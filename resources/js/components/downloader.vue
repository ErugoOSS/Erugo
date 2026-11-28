<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue'
import { niceFileSize, timeUntilExpiration, getApiUrl, niceFileType, niceFileName } from '../utils'
import { FileIcon, HeartCrack, TrendingDown, FileX, Boxes, Loader2, AlertTriangle, CheckCircle2 } from 'lucide-vue-next'
import { getShare } from '../api'
import { domError } from '../domData'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'
import DirectoryItem from './directory-item.vue'

const { t } = useTranslate()

const apiUrl = getApiUrl()
const toast = useToast()
const share = ref(null)
const showFilesCount = ref(5)
const shareExpired = ref(false)
const downloadLimitReached = ref(false)
const shareNotFound = ref(false)

// ── Pending-state polling & ETA ──────────────────────────────────────────
const BYTES_PER_SECOND = 10 * 1024 * 1024 // conservative 10 MB/s estimate

const shareIsPending = computed(() => share.value?.status === 'pending')
const shareIsFailed  = computed(() => share.value?.status === 'failed')

// Reactive "now" ticked every second so countdown re-computes automatically
const now = ref(Date.now())
let tickInterval  = null
let pollInterval  = null

const pendingSince = computed(() => {
  if (!share.value?.updated_at) return now.value
  return new Date(share.value.updated_at).getTime()
})

const estimatedMs = computed(() =>
  Math.max(5000, ((share.value?.size ?? 0) / BYTES_PER_SECOND) * 1000)
)

const elapsedMs = computed(() => Math.max(0, now.value - pendingSince.value))

const remainingSeconds = computed(() =>
  Math.ceil(Math.max(0, estimatedMs.value - elapsedMs.value) / 1000)
)

const progressPercent = computed(() =>
  Math.min(100, (elapsedMs.value / estimatedMs.value) * 100)
)

const isOverdue = computed(() => elapsedMs.value >= estimatedMs.value)

const startPendingMode = () => {
  now.value = Date.now()
  tickInterval = setInterval(() => { now.value = Date.now() }, 1000)
  pollInterval = setInterval(pollStatus, 3000)
}

const stopPendingMode = () => {
  clearInterval(tickInterval)
  clearInterval(pollInterval)
  tickInterval = null
  pollInterval = null
}

const pollStatus = async () => {
  try {
    const updated = await getShare(props.downloadShareCode)
    if (updated.status !== 'pending') {
      share.value = updated
      stopPendingMode()
      if (updated.status === 'ready') {
        toast.success(t.value('share.processing.ready_toast'))
      }
    }
  } catch (_) {
    // ignore transient poll errors
  }
}

//define props
const props = defineProps({
  downloadShareCode: {
    type: String,
    required: true
  }
})

onMounted(() => {
  fetchShare()
  setTimeout(() => {
    const urlParams = new URLSearchParams(window.location.search)
    const errorMessage = urlParams.get('error')

    if (errorMessage) {
      if (errorMessage == 'password_required') {
        toast.error(t.value('share.download.password_required'))
      } else if (errorMessage == 'invalid_password') {
        toast.error(t.value('share.download.invalid_password'))
      }
    }
  }, 100)
})

onUnmounted(() => {
  stopPendingMode()
})

const fetchShare = async () => {
  try {
    share.value = await getShare(props.downloadShareCode)
    document.title = share.value.name
    if (share.value.status === 'pending') {
      startPendingMode()
    }
  } catch (error) {
    console.log('error', error)
    if (error.message == 'Download limit reached') {
      downloadLimitReached.value = true
    } else if (error.message == 'Share expired') {
      shareExpired.value = true
    } else if (error.message == 'Share not found') {
      shareNotFound.value = true
    }
  }
}

const downloadFiles = () => {
  const downloadUrl = `${apiUrl}/api/shares/${props.downloadShareCode}/download`
  window.location.href = downloadUrl
}

const splitFullName = (fullName) => {
  if (!fullName) {
    return 'creator'
  }
  const nameParts = fullName.split(' ')
  return nameParts[0]
}

const password = ref('')
const error = ref(null)

const downloadPasswordProtectedFiles = () => {
  //is the password filled in?
  if (!password.value) {
    toast.error(t.value('share.download.password_required'))
    error.value = t.value('share.download.password_required_short')
    return
  }

  //create a form and submit it
  const form = document.createElement('form')
  form.action = `${apiUrl}/api/shares/${props.downloadShareCode}/download`
  form.method = 'POST'

  //add the password input
  const passwordInput = document.createElement('input')
  passwordInput.type = 'password'
  passwordInput.name = 'password'
  passwordInput.value = password.value
  form.appendChild(passwordInput)

  // Add the form to the document body - THIS LINE IS CRUCIAL
  document.body.appendChild(form)

  // Submit the form
  form.submit()
  setTimeout(() => document.body.removeChild(form), 0)
}

const filesByDirectory = computed(() => {
  const files = share?.value?.files
  const structure = {}

  if (!files) {
    return {}
  }

  files.forEach((file) => {
    const path = file.full_path || ''
    const dirs = path ? path.split('/') : ['']

    // Create nested structure
    let current = structure
    for (const dir of dirs) {
      if (dir) {
        if (!current[dir]) {
          current[dir] = { files: [], directories: {} }
        }
        current = current[dir].directories
      }
    }

    // Add file to its directory
    if (path) {
      const parentDir = dirs.reduce((acc, dir, index) => {
        if (index < dirs.length - 1 && dir) {
          return acc[dir].directories
        }
        return acc
      }, structure)

      const lastDir = dirs[dirs.length - 1]
      if (lastDir) {
        parentDir[lastDir].files.push(file)
      }
    } else {
      // Root files
      if (!structure.files) {
        structure.files = []
      }
      structure.files.push(file)
    }
  })

  return structure
})
</script>

<template>
  <div class="download-panel-content">
    <template v-if="share">
      <h1 class="share-name">
        <Boxes />
        {{ share.name }}
      </h1>
      <div class="stats">
        <div class="total-size stat">{{ niceFileSize(share.size) }}</div>
        <div class="file-count stat">
          {{ $t('share.contains.count', 'Contains: {value} files', { value: share.file_count }) }}
        </div>
      </div>
      <div class="share-expires">
        {{
          $t('share.expires.in', {
            days: timeUntilExpiration(share.expires_at).days,
            hours: timeUntilExpiration(share.expires_at).hours,
            minutes: timeUntilExpiration(share.expires_at).minutes
          })
        }}
      </div>
      <!-- Pending: zip is being built -->
      <div class="processing-banner" v-if="shareIsPending">
        <div class="processing-banner-header">
          <Loader2 class="processing-spinner" />
          <span>{{ $t('share.processing.title') }}</span>
        </div>
        <p class="processing-eta" v-if="!isOverdue">
          {{ $t('share.processing.eta', { seconds: remainingSeconds }) }}
        </p>
        <p class="processing-eta overdue" v-else>
          {{ $t('share.processing.overdue') }}
        </p>
        <div class="processing-progress-track">
          <div class="processing-progress-fill" :style="{ width: progressPercent + '%' }"></div>
        </div>
      </div>

      <!-- Failed: zip creation failed -->
      <div class="processing-banner failed" v-if="shareIsFailed">
        <div class="processing-banner-header">
          <AlertTriangle class="processing-failed-icon" />
          <span>{{ $t('share.processing.failed_title') }}</span>
        </div>
        <p class="processing-eta">{{ $t('share.processing.failed_message') }}</p>
      </div>

      <div class="share-files-list">
        <directory-item
          :structure="filesByDirectory"
          :is-root="true"
          :read-only="true"
          :share-code="downloadShareCode"
          :disabled="shareIsPending || shareIsFailed"
        />
      </div>
      <div class="share-message mt-3" v-if="share.description">
        <h6>{{ $t('message.from', { name: splitFullName(share.user.name) }) }}</h6>
        <div class="message">
          {{ share.description }}
        </div>
      </div>
      <div class="download-button-container mt-3" v-if="!share.password_protected">
        <button
          class="download-button"
          :class="{ 'download-button-disabled': shareIsPending || shareIsFailed }"
          :disabled="shareIsPending || shareIsFailed"
          @click="downloadFiles"
        >
          {{ $t('download.files', 'Download {value} files', { value: share.file_count }) }}
        </button>
      </div>

      <div class="password-input-container" v-else>
        <div class="input-container">
          <input
            type="password"
            v-model="password"
            :placeholder="$t('settings.share.password')"
            :class="{ error: error }"
            @keyup.enter="downloadPasswordProtectedFiles"
          />
          <div class="error-message" v-if="error">
            {{ error }}
          </div>
        </div>
        <button class="download-button mt-3" @click="downloadPasswordProtectedFiles">
          {{ $t('download.files', 'Download {value} files', { value: share.file_count }) }}
        </button>
      </div>
    </template>
    <template v-else>
      <template v-if="shareExpired">
        <h1>
          <HeartCrack />
          {{ $t('share.expired') }}
        </h1>
        <p>{{ $t('share.expired.message') }}</p>
      </template>
      <template v-else-if="downloadLimitReached">
        <h1>
          <TrendingDown />
          {{ $t('share.download_limit_reached') }}
        </h1>
        <p>
          {{ $t('share.download_limit_reached.message') }}
        </p>
      </template>
      <template v-else-if="shareNotFound">
        <div class="my-5">
          <FileX />
        </div>
        <h1>
          {{ $t('share.not_found') }}
        </h1>
      </template>
      <h1 v-else>{{ $t('share.data_loading') }}</h1>
    </template>
  </div>
</template>
<style lang="scss" scoped>
.file-list {
  padding: 20px;
}
.share-message {
  width: 100%;
  margin-top: 20px;
  background: var(--panel-section-background-color);
  padding: 20px;
  h6 {
    font-weight: 500;
    &:after {
      content: '';
      display: block;
      width: 100%;
      height: 1px;
      background: var(--panel-section-background-color-alt);
      margin-top: 5px;
    }
  }
  .message {
    font-weight: 200;
  }
}

.download-button-container {
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: 20px;
}

.password-input-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  margin-top: 20px;
  padding: 20px;
  input {
    width: 100%;
    display: block;
  }
}

.error-message {
  margin-top: -24px;
}

.share-name {
  color: var(--panel-text-color);
  font-size: 1.2rem;
  font-weight: bold;
  margin-bottom: 10px;
  background: var(--primary-button-background-color);
  color: var(--primary-button-text-color);
  padding: 20px 20px;
  border-radius: var(--panel-border-radius);
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
  width: calc(100%);
  margin-top: -20px;
  margin-bottom: 0px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  svg {
    width: 16px;
    height: 16px;
  }
}

.stats {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-bottom: 0px!important;
  width: 100%;
  background: var(--panel-section-background-color-alt);
  padding: 10px 20px;
}
.stat {
  color: var(--panel-text-color);
  font-size: 0.8rem;
  display: block;
  background: var(--panel-section-background-color);
  padding: 10px 20px;
  border-radius: var(--panel-border-radius);
  margin-bottom: 0!important;
}


.share-expires {
  width: 100%;
  background: var(--panel-item-background-color);
  padding: 10px 20px;
  display: flex;
  justify-content: center;
  margin-top: 0!important;
}

// ── Pending / failed processing banner ──────────────────────────────────────

.processing-banner {
  width: 100%;
  margin-top: 0;
  padding: 16px 20px;
  background: color-mix(in srgb, var(--accent-color) 12%, transparent);
  border-left: 3px solid var(--accent-color);

  &.failed {
    background: color-mix(in srgb, #ef4444 12%, transparent);
    border-left-color: #ef4444;

    .processing-failed-icon {
      color: #ef4444;
    }
  }
}

.processing-banner-header {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 600;
  font-size: 0.95rem;
  color: var(--panel-text-color);
  margin-bottom: 6px;
}

.processing-spinner {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  animation: spin 1.2s linear infinite;
}

.processing-failed-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.processing-eta {
  font-size: 0.85rem;
  color: var(--panel-text-color);
  opacity: 0.8;
  margin: 0 0 10px 28px;

  &.overdue {
    font-style: italic;
  }
}

.processing-progress-track {
  height: 4px;
  background: color-mix(in srgb, var(--accent-color) 25%, transparent);
  border-radius: 2px;
  overflow: hidden;
  margin-left: 28px;
}

.processing-progress-fill {
  height: 100%;
  background: var(--accent-color);
  border-radius: 2px;
  transition: width 0.8s ease;
}

.download-button-disabled {
  opacity: 0.4;
  cursor: not-allowed;
  pointer-events: none;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}
</style>
