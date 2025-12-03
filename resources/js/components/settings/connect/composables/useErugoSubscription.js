import { ref, computed, onUnmounted } from 'vue'
import {
  getCloudConnectPlans,
  getCloudConnectPublicPlans,
  getCloudConnectSubscription,
  createCloudConnectCheckout,
  createCloudConnectBillingPortal,
  changeCloudConnectPlan,
  cancelCloudConnectSubscription,
  reactivateCloudConnectSubscription
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
  const changingPlan = ref(false)
  
  // Plan change confirmation modal state
  const showPlanChangeConfirm = ref(false)
  const pendingPlanAction = ref(null) // 'upgrade', 'downgrade', 'cancel', 'reactivate'
  const pendingTargetPlan = ref(null)

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
        // Check for active/trialing status OR a valid paid plan exists
        // The API may return status 'none' but still have a valid plan after checkout
        const hasValidStatus = subscription.status === 'active' || subscription.status === 'trialing'
        const hasValidPlan = subscription.plan && subscription.plan !== 'none' && subscription.plan !== 'free'
        
        if (hasValidStatus || hasValidPlan) {
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

  // Check if user has a paid subscription (not just free plan)
  const hasPaidSubscription = computed(() => {
    if (!hasActiveSubscription.value) return false
    // Check if current plan is a paid plan
    const currentPlanObj = plans.value.find((p) => p.name === statusRef.value?.subscription_plan)
    return currentPlanObj && !currentPlanObj.is_free
  })

  // Determine what action type a plan change would be
  const getPlanChangeActionType = (targetPlanName) => {
    const currentPlanName = statusRef.value?.subscription_plan
    const hasSubscription = hasActiveSubscription.value
    const hasPaid = hasPaidSubscription.value
    const targetPlanObj = plans.value.find((p) => p.name === targetPlanName)
    const currentPlanObj = plans.value.find((p) => p.name === currentPlanName)

    // No subscription at all - no confirmation needed, will go to checkout or activate free
    if (!hasSubscription) {
      return null
    }

    // Has subscription (free or paid) and selecting free plan - this is a cancel
    // (or no-op if already on free, but that's handled elsewhere)
    if (hasSubscription && targetPlanObj?.is_free) {
      // Only show cancel confirmation if currently on a paid plan
      if (hasPaid) {
        return 'cancel'
      }
      return null // Already on free, selecting free - no action needed
    }

    // Currently on FREE plan and selecting PAID plan - needs checkout, not plan change
    // No confirmation needed here as they'll go through Stripe checkout
    if (hasSubscription && currentPlanObj?.is_free && !targetPlanObj?.is_free) {
      return null // Will create checkout session
    }

    // Has PAID subscription and selecting different PAID plan - this is upgrade/downgrade
    if (hasPaid && !targetPlanObj?.is_free && targetPlanName !== currentPlanName) {
      const currentPrice = currentPlanObj?.price_cents || 0
      const targetPrice = targetPlanObj?.price_cents || 0
      return targetPrice > currentPrice ? 'upgrade' : 'downgrade'
    }

    return null
  }

  // Request plan change - shows confirmation modal if needed
  const requestPlanChange = (targetPlanName = null) => {
    const planName = targetPlanName || selectedPlan.value
    const actionType = getPlanChangeActionType(planName)
    
    if (actionType) {
      // Show confirmation modal
      pendingPlanAction.value = actionType
      pendingTargetPlan.value = plans.value.find((p) => p.name === planName)
      showPlanChangeConfirm.value = true
    } else {
      // No confirmation needed - proceed directly
      executeCheckout(planName)
    }
  }

  // Request reactivation - shows confirmation modal
  const requestReactivation = () => {
    pendingPlanAction.value = 'reactivate'
    pendingTargetPlan.value = null
    showPlanChangeConfirm.value = true
  }

  // Close the confirmation modal
  const closePlanChangeConfirm = () => {
    showPlanChangeConfirm.value = false
    pendingPlanAction.value = null
    pendingTargetPlan.value = null
  }

  // Confirm and execute the pending plan change
  const confirmPlanChange = async () => {
    if (pendingPlanAction.value === 'reactivate') {
      await executeReactivation()
    } else {
      await executeCheckout(pendingTargetPlan.value?.name || selectedPlan.value)
    }
    closePlanChangeConfirm()
  }

  // Execute the actual checkout/plan change
  const executeCheckout = async (targetPlanName) => {
    const currentPlanName = statusRef.value?.subscription_plan
    const hasSubscription = hasActiveSubscription.value
    const hasPaid = hasPaidSubscription.value
    const selectedPlanObj = plans.value.find((p) => p.name === targetPlanName)
    const currentPlanObj = plans.value.find((p) => p.name === currentPlanName)

    try {
      changingPlan.value = true

      // Case 1: User has no subscription and selects free plan - nothing to do
      if (!hasSubscription && selectedPlanObj?.is_free) {
        toast.success(t.value('cloudConnect.subscription.freePlanActivated'))
        if (onSubscriptionActive) {
          await onSubscriptionActive()
        }
        return
      }

      // Case 2: User has no subscription and selects paid plan - create checkout
      if (!hasSubscription && !selectedPlanObj?.is_free) {
        const result = await createCloudConnectCheckout(targetPlanName)
        window.open(result.checkout_url, '_blank')
        startSubscriptionPolling(result.poll_interval || 3000)
        toast.info(t.value('cloudConnect.checkoutOpened'))
        return
      }

      // Case 3: User is on FREE plan and selects PAID plan - needs checkout (no payment method on file)
      if (hasSubscription && currentPlanObj?.is_free && !selectedPlanObj?.is_free) {
        const result = await createCloudConnectCheckout(targetPlanName)
        window.open(result.checkout_url, '_blank')
        startSubscriptionPolling(result.poll_interval || 3000)
        toast.info(t.value('cloudConnect.checkoutOpened'))
        return
      }

      // Case 4: User has paid subscription and selects free plan - cancel subscription
      if (hasPaid && selectedPlanObj?.is_free) {
        await cancelCloudConnectSubscription()
        toast.success(t.value('cloudConnect.subscription.cancellationScheduled'))
        if (onSubscriptionActive) {
          await onSubscriptionActive()
        }
        return
      }

      // Case 5: User has PAID subscription and selects different PAID plan - change plan via API
      if (hasPaid && !selectedPlanObj?.is_free && targetPlanName !== currentPlanName) {
        await changeCloudConnectPlan(targetPlanName)
        toast.success(t.value('cloudConnect.subscription.planChanged'))
        if (onSubscriptionActive) {
          await onSubscriptionActive()
        }
        return
      }
    } catch (error) {
      toast.error(error.message || t.value('cloudConnect.checkoutFailed'))
    } finally {
      changingPlan.value = false
    }
  }

  // Legacy handleCheckout - now calls requestPlanChange
  const handleCheckout = async () => {
    requestPlanChange()
  }

  // Execute the actual reactivation
  const executeReactivation = async () => {
    try {
      changingPlan.value = true
      await reactivateCloudConnectSubscription()
      toast.success(t.value('cloudConnect.subscription.reactivated'))
      if (onSubscriptionActive) {
        await onSubscriptionActive()
      }
    } catch (error) {
      toast.error(error.message || t.value('cloudConnect.subscription.reactivateFailed'))
    } finally {
      changingPlan.value = false
    }
  }

  // Legacy handler - now shows confirmation
  const handleReactivateSubscription = async () => {
    requestReactivation()
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

    // Use the unified handleCheckout logic
    await handleCheckout()
    closePlanManagement()
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
    changingPlan,
    
    // Plan change confirmation state
    showPlanChangeConfirm,
    pendingPlanAction,
    pendingTargetPlan,

    // Computed
    currentPlan,
    hasActiveSubscription,

    // Methods
    loadPlans,
    handleCheckout,
    handleChangePlan,
    handleReactivateSubscription,
    startSubscriptionPolling,
    stopPolling,
    openPlanManagement,
    closePlanManagement,
    openBillingPortal,
    
    // Plan change confirmation methods
    requestPlanChange,
    requestReactivation,
    confirmPlanChange,
    closePlanChangeConfirm
  }
}
