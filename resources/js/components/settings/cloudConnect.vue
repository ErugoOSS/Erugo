<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue'
import {
  Cloud,
  CloudOff,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Loader2,
  ExternalLink,
  RefreshCw,
  LogIn,
  LogOut,
  UserPlus,
  CreditCard,
  Globe,
  Server,
  Wifi,
  WifiOff,
  Copy,
  Check,
  Mail
} from 'lucide-vue-next'
import {
  getCloudConnectStatus,
  cloudConnectRegister,
  cloudConnectLogin,
  cloudConnectLogout,
  getCloudConnectSubscription,
  createCloudConnectCheckout,
  checkCloudConnectSubdomain,
  createCloudConnectInstance,
  connectCloudConnect,
  disconnectCloudConnect,
  resendCloudConnectVerification
} from '../../api'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()
const toast = useToast()

// State
const loading = ref(true)
const status = ref(null)
const currentStep = ref('loading') // loading, capabilities_error, auth, subscription, instance, connected
const authMode = ref('login') // login or register
const connecting = ref(false)
const disconnecting = ref(false)
const checkingSubdomain = ref(false)
const subdomainAvailable = ref(null)
const subdomainSuggestions = ref([])
const pollingSubscription = ref(false)
const pollInterval = ref(null)
const copiedDomain = ref(false)

// Form data
const loginForm = ref({
  email: '',
  password: ''
})

const registerForm = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  accept_terms: false,
  accept_privacy: false,
  accept_marketing: false
})

const instanceForm = ref({
  name: 'My Erugo Server',
  subdomain: ''
})

const selectedPlan = ref('pro')

// Computed
const canConnect = computed(() => {
  return status.value?.capabilities?.capable && 
         status.value?.has_instance && 
         status.value?.status !== 'connected'
})

const isConnected = computed(() => {
  return status.value?.status === 'connected' && status.value?.tunnel_active
})

const hasActiveSubscription = computed(() => {
  return status.value?.subscription_status === 'active' || 
         status.value?.subscription_status === 'trialing'
})

const needsEmailVerification = computed(() => {
  return status.value?.account_status === 'pending_email_verification'
})

// Lifecycle
onMounted(async () => {
  await loadStatus()
})

onUnmounted(() => {
  if (pollInterval.value) {
    clearInterval(pollInterval.value)
  }
})

// Methods
const loadStatus = async () => {
  try {
    loading.value = true
    status.value = await getCloudConnectStatus()
    determineStep()
  } catch (error) {
    toast.error(t.value('cloudConnect.errorLoadingStatus'))
    console.error(error)
  } finally {
    loading.value = false
  }
}

const determineStep = () => {
  if (!status.value) {
    currentStep.value = 'loading'
    return
  }

  // Check capabilities first
  if (!status.value.capabilities?.capable) {
    currentStep.value = 'capabilities_error'
    return
  }

  // Check if connected
  if (status.value.status === 'connected' && status.value.tunnel_active) {
    currentStep.value = 'connected'
    return
  }

  // Check if logged in
  if (!status.value.is_logged_in) {
    currentStep.value = 'auth'
    return
  }

  // Check if email verification is needed
  if (needsEmailVerification.value) {
    currentStep.value = 'email_verification'
    return
  }

  // Check subscription
  if (!hasActiveSubscription.value) {
    currentStep.value = 'subscription'
    return
  }

  // Check if has instance
  if (!status.value.has_instance) {
    currentStep.value = 'instance'
    return
  }

  // Ready to connect
  currentStep.value = 'ready'
}

const handleLogin = async () => {
  try {
    loading.value = true
    await cloudConnectLogin(loginForm.value.email, loginForm.value.password)
    toast.success(t.value('cloudConnect.loginSuccess'))
    await loadStatus()
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.loginFailed'))
  } finally {
    loading.value = false
  }
}

const handleRegister = async () => {
  if (registerForm.value.password !== registerForm.value.password_confirmation) {
    toast.error(t.value('cloudConnect.passwordMismatch'))
    return
  }

  if (!registerForm.value.accept_terms || !registerForm.value.accept_privacy) {
    toast.error(t.value('cloudConnect.mustAcceptTerms'))
    return
  }

  try {
    loading.value = true
    await cloudConnectRegister(registerForm.value)
    toast.success(t.value('cloudConnect.registerSuccess'))
    // After registration, try to login
    await cloudConnectLogin(registerForm.value.email, registerForm.value.password)
    await loadStatus()
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.registerFailed'))
  } finally {
    loading.value = false
  }
}

