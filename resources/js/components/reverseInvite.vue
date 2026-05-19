<script setup>
import { ref, defineExpose, watch } from 'vue'
import { useTranslate } from '@tolgee/vue'
import { MessageCircleMore, UserRoundCheck, CircleX } from 'lucide-vue-next'
import { sendReverseShareInvite, searchRecipientHistories } from '../api'
import { useToast } from 'vue-toastification'
import debounce from '../debounce'
const { t } = useTranslate()
const toast = useToast()
const reverseInviteActive = ref(false)
const invite = ref({
  email: '',
  name: '',
  message: ''
})
const errors = ref({})

const recipientSuggestions = ref([])
const recipientSuggestionsOpen = ref(false)

const closeRecipientSuggestions = () => {
  recipientSuggestionsOpen.value = false
  recipientSuggestions.value = []
}

const getRecipientQuery = () => {
  const email = (invite.value.email || '').trim()
  const name = (invite.value.name || '').trim()
  if (email.length >= 2) return email
  if (name.length >= 2) return name
  return ''
}

const loadRecipientSuggestions = debounce(async () => {
  const q = getRecipientQuery()
  if (!q) {
    closeRecipientSuggestions()
    return
  }

  try {
    const items = await searchRecipientHistories(q, 10)
    recipientSuggestions.value = Array.isArray(items) ? items : []
    recipientSuggestionsOpen.value = recipientSuggestions.value.length > 0
  } catch (e) {
    closeRecipientSuggestions()
  }
}, 200)

watch(
  () => [invite.value.email, invite.value.name],
  () => {
    loadRecipientSuggestions()
  }
)

const reverseInviteClickOutside = (event) => {
  if (!event.target.closest('.user-form')) {
    reverseInviteActive.value = false
    closeRecipientSuggestions()
  }
}

const handleRecipientSuggestionClick = (event, item) => {
  event.stopPropagation()
  if (!item) return
  invite.value.email = item.email || ''
  if (!invite.value.name && item.name) {
    invite.value.name = item.name
  }
  closeRecipientSuggestions()
}

const sendReverseInvite = async () => {
  try {
    await sendReverseShareInvite(invite.value.email, invite.value.name, invite.value.message)
    reverseInviteActive.value = false
    closeRecipientSuggestions()
    toast.success(t.value('reverse_invite_send.success'))
  } catch (error) {
    console.error(error)
    toast.error(t.value('reverse_invite_send.error'))
  }
}
const showReverseInviteForm = () => {
  reverseInviteActive.value = true
}

watch(
  () => reverseInviteActive.value,
  (active) => {
    if (!active) {
      closeRecipientSuggestions()
    }
  }
)

//expose the functions
defineExpose({
  showReverseInviteForm
})
</script>

<template>
  <div class="user-form-overlay" :class="{ active: reverseInviteActive }" @click="reverseInviteClickOutside">
    <div class="user-form">
      <h2>
        <MessageCircleMore />
        {{ $t('settings.title.reverse_invite') }}
      </h2>
      <p>{{ $t('settings.reverse_invite.description') }}</p>
      <div class="input-container">
        <label for="edit_user_email">{{ $t('settings.users.email') }}</label>
        <input
          type="email"
          v-model="invite.email"
          id="edit_user_email"
          :placeholder="$t('settings.users.email')"
          required
          :class="{ error: errors.email }"
        />
        <div v-if="recipientSuggestionsOpen" class="recipient-suggestions">
          <button
            v-for="(item, idx) in recipientSuggestions"
            :key="`${item.email}-${idx}`"
            type="button"
            class="recipient-suggestion"
            @click="handleRecipientSuggestionClick($event, item)"
          >
            <span class="recipient-name" v-if="item.name">{{ item.name }}</span>
            <span class="recipient-email">{{ item.email }}</span>
          </button>
        </div>
        <div class="error-message" v-if="errors.email">
          {{ errors.email }}
        </div>
      </div>
      <div class="input-container">
        <label for="edit_user_name">{{ $t('settings.users.name') }}</label>
        <input
          type="text"
          v-model="invite.name"
          id="edit_user_name"
          :placeholder="$t('settings.users.name')"
          required
          :class="{ error: errors.name }"
        />
        <div class="error-message" v-if="errors.name">
          {{ errors.name }}
        </div>
      </div>
      <div class="input-container">
        <label for="edit_user_message">{{ $t('invite.labels.message') }}</label>
        <textarea
          v-model="invite.message"
          id="edit_user_message"
          :placeholder="$t('invite.message')"
        ></textarea>
      </div>
      <div class="button-bar">
        <button @click="sendReverseInvite">
          <MessageCircleMore />
          {{ $t('button.reverse_share_invite_send') }}
        </button>
        <button class="secondary close-button" @click="reverseInviteActive = false">
          <CircleX />
          {{ $t('settings.close') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.user-form-overlay {
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
  .user-form {
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
    padding-bottom: 20px;
    button {
      display: block;
      width: 100%;
    }
  }

  &.active {
    opacity: 1;
    pointer-events: auto;
    .user-form {
      transform: translate(-50%, 0%);
    }
  }
}

.recipient-suggestions {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 8px;
  padding: 8px;
  border-radius: 8px;
  background: var(--panel-background-color);
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
}

.recipient-suggestion {
  width: 100%;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.recipient-name {
  font-weight: 600;
}

.recipient-email {
  opacity: 0.85;
}
</style>
