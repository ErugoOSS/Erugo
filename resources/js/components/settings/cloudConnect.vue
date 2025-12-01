<script setup>
import { ref, onMounted, computed, onUnmounted, watch } from 'vue'
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
  Mail,
  CircleX,
  BarChart3,
  Pencil,
  Trash2,
  Key,
  User,
  ArrowUpDown,
  ArrowDown,
  ArrowUp,
  HardDrive,
  X
} from 'lucide-vue-next'
import {
  getCloudConnectStatus,
  cloudConnectRegister,
  cloudConnectLogin,
  cloudConnectLogout,
  getCloudConnectSubscription,
  getCloudConnectPlans,
  createCloudConnectCheckout,
  checkCloudConnectSubdomain,
  createCloudConnectInstance,
  connectCloudConnect,
  disconnectCloudConnect,
  resendCloudConnectVerification,
  getCloudConnectUsage,
  getCloudConnectInstances,
  getCloudConnectInstance,
  updateCloudConnectInstance,
  deleteCloudConnectInstance,
  regenerateCloudConnectInstanceToken,
  createCloudConnectBillingPortal,
  cloudConnectForgotPassword
} from '../../api'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()
const toast = useToast()
const emit = defineEmits(['loginStateChanged', 'navItemClicked'])

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
const subdomainOwnedByUser = ref(false)
const existingInstanceName = ref(null)
const showReclaimConfirm = ref(false)
const pollingSubscription = ref(false)
const pollInterval = ref(null)
const statusPollInterval = ref(null)
const copiedDomain = ref(false)
const showLoginForm = ref(false)
const showRegisterForm = ref(false)

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

const selectedPlan = ref(null)
const plans = ref([])
const loadingPlans = ref(false)
const showPlanManagement = ref(false)

// Usage data
const usageData = ref(null)
const loadingUsage = ref(false)

// Instances management
const instances = ref([])
const loadingInstances = ref(false)
const selectedInstance = ref(null)
const showEditInstanceForm = ref(false)
const showDeleteConfirm = ref(false)
const showRegenerateTokenConfirm = ref(false)
const editInstanceForm = ref({
  name: '',
  subdomain: ''
})
const editingInstanceSubdomain = ref(false)
const regeneratedToken = ref(null)
const showRegeneratedToken = ref(false)

// Billing portal
const loadingBillingPortal = ref(false)

// Account settings
const showForgotPasswordForm = ref(false)
const forgotPasswordEmail = ref('')
const sendingForgotPassword = ref(false)

// Computed
const canConnect = computed(() => {
  return status.value?.capabilities?.capable && 
         status.value?.has_instance && 
         status.value?.status !== 'connected'
})

const isConnected = computed(() => {
  return status.value?.status === 'connected' && status.value?.tunnel_active
})

const isReconnecting = computed(() => {
  return status.value?.status === 'reconnecting'
})

const lastHeartbeatFormatted = computed(() => {
  if (!status.value?.last_heartbeat_at) {
    return t.value('cloudConnect.connected.heartbeatNever')
  }
  
  const lastHeartbeat = new Date(status.value.last_heartbeat_at)
  const now = new Date()
  const diffSeconds = Math.floor((now - lastHeartbeat) / 1000)
  
  if (diffSeconds < 60) {
    return t.value('cloudConnect.connected.heartbeatAgo', { time: `${diffSeconds}s` })
  } else if (diffSeconds < 3600) {
    const mins = Math.floor(diffSeconds / 60)
    return t.value('cloudConnect.connected.heartbeatAgo', { time: `${mins}m` })
  } else {
    const hours = Math.floor(diffSeconds / 3600)
    return t.value('cloudConnect.connected.heartbeatAgo', { time: `${hours}h` })
  }
})

const heartbeatHealthy = computed(() => {
  if (!status.value?.last_heartbeat_at) return false
  if (!status.value?.last_heartbeat_success) return false
  
  // Consider unhealthy if last heartbeat was more than 2 minutes ago
  const lastHeartbeat = new Date(status.value.last_heartbeat_at)
  const now = new Date()
  const diffSeconds = Math.floor((now - lastHeartbeat) / 1000)
  
  return diffSeconds < 120
})

const hasActiveSubscription = computed(() => {
  // Consider active if status is active/trialing OR if user has any plan assigned (including free)
  return status.value?.subscription_status === 'active' || 
         status.value?.subscription_status === 'trialing' ||
         (status.value?.subscription_plan && status.value?.subscription_plan !== 'none')
})

const currentPlan = computed(() => {
  if (!status.value?.subscription_plan || plans.value.length === 0) return null
  return plans.value.find(p => p.name === status.value.subscription_plan)
})

const needsEmailVerification = computed(() => {
  return status.value?.account_status === 'pending_email_verification'
})

// Watch for login state changes and emit to parent
watch(() => status.value?.is_logged_in, (isLoggedIn) => {
  emit('loginStateChanged', !!isLoggedIn)
}, { immediate: true })

// Lifecycle
onMounted(async () => {
  await loadStatus()
})

onUnmounted(() => {
  if (pollInterval.value) {
    clearInterval(pollInterval.value)
  }
  if (statusPollInterval.value) {
    clearInterval(statusPollInterval.value)
  }
})

// Methods
const loadStatus = async (refresh = false) => {
  try {
    loading.value = true
    status.value = await getCloudConnectStatus(refresh)
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
    stopStatusPolling()
    return
  }

  // Check capabilities first
  if (!status.value.capabilities?.capable) {
    currentStep.value = 'capabilities_error'
    stopStatusPolling()
    return
  }

  // Check if connected (or reconnecting - still show connected UI)
  if ((status.value.status === 'connected' || status.value.status === 'reconnecting') && status.value.tunnel_active) {
    currentStep.value = 'connected'
    // Start polling for status updates when connected
    startStatusPolling()
    // Pre-load plans in background for plan management display
    loadPlans()
    return
  }

  // Not connected, stop polling
  stopStatusPolling()

  // Check if logged in
  if (!status.value.is_logged_in) {
    currentStep.value = 'auth'
    return
  }

  // User is logged in - load plans in background for plan section
  loadPlans()

  // Check if email verification is needed
  if (needsEmailVerification.value) {
    currentStep.value = 'email_verification'
    return
  }

  // Check subscription
  if (!hasActiveSubscription.value) {
    currentStep.value = 'subscription'
    loadPlans()
    return
  }

  // Check if has instance
  if (!status.value.has_instance) {
    currentStep.value = 'instance'
    return
  }

  // Ready to connect
  currentStep.value = 'ready'
  // Pre-load plans in background for plan management display
  loadPlans()
}

const startStatusPolling = () => {
  if (statusPollInterval.value) return // Already polling
  
  // Poll every 30 seconds to get updated heartbeat status
  statusPollInterval.value = setInterval(async () => {
    try {
      status.value = await getCloudConnectStatus(false)
      // Re-determine step in case status changed
      if (status.value.status !== 'connected' && status.value.status !== 'reconnecting') {
        determineStep()
      }
    } catch (error) {
      console.error('Status poll error:', error)
    }
  }, 30000)
}

const stopStatusPolling = () => {
  if (statusPollInterval.value) {
    clearInterval(statusPollInterval.value)
    statusPollInterval.value = null
  }
}