const handleLogout = async () => {
  try {
    loading.value = true
    await cloudConnectLogout()
    toast.success(t.value('cloudConnect.logoutSuccess'))
    await loadStatus()
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.logoutFailed'))
  } finally {
    loading.value = false
  }
}

const checkVerificationStatus = async () => {
  try {
    loading.value = true
    await loadStatus()
    if (!needsEmailVerification.value) {
      toast.success(t.value('cloudConnect.emailVerification.verified'))
    } else {
      toast.info(t.value('cloudConnect.emailVerification.stillPending'))
    }
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.emailVerification.checkFailed'))
  } finally {
    loading.value = false
  }
}

const resendVerificationEmail = async () => {
  try {
    loading.value = true
    await resendCloudConnectVerification()
    toast.success(t.value('cloudConnect.emailVerification.resent'))
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.emailVerification.resendFailed'))
  } finally {
    loading.value = false
  }
}

const handleCheckout = async () => {
  try {
    loading.value = true
    const result = await createCloudConnectCheckout(selectedPlan.value)
    
    // Open checkout in new tab
    window.open(result.checkout_url, '_blank')
    
    // Start polling for subscription status
    startSubscriptionPolling(result.poll_interval || 3000)
    
    toast.info(t.value('cloudConnect.checkoutOpened'))
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.checkoutFailed'))
  } finally {
    loading.value = false
  }
}

const startSubscriptionPolling = (interval) => {
  pollingSubscription.value = true
  
  pollInterval.value = setInterval(async () => {
    try {
      const subscription = await getCloudConnectSubscription()
      if (subscription.status === 'active' || subscription.status === 'trialing') {
        clearInterval(pollInterval.value)
        pollingSubscription.value = false
        toast.success(t.value('cloudConnect.subscriptionActive'))
        await loadStatus()
      }
    } catch (error) {
      console.error('Subscription poll error:', error)
    }
  }, interval)
  
  // Stop polling after 5 minutes
  setTimeout(() => {
    if (pollInterval.value) {
      clearInterval(pollInterval.value)
      pollingSubscription.value = false
    }
  }, 5 * 60 * 1000)
}

const stopPolling = () => {
  if (pollInterval.value) {
    clearInterval(pollInterval.value)
    pollingSubscription.value = false
  }
}

const handleCheckSubdomain = async () => {
  if (!instanceForm.value.subdomain || instanceForm.value.subdomain.length < 3) {
    return
  }

  try {
    checkingSubdomain.value = true
    const result = await checkCloudConnectSubdomain(instanceForm.value.subdomain)
    subdomainAvailable.value = result.available
    subdomainSuggestions.value = result.suggestions || []
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.subdomainCheckFailed'))
    subdomainAvailable.value = null
  } finally {
    checkingSubdomain.value = false
  }
}

const selectSuggestion = (suggestion) => {
  instanceForm.value.subdomain = suggestion
  subdomainAvailable.value = true
  subdomainSuggestions.value = []
}

const handleCreateInstance = async () => {
  if (!subdomainAvailable.value) {
    toast.error(t.value('cloudConnect.subdomainNotAvailable'))
    return
  }

  try {
    loading.value = true
    await createCloudConnectInstance(instanceForm.value.name, instanceForm.value.subdomain)
    toast.success(t.value('cloudConnect.instanceCreated'))
    await loadStatus()
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.instanceCreateFailed'))
  } finally {
    loading.value = false
  }
}

const handleConnect = async () => {
  try {
    connecting.value = true
    await connectCloudConnect()
    toast.success(t.value('cloudConnect.connected'))
    await loadStatus()
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.connectFailed'))
  } finally {
    connecting.value = false
  }
}

const handleDisconnect = async () => {
  try {
    disconnecting.value = true
    await disconnectCloudConnect()
    toast.success(t.value('cloudConnect.disconnected'))
    await loadStatus()
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.disconnectFailed'))
  } finally {
    disconnecting.value = false
  }
}

const copyDomain = async () => {
  try {
    await navigator.clipboard.writeText(`https://${status.value.full_domain}`)
    copiedDomain.value = true
    setTimeout(() => {
      copiedDomain.value = false
    }, 2000)
  } catch (error) {
    toast.error(t.value('cloudConnect.copyFailed'))
  }
}

const refreshStatus = async () => {
  await loadStatus()
}

