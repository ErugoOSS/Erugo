<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import { store } from '../../store'
import {
  getLiveshareInviteInfo,
  acceptLiveshareInvite,
  registerViaLiveshareInvite,
  login,
  refresh,
  getLiveshareAvatarUrl
} from '../../api'
import { KeyRound, UserPlus, Loader2, AlertCircle } from 'lucide-vue-next'

const props = defineProps({
  token: { type: String, required: true }
})

const toast = useToast()

// Invite state
const loading = ref(true)
const inviteInfo = ref(null)
const inviteError = ref('')

// UI tab: 'login' or 'register' -- default to register since most invite recipients are new users
const activeTab = ref('register')

// Login form
const loginEmail = ref('')
const loginPassword = ref('')
const loggingIn = ref(false)

// Register form
const registerName = ref('')
const registerEmail = ref('')
const registerPassword = ref('')
const registerPasswordConfirm = ref('')
const registering = ref(false)

// Accepting
const accepting = ref(false)

// Avatar
const avatarFailed = ref(false)
const avatarUrl = computed(() => {
  if (!inviteInfo.value?.liveshare_long_id) return null
  return getLiveshareAvatarUrl(inviteInfo.value.liveshare_long_id, props.token)
})
const initials = computed(() => {
  if (!inviteInfo.value?.liveshare_name) return ''
  return inviteInfo.value.liveshare_name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(w => w[0].toUpperCase())
    .join('')
})
const onAvatarError = () => {
  avatarFailed.value = true
}

// Form wrapper ref for height animation
const formWrapper = ref(null)

const onBeforeLeave = () => {
  // Lock the wrapper to its current height before anything changes
  if (formWrapper.value) {
    formWrapper.value.style.height = formWrapper.value.offsetHeight + 'px'
  }
}

const onLeave = (el) => {
  // Take leaving element out of flow so it overlaps with entering element
  el.style.position = 'absolute'
  el.style.left = '0'
  el.style.right = '0'
  el.style.top = '0'
}

const onEnter = (el) => {
  // After the entering element is in the DOM, animate wrapper to its height
  nextTick(() => {
    if (formWrapper.value) {
      formWrapper.value.style.height = el.offsetHeight + 'px'
    }
  })
}

const onAfterEnter = () => {
  // Remove explicit height so wrapper sizes naturally again
  if (formWrapper.value) {
    formWrapper.value.style.height = ''
  }
}

onMounted(async () => {
  await loadInviteInfo()

  // If user is already logged in (via refresh), try to accept immediately
  try {
    const data = await refresh()
    store.authSuccess(data)
    if (store.isLoggedIn()) {
      await tryAcceptInvite()
    }
  } catch {
    // Not logged in, that's fine
  }

  loading.value = false
})

const loadInviteInfo = async () => {
  try {
    inviteInfo.value = await getLiveshareInviteInfo(props.token)
  } catch (error) {
    inviteError.value = error.message || 'This invite is not valid'
  }
}

const tryAcceptInvite = async () => {
  accepting.value = true
  try {
    const data = await acceptLiveshareInvite(props.token)
    toast.success('You have joined the liveshare')
    // Redirect to the liveshare workspace
    window.location.href = '/liveshares/' + data.liveshare_long_id
  } catch (error) {
    toast.error(error.message || 'Failed to accept invite')
    accepting.value = false
  }
}

const handleLogin = async () => {
  if (!loginEmail.value || !loginPassword.value) {
    toast.error('Please enter email and password')
    return
  }

  loggingIn.value = true
  try {
    const data = await login(loginEmail.value, loginPassword.value)
    store.authSuccess(data)
    toast.success('Login successful')
    // Now accept the invite
    await tryAcceptInvite()
  } catch (error) {
    toast.error(error.message || 'Invalid email or password')
  }
  loggingIn.value = false
}

const handleRegister = async () => {
  if (!registerName.value || !registerEmail.value || !registerPassword.value || !registerPasswordConfirm.value) {
    toast.error('Please fill in all fields')
    return
  }
  if (registerPassword.value !== registerPasswordConfirm.value) {
    toast.error('Passwords do not match')
    return
  }

  registering.value = true
  try {
    const data = await registerViaLiveshareInvite(
      props.token,
      registerName.value,
      registerEmail.value,
      registerPassword.value,
      registerPasswordConfirm.value
    )
    store.authSuccess(data)
    toast.success('Account created -- welcome!')
    // Redirect to the liveshare workspace using the long_id from the response
    window.location.href = '/liveshares/' + data.liveshareLongId
  } catch (error) {
    toast.error(error.message || 'Registration failed')
  }
  registering.value = false
}
</script>