const handleLogin = async () => {
  try {
    loading.value = true
    await cloudConnectLogin(loginForm.value.email, loginForm.value.password)
    toast.success(t.value('cloudConnect.loginSuccess'))
    showLoginForm.value = false
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
    showRegisterForm.value = false
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
    await loadStatus(true) // Force refresh from API
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

const loadPlans = async (forceRefresh = false) => {
  if (plans.value.length > 0 && !forceRefresh) return // Already loaded
  
  try {
    loadingPlans.value = true
    const result = await getCloudConnectPlans()
    plans.value = result.plans || []
    
    // Auto-select first plan if none selected
    if (!selectedPlan.value && plans.value.length > 0) {
      selectedPlan.value = plans.value[0].name
    }
  } catch (error) {
    console.error('Failed to load plans:', error)
    toast.error(t.value('cloudConnect.subscription.loadPlansFailed'))
  } finally {
    loadingPlans.value = false
  }
}

const openPlanManagement = async () => {
  await loadPlans()
  // Pre-select current plan
  if (status.value?.subscription_plan) {
    selectedPlan.value = status.value.subscription_plan
  }
  showPlanManagement.value = true
}

const closePlanManagement = () => {
  showPlanManagement.value = false
}

const handleChangePlan = async () => {
  // If selecting current plan, just close
  if (selectedPlan.value === status.value?.subscription_plan) {
    closePlanManagement()
    return
  }
  
  try {
    loading.value = true
    
    // Check if selected plan is free
    const plan = plans.value.find(p => p.name === selectedPlan.value)
    if (plan?.is_free) {
      // For downgrading to free, we might need API support
      // For now, show a message that they need to cancel their subscription
      toast.info(t.value('cloudConnect.planManagement.contactToDowngrade'))
      closePlanManagement()
      return
    }
    
    const result = await createCloudConnectCheckout(selectedPlan.value)
    
    // Open checkout in new tab
    window.open(result.checkout_url, '_blank')
    
    // Start polling for subscription status
    startSubscriptionPolling(result.poll_interval || 3000)
    
    toast.info(t.value('cloudConnect.checkoutOpened'))
    closePlanManagement()
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.checkoutFailed'))
  } finally {
    loading.value = false
  }
}

const handleCheckout = async () => {
  try {
    loading.value = true
    
    // Check if selected plan is free
    const plan = plans.value.find(p => p.name === selectedPlan.value)
    if (plan?.is_free) {
      // Free plan - just reload status, API assigns it by default
      toast.success(t.value('cloudConnect.subscription.freePlanSelected'))
      await loadStatus()
      return
    }
    
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
    subdomainOwnedByUser.value = false
    existingInstanceName.value = null
    const result = await checkCloudConnectSubdomain(instanceForm.value.subdomain)
    subdomainAvailable.value = result.available
    subdomainSuggestions.value = result.suggestions || []
    
    // Check if subdomain is owned by the current user
    if (!result.available && result.owned_by_user) {
      subdomainOwnedByUser.value = true
      existingInstanceName.value = result.existing_instance_name
      subdomainAvailable.value = true // Allow them to proceed (will trigger reclaim flow)
    }
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
  subdomainOwnedByUser.value = false
  existingInstanceName.value = null
  subdomainSuggestions.value = []
}

const handleCreateInstance = async (confirmReclaim = false) => {
  if (!subdomainAvailable.value) {
    toast.error(t.value('cloudConnect.subdomainNotAvailable'))
    return
  }

  try {
    loading.value = true
    showReclaimConfirm.value = false
    const result = await createCloudConnectInstance(instanceForm.value.name, instanceForm.value.subdomain, confirmReclaim)
    
    if (result.reclaimed) {
      toast.success(t.value('cloudConnect.instanceReclaimed'))
    } else {
      toast.success(t.value('cloudConnect.instanceCreated'))
    }
    await loadStatus()
  } catch (error) {
    // Handle SUBDOMAIN_OWNED_BY_USER error - show confirmation dialog
    if (error.code === 'SUBDOMAIN_OWNED_BY_USER') {
      existingInstanceName.value = error.data?.existing_instance_name
      showReclaimConfirm.value = true
      return
    }
    toast.error(error.message || t.value('cloudConnect.instanceCreateFailed'))
  } finally {
    loading.value = false
  }
}

const handleConfirmReclaim = () => {
  handleCreateInstance(true)
}

const handleCancelReclaim = () => {
  showReclaimConfirm.value = false
}

const handleConnect = async () => {
  try {
    connecting.value = true
    await connectCloudConnect()
    toast.success(t.value('cloudConnect.tunnelConnected'))
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

const openLoginForm = () => {
  showLoginForm.value = true
  showRegisterForm.value = false
}

const openRegisterForm = () => {
  showRegisterForm.value = true
  showLoginForm.value = false
}

const closeAuthForms = () => {
  showLoginForm.value = false
  showRegisterForm.value = false
}

const loginFormClickOutside = (event) => {
  if (!event.target.closest('.auth-slide-form')) {
    showLoginForm.value = false
  }
}

const registerFormClickOutside = (event) => {
  if (!event.target.closest('.auth-slide-form')) {
    showRegisterForm.value = false
  }
}

const refreshStatus = async () => {
  await loadStatus(true) // Force refresh from API
}

const handleNavItemClicked = (item) => {
  emit('navItemClicked', item)
}

// Usage data methods
const loadUsage = async () => {
  try {
    loadingUsage.value = true
    usageData.value = await getCloudConnectUsage()
  } catch (error) {
    console.error('Failed to load usage:', error)
  } finally {
    loadingUsage.value = false
  }
}

// Instances management methods
const loadInstances = async () => {
  try {
    loadingInstances.value = true
    const result = await getCloudConnectInstances()
    instances.value = result.instances || []
  } catch (error) {
    console.error('Failed to load instances:', error)
    toast.error(t.value('cloudConnect.instances.loadFailed'))
  } finally {
    loadingInstances.value = false
  }
}

const openEditInstanceForm = (instance) => {
  selectedInstance.value = instance
  editInstanceForm.value = {
    name: instance.name || '',
    subdomain: instance.subdomain || ''
  }
  editingInstanceSubdomain.value = false
  showEditInstanceForm.value = true
}

const closeEditInstanceForm = () => {
  showEditInstanceForm.value = false
  selectedInstance.value = null
  editInstanceForm.value = { name: '', subdomain: '' }
  editingInstanceSubdomain.value = false
}

const handleUpdateInstance = async () => {
  if (!selectedInstance.value) return
  
  try {
    loading.value = true
    const updateData = { name: editInstanceForm.value.name }
    
    // Only include subdomain if it was changed
    if (editingInstanceSubdomain.value && editInstanceForm.value.subdomain !== selectedInstance.value.subdomain) {
      updateData.subdomain = editInstanceForm.value.subdomain
    }
    
    await updateCloudConnectInstance(selectedInstance.value.id, updateData)
    toast.success(t.value('cloudConnect.instances.updateSuccess'))
    closeEditInstanceForm()
    await loadInstances()
    await loadStatus(true)
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.instances.updateFailed'))
  } finally {
    loading.value = false
  }
}

const openDeleteConfirm = (instance) => {
  selectedInstance.value = instance
  showDeleteConfirm.value = true
}

const closeDeleteConfirm = () => {
  showDeleteConfirm.value = false
  selectedInstance.value = null
}

const handleDeleteInstance = async () => {
  if (!selectedInstance.value) return
  
  try {
    loading.value = true
    await deleteCloudConnectInstance(selectedInstance.value.id)
    toast.success(t.value('cloudConnect.instances.deleteSuccess'))
    closeDeleteConfirm()
    await loadInstances()
    await loadStatus(true)
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.instances.deleteFailed'))
  } finally {
    loading.value = false
  }
}

const openRegenerateTokenConfirm = (instance) => {
  selectedInstance.value = instance
  showRegenerateTokenConfirm.value = true
}

const closeRegenerateTokenConfirm = () => {
  showRegenerateTokenConfirm.value = false
  selectedInstance.value = null
}

const handleRegenerateToken = async () => {
  if (!selectedInstance.value) return
  
  try {
    loading.value = true
    const result = await regenerateCloudConnectInstanceToken(selectedInstance.value.id)
    regeneratedToken.value = result.instance_token
    showRegenerateTokenConfirm.value = false
    showRegeneratedToken.value = true
    toast.success(t.value('cloudConnect.instances.tokenRegenerated'))
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.instances.tokenRegenerateFailed'))
  } finally {
    loading.value = false
  }
}

const closeRegeneratedTokenModal = () => {
  showRegeneratedToken.value = false
  regeneratedToken.value = null
  selectedInstance.value = null
}

const copyToken = async () => {
  if (!regeneratedToken.value) return
  try {
    await navigator.clipboard.writeText(regeneratedToken.value)
    toast.success(t.value('cloudConnect.instances.tokenCopied'))
  } catch (error) {
    toast.error(t.value('cloudConnect.copyFailed'))
  }
}

// Billing portal methods
const openBillingPortal = async () => {
  try {
    loadingBillingPortal.value = true
    const result = await createCloudConnectBillingPortal(window.location.href)
    window.open(result.portal_url, '_blank')
  } catch (error) {
    toast.error(error.message || t.value('cloudConnect.billing.portalFailed'))
  } finally {
    loadingBillingPortal.value = false
  }
}

// Forgot password methods
const openForgotPasswordForm = () => {
  forgotPasswordEmail.value = status.value?.user_email || loginForm.value.email || ''
  showForgotPasswordForm.value = true
}

const closeForgotPasswordForm = () => {
  showForgotPasswordForm.value = false
  forgotPasswordEmail.value = ''
}

const handleForgotPassword = async () => {
  if (!forgotPasswordEmail.value) {
    toast.error(t.value('cloudConnect.auth.emailRequired'))
    return
  }
  
  try {
    sendingForgotPassword.value = true
    await cloudConnectForgotPassword(forgotPasswordEmail.value)
    toast.success(t.value('cloudConnect.auth.resetEmailSent'))
    closeForgotPasswordForm()
  } catch (error) {
    // Still show success to not reveal if email exists
    toast.success(t.value('cloudConnect.auth.resetEmailSent'))
    closeForgotPasswordForm()
  } finally {
    sendingForgotPassword.value = false
  }
}

// Helper functions
const formatBytes = (bytes) => {
  if (bytes === 0 || bytes === null || bytes === undefined) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString()
}

const getInstanceStatusClass = (instance) => {
  if (instance.status === 'online' || instance.status === 'connected') return 'online'
  if (instance.status === 'offline' || instance.status === 'disconnected') return 'offline'
  return ''
}

// Load data when logged in
watch(() => status.value?.is_logged_in, async (isLoggedIn) => {
  if (isLoggedIn) {
    await loadUsage()
    await loadInstances()
  } else {
    usageData.value = null
    instances.value = []
  }
}, { immediate: true })

defineExpose({
  refreshStatus,
  handleLogout
})
</script>

<template>
  <div class="container-fluid">
    <!-- Loading State -->
    <div v-if="loading && !status" class="loading-state">
      <Loader2 class="spinner" />
      <p>{{ $t('cloudConnect.loading') }}</p>
    </div>

    <!-- Capabilities Error - Full Width Block -->
    <div v-else-if="currentStep === 'capabilities_error'" class="row">
      <div class="col-12 pt-5">
        <div class="setting-group">
          <div class="setting-group-header error">
            <h3>
              <AlertTriangle />
              {{ $t('cloudConnect.capabilitiesError.title') }}
            </h3>
          </div>
          <div class="setting-group-body">
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
            <button @click="refreshStatus" class="secondary">
              <RefreshCw />
              {{ $t('cloudConnect.checkAgain') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Layout with Sidebar -->
    <div v-else class="row">
      <!-- Sidebar Navigation -->
      <div class="col-2 d-none d-md-block">
        <ul class="settings-nav pt-5">
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('status-section')">
              <component :is="isConnected ? Wifi : WifiOff" />
              {{ $t('cloudConnect.nav.status') }}
            </a>
          </li>
          <li v-if="status?.is_logged_in">
            <a href="#" @click.prevent="handleNavItemClicked('usage-section')">
              <BarChart3 />
              {{ $t('cloudConnect.nav.usage') || 'Usage' }}
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('account-section')">
              <component :is="status?.is_logged_in ? LogOut : LogIn" />
              {{ $t('cloudConnect.nav.account') }}
            </a>
          </li>
          <li v-if="status?.is_logged_in">
            <a href="#" @click.prevent="handleNavItemClicked('plan-section')">
              <CreditCard />
              {{ $t('cloudConnect.nav.plan') }}
            </a>
          </li>
          <li v-if="status?.is_logged_in && hasActiveSubscription">
            <a href="#" @click.prevent="handleNavItemClicked('instance-section')">
              <Server />
              {{ $t('cloudConnect.nav.instance') }}
            </a>
          </li>
          <li v-if="status?.has_instance">
            <a href="#" @click.prevent="handleNavItemClicked('connection-section')">
              <Globe />
              {{ $t('cloudConnect.nav.connection') }}
            </a>
          </li>
        </ul>
      </div>

      <!-- Main Content -->
      <div class="col-12 col-md-10 pt-5">
        <!-- Status Section -->
        <div class="row mb-4">
          <div class="col-12">
            <div class="setting-group" id="status-section">
              <div class="setting-group-header" :class="{ success: isConnected && heartbeatHealthy, warning: isConnected && !heartbeatHealthy }">
                <h3>
                  <component :is="isConnected ? (heartbeatHealthy ? CheckCircle : AlertTriangle) : CloudOff" />
                  {{ $t('cloudConnect.nav.status') }}
                </h3>
              </div>
              <div class="setting-group-body">
                <div class="status-overview">
                  <div class="status-item">
                    <span class="status-label">{{ $t('cloudConnect.status.connection') }}:</span>
                    <span class="status-value" :class="{ online: isConnected, offline: !isConnected }">
                      <Wifi v-if="isConnected" />
                      <WifiOff v-else />
                      {{ isConnected ? $t('cloudConnect.status.connected') : $t('cloudConnect.status.disconnected') }}
                    </span>
                  </div>
                  <div class="status-item">
                    <span class="status-label">{{ $t('cloudConnect.status.account') }}:</span>
                    <span class="status-value">
                      {{ status?.is_logged_in ? status?.user_email : $t('cloudConnect.status.notLoggedIn') }}
                    </span>
                  </div>
                  <div v-if="status?.is_logged_in" class="status-item">
                    <span class="status-label">{{ $t('cloudConnect.status.plan') }}:</span>
                    <span class="status-value">
                      {{ currentPlan?.display_name || status?.subscription_plan || $t('cloudConnect.status.noPlan') }}
                    </span>
                  </div>
                  <div v-if="status?.full_domain" class="status-item">
                    <span class="status-label">{{ $t('cloudConnect.status.domain') }}:</span>
                    <span class="status-value domain">
                      <a :href="`https://${status?.full_domain}`" target="_blank">
                        {{ status?.full_domain }}
                        <ExternalLink />
                      </a>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Usage Dashboard Section (visible when logged in) -->
        <div v-if="status?.is_logged_in && !needsEmailVerification" class="row mb-4">
          <div class="col-12">
            <div class="setting-group" id="usage-section">
              <div class="setting-group-header">
                <h3>
                  <BarChart3 />
                  {{ $t('cloudConnect.nav.usage') || 'Usage' }}
                </h3>
              </div>
              <div class="setting-group-body">
                <div v-if="loadingUsage" class="loading-plans">
                  <Loader2 class="spinner" />
                  <span>{{ $t('cloudConnect.usage.loading') || 'Loading usage data...' }}</span>
                </div>
                
                <div v-else-if="usageData" class="usage-dashboard">
                  <!-- Instances Usage -->
                  <div class="usage-card">
                    <div class="usage-card-header">
                      <Server />
                      <span>{{ $t('cloudConnect.usage.instances') || 'Instances' }}</span>
                    </div>
                    <div class="usage-stats">
                      <div class="usage-main">
                        <span class="usage-current">{{ usageData.instances?.total || 0 }}</span>
                        <span class="usage-separator">/</span>
                        <span class="usage-max">{{ usageData.instances?.limit || '∞' }}</span>
                      </div>
                      <div class="usage-progress" v-if="usageData.instances?.limit">
                        <div 
                          class="usage-progress-bar" 
                          :style="{ width: Math.min(100, (usageData.instances?.total / usageData.instances?.limit) * 100) + '%' }"
                          :class="{ warning: usageData.instances?.total >= usageData.instances?.limit }"
                        ></div>
                      </div>
                    </div>
                  </div>

                  <!-- Transfer Usage -->
                  <div class="usage-card">
                    <div class="usage-card-header">
                      <ArrowUpDown />
                      <span>{{ $t('cloudConnect.usage.transfer') || 'Transfer This Period' }}</span>
                    </div>
                    <div class="usage-stats">
                      <div class="transfer-breakdown">
                        <div class="transfer-item">
                          <ArrowDown class="download" />
                          <span class="transfer-label">{{ $t('cloudConnect.usage.in') || 'In' }}:</span>
                          <span class="transfer-value">{{ formatBytes(usageData.transfer?.bytes_in) }}</span>
                        </div>
                        <div class="transfer-item">
                          <ArrowUp class="upload" />
                          <span class="transfer-label">{{ $t('cloudConnect.usage.out') || 'Out' }}:</span>
                          <span class="transfer-value">{{ formatBytes(usageData.transfer?.bytes_out) }}</span>
                        </div>
                        <div class="transfer-item total">
                          <HardDrive />
                          <span class="transfer-label">{{ $t('cloudConnect.usage.total') || 'Total' }}:</span>
                          <span class="transfer-value">{{ formatBytes(usageData.transfer?.bytes_total) }}</span>
                        </div>
                      </div>
                    </div>
                    <div v-if="usageData.transfer?.period_start" class="usage-period">
                      {{ $t('cloudConnect.usage.periodStart') || 'Period started' }}: {{ formatDate(usageData.transfer?.period_start) }}
                    </div>
                  </div>

                  <!-- Plan Limits -->
                  <div v-if="usageData.plan" class="usage-card">
                    <div class="usage-card-header">
                      <CreditCard />
                      <span>{{ $t('cloudConnect.usage.planLimits') || 'Plan Limits' }}</span>
                    </div>
                    <div class="plan-limits-list">
                      <div class="plan-limit-item">
                        <span class="limit-label">{{ $t('cloudConnect.usage.maxInstances') || 'Max Instances' }}:</span>
                        <span class="limit-value">{{ usageData.plan.max_instances || '∞' }}</span>
                      </div>
                      <div v-if="usageData.plan.max_transfer_gb" class="plan-limit-item">
                        <span class="limit-label">{{ $t('cloudConnect.usage.maxTransfer') || 'Max Transfer' }}:</span>
                        <span class="limit-value">{{ usageData.plan.max_transfer_gb }} GB</span>
                      </div>
                      <div v-if="usageData.plan.max_bandwidth_mbps" class="plan-limit-item">
                        <span class="limit-label">{{ $t('cloudConnect.usage.maxBandwidth') || 'Max Bandwidth' }}:</span>
                        <span class="limit-value">{{ usageData.plan.max_bandwidth_mbps }} Mbps</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div v-else class="no-usage-data">
                  <p>{{ $t('cloudConnect.usage.noData') || 'No usage data available' }}</p>
                  <button @click="loadUsage" class="secondary">
                    <RefreshCw />
                    {{ $t('cloudConnect.refresh') || 'Refresh' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Account Section -->
        <div class="row mb-4">
          <div class="col-12">
            <div class="setting-group" id="account-section">
              <div class="setting-group-header">
                <h3>
                  <component :is="status?.is_logged_in ? LogOut : LogIn" />
                  {{ $t('cloudConnect.nav.account') }}
                </h3>
              </div>
              <div class="setting-group-body">
                <!-- Not logged in -->
                <div v-if="!status?.is_logged_in">
                  <p>{{ $t('cloudConnect.auth.description') }}</p>
                  <div class="auth-buttons">
                    <button  @click="openLoginForm">
                      <LogIn />
                      {{ $t('cloudConnect.auth.login') }}
                    </button>
                    <button class="secondary" @click="openRegisterForm">
                      <UserPlus />
                      {{ $t('cloudConnect.auth.register') }}
                    </button>
                  </div>
                </div>
                
                <!-- Logged in but needs email verification -->
                <div v-else-if="needsEmailVerification">
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
                  <div class="button-row">
                    <button @click="checkVerificationStatus"  :disabled="loading">
                      <Loader2 v-if="loading" class="spinner" />
                      <RefreshCw v-else />
                      {{ $t('cloudConnect.emailVerification.checkStatus') }}
                    </button>
                    <button @click="resendVerificationEmail" class="secondary" :disabled="loading">
                      <Mail />
                      {{ $t('cloudConnect.emailVerification.resend') }}
                    </button>
                  </div>
                </div>
                
                <!-- Logged in -->
                <div v-else>
                  <div class="account-info">
                    <div class="account-row">
                      <span class="account-label">{{ $t('cloudConnect.account.email') }}:</span>
                      <span class="account-value">{{ status?.user_email }}</span>
                    </div>
                    <div class="account-row">
                      <span class="account-label">{{ $t('cloudConnect.account.status') }}:</span>
                      <span class="account-value status-badge" :class="status?.account_status">
                        {{ status?.account_status }}
                      </span>
                    </div>
                  </div>
                  <button @click="handleLogout" class="secondary" :disabled="loading">
                    <Loader2 v-if="loading" class="spinner" />
                    <LogOut v-else />
                    {{ $t('cloudConnect.auth.logout') }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Plan Section (visible when logged in) -->
        <div v-if="status?.is_logged_in && !needsEmailVerification" class="row mb-4">
          <div class="col-12">
            <div class="setting-group" id="plan-section">
              <div class="setting-group-header">
                <h3>
                  <CreditCard />
                  {{ $t('cloudConnect.nav.plan') }}
                </h3>
              </div>
              <div class="setting-group-body">
                <!-- No plan / Select plan -->
                <div v-if="!hasActiveSubscription || !currentPlan">
                  <p>{{ $t('cloudConnect.subscription.description') }}</p>
                </div>

                <div v-if="loadingPlans" class="loading-plans">
                  <Loader2 class="spinner" />
                  <span>{{ $t('cloudConnect.subscription.loadingPlans') }}</span>
                </div>

                <div v-else class="plan-selector">
                  <div 
                    v-for="plan in plans" 
                    :key="plan.name"
                    class="plan-card" 
                    :class="{ selected: selectedPlan === plan.name, current: plan.name === status?.subscription_plan }"
                    @click="selectedPlan = plan.name"
                  >
                    <div class="plan-card-header">
                      <h4>{{ plan.display_name }}</h4>
                      <span v-if="plan.name === status?.subscription_plan" class="current-badge">
                        {{ $t('cloudConnect.planManagement.current') }}
                      </span>
                    </div>
                    <div v-if="plan.price_cents" class="price">
                      ${{ Math.floor(plan.price_cents / 100) }}<span>/{{ $t('cloudConnect.subscription.month') }}</span>
                    </div>
                    <div v-else class="price free">{{ $t('cloudConnect.planManagement.free') }}</div>
                    <ul>
                      <li><Check class="list-icon included" />{{ $t('cloudConnect.subscription.instances', { count: plan.max_instances }) }}</li>
                      <li>
                        <Check class="list-icon included" />
                        {{ plan.max_transfer_gb 
                          ? ($t('cloudConnect.subscription.transfer', { count: plan.max_transfer_gb }) || `${plan.max_transfer_gb} GB transfer`) 
                          : ($t('cloudConnect.subscription.transferUnlimited') || 'Unlimited transfer') }}
                      </li>
                      <li>
                        <Check class="list-icon included" />
                        {{ plan.max_bandwidth_mbps 
                          ? ($t('cloudConnect.subscription.bandwidth', { count: plan.max_bandwidth_mbps }) || `${plan.max_bandwidth_mbps} Mbps bandwidth`) 
                          : ($t('cloudConnect.subscription.bandwidthUnlimited') || 'Unlimited bandwidth') }}
                      </li>
                      <li v-if="plan.custom_domains_allowed">
                        <Check class="list-icon included" />
                        {{ $t('cloudConnect.subscription.customDomains', { count: plan.max_domains_per_instance }) }}
                      </li>
                      <li v-else class="not-included">
                        <X class="list-icon" />
                        {{ $t('cloudConnect.subscription.noCustomDomains') }}
                      </li>
                    </ul>
                  </div>
                </div>

                <button 
                  v-if="selectedPlan && selectedPlan !== status?.subscription_plan"
                  @click="handleCheckout" 
                  :disabled="loading || pollingSubscription || loadingPlans"
                >
                  <Loader2 v-if="loading || pollingSubscription" class="spinner" />
                  <CreditCard v-else />
                  {{ pollingSubscription ? $t('cloudConnect.subscription.waitingForPayment') : $t('cloudConnect.planManagement.changePlanButton') }}
                </button>

                <p v-if="pollingSubscription" class="polling-note">
                  {{ $t('cloudConnect.subscription.pollingNote') }}
                  <button @click="stopPolling" class="secondary">{{ $t('cloudConnect.subscription.stopWaiting') }}</button>
                </p>
                
                <!-- Billing Portal Button -->
                <div v-if="hasActiveSubscription && currentPlan" class="billing-portal-section">
                  <hr class="section-divider" />
                  <p class="billing-portal-description">
                    {{ $t('cloudConnect.billing.portalDescription') || 'Manage your subscription, update payment methods, or view invoices in the billing portal.' }}
                  </p>
                  <button 
                    @click="openBillingPortal" 
                    class="secondary"
                    :disabled="loadingBillingPortal"
                  >
                    <Loader2 v-if="loadingBillingPortal" class="spinner" />
                    <ExternalLink v-else />
                    {{ $t('cloudConnect.billing.openPortal') || 'Manage Subscription' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Instance Section (visible when logged in and has subscription) -->
        <div v-if="status?.is_logged_in && hasActiveSubscription && !needsEmailVerification" class="row mb-4">
          <div class="col-12">
            <div class="setting-group" id="instance-section">
              <div class="setting-group-header">
                <h3>
                  <Server />
                  {{ $t('cloudConnect.nav.instance') }}
                </h3>
              </div>
              <div class="setting-group-body">
                <!-- Loading instances -->
                <div v-if="loadingInstances" class="loading-plans">
                  <Loader2 class="spinner" />
                  <span>{{ $t('cloudConnect.instances.loading') || 'Loading instances...' }}</span>
                </div>
                
                <!-- Instances list -->
                <div v-else-if="instances.length > 0" class="instances-list">
                  <div 
                    v-for="instance in instances" 
                    :key="instance.id"
                    class="instance-card"
                    :class="{ 
                      current: instance.id === status?.instance_id,
                      online: instance.status === 'online' || instance.status === 'connected',
                      offline: instance.status === 'offline' || instance.status === 'disconnected'
                    }"
                  >
                    <div class="instance-card-header">
                      <div class="instance-name">
                        <Server />
                        <span>{{ instance.name || 'Unnamed Instance' }}</span>
                        <span v-if="instance.id === status?.instance_id" class="current-badge">
                          {{ $t('cloudConnect.instances.current') || 'Current' }}
                        </span>
                      </div>
                      <div class="instance-status" :class="getInstanceStatusClass(instance)">
                        <component :is="instance.status === 'online' || instance.status === 'connected' ? Wifi : WifiOff" />
                        <span>{{ instance.status || 'unknown' }}</span>
                      </div>
                    </div>
                    
                    <div class="instance-card-body">
                      <div class="instance-detail">
                        <Globe />
                        <span>{{ instance.full_domain || `${instance.subdomain}.erugo.cloud` }}</span>
                      </div>
                      <div v-if="instance.tunnel_ip" class="instance-detail">
                        <span class="detail-label">{{ $t('cloudConnect.connected.tunnelIp') || 'Tunnel IP' }}:</span>
                        <span>{{ instance.tunnel_ip }}</span>
                      </div>
                      <div v-if="instance.last_seen" class="instance-detail muted">
                        <span class="detail-label">{{ $t('cloudConnect.instances.lastSeen') || 'Last seen' }}:</span>
                        <span>{{ formatDate(instance.last_seen) }}</span>
                      </div>
                      
                      <!-- Transfer stats if available -->
                      <div v-if="instance.transfer" class="instance-transfer">
                        <div class="transfer-mini">
                          <ArrowDown class="download" />
                          <span>{{ formatBytes(instance.transfer.bytes_in) }}</span>
                        </div>
                        <div class="transfer-mini">
                          <ArrowUp class="upload" />
                          <span>{{ formatBytes(instance.transfer.bytes_out) }}</span>
                        </div>
                      </div>
                    </div>
                    
                    <div class="instance-card-actions">
                      <button class="icon-only" @click="openEditInstanceForm(instance)" :title="$t('cloudConnect.instances.edit') || 'Edit'">
                        <Pencil />
                      </button>
                      <button class="icon-only" @click="openRegenerateTokenConfirm(instance)" :title="$t('cloudConnect.instances.regenerateToken') || 'Regenerate Token'">
                        <Key />
                      </button>
                      <button class="icon-only danger" @click="openDeleteConfirm(instance)" :title="$t('cloudConnect.instances.delete') || 'Delete'" :disabled="instances.length <= 1">
                        <Trash2 />
                      </button>
                    </div>
                  </div>
                </div>
                
                <!-- No instances - show create form -->
                <div v-else>
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
                        <span v-else-if="subdomainAvailable === true && !subdomainOwnedByUser" class="subdomain-status available">
                          <CheckCircle />
                        </span>
                        <span v-else-if="subdomainOwnedByUser" class="subdomain-status owned">
                          <RefreshCw />
                        </span>
                        <span v-else-if="subdomainAvailable === false" class="subdomain-status unavailable">
                          <XCircle />
                        </span>
                      </div>
                      <div v-if="subdomainOwnedByUser" class="subdomain-owned-notice">
                        <AlertTriangle />
                        <span>{{ $t('cloudConnect.subdomainOwnedByYou') }}</span>
                      </div>
                      <div v-else-if="subdomainAvailable === false && subdomainSuggestions.length > 0" class="subdomain-suggestions">
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
                      :disabled="loading || !subdomainAvailable"
                    >
                      <Loader2 v-if="loading" class="spinner" />
                      <Server v-else />
                      {{ $t('cloudConnect.instance.create') }}
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Connection Section (visible when has instance) -->
        <div v-if="status?.has_instance" class="row mb-4">
          <div class="col-12">
            <div class="setting-group" id="connection-section">
              <div class="setting-group-header" :class="{ success: isConnected && heartbeatHealthy, warning: isConnected && !heartbeatHealthy }">
                <h3>
                  <Globe />
                  {{ $t('cloudConnect.nav.connection') }}
                </h3>
              </div>
              <div class="setting-group-body">
                <!-- Connected -->
                <div v-if="isConnected">
                  <div class="connected-status">
                    <div class="status-indicator" :class="{ online: heartbeatHealthy, reconnecting: isReconnecting && !heartbeatHealthy, offline: !isReconnecting && !heartbeatHealthy }">
                      <Loader2 v-if="isReconnecting" class="spinner" />
                      <Wifi v-else-if="heartbeatHealthy" />
                      <WifiOff v-else />
                      <span v-if="isReconnecting">{{ $t('cloudConnect.connected.reconnecting') }}</span>
                      <span v-else-if="heartbeatHealthy">{{ $t('cloudConnect.connected.online') }}</span>
                      <span v-else>{{ $t('cloudConnect.connected.offline') }}</span>
                    </div>
                  </div>

                  <div class="domain-display">
                    <Globe />
                    <a :href="`https://${status?.full_domain}`" target="_blank" class="domain-link">
                      https://{{ status?.full_domain }}
                      <ExternalLink />
                    </a>
                    <button @click="copyDomain" class="icon-only" :title="$t('cloudConnect.connected.copyUrl')">
                      <Check v-if="copiedDomain" />
                      <Copy v-else />
                    </button>
                  </div>

                  <div class="connection-details">
                    <div class="detail-row">
                      <span class="label">{{ $t('cloudConnect.connected.lastHeartbeat') }}:</span>
                      <span class="value" :class="{ 'heartbeat-healthy': heartbeatHealthy, 'heartbeat-unhealthy': !heartbeatHealthy }">
                        {{ lastHeartbeatFormatted }}
                        <span v-if="!status?.last_heartbeat_success && status?.last_heartbeat_error" class="heartbeat-error">
                          ({{ $t('cloudConnect.connected.heartbeatFailed') }})
                        </span>
                      </span>
                    </div>
                  </div>

                  <div class="action-buttons">
                    <button @click="handleDisconnect" class="secondary" :disabled="disconnecting">
                      <Loader2 v-if="disconnecting" class="spinner" />
                      <WifiOff v-else />
                      {{ disconnecting ? $t('cloudConnect.connected.disconnecting') : $t('cloudConnect.connected.disconnect') }}
                    </button>
                  </div>
                </div>
                
                <!-- Not connected -->
                <div v-else>
                  <p>{{ $t('cloudConnect.ready.description') }}</p>
                  <div class="instance-info">
                    <div class="info-row">
                      <Globe />
                      <span>{{ status?.full_domain }}</span>
                    </div>
                  </div>
                  <button @click="handleConnect"  :disabled="connecting">
                    <Loader2 v-if="connecting" class="spinner" />
                    <Wifi v-else />
                    {{ connecting ? $t('cloudConnect.ready.connecting') : $t('cloudConnect.ready.connect') }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Login Slide-out Form -->
  <div class="auth-form-overlay" :class="{ active: showLoginForm }" @click="loginFormClickOutside">
    <div class="auth-slide-form">
      <h2>
        <LogIn />
        {{ $t('cloudConnect.auth.login') }}
      </h2>
      <p>{{ $t('cloudConnect.auth.loginDescription') }}</p>
      <form @submit.prevent="handleLogin">
        <div class="input-container">
          <label for="login-email">{{ $t('cloudConnect.auth.email') }}</label>
          <input 
            type="email" 
            id="login-email" 
            v-model="loginForm.email" 
            required 
            :placeholder="$t('cloudConnect.auth.emailPlaceholder')"
          />
        </div>
        <div class="input-container">
          <label for="login-password">{{ $t('cloudConnect.auth.password') }}</label>
          <input 
            type="password" 
            id="login-password" 
            v-model="loginForm.password" 
            required 
            :placeholder="$t('cloudConnect.auth.passwordPlaceholder')"
          />
        </div>
        <div class="button-bar">
          <button type="submit" :disabled="loading">
            <Loader2 v-if="loading" class="spinner" />
            <LogIn v-else />
            {{ $t('cloudConnect.auth.loginButton') }}
          </button>
          <button type="button" class="secondary close-button" @click="showLoginForm = false">
            <CircleX />
            {{ $t('settings.close') }}
          </button>
        </div>
      </form>
      <p class="switch-form-text">
        {{ $t('cloudConnect.auth.noAccount') }}
        <button type="button" class="btn-text-inline" @click="openRegisterForm">
          {{ $t('cloudConnect.auth.registerInstead') }}
        </button>
      </p>
      <p class="switch-form-text">
        <button type="button" class="btn-text-inline" @click="openForgotPasswordForm">
          {{ $t('cloudConnect.auth.forgotPassword') || 'Forgot your password?' }}
        </button>
      </p>
    </div>
  </div>

  <!-- Register Slide-out Form -->
  <div class="auth-form-overlay" :class="{ active: showRegisterForm }" @click="registerFormClickOutside">
    <div class="auth-slide-form">
      <h2>
        <UserPlus />
        {{ $t('cloudConnect.auth.register') }}
      </h2>
      <p>{{ $t('cloudConnect.auth.registerDescription') }}</p>
      <form @submit.prevent="handleRegister">
        <div class="input-container">
          <label for="register-name">{{ $t('cloudConnect.auth.name') }}</label>
          <input 
            type="text" 
            id="register-name" 
            v-model="registerForm.name" 
            required 
            :placeholder="$t('cloudConnect.auth.namePlaceholder')"
          />
        </div>
        <div class="input-container">
          <label for="register-email">{{ $t('cloudConnect.auth.email') }}</label>
          <input 
            type="email" 
            id="register-email" 
            v-model="registerForm.email" 
            required 
            :placeholder="$t('cloudConnect.auth.emailPlaceholder')"
          />
        </div>
        <div class="input-container">
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
        <div class="input-container">
          <label for="register-password-confirm">{{ $t('cloudConnect.auth.confirmPassword') }}</label>
          <input 
            type="password" 
            id="register-password-confirm" 
            v-model="registerForm.password_confirmation" 
            required 
            :placeholder="$t('cloudConnect.auth.confirmPasswordPlaceholder')"
          />
        </div>
        <div class="checkbox-container">
          <input type="checkbox" id="accept-terms" v-model="registerForm.accept_terms" required />
          <label for="accept-terms">
            {{ $t('cloudConnect.auth.acceptTerms') }}
            <a href="https://erugo.cloud/terms" target="_blank">{{ $t('cloudConnect.auth.termsLink') }}</a>
          </label>
        </div>
        <div class="checkbox-container">
          <input type="checkbox" id="accept-privacy" v-model="registerForm.accept_privacy" required />
          <label for="accept-privacy">
            {{ $t('cloudConnect.auth.acceptPrivacy') }}
            <a href="https://erugo.cloud/privacy" target="_blank">{{ $t('cloudConnect.auth.privacyLink') }}</a>
          </label>
        </div>
        <div class="checkbox-container">
          <input type="checkbox" id="accept-marketing" v-model="registerForm.accept_marketing" />
          <label for="accept-marketing">{{ $t('cloudConnect.auth.acceptMarketing') }}</label>
        </div>
        <div class="button-bar">
          <button type="submit" :disabled="loading">
            <Loader2 v-if="loading" class="spinner" />
            <UserPlus v-else />
            {{ $t('cloudConnect.auth.registerButton') }}
          </button>
          <button type="button" class="secondary close-button" @click="showRegisterForm = false">
            <CircleX />
            {{ $t('settings.close') }}
          </button>
        </div>
      </form>
      <p class="switch-form-text">
        {{ $t('cloudConnect.auth.haveAccount') }}
        <button type="button" class="btn-text-inline" @click="openLoginForm">
          {{ $t('cloudConnect.auth.loginInstead') }}
        </button>
      </p>
    </div>
  </div>

  <!-- Reclaim Instance Confirmation Dialog -->
  <div class="auth-form-overlay" :class="{ active: showReclaimConfirm }">
    <div class="auth-slide-form reclaim-dialog">
      <h2>
        <AlertTriangle />
        {{ $t('cloudConnect.reclaimInstance.title') }}
      </h2>
      <p class="reclaim-message">
        {{ $t('cloudConnect.reclaimInstance.message', { name: existingInstanceName || instanceForm.subdomain }) }}
      </p>
      <div class="button-bar">
        <button type="button" @click="handleConfirmReclaim" :disabled="loading" class="secondary">
          <Loader2 v-if="loading" class="spinner" />
          <RefreshCw v-else />
          {{ $t('cloudConnect.reclaimInstance.confirm') }}
        </button>
        <button type="button" class="secondary close-button" @click="handleCancelReclaim">
          <CircleX />
          {{ $t('cloudConnect.reclaimInstance.cancel') }}
        </button>
      </div>
    </div>
  </div>

  <!-- Plan Management Slide-out -->
  <div class="auth-form-overlay" :class="{ active: showPlanManagement }" @click.self="closePlanManagement">
    <div class="auth-slide-form plan-management-form">
      <h2>
        <CreditCard />
        {{ $t('cloudConnect.planManagement.title') }}
      </h2>
      
      <!-- Current Plan Info -->
      <div v-if="currentPlan" class="current-plan-info">
        <h4>{{ $t('cloudConnect.planManagement.currentPlan') }}</h4>
        <div class="plan-details">
          <div class="plan-name">{{ currentPlan.display_name }}</div>
          <ul class="plan-limits">
            <li>{{ $t('cloudConnect.subscription.instances', { count: currentPlan.max_instances }) }}</li>
            <li v-if="currentPlan.custom_domains_allowed">
              {{ $t('cloudConnect.subscription.customDomains', { count: currentPlan.max_domains_per_instance }) }}
            </li>
            <li v-else>{{ $t('cloudConnect.subscription.noCustomDomains') }}</li>
          </ul>
        </div>
      </div>

      <h4>{{ $t('cloudConnect.planManagement.changePlan') }}</h4>
      
      <div v-if="loadingPlans" class="loading-plans">
        <Loader2 class="spinner" />
        <span>{{ $t('cloudConnect.subscription.loadingPlans') }}</span>
      </div>

      <div v-else class="plan-selector compact">
        <div 
          v-for="plan in plans" 
          :key="plan.name"
          class="plan-card" 
          :class="{ selected: selectedPlan === plan.name, current: plan.name === status?.subscription_plan }"
          @click="selectedPlan = plan.name"
        >
          <div class="plan-card-header">
            <h4>{{ plan.display_name }}</h4>
            <span v-if="plan.name === status?.subscription_plan" class="current-badge">
              {{ $t('cloudConnect.planManagement.current') }}
            </span>
          </div>
          <div v-if="plan.price_cents" class="price">
            ${{ Math.floor(plan.price_cents / 100) }}<span>/{{ $t('cloudConnect.subscription.month') }}</span>
          </div>
          <div v-else class="price free">{{ $t('cloudConnect.planManagement.free') }}</div>
          <ul>
            <li>{{ $t('cloudConnect.subscription.instances', { count: plan.max_instances }) }}</li>
            <li v-if="plan.custom_domains_allowed">
              {{ $t('cloudConnect.subscription.customDomains', { count: plan.max_domains_per_instance }) }}
            </li>
            <li v-else>{{ $t('cloudConnect.subscription.noCustomDomains') }}</li>
          </ul>
        </div>
      </div>

      <div class="button-bar">
        <button 
          type="button" 
          @click="handleChangePlan" 
          :disabled="loading || loadingPlans || selectedPlan === status?.subscription_plan"
        >
          <Loader2 v-if="loading" class="spinner" />
          <CreditCard v-else />
          {{ selectedPlan === status?.subscription_plan ? $t('cloudConnect.planManagement.currentPlanSelected') : $t('cloudConnect.planManagement.changePlanButton') }}
        </button>
        <button type="button" class="secondary close-button" @click="closePlanManagement">
          <CircleX />
          {{ $t('settings.close') }}
        </button>
      </div>
    </div>
  </div>

  <!-- Forgot Password Modal -->
  <div class="auth-form-overlay" :class="{ active: showForgotPasswordForm }" @click.self="closeForgotPasswordForm">
    <div class="auth-slide-form">
      <h2>
        <Mail />
        {{ $t('cloudConnect.auth.forgotPasswordTitle') || 'Reset Password' }}
      </h2>
      <p>{{ $t('cloudConnect.auth.forgotPasswordDescription') || 'Enter your email address and we\'ll send you a link to reset your password.' }}</p>
      <form @submit.prevent="handleForgotPassword">
        <div class="input-container">
          <label for="forgot-email">{{ $t('cloudConnect.auth.email') }}</label>
          <input 
            type="email" 
            id="forgot-email" 
            v-model="forgotPasswordEmail" 
            required 
            :placeholder="$t('cloudConnect.auth.emailPlaceholder')"
          />
        </div>
        <div class="button-bar">
          <button type="submit" :disabled="sendingForgotPassword">
            <Loader2 v-if="sendingForgotPassword" class="spinner" />
            <Mail v-else />
            {{ $t('cloudConnect.auth.sendResetLink') || 'Send Reset Link' }}
          </button>
          <button type="button" class="secondary close-button" @click="closeForgotPasswordForm">
            <CircleX />
            {{ $t('settings.close') }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Instance Modal -->
  <div class="auth-form-overlay" :class="{ active: showEditInstanceForm }" @click.self="closeEditInstanceForm">
    <div class="auth-slide-form">
      <h2>
        <Pencil />
        {{ $t('cloudConnect.instances.editTitle') || 'Edit Instance' }}
      </h2>
      <form @submit.prevent="handleUpdateInstance">
        <div class="input-container">
          <label for="edit-instance-name">{{ $t('cloudConnect.instance.name') }}</label>
          <input 
            type="text" 
            id="edit-instance-name" 
            v-model="editInstanceForm.name" 
            required 
            :placeholder="$t('cloudConnect.instance.namePlaceholder')"
          />
        </div>
        <div class="input-container">
          <label for="edit-instance-subdomain">
            {{ $t('cloudConnect.instance.subdomain') }}
            <button type="button" class="btn-text-inline" @click="editingInstanceSubdomain = !editingInstanceSubdomain">
              {{ editingInstanceSubdomain ? ($t('cloudConnect.instances.cancelSubdomainEdit') || 'Cancel') : ($t('cloudConnect.instances.editSubdomain') || 'Change') }}
            </button>
          </label>
          <div class="subdomain-input">
            <input 
              type="text" 
              id="edit-instance-subdomain" 
              v-model="editInstanceForm.subdomain" 
              :disabled="!editingInstanceSubdomain"
              pattern="^[a-z0-9][a-z0-9-]*[a-z0-9]$"
              minlength="3"
              maxlength="63"
            />
            <span class="subdomain-suffix">.erugo.cloud</span>
          </div>
          <p v-if="editingInstanceSubdomain" class="warning-text">
            {{ $t('cloudConnect.instances.subdomainWarning') || 'Changing the subdomain will change your instance URL. You may need to reconnect.' }}
          </p>
        </div>
        <div class="button-bar">
          <button type="submit" :disabled="loading">
            <Loader2 v-if="loading" class="spinner" />
            <Check v-else />
            {{ $t('cloudConnect.instances.save') || 'Save Changes' }}
          </button>
          <button type="button" class="secondary close-button" @click="closeEditInstanceForm">
            <CircleX />
            {{ $t('settings.close') }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Instance Confirmation Modal -->
  <div class="auth-form-overlay" :class="{ active: showDeleteConfirm }" @click.self="closeDeleteConfirm">
    <div class="auth-slide-form danger-dialog">
      <h2>
        <Trash2 />
        {{ $t('cloudConnect.instances.deleteTitle') || 'Delete Instance' }}
      </h2>
      <p class="danger-message">
        {{ $t('cloudConnect.instances.deleteWarning') || 'Are you sure you want to delete this instance? This action cannot be undone.' }}
      </p>
      <div v-if="selectedInstance" class="instance-to-delete">
        <strong>{{ selectedInstance.name }}</strong>
        <span>{{ selectedInstance.full_domain || `${selectedInstance.subdomain}.erugo.cloud` }}</span>
      </div>
      <div class="button-bar">
        <button type="button" class="danger" @click="handleDeleteInstance" :disabled="loading">
          <Loader2 v-if="loading" class="spinner" />
          <Trash2 v-else />
          {{ $t('cloudConnect.instances.confirmDelete') || 'Delete Instance' }}
        </button>
        <button type="button" class="secondary close-button" @click="closeDeleteConfirm">
          <CircleX />
          {{ $t('settings.close') }}
        </button>
      </div>
    </div>
  </div>

  <!-- Regenerate Token Confirmation Modal -->
  <div class="auth-form-overlay" :class="{ active: showRegenerateTokenConfirm }" @click.self="closeRegenerateTokenConfirm">
    <div class="auth-slide-form warning-dialog">
      <h2>
        <Key />
        {{ $t('cloudConnect.instances.regenerateTokenTitle') || 'Regenerate Token' }}
      </h2>
      <p class="warning-message">
        {{ $t('cloudConnect.instances.regenerateTokenWarning') || 'Regenerating the token will invalidate the current token. You will need to reconnect this instance.' }}
      </p>
      <div v-if="selectedInstance" class="instance-to-delete">
        <strong>{{ selectedInstance.name }}</strong>
        <span>{{ selectedInstance.full_domain || `${selectedInstance.subdomain}.erugo.cloud` }}</span>
      </div>
      <div class="button-bar">
        <button type="button" @click="handleRegenerateToken" :disabled="loading">
          <Loader2 v-if="loading" class="spinner" />
          <Key v-else />
          {{ $t('cloudConnect.instances.confirmRegenerate') || 'Regenerate Token' }}
        </button>
        <button type="button" class="secondary close-button" @click="closeRegenerateTokenConfirm">
          <CircleX />
          {{ $t('settings.close') }}
        </button>
      </div>
    </div>
  </div>

  <!-- Regenerated Token Display Modal -->
  <div class="auth-form-overlay" :class="{ active: showRegeneratedToken }" @click.self="closeRegeneratedTokenModal">
    <div class="auth-slide-form">
      <h2>
        <CheckCircle />
        {{ $t('cloudConnect.instances.tokenRegeneratedTitle') || 'New Token Generated' }}
      </h2>
      <p>{{ $t('cloudConnect.instances.tokenRegeneratedDescription') || 'Your new instance token is shown below. Make sure to copy it now - you won\'t be able to see it again!' }}</p>
      <div class="token-display">
        <code>{{ regeneratedToken }}</code>
        <button type="button" class="icon-only" @click="copyToken" :title="$t('cloudConnect.instances.copyToken') || 'Copy Token'">
          <Copy />
        </button>
      </div>
      <div class="button-bar">
        <button type="button" @click="closeRegeneratedTokenModal">
          <Check />
          {{ $t('cloudConnect.instances.done') || 'Done' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
// Use global .settings-nav, .setting-group, .setting-group-header, .setting-group-body styles from style.scss

// Header color modifiers
.setting-group-header {
  &.error h3 svg {
    color: var(--color-danger, #ef4444);
  }

  &.success h3 svg {
    color: var(--color-success, #22c55e);
  }

  &.warning h3 svg {
    color: var(--color-warning, #f59e0b);
  }
}

.status-overview {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.status-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;

  .status-label {
    font-size: 0.875rem;
    color: var(--panel-section-text-color);
    opacity: 0.7;
    min-width: 100px;
  }

  .status-value {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    color: var(--panel-section-text-color);

    svg {
      width: 16px;
      height: 16px;
    }

    &.online {
      color: var(--color-success, #22c55e);
    }

    &.offline {
      color: var(--color-danger, #ef4444);
    }

    &.domain a {
      display: flex;
      align-items: center;
      gap: 6px;
      color: var(--button-primary-background-color);
      text-decoration: none;

      svg {
        width: 14px;
        height: 14px;
      }

      &:hover {
        text-decoration: underline;
      }
    }
  }
}

.account-info {
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
}

.account-row {
  display: flex;
  align-items: center;
  padding: 8px 0;

  &:not(:last-child) {
    border-bottom: 1px solid var(--panel-border-color);
  }

  .account-label {
    font-size: 0.875rem;
    color: var(--panel-section-text-color);
    opacity: 0.7;
    min-width: 80px;
  }

  .account-value {
    font-weight: 500;
    color: var(--panel-section-text-color);

    &.status-badge {
      padding: 2px 10px;
      border-radius: 12px;
      font-size: 0.8rem;
      background: var(--color-success, #22c55e);
      color: white;

      &.pending_email_verification {
        background: var(--color-warning, #f59e0b);
      }

      &.suspended {
        background: var(--color-danger, #ef4444);
      }
    }
  }
}

.button-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.current-plan-display {
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;

  .current-plan-header {
    display: flex;
    align-items: baseline;
    gap: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;

    .plan-name-large {
      font-size: 1.5rem;
      font-weight: 600;
      color: var(--button-primary-background-color);
    }

    .plan-price {
      font-size: 1.1rem;
      color: var(--panel-section-text-color);
      opacity: 0.7;

      &.free {
        color: var(--color-success, #22c55e);
        opacity: 1;
      }
    }
  }

  .plan-features {
    margin: 0;
    padding: 0;
    list-style: none;

    li {
      padding: 6px 0;
      font-size: 0.9rem;
      color: var(--panel-section-text-color);
      opacity: 0.8;

      &::before {
        content: '✓';
        margin-right: 10px;
        color: var(--color-success, #22c55e);
      }
    }
  }
}

.instance-info-display {
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;
  padding: 16px;

  .info-row {
    display: flex;
    align-items: center;
    padding: 8px 0;

    &:not(:last-child) {
      border-bottom: 1px solid var(--panel-border-color);
    }

    .info-label {
      font-size: 0.875rem;
      color: var(--panel-section-text-color);
      opacity: 0.7;
      min-width: 120px;
    }

    .info-value {
      font-weight: 500;
      color: var(--panel-section-text-color);
    }
  }
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

.auth-buttons {
  display: flex;
  gap: 12px;
  margin-top: 8px;

  button {
    flex: 1;
  }
}

.auth-form-overlay {
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

  > p {
    text-align: center;
    margin-bottom: 16px;
    color: var(--panel-text-color);
    opacity: 0.8;
  }

  .auth-slide-form {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translate(-50%, 100%);
    width: min(500px, 100vw);
    max-height: 90vh;
    overflow-y: auto;
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

    > p {
      width: 100%;
      text-align: center;
      margin: 0;
      color: var(--panel-text-color);
      opacity: 0.8;
    }

    form {
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    button {
      display: block;
      width: 100%;
    }
  }

  &.active {
    opacity: 1;
    pointer-events: auto;
    .auth-slide-form {
      transform: translate(-50%, 0%);
    }
  }
}

.input-container {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 100%;

  label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--panel-text-color);
  }

  input {
    padding: 12px 16px;
    border: 1px solid var(--panel-border-color);
    border-radius: 8px;
    background: var(--panel-section-background-color-alt);
    color: var(--panel-text-color);
    font-size: 1rem;
    width: 100%;

    &:focus {
      outline: none;
      border-color: var(--button-primary-background-color);
    }
  }
}

.checkbox-container {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  width: 100%;

  input[type="checkbox"] {
    margin-top: 3px;
    flex-shrink: 0;
  }

  label {
    font-size: 0.875rem;
    color: var(--panel-text-color);
    opacity: 0.8;

    a {
      color: var(--button-primary-background-color);
    }
  }
}

.button-bar {
  display: flex;
  gap: 10px;
  width: 100%;
  margin-top: 10px;

  button {
    flex: 1;
  }
}

.switch-form-text {
  width: 100%;
  text-align: center;
  font-size: 0.875rem;
  color: var(--panel-text-color);
  opacity: 0.7;
  margin-top: 8px;
}

.btn-text-inline {
  background: none;
  border: none;
  color: var(--button-primary-background-color);
  cursor: pointer;
  font-size: inherit;
  padding: 0;
  text-decoration: underline;

  &:hover {
    opacity: 0.8;
  }
}

.reclaim-dialog {
  h2 {
    color: var(--color-warning, #f59e0b);

    svg {
      color: var(--color-warning, #f59e0b);
    }
  }

  .reclaim-message {
    text-align: center;
    margin: 16px 0 24px;
    color: var(--panel-text-color);
    line-height: 1.6;
  }
}

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

.loading-plans {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 48px 24px;
  color: var(--panel-section-text-color);
  opacity: 0.7;

  .spinner {
    animation: spin 1s linear infinite;
  }
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
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 6px 0;
      font-size: 0.875rem;
      color: var(--panel-section-text-color);
      opacity: 0.8;

      .list-icon {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        
        &.included {
          color: var(--color-success, #22c55e);
        }
      }
      
      &.not-included {
        opacity: 0.5;
        
        .list-icon {
          color: var(--panel-section-text-color);
        }
      }
    }
  }

  .plan-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;

    h4 {
      margin: 0;
    }
  }

  .current-badge {
    font-size: 0.75rem;
    padding: 2px 8px;
    background: var(--button-primary-background-color);
    color: var(--button-primary-text-color);
    border-radius: 4px;
  }

  .price.free {
    font-size: 1.5rem;
    color: var(--color-success, #22c55e);
  }

  &.current {
    border-color: var(--color-success, #22c55e);
  }
}

.plan-selector.compact {
  grid-template-columns: 1fr;
  
  .plan-card {
    padding: 16px;
    
    .price {
      font-size: 1.5rem;
      margin-bottom: 8px;
    }
    
    ul li {
      padding: 4px 0;
    }
  }
}

.current-plan-info {
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 20px;

  h4 {
    margin: 0 0 12px;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.7;
  }

  .plan-details {
    .plan-name {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--button-primary-background-color);
      margin-bottom: 8px;
    }

    .plan-limits {
      margin: 0;
      padding: 0;
      list-style: none;

      li {
        font-size: 0.875rem;
        padding: 4px 0;
        color: var(--panel-section-text-color);
        opacity: 0.8;

        &::before {
          content: '•';
          margin-right: 8px;
        }
      }
    }
  }
}

.plan-management-form {
  h4 {
    margin: 16px 0 12px;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.7;
  }
}

.btn-link {
  background: none;
  border: none;
  color: var(--button-primary-background-color);
  cursor: pointer;
  padding: 0;
  margin-left: 8px;
  font-size: 0.875rem;
  text-decoration: underline;

  &:hover {
    opacity: 0.8;
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

    &.owned svg {
      color: var(--color-warning, #f59e0b);
    }

    &.unavailable svg {
      color: var(--color-danger, #ef4444);
    }
  }
}

.subdomain-owned-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  padding: 8px 12px;
  background: color-mix(in srgb, var(--color-warning, #f59e0b) 15%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-warning, #f59e0b) 30%, transparent);
  border-radius: 6px;
  font-size: 0.875rem;
  color: var(--color-warning, #f59e0b);

  svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
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

    &.reconnecting {
      background: color-mix(in srgb, var(--color-warning, #f59e0b) 15%, transparent);
      color: var(--color-warning, #f59e0b);
    }

    &.offline {
      background: color-mix(in srgb, var(--color-danger, #ef4444) 15%, transparent);
      color: var(--color-danger, #ef4444);
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

      &.heartbeat-healthy {
        color: var(--color-success, #22c55e);
      }

      &.heartbeat-unhealthy {
        color: var(--color-danger, #ef4444);
      }

      .heartbeat-error {
        font-size: 0.8em;
        opacity: 0.8;
      }
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

// Usage Dashboard Styles
.usage-dashboard {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
}

.usage-card {
  background: var(--panel-section-background-color-alt);
  border-radius: 12px;
  padding: 20px;
  
  .usage-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    font-weight: 500;
    color: var(--panel-section-text-color);
    
    svg {
      width: 20px;
      height: 20px;
      color: var(--button-primary-background-color);
    }
  }
  
  .usage-stats {
    .usage-main {
      display: flex;
      align-items: baseline;
      gap: 4px;
      margin-bottom: 12px;
      
      .usage-current {
        font-size: 2rem;
        font-weight: 700;
        color: var(--button-primary-background-color);
      }
      
      .usage-separator {
        font-size: 1.5rem;
        opacity: 0.5;
      }
      
      .usage-max {
        font-size: 1.5rem;
        opacity: 0.7;
      }
    }
    
    .usage-progress {
      height: 8px;
      background: var(--panel-border-color);
      border-radius: 4px;
      overflow: hidden;
      
      .usage-progress-bar {
        height: 100%;
        background: var(--button-primary-background-color);
        border-radius: 4px;
        transition: width 0.3s ease;
        
        &.warning {
          background: var(--color-warning, #f59e0b);
        }
      }
    }
  }
  
  .transfer-breakdown {
    display: flex;
    flex-direction: column;
    gap: 8px;
    
    .transfer-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.9rem;
      
      svg {
        width: 16px;
        height: 16px;
        
        &.download {
          color: var(--color-success, #22c55e);
        }
        
        &.upload {
          color: var(--color-info, #3b82f6);
        }
      }
      
      .transfer-label {
        opacity: 0.7;
      }
      
      .transfer-value {
        font-weight: 500;
        margin-left: auto;
      }
      
      &.total {
        padding-top: 8px;
        border-top: 1px solid var(--panel-border-color);
        font-weight: 500;
      }
    }
  }
  
  .usage-period {
    margin-top: 12px;
    font-size: 0.75rem;
    opacity: 0.6;
    text-align: right;
  }
  
  .plan-limits-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    
    .plan-limit-item {
      display: flex;
      justify-content: space-between;
      font-size: 0.9rem;
      
      .limit-label {
        opacity: 0.7;
      }
      
      .limit-value {
        font-weight: 500;
      }
    }
  }
}

.no-usage-data {
  text-align: center;
  padding: 24px;
  opacity: 0.7;
  
  p {
    margin-bottom: 16px;
  }
}

// Instances List Styles
.instances-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.instance-card {
  background: var(--panel-section-background-color-alt);
  border: 2px solid var(--panel-border-color);
  border-radius: 12px;
  padding: 16px;
  transition: all 0.2s;
  
  &.current {
    border-color: var(--button-primary-background-color);
  }
  
  &.online {
    border-left: 4px solid var(--color-success, #22c55e);
  }
  
  &.offline {
    border-left: 4px solid var(--color-danger, #ef4444);
  }
  
  .instance-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    
    .instance-name {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 600;
      font-size: 1rem;
      
      svg {
        width: 20px;
        height: 20px;
        color: var(--button-primary-background-color);
      }
      
      .current-badge {
        font-size: 0.7rem;
        padding: 2px 8px;
        background: var(--button-primary-background-color);
        color: var(--button-primary-text-color);
        border-radius: 4px;
        font-weight: 500;
      }
    }
    
    .instance-status {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.8rem;
      padding: 4px 10px;
      border-radius: 12px;
      
      svg {
        width: 14px;
        height: 14px;
      }
      
      &.online {
        background: color-mix(in srgb, var(--color-success, #22c55e) 15%, transparent);
        color: var(--color-success, #22c55e);
      }
      
      &.offline {
        background: color-mix(in srgb, var(--color-danger, #ef4444) 15%, transparent);
        color: var(--color-danger, #ef4444);
      }
    }
  }
  
  .instance-card-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
    
    .instance-detail {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.875rem;
      
      svg {
        width: 16px;
        height: 16px;
        opacity: 0.6;
      }
      
      .detail-label {
        opacity: 0.7;
      }
      
      &.muted {
        opacity: 0.6;
        font-size: 0.8rem;
      }
    }
    
    .instance-transfer {
      display: flex;
      gap: 16px;
      margin-top: 8px;
      padding-top: 8px;
      border-top: 1px solid var(--panel-border-color);
      
      .transfer-mini {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        
        svg {
          width: 14px;
          height: 14px;
          
          &.download {
            color: var(--color-success, #22c55e);
          }
          
          &.upload {
            color: var(--color-info, #3b82f6);
          }
        }
      }
    }
  }
  
  .instance-card-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--panel-border-color);
    
    button.icon-only {
      padding: 8px;
      background: transparent;
      border: 1px solid var(--panel-border-color);
      border-radius: 6px;
      color: var(--panel-section-text-color);
      cursor: pointer;
      transition: all 0.2s;
      
      svg {
        width: 16px;
        height: 16px;
      }
      
      &:hover:not(:disabled) {
        background: var(--panel-border-color);
      }
      
      &.danger:hover:not(:disabled) {
        background: color-mix(in srgb, var(--color-danger, #ef4444) 15%, transparent);
        border-color: var(--color-danger, #ef4444);
        color: var(--color-danger, #ef4444);
      }
      
      &:disabled {
        opacity: 0.3;
        cursor: not-allowed;
      }
    }
  }
}

// Billing Portal Section
.billing-portal-section {
  margin-top: 24px;
  
  .section-divider {
    border: none;
    border-top: 1px solid var(--panel-border-color);
    margin: 0 0 16px 0;
  }
  
  .billing-portal-description {
    font-size: 0.875rem;
    opacity: 0.8;
    margin-bottom: 16px;
  }
}

// Modal variations
.danger-dialog {
  h2 {
    color: var(--color-danger, #ef4444);
    
    svg {
      color: var(--color-danger, #ef4444);
    }
  }
  
  .danger-message {
    text-align: center;
    margin: 16px 0;
    color: var(--panel-text-color);
  }
}

.warning-dialog {
  h2 {
    color: var(--color-warning, #f59e0b);
    
    svg {
      color: var(--color-warning, #f59e0b);
    }
  }
  
  .warning-message {
    text-align: center;
    margin: 16px 0;
    color: var(--panel-text-color);
  }
}

.instance-to-delete {
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;
  padding: 16px;
  margin: 16px 0;
  text-align: center;
  
  strong {
    display: block;
    font-size: 1.1rem;
    margin-bottom: 4px;
  }
  
  span {
    font-size: 0.875rem;
    opacity: 0.7;
  }
}

.token-display {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;
  padding: 12px 16px;
  margin: 16px 0;
  
  code {
    flex: 1;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.8rem;
    word-break: break-all;
    color: var(--button-primary-background-color);
  }
  
  button.icon-only {
    padding: 8px;
    background: transparent;
    border: 1px solid var(--panel-border-color);
    border-radius: 6px;
    cursor: pointer;
    
    svg {
      width: 16px;
      height: 16px;
    }
    
    &:hover {
      background: var(--panel-border-color);
    }
  }
}

.warning-text {
  margin-top: 8px;
  font-size: 0.8rem;
  color: var(--color-warning, #f59e0b);
}

button.danger {
  background: var(--color-danger, #ef4444);
  border-color: var(--color-danger, #ef4444);
  color: white;
  
  &:hover:not(:disabled) {
    background: color-mix(in srgb, var(--color-danger, #ef4444) 80%, black);
  }
}
</style>