defineExpose({
  refreshStatus
})
</script>

<template>
  <div class="cloud-connect-container">
    <!-- Loading State -->
    <div v-if="loading && currentStep === 'loading'" class="loading-state">
      <Loader2 class="spinner" />
      <p>{{ $t('cloudConnect.loading') }}</p>
    </div>

    <!-- Capabilities Error -->
    <div v-else-if="currentStep === 'capabilities_error'" class="step-container">
      <div class="step-header error">
        <AlertTriangle />
        <h3>{{ $t('cloudConnect.capabilitiesError.title') }}</h3>
      </div>
      <div class="step-content">
        <p>{{ $t('cloudConnect.capabilitiesError.description') }}</p>
        <div class="capabilities-list">
          <div class="capability-item" :class="{ success: status?.capabilities?.has_wg_tools, error: !status?.capabilities?.has_wg_tools }">
            <component :is="status?.capabilities?.has_wg_tools ? CheckCircle : XCircle" />
            <span>{{ $t('cloudConnect.capabilities.wireguardTools') }}</span>
          </div>
          <div class="capability-item" :class="{ success: status?.capabilities?.has_tun_device, error: !status?.capabilities?.has_tun_device }">
            <component :is="status?.capabilities?.has_tun_device ? CheckCircle : XCircle" />
            <span>{{ $t('cloudConnect.capabilities.tunDevice') }}</span>
          </div>
          <div class="capability-item" :class="{ success: status?.capabilities?.has_net_admin, error: !status?.capabilities?.has_net_admin }">
            <component :is="status?.capabilities?.has_net_admin ? CheckCircle : XCircle" />
            <span>{{ $t('cloudConnect.capabilities.netAdmin') }}</span>
          </div>
        </div>
        <div class="help-box">
          <h4>{{ $t('cloudConnect.capabilitiesError.howToFix') }}</h4>
          <p>{{ $t('cloudConnect.capabilitiesError.addToCompose') }}</p>
          <pre><code>services:
  app:
    cap_add:
      - NET_ADMIN
    devices:
      - /dev/net/tun:/dev/net/tun</code></pre>
        </div>
        <button @click="refreshStatus" class="btn-secondary">
          <RefreshCw />
          {{ $t('cloudConnect.checkAgain') }}
        </button>
      </div>
    </div>

    <!-- Auth Step -->
    <div v-else-if="currentStep === 'auth'" class="step-container">
      <div class="step-header">
        <Cloud />
        <h3>{{ $t('cloudConnect.auth.title') }}</h3>
      </div>
      <div class="step-content">
        <p>{{ $t('cloudConnect.auth.description') }}</p>
        
        <div class="auth-tabs">
          <button 
            :class="{ active: authMode === 'login' }" 
            @click="authMode = 'login'"
          >
            <LogIn />
            {{ $t('cloudConnect.auth.login') }}
          </button>
          <button 
            :class="{ active: authMode === 'register' }" 
            @click="authMode = 'register'"
          >
            <UserPlus />
            {{ $t('cloudConnect.auth.register') }}
          </button>
        </div>

        <!-- Login Form -->
        <form v-if="authMode === 'login'" @submit.prevent="handleLogin" class="auth-form">
          <div class="form-group">
            <label for="login-email">{{ $t('cloudConnect.auth.email') }}</label>
            <input 
              type="email" 
              id="login-email" 
              v-model="loginForm.email" 
              required 
              :placeholder="$t('cloudConnect.auth.emailPlaceholder')"
            />
          </div>
          <div class="form-group">
            <label for="login-password">{{ $t('cloudConnect.auth.password') }}</label>
            <input 
              type="password" 
              id="login-password" 
              v-model="loginForm.password" 
              required 
              :placeholder="$t('cloudConnect.auth.passwordPlaceholder')"
            />
          </div>
          <button type="submit" class="btn-primary" :disabled="loading">
            <Loader2 v-if="loading" class="spinner" />
            <LogIn v-else />
            {{ $t('cloudConnect.auth.loginButton') }}
          </button>
        </form>

        <!-- Register Form -->
        <form v-else @submit.prevent="handleRegister" class="auth-form">
          <div class="form-group">
            <label for="register-name">{{ $t('cloudConnect.auth.name') }}</label>
            <input 
              type="text" 
              id="register-name" 
              v-model="registerForm.name" 
              required 
              :placeholder="$t('cloudConnect.auth.namePlaceholder')"
            />
          </div>
          <div class="form-group">
            <label for="register-email">{{ $t('cloudConnect.auth.email') }}</label>
            <input 
              type="email" 
              id="register-email" 
              v-model="registerForm.email" 
              required 
              :placeholder="$t('cloudConnect.auth.emailPlaceholder')"
            />
          </div>
          <div class="form-group">
            <label for="register-password">{{ $t('cloudConnect.auth.password') }}</label>
            <input 
              type="password" 
              id="register-password" 
              v-model="registerForm.password" 
              required 
              minlength="8"
              :placeholder="$t('cloudConnect.auth.passwordPlaceholder')"
            />
          </div>
          <div class="form-group">
            <label for="register-password-confirm">{{ $t('cloudConnect.auth.confirmPassword') }}</label>
            <input 
              type="password" 
              id="register-password-confirm" 
              v-model="registerForm.password_confirmation" 
              required 
              :placeholder="$t('cloudConnect.auth.confirmPasswordPlaceholder')"
            />
          </div>
          <div class="checkbox-group">
            <input type="checkbox" id="accept-terms" v-model="registerForm.accept_terms" required />
            <label for="accept-terms">
              {{ $t('cloudConnect.auth.acceptTerms') }}
              <a href="https://erugo.cloud/terms" target="_blank">{{ $t('cloudConnect.auth.termsLink') }}</a>
            </label>
          </div>
          <div class="checkbox-group">
            <input type="checkbox" id="accept-privacy" v-model="registerForm.accept_privacy" required />
            <label for="accept-privacy">
              {{ $t('cloudConnect.auth.acceptPrivacy') }}
              <a href="https://erugo.cloud/privacy" target="_blank">{{ $t('cloudConnect.auth.privacyLink') }}</a>
            </label>
          </div>
          <div class="checkbox-group">
            <input type="checkbox" id="accept-marketing" v-model="registerForm.accept_marketing" />
            <label for="accept-marketing">{{ $t('cloudConnect.auth.acceptMarketing') }}</label>
          </div>
          <button type="submit" class="btn-primary" :disabled="loading">
            <Loader2 v-if="loading" class="spinner" />
            <UserPlus v-else />
            {{ $t('cloudConnect.auth.registerButton') }}
          </button>
        </form>
      </div>
    </div>

    <!-- Email Verification Step -->
    <div v-else-if="currentStep === 'email_verification'" class="step-container">
      <div class="step-header warning">
        <AlertTriangle />
        <h3>{{ $t('cloudConnect.emailVerification.title') }}</h3>
      </div>
      <div class="step-content">
        <div class="verification-message">
          <p>{{ $t('cloudConnect.emailVerification.description') }}</p>
          <p class="email-sent-to">
            {{ $t('cloudConnect.emailVerification.sentTo') }}: <strong>{{ status?.user_email }}</strong>
          </p>
        </div>

        <div class="verification-instructions">
          <h4>{{ $t('cloudConnect.emailVerification.instructions') }}</h4>
          <ol>
            <li>{{ $t('cloudConnect.emailVerification.step1') }}</li>
            <li>{{ $t('cloudConnect.emailVerification.step2') }}</li>
            <li>{{ $t('cloudConnect.emailVerification.step3') }}</li>
          </ol>
        </div>

        <button @click="checkVerificationStatus" class="btn-primary" :disabled="loading">
          <Loader2 v-if="loading" class="spinner" />
          <RefreshCw v-else />
          {{ $t('cloudConnect.emailVerification.checkStatus') }}
        </button>

        <p class="resend-note">
          {{ $t('cloudConnect.emailVerification.didntReceive') }}
          <button @click="resendVerificationEmail" class="btn-text" :disabled="loading">
            {{ $t('cloudConnect.emailVerification.resend') }}
          </button>
        </p>

        <div class="user-info">
          <span>{{ status?.user_email }}</span>
          <button @click="handleLogout" class="btn-text">
            <LogOut />
            {{ $t('cloudConnect.auth.logout') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Subscription Step -->
    <div v-else-if="currentStep === 'subscription'" class="step-container">
      <div class="step-header">
        <CreditCard />
        <h3>{{ $t('cloudConnect.subscription.title') }}</h3>
      </div>
      <div class="step-content">
        <p>{{ $t('cloudConnect.subscription.description') }}</p>
        
        <div class="user-info">
          <span>{{ $t('cloudConnect.subscription.loggedInAs') }}: {{ status?.user_email }}</span>
          <button @click="handleLogout" class="btn-text">
            <LogOut />
            {{ $t('cloudConnect.auth.logout') }}
          </button>
        </div>

        <div class="plan-selector">
          <div 
            class="plan-card" 
            :class="{ selected: selectedPlan === 'pro' }"
            @click="selectedPlan = 'pro'"
          >
            <h4>Pro</h4>
            <div class="price">$5<span>/{{ $t('cloudConnect.subscription.month') }}</span></div>
            <ul>
              <li>{{ $t('cloudConnect.subscription.pro.instances', { count: 3 }) }}</li>
              <li>{{ $t('cloudConnect.subscription.pro.subdomain') }}</li>
              <li>{{ $t('cloudConnect.subscription.pro.customDomain', { count: 1 }) }}</li>
              <li>{{ $t('cloudConnect.subscription.pro.emailSupport') }}</li>
            </ul>
          </div>
          <div 
            class="plan-card" 
            :class="{ selected: selectedPlan === 'business' }"
            @click="selectedPlan = 'business'"
          >
            <h4>Business</h4>
            <div class="price">$15<span>/{{ $t('cloudConnect.subscription.month') }}</span></div>
            <ul>
              <li>{{ $t('cloudConnect.subscription.business.instances', { count: 10 }) }}</li>
              <li>{{ $t('cloudConnect.subscription.business.subdomain') }}</li>
              <li>{{ $t('cloudConnect.subscription.business.customDomains', { count: 5 }) }}</li>
              <li>{{ $t('cloudConnect.subscription.business.prioritySupport') }}</li>
            </ul>
          </div>
        </div>

        <button @click="handleCheckout" class="btn-primary" :disabled="loading || pollingSubscription">
          <Loader2 v-if="loading || pollingSubscription" class="spinner" />
          <CreditCard v-else />
          {{ pollingSubscription ? $t('cloudConnect.subscription.waitingForPayment') : $t('cloudConnect.subscription.subscribe') }}
        </button>

        <p v-if="pollingSubscription" class="polling-note">
          {{ $t('cloudConnect.subscription.pollingNote') }}
          <button @click="stopPolling" class="btn-text">{{ $t('cloudConnect.subscription.stopWaiting') }}</button>
        </p>
      </div>
    </div>

    <!-- Instance Setup Step -->
    <div v-else-if="currentStep === 'instance'" class="step-container">
      <div class="step-header">
        <Server />
        <h3>{{ $t('cloudConnect.instance.title') }}</h3>
      </div>
      <div class="step-content">
        <p>{{ $t('cloudConnect.instance.description') }}</p>

        <form @submit.prevent="handleCreateInstance" class="instance-form">
          <div class="form-group">
            <label for="instance-name">{{ $t('cloudConnect.instance.name') }}</label>
            <input 
              type="text" 
              id="instance-name" 
              v-model="instanceForm.name" 
              required 
              :placeholder="$t('cloudConnect.instance.namePlaceholder')"
            />
          </div>
          <div class="form-group">
            <label for="instance-subdomain">{{ $t('cloudConnect.instance.subdomain') }}</label>
            <div class="subdomain-input">
              <input 
                type="text" 
                id="instance-subdomain" 
                v-model="instanceForm.subdomain" 
                required 
                pattern="^[a-z0-9][a-z0-9-]*[a-z0-9]$"
                minlength="3"
                maxlength="63"
                :placeholder="$t('cloudConnect.instance.subdomainPlaceholder')"
                @blur="handleCheckSubdomain"
                @input="subdomainAvailable = null"
              />
              <span class="subdomain-suffix">.erugo.cloud</span>
              <span v-if="checkingSubdomain" class="subdomain-status checking">
                <Loader2 class="spinner" />
              </span>
              <span v-else-if="subdomainAvailable === true" class="subdomain-status available">
                <CheckCircle />
              </span>
              <span v-else-if="subdomainAvailable === false" class="subdomain-status unavailable">
                <XCircle />
              </span>
            </div>
            <div v-if="subdomainAvailable === false && subdomainSuggestions.length > 0" class="subdomain-suggestions">
              <span>{{ $t('cloudConnect.instance.suggestions') }}:</span>
              <button 
                v-for="suggestion in subdomainSuggestions" 
                :key="suggestion" 
                type="button"
                @click="selectSuggestion(suggestion)"
                class="suggestion-btn"
              >
                {{ suggestion }}
              </button>
            </div>
          </div>
          <button 
            type="submit" 
            class="btn-primary" 
            :disabled="loading || !subdomainAvailable"
          >
            <Loader2 v-if="loading" class="spinner" />
            <Server v-else />
            {{ $t('cloudConnect.instance.create') }}
          </button>
        </form>
      </div>
    </div>

    <!-- Ready to Connect Step -->
    <div v-else-if="currentStep === 'ready'" class="step-container">
      <div class="step-header">
        <Wifi />
        <h3>{{ $t('cloudConnect.ready.title') }}</h3>
      </div>
      <div class="step-content">
        <p>{{ $t('cloudConnect.ready.description') }}</p>

        <div class="instance-info">
          <div class="info-row">
            <Globe />
            <span>{{ status?.full_domain }}</span>
          </div>
          <div class="info-row">
            <Server />
            <span>{{ status?.tunnel_ip }}</span>
          </div>
        </div>

        <button @click="handleConnect" class="btn-primary btn-large" :disabled="connecting">
          <Loader2 v-if="connecting" class="spinner" />
          <Wifi v-else />
          {{ connecting ? $t('cloudConnect.ready.connecting') : $t('cloudConnect.ready.connect') }}
        </button>

        <div class="user-info">
          <span>{{ status?.user_email }}</span>
          <button @click="handleLogout" class="btn-text">
            <LogOut />
            {{ $t('cloudConnect.auth.logout') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Connected Step -->
    <div v-else-if="currentStep === 'connected'" class="step-container">
      <div class="step-header success">
        <CheckCircle />
        <h3>{{ $t('cloudConnect.connected.title') }}</h3>
      </div>
      <div class="step-content">
        <div class="connected-status">
          <div class="status-indicator online">
            <Wifi />
            <span>{{ $t('cloudConnect.connected.online') }}</span>
          </div>
        </div>

        <div class="domain-display">
          <Globe />
          <a :href="`https://${status?.full_domain}`" target="_blank" class="domain-link">
            https://{{ status?.full_domain }}
            <ExternalLink />
          </a>
          <button @click="copyDomain" class="btn-icon" :title="$t('cloudConnect.connected.copyUrl')">
            <Check v-if="copiedDomain" />
            <Copy v-else />
          </button>
        </div>

        <div class="connection-details">
          <div class="detail-row">
            <span class="label">{{ $t('cloudConnect.connected.subdomain') }}:</span>
            <span class="value">{{ status?.subdomain }}</span>
          </div>
          <div class="detail-row">
            <span class="label">{{ $t('cloudConnect.connected.tunnelIp') }}:</span>
            <span class="value">{{ status?.tunnel_ip }}</span>
          </div>
          <div class="detail-row">
            <span class="label">{{ $t('cloudConnect.connected.subscription') }}:</span>
            <span class="value">{{ status?.subscription_plan }} ({{ status?.subscription_status }})</span>
          </div>
        </div>

        <div class="action-buttons">
          <button @click="handleDisconnect" class="btn-danger" :disabled="disconnecting">
            <Loader2 v-if="disconnecting" class="spinner" />
            <WifiOff v-else />
            {{ disconnecting ? $t('cloudConnect.connected.disconnecting') : $t('cloudConnect.connected.disconnect') }}
          </button>
          <button @click="refreshStatus" class="btn-secondary">
            <RefreshCw />
            {{ $t('cloudConnect.refresh') }}
          </button>
        </div>

        <div class="user-info">
          <span>{{ status?.user_email }}</span>
          <button @click="handleLogout" class="btn-text">
            <LogOut />
            {{ $t('cloudConnect.auth.logout') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.cloud-connect-container {
  padding: 20px;
  max-width: 800px;
  margin: 0 auto;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: var(--panel-section-text-color);

  .spinner {
    width: 48px;
    height: 48px;
    animation: spin 1s linear infinite;
    margin-bottom: 16px;
  }
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.step-container {
  background: var(--panel-section-background-color);
  border-radius: 12px;
  overflow: hidden;
}

.step-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px 24px;
  background: var(--panel-section-background-color-alt);
  border-bottom: 1px solid var(--panel-border-color);

  svg {
    width: 24px;
    height: 24px;
    color: var(--button-primary-background-color);
  }

  h3 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--panel-section-text-color);
  }

  &.error svg {
    color: var(--color-danger, #ef4444);
  }

  &.success svg {
    color: var(--color-success, #22c55e);
  }

  &.warning svg {
    color: var(--color-warning, #f59e0b);
  }
}

.step-content {
  padding: 24px;

  > p {
    margin: 0 0 24px;
    color: var(--panel-section-text-color);
    opacity: 0.8;
  }
}

.verification-message {
  background: color-mix(in srgb, var(--color-warning, #f59e0b) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-warning, #f59e0b) 30%, transparent);
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 24px;

  p {
    margin: 0;
    color: var(--panel-section-text-color);

    &.email-sent-to {
      margin-top: 12px;
      font-size: 0.95rem;

      strong {
        color: var(--button-primary-background-color);
      }
    }
  }
}

.verification-instructions {
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 24px;

  h4 {
    margin: 0 0 12px;
    font-size: 1rem;
    color: var(--panel-section-text-color);
  }

  ol {
    margin: 0;
    padding-left: 20px;

    li {
      padding: 6px 0;
      color: var(--panel-section-text-color);
      opacity: 0.8;
    }
  }
}

.resend-note {
  margin-top: 16px;
  text-align: center;
  font-size: 0.875rem;
  color: var(--panel-section-text-color);
  opacity: 0.7;
}

.capabilities-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 24px;
}

.capability-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;

  svg {
    width: 20px;
    height: 20px;
  }

  &.success svg {
    color: var(--color-success, #22c55e);
  }

  &.error svg {
    color: var(--color-danger, #ef4444);
  }
}

.help-box {
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 24px;

  h4 {
    margin: 0 0 12px;
    font-size: 1rem;
    color: var(--panel-section-text-color);
  }

  p {
    margin: 0 0 12px;
    font-size: 0.875rem;
    opacity: 0.8;
  }

  pre {
    background: var(--panel-background-color);
    border-radius: 6px;
    padding: 12px;
    overflow-x: auto;
    margin: 0;

    code {
      font-family: 'Monaco', 'Menlo', monospace;
      font-size: 0.8rem;
      color: var(--panel-section-text-color);
    }
  }
}

.auth-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 24px;

  button {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    border: 2px solid var(--panel-border-color);
    background: transparent;
    border-radius: 8px;
    color: var(--panel-section-text-color);
    cursor: pointer;
    transition: all 0.2s;

    svg {
      width: 18px;
      height: 18px;
    }

    &.active {
      border-color: var(--button-primary-background-color);
      background: var(--button-primary-background-color);
      color: var(--button-primary-text-color);
    }

    &:hover:not(.active) {
      border-color: var(--button-primary-background-color);
    }
  }
}

.auth-form,
.instance-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;

  label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--panel-section-text-color);
  }

  input {
    padding: 12px 16px;
    border: 1px solid var(--panel-border-color);
    border-radius: 8px;
    background: var(--panel-section-background-color-alt);
    color: var(--panel-section-text-color);
    font-size: 1rem;

    &:focus {
      outline: none;
      border-color: var(--button-primary-background-color);
    }
  }
}

.checkbox-group {
  display: flex;
  align-items: flex-start;
  gap: 8px;

  input[type="checkbox"] {
    margin-top: 3px;
  }

  label {
    font-size: 0.875rem;
    color: var(--panel-section-text-color);
    opacity: 0.8;

    a {
      color: var(--button-primary-background-color);
    }
  }
}

.btn-primary {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 24px;
  background: var(--button-primary-background-color);
  color: var(--button-primary-text-color);
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 500;
  cursor: pointer;
  transition: opacity 0.2s;

  svg {
    width: 20px;
    height: 20px;
  }

  &:hover:not(:disabled) {
    opacity: 0.9;
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  &.btn-large {
    padding: 18px 32px;
    font-size: 1.125rem;
  }
}

.btn-secondary {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 20px;
  background: var(--panel-section-background-color-alt);
  color: var(--panel-section-text-color);
  border: 1px solid var(--panel-border-color);
  border-radius: 8px;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;

  svg {
    width: 18px;
    height: 18px;
  }

  &:hover {
    background: var(--panel-border-color);
  }
}

.btn-danger {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 20px;
  background: var(--color-danger, #ef4444);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 0.875rem;
  cursor: pointer;
  transition: opacity 0.2s;

  svg {
    width: 18px;
    height: 18px;
  }

  &:hover:not(:disabled) {
    opacity: 0.9;
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.btn-text {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: transparent;
  color: var(--panel-section-text-color);
  border: none;
  font-size: 0.875rem;
  cursor: pointer;
  opacity: 0.7;
  transition: opacity 0.2s;

  svg {
    width: 16px;
    height: 16px;
  }

  &:hover {
    opacity: 1;
  }
}

.btn-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
  background: transparent;
  color: var(--panel-section-text-color);
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.2s;

  svg {
    width: 18px;
    height: 18px;
  }

  &:hover {
    background: var(--panel-section-background-color-alt);
  }
}

.user-info {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid var(--panel-border-color);
  font-size: 0.875rem;
  color: var(--panel-section-text-color);
  opacity: 0.7;
}

.plan-selector {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.plan-card {
  padding: 24px;
  background: var(--panel-section-background-color-alt);
  border: 2px solid var(--panel-border-color);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;

  &.selected {
    border-color: var(--button-primary-background-color);
    background: color-mix(in srgb, var(--button-primary-background-color) 10%, transparent);
  }

  &:hover:not(.selected) {
    border-color: var(--button-primary-background-color);
  }

  h4 {
    margin: 0 0 8px;
    font-size: 1.25rem;
    color: var(--panel-section-text-color);
  }

  .price {
    font-size: 2rem;
    font-weight: 700;
    color: var(--button-primary-background-color);
    margin-bottom: 16px;

    span {
      font-size: 1rem;
      font-weight: 400;
      opacity: 0.7;
    }
  }

  ul {
    margin: 0;
    padding: 0;
    list-style: none;

    li {
      padding: 6px 0;
      font-size: 0.875rem;
      color: var(--panel-section-text-color);
      opacity: 0.8;

      &::before {
        content: '✓';
        margin-right: 8px;
        color: var(--color-success, #22c55e);
      }
    }
  }
}

.polling-note {
  margin-top: 16px;
  font-size: 0.875rem;
  color: var(--panel-section-text-color);
  opacity: 0.7;
  text-align: center;
}

.subdomain-input {
  display: flex;
  align-items: center;
  gap: 4px;
  position: relative;

  input {
    flex: 1;
    padding-right: 140px;
  }

  .subdomain-suffix {
    position: absolute;
    right: 40px;
    color: var(--panel-section-text-color);
    opacity: 0.6;
    font-size: 0.875rem;
  }

  .subdomain-status {
    position: absolute;
    right: 12px;

    svg {
      width: 20px;
      height: 20px;
    }

    &.checking svg {
      animation: spin 1s linear infinite;
    }

    &.available svg {
      color: var(--color-success, #22c55e);
    }

    &.unavailable svg {
      color: var(--color-danger, #ef4444);
    }
  }
}

.subdomain-suggestions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
  font-size: 0.875rem;
  color: var(--panel-section-text-color);

  .suggestion-btn {
    padding: 4px 12px;
    background: var(--panel-section-background-color-alt);
    border: 1px solid var(--panel-border-color);
    border-radius: 16px;
    color: var(--button-primary-background-color);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;

    &:hover {
      background: var(--button-primary-background-color);
      color: var(--button-primary-text-color);
    }
  }
}

.instance-info {
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 24px;

  .info-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    color: var(--panel-section-text-color);

    svg {
      width: 18px;
      height: 18px;
      opacity: 0.6;
    }
  }
}

.connected-status {
  display: flex;
  justify-content: center;
  margin-bottom: 24px;

  .status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 24px;
    font-weight: 500;

    svg {
      width: 20px;
      height: 20px;
    }

    &.online {
      background: color-mix(in srgb, var(--color-success, #22c55e) 15%, transparent);
      color: var(--color-success, #22c55e);
    }
  }
}

.domain-display {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 20px;
  background: var(--panel-section-background-color-alt);
  border-radius: 12px;
  margin-bottom: 24px;

  svg:first-child {
    width: 24px;
    height: 24px;
    color: var(--button-primary-background-color);
  }

  .domain-link {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1.125rem;
    font-weight: 500;
    color: var(--button-primary-background-color);
    text-decoration: none;

    svg {
      width: 16px;
      height: 16px;
    }

    &:hover {
      text-decoration: underline;
    }
  }
}

.connection-details {
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 24px;

  .detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 0.875rem;

    &:not(:last-child) {
      border-bottom: 1px solid var(--panel-border-color);
    }

    .label {
      color: var(--panel-section-text-color);
      opacity: 0.7;
    }

    .value {
      color: var(--panel-section-text-color);
      font-weight: 500;
    }
  }
}

.action-buttons {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.spinner {
  animation: spin 1s linear infinite;
}
</style>

