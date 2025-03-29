<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { niceFileSize, timeUntilExpiration, getApiUrl, niceFileType, niceFileName } from '../utils'
import { FileIcon, HeartCrack, TrendingDown, FileX } from 'lucide-vue-next'
import { getShare } from '../api'
import { domError } from '../domData'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'
const { t } = useTranslate()

const apiUrl = getApiUrl()
const toast = useToast()
const share = ref(null)
const showFilesCount = ref(5)
const shareExpired = ref(false)
const downloadLimitReached = ref(false)
const shareNotFound = ref(false)

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

const fetchShare = async () => {
  try {
    share.value = await getShare(props.downloadShareCode)
    document.title = share.value.name
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
</script>

<template>
  <div class="download-panel-content">
    <template v-if="share">
      <h1>
        {{ share.name }}
      </h1>
      <div class="total-size">{{ $t('total_size') }}: {{ niceFileSize(share.size) }}</div>
      <div class="file-count">
        {{ $t('share.contains.count', 'Contains: {value} files', { value: share.file_count }) }}
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
      <div class="file-list">
        <div v-for="file in share.files.slice(0, showFilesCount)" :key="file" class="file-item">
          <div class="file-name">
            <div class="name">
              {{ niceFileName(file.name) }}
              <div class="size">
                {{ niceFileSize(file.size) }}
              </div>
            </div>
          </div>

          <div class="type">
            {{ niceFileType(file.type) }}
          </div>
        </div>
        <div v-if="share.files.length > showFilesCount" class="file-item more-files">
          <div class="file-name more-files">and {{ share.files.length - showFilesCount }} more</div>
        </div>
      </div>
      <div class="share-message mt-3" v-if="share.description">
        <h6>{{ $t('message.from', { name: splitFullName(share.user.name) }) }}</h6>
        <div class="message">
          {{ share.description }}
        </div>
      </div>
      <div class="download-button-container mt-3" v-if="!share.password_protected">
        <button class="download-button" @click="downloadFiles">
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
</style>
