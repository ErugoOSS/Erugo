<script setup>
import { ref, defineExpose } from 'vue'
import { useTranslate } from '@tolgee/vue'
import { MessageCircleMore, UserRoundCheck, CircleX, Link, Copy, Check } from 'lucide-vue-next'
import { sendReverseShareInvite } from '../api'
import { useToast } from 'vue-toastification'
const { t } = useTranslate()
const toast = useToast()
const reverseInviteActive = ref(false)
const generatedLink = ref(null)
const copied = ref(false)

const invite = ref({
  email: '',
  name: '',
  message: '',
  send_email: true
})
const errors = ref({})

const reverseInviteClickOutside = (event) => {
  if (!event.target.closest('.user-form')) {
    closeForm()
  }
}

const closeForm = () => {
  reverseInviteActive.value = false
  // Reset the form after the closing animation
  setTimeout(() => {
    generatedLink.value = null
    copied.value = false
    invite.value = { email: '', name: '', message: '', send_email: true }
    errors.value = {}
  }, 300)
}

const sendReverseInvite = async () => {
  // Front-end validation
  errors.value = {}
  if (!invite.value.name) {
    errors.value.name = t.value('settings.users.name_required', 'Name is required')
    return
  }
  if (invite.value.send_email && !invite.value.email) {
    errors.value.email = t.value('settings.users.email_required', 'Email address is required')
    return
  }

  try {
    const response = await sendReverseShareInvite(
      invite.value.email, 
      invite.value.name, 
      invite.value.message,
      invite.value.send_email
    )

    if (invite.value.send_email) {
      toast.success(t.value('reverse_invite_send.success'))
    }

    // If a link is received, toggle the view to show it
    if (response && response.data && response.data.link) {
      generatedLink.value = response.data.link
    } else {
      closeForm()
    }
  } catch (error) {
    console.error(error)
    toast.error(t.value('reverse_invite_send.error'))
  }
}

const copyLink = () => {
  if (generatedLink.value) {
    navigator.clipboard.writeText(generatedLink.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  }
}

const showReverseInviteForm = () => {
  generatedLink.value = null
  invite.value = { email: '', name: '', message: '', send_email: true }
  reverseInviteActive.value = true
}

//expose the functions
defineExpose({
  showReverseInviteForm
})
</script>

<template>
  <div class="user-form-overlay" :class="{ active: reverseInviteActive }" @click="reverseInviteClickOutside">
    <div class="user-form">
      
      <template v-if="!generatedLink">
        <h2>
          <MessageCircleMore />
          {{ $t('settings.title.reverse_invite') }}
        </h2>
        <p>{{ $t('settings.reverse_invite.description') }}</p>

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

        <div class="checkbox-container">
          <input type="checkbox" id="send_email_toggle" v-model="invite.send_email" />
          <label for="send_email_toggle">{{ $t('reverse_invite.send_via_email') }}</label>
        </div>

        <template v-if="invite.send_email">
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
            <div class="error-message" v-if="errors.email">
              {{ errors.email }}
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
        </template>

        <div class="button-bar">
          <button @click="sendReverseInvite">
            <MessageCircleMore v-if="invite.send_email" />
            <Link v-else />
            {{ invite.send_email ? $t('button.reverse_share_invite_send') : $t('button.generate_link') }}
          </button>
          <button class="secondary close-button" @click="closeForm">
            <CircleX />
            {{ $t('settings.close') }}
          </button>
        </div>
      </template>

      <template v-else>
        <h2>
          <Link />
          {{ $t('reverse_invite.link_generated_title', 'Link Generated') }}
        </h2>
        <p>
          {{ $t('reverse_invite.link_generated_desc', { name: invite.name }) }}
        </p>

        <div class="share-link-container">
          <input type="text" readonly :value="generatedLink" @click="$event.target.select()" />
          <button class="icon-only" @click="copyLink" :title="copied ? $t('button.copied') : $t('button.copy')">
            <Check v-if="copied" />
            <Copy v-else />
          </button>
        </div>

        <div class="button-bar">
          <button class="secondary close-button" @click="closeForm" style="width: 100%">
            <CircleX />
            {{ $t('settings.close') }}
          </button>
        </div>
      </template>

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

.checkbox-container {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 15px;
  width: 100%;

  input[type="checkbox"] {
    width: auto;
    margin: 0;
    cursor: pointer;
  }
  
  label {
    margin: 0;
    cursor: pointer;
    font-size: 0.9em;
    color: var(--panel-text-color);
  }
}

.user-form-overlay .user-form .share-link-container {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 10px;
  width: 100%;
  margin: 15px 0;

  input {
    flex: 1;
    margin: 0;
    height: var(--button-height);
    padding: 0 15px;
    background: var(--input-background-color);
    color: var(--input-text-color);
    border: 1px solid var(--input-border-color);
    border-radius: var(--button-border-radius);
    // Empêche le texte de dépasser bizarrement
    text-overflow: ellipsis;
    white-space: nowrap;
    overflow: hidden;
  }

  button {
    width: var(--icon-only-button-width) !important;
    height: var(--button-height);
    flex-shrink: 0;
    margin: 0;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
  }
}

</style>
