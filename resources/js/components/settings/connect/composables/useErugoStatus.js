import { ref, computed, onUnmounted } from 'vue'
import {
  getCloudConnectStatus,
  connectCloudConnect,
  disconnectCloudConnect,
  resendCloudConnectVerification
} from '../../../../api'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'

export function useErugoStatus(options = {}) {
  const { t } = useTranslate()
  const toast = useToast()
  const { onStatusLoaded, onHasSubscription } = options

  // State
  const loading = ref(true)
  const status = ref(null)
  const currentStep = ref('loading') // loading, capabilities_error, auth, subscription, instance, connected
  const connecting = ref(false)
  const disconnecting = ref(false)
  const currentTime = ref(Date.now())
  const statusPollInterval = ref(null)
  const heartbeatTickInterval = ref(null)

  // Computed
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
    const diffSeconds = Math.floor((currentTime.value - lastHeartbeat.getTime()) / 1000)

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

    const lastHeartbeat = new Date(status.value.last_heartbeat_at)
    const diffSeconds = Math.floor((currentTime.value - lastHeartbeat.getTime()) / 1000)

    return diffSeconds < 120
  })

  const needsEmailVerification = computed(() => {
    return status.value?.account_status === 'pending_email_verification'
  })

  const hasActiveSubscription = computed(() => {
    return (
      status.value?.subscription_status === 'active' ||
      status.value?.subscription_status === 'trialing' ||
      (status.value?.subscription_plan && status.value?.subscription_plan !== 'none')
    )
  })

  const canConnect = computed(() => {
    return status.value?.capabilities?.capable && status.value?.has_instance && status.value?.status !== 'connected'
  })

  // Methods
  const startHeartbeatTick = () => {
    if (heartbeatTickInterval.value) return
    heartbeatTickInterval.value = setInterval(() => {
      currentTime.value = Date.now()
    }, 1000)
  }

  const stopHeartbeatTick = () => {
    if (heartbeatTickInterval.value) {
      clearInterval(heartbeatTickInterval.value)
      heartbeatTickInterval.value = null
    }
  }

  const startStatusPolling = () => {
    if (statusPollInterval.value) return

    startHeartbeatTick()

    statusPollInterval.value = setInterval(async () => {
      try {
        status.value = await getCloudConnectStatus(false)
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
    stopHeartbeatTick()
  }

  const determineStep = () => {
    if (!status.value) {
      currentStep.value = 'loading'
      stopStatusPolling()
      return
    }

    if (!status.value.capabilities?.capable) {
      currentStep.value = 'capabilities_error'
      stopStatusPolling()
      return
    }

    if ((status.value.status === 'connected' || status.value.status === 'reconnecting') && status.value.tunnel_active) {
      currentStep.value = 'connected'
      startStatusPolling()
      if (onHasSubscription) onHasSubscription()
      return
    }

    stopStatusPolling()

    if (!status.value.is_logged_in) {
      currentStep.value = 'auth'
      return
    }

    if (onHasSubscription) onHasSubscription()

    if (needsEmailVerification.value) {
      currentStep.value = 'email_verification'
      return
    }

    if (!hasActiveSubscription.value) {
      currentStep.value = 'subscription'
      return
    }

    if (!status.value.has_instance) {
      currentStep.value = 'instance'
      return
    }

    currentStep.value = 'ready'
  }

  const loadStatus = async (refresh = false) => {
    try {
      loading.value = true
      status.value = await getCloudConnectStatus(refresh)
      determineStep()
      if (onStatusLoaded) {
        onStatusLoaded(status.value)
      }
    } catch (error) {
      toast.error(t.value('cloudConnect.errorLoadingStatus'))
      console.error(error)
    } finally {
      loading.value = false
    }
  }

  const refreshStatus = async () => {
    await loadStatus(true)
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

  const checkVerificationStatus = async () => {
    try {
      loading.value = true
      await loadStatus(true)
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

  // Cleanup on unmount
  onUnmounted(() => {
    stopStatusPolling()
    stopHeartbeatTick()
  })

  return {
    // State
    loading,
    status,
    currentStep,
    connecting,
    disconnecting,
    currentTime,

    // Computed
    isConnected,
    isReconnecting,
    lastHeartbeatFormatted,
    heartbeatHealthy,
    needsEmailVerification,
    hasActiveSubscription,
    canConnect,

    // Methods
    loadStatus,
    refreshStatus,
    determineStep,
    handleConnect,
    handleDisconnect,
    checkVerificationStatus,
    resendVerificationEmail,
    startStatusPolling,
    stopStatusPolling
  }
}