<template>
  <div class="invite-accept">
    <!-- Loading -->
    <div class="invite-loading" v-if="loading">
      <Loader2 class="spin" />
      <p>Loading invite...</p>
    </div>

    <!-- Error -->
    <div class="invite-error" v-else-if="inviteError">
      <AlertCircle />
      <h2>Invite Not Available</h2>
      <p>{{ inviteError }}</p>
      <a href="/">Go to home page</a>
    </div>

    <!-- Accepting (logged in, redirect pending) -->
    <div class="invite-loading" v-else-if="accepting">
      <Loader2 class="spin" />
      <p>Joining liveshare...</p>
    </div>

    <!-- Invite info + login/register -->
    <div class="invite-content" v-else-if="inviteInfo && !store.isLoggedIn()">
      <div class="invite-banner">
        <div class="liveshare-avatar">
          <img
            v-if="avatarUrl && !avatarFailed"
            :src="avatarUrl"
            alt=""
            @error="onAvatarError"
          />
          <span class="avatar-initials">{{ initials }}</span>
        </div>
        <div class="invite-banner-text">
          <h2>You have been invited to join</h2>
          <h1>{{ inviteInfo.liveshare_name }}</h1>
          <p>
            Invited by <strong>{{ inviteInfo.inviter_name }}</strong>
            as <strong>{{ inviteInfo.role }}</strong>
          </p>
        </div>
      </div>

      <div class="auth-tabs">
        <button
          :class="activeTab === 'login' ? '' : 'secondary'"
          @click="activeTab = 'login'"
        >
          <KeyRound />
          I have an account
        </button>
        <button
          :class="activeTab === 'register' ? '' : 'secondary'"
          @click="activeTab = 'register'"
        >
          <UserPlus />
          I'm new here
        </button>
      </div>

      <div class="auth-form-wrapper" ref="formWrapper">
        <Transition
          name="tab-switch"
          @before-leave="onBeforeLeave"
          @leave="onLeave"
          @enter="onEnter"
          @after-enter="onAfterEnter"
        >
          <!-- Login Tab -->
          <div class="auth-form" v-if="activeTab === 'login'" key="login">
            <p class="auth-form-hint">Log in with your existing account to join this liveshare.</p>
            <div class="input-container">
              <label>Email</label>
              <input type="email" v-model="loginEmail" placeholder="Email" @keyup.enter="handleLogin" />
            </div>
            <div class="input-container">
              <label>Password</label>
              <input type="password" v-model="loginPassword" placeholder="Password" @keyup.enter="handleLogin" />
            </div>
            <button class="block" @click="handleLogin" :disabled="loggingIn">
              <Loader2 v-if="loggingIn" class="spin" />
              <KeyRound v-else />
              {{ loggingIn ? 'Logging in...' : 'Log In & Join' }}
            </button>
          </div>

          <!-- Register Tab -->
          <div class="auth-form" v-else key="register">
            <p class="auth-form-hint">Create an account to join this liveshare.</p>
            <div class="input-container">
              <label>Name</label>
              <input type="text" v-model="registerName" placeholder="Your name" />
            </div>
            <div class="input-container">
              <label>Email</label>
              <input type="email" v-model="registerEmail" placeholder="Email" />
            </div>
            <div class="input-container">
              <label>Password</label>
              <input type="password" v-model="registerPassword" placeholder="Password" />
            </div>
            <div class="input-container">
              <label>Confirm Password</label>
              <input
                type="password"
                v-model="registerPasswordConfirm"
                placeholder="Confirm password"
                @keyup.enter="handleRegister"
              />
            </div>
            <button class="block" @click="handleRegister" :disabled="registering">
              <Loader2 v-if="registering" class="spin" />
              <UserPlus v-else />
              {{ registering ? 'Creating account...' : 'Create Account & Join' }}
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.invite-accept {
  width: 100%;
  max-width: 440px;
  margin: 0 auto;
}

.invite-loading {
  text-align: center;
  padding: 40px 20px;
  color: var(--panel-section-text-color);

  svg {
    width: 32px;
    height: 32px;
    margin-bottom: 10px;
  }

  p {
    font-size: 0.9rem;
    opacity: 0.7;
  }
}

.invite-error {
  text-align: center;
  padding: 40px 20px;
  color: var(--panel-section-text-color);

  svg {
    width: 40px;
    height: 40px;
    margin-bottom: 10px;
    opacity: 0.5;
  }

  h2 {
    font-size: 1.2rem;
    margin: 0 0 10px 0;
  }

  p {
    font-size: 0.9rem;
    opacity: 0.7;
    margin: 0 0 20px 0;
  }

  a {
    color: var(--link-color);
    text-decoration: none;
    font-size: 0.85rem;

    &:hover {
      text-decoration: underline;
    }
  }
}

.invite-content {
  background: var(--panel-section-background-color);
  border-radius: 12px;
  overflow: hidden;
}

.invite-banner {
  background: var(--panel-section-background-color-alt);
  padding: 30px;
  display: flex;
  gap: 15px;
  align-items: flex-start;

  .liveshare-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    overflow: hidden;
    position: relative;
    flex-shrink: 0;
    background: var(--primary-button-background-color);

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .avatar-initials {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      font-weight: 700;
      color: white;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
      pointer-events: none;
    }
  }

  .invite-banner-text {
    h2 {
      font-size: 0.75rem;
      font-weight: 400;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--panel-section-text-color);
      opacity: 0.5;
      margin: 0 0 4px 0;
    }

    h1 {
      font-size: 1.4rem;
      font-weight: 600;
      color: var(--panel-section-text-color);
      margin: 0 0 8px 0;
    }

    p {
      font-size: 0.8rem;
      color: var(--panel-section-text-color);
      opacity: 0.6;
      margin: 0;
    }
  }
}

.auth-tabs {
  display: flex;
  gap: 8px;
  padding: 20px 30px 0;

  button {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }
}

.auth-form {
  padding: 20px 30px 30px;

  .auth-form-hint {
    font-size: 0.8rem;
    color: var(--panel-section-text-color);
    opacity: 0.5;
    margin: 0 0 16px 0;
  }

  .input-container {
    margin-bottom: 12px;

    label {
      display: block;
      font-size: 0.75rem;
      font-weight: 500;
      color: var(--panel-section-text-color);
      opacity: 0.6;
      margin-bottom: 4px;
    }

    input {
      width: 100%;
    }
  }

  > button {
    margin-top: 10px;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
  }
}

.auth-form-wrapper {
  position: relative;
  overflow: hidden;
  transition: height 0.25s ease;
}

.tab-switch-enter-active,
.tab-switch-leave-active {
  transition: opacity 0.2s ease;
}

.tab-switch-enter-from,
.tab-switch-leave-to {
  opacity: 0;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
