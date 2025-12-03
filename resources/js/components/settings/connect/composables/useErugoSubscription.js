import { ref, computed, onUnmounted } from 'vue'
import {
  getCloudConnectPlans,
  getCloudConnectPublicPlans,
  getCloudConnectSubscription,
  createCloudConnectCheckout,
  createCloudConnectBillingPortal
} from '../../../../api'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'

export function useErugoSubscription(statusRef, onSubscriptionActive) {
  const { t } = useTranslate()
  const toast = useToast()

  // State
  const plans = ref([])
  const selectedPlan = ref(null)
  const loadingPlans = ref(false)
  const pollingSubscription = ref(false)
  const pollInterval = ref(null)
  const showPlanManagement = ref(false)
  const loadingBillingPortal = ref(false)

  // Computed
  const currentPlan = computed(() => {
    if (!statusRef.value?.subscription_plan || plans.value.length === 0) return null
    return plans.value.find((p) => p.name === statusRef.value.subscription_plan)
  })

  const hasActiveSubscription = computed(() => {
    return (
      statusRef.value?.subscription_status === 'active' ||
      statusRef.value?.subscription_status === 'trialing' ||
      (statusRef.value?.subscription_plan && statusRef.value?.subscription_plan !== 'none')
    )
  })

  // Methods
  const loadPlans = async (forceRefresh = false) => {
    if (plans.value.length > 0 && !forceRefresh) return

    try {
      loadingPlans.value = true
      // Use public endpoint when not logged in, authenticated endpoint when logged in
      const result = statusRef.value?.is_logged_in ? await getCloudConnectPlans() : await getCloudConnectPublicPlans()
      plans.value = result.plans || []

      if (!selectedPlan.value && plans.value.length > 0) {
        selectedPlan.value = plans.value[0].name
      }
    } catch (error) {
      console.error('Failed to load plans:', error)
      if (statusRef.value?.is_logged_in) {
        toast.error(t.value('cloudConnect.subscription.loadPlansFailed'))
      }
    } finally {
      loadingPlans.value = false
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
          if (onSubscriptionActive) {
            await onSubscriptionActive()
          }
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

  const handleCheckout = async () => {
    try {
      const plan = plans.value.find((p) => p.name === selectedPlan.value)
      if (plan?.is_free) {
        toast.success(t.value('cloudConnect.subscription.freePlanSelected'))
        if (onSubscriptionActive) {
          await onSubscriptionActive()
        }
        return
      }

      const result = await createCloudConnectCheckout(selectedPlan.value)
      window.open(result.checkout_url, '_blank')
      startSubscriptionPolling(result.poll_interval || 3000)
      toast.info(t.value('cloudConnect.checkoutOpened'))
    } catch (error) {
      toast.error(error.message || t.value('cloudConnect.checkoutFailed'))
    }
  }

  const openPlanManagement = async () => {
    await loadPlans()
    if (statusRef.value?.subscription_plan) {
      selectedPlan.value = statusRef.value.subscription_plan
    }
    showPlanManagement.value = true
  }

  const closePlanManagement = () => {
    showPlanManagement.value = false
  }

  const handleChangePlan = async () => {
    if (selectedPlan.value === statusRef.value?.subscription_plan) {
      closePlanManagement()
      return
    }

    try {
      const plan = plans.value.find((p) => p.name === selectedPlan.value)
      if (plan?.is_free) {
        toast.info(t.value('cloudConnect.planManagement.contactToDowngrade'))
        closePlanManagement()
        return
      }

      const result = await createCloudConnectCheckout(selectedPlan.value)
      window.open(result.checkout_url, '_blank')
      startSubscriptionPolling(result.poll_interval || 3000)
      toast.info(t.value('cloudConnect.checkoutOpened'))
      closePlanManagement()
    } catch (error) {
      toast.error(error.message || t.value('cloudConnect.checkoutFailed'))
    }
  }

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

  // Cleanup on unmount
  onUnmounted(() => {
    stopPolling()
  })

  return {
    // State
    plans,
    selectedPlan,
    loadingPlans,
    pollingSubscription,
    showPlanManagement,
    loadingBillingPortal,

    // Computed
    currentPlan,
    hasActiveSubscription,

    // Methods
    loadPlans,
    handleCheckout,
    handleChangePlan,
    startSubscriptionPolling,
    stopPolling,
    openPlanManagement,
    closePlanManagement,
    openBillingPortal
  }
}
