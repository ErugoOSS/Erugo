<script setup>
import { computed } from 'vue'
import { CreditCard, Check, X, Loader2, AlertTriangle, RotateCcw } from 'lucide-vue-next'
import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()

const props = defineProps({
  plans: Array,
  selectedPlan: String,
  currentSubscriptionPlan: String,
  loading: Boolean,
  loadingPlans: Boolean,
  pollingSubscription: Boolean,
  hasActiveSubscription: Boolean,
  currentPlan: Object,
  readOnly: Boolean,
  cancelAtPeriodEnd: Boolean,
  currentPeriodEnd: String,
  compact: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:selectedPlan', 'checkout', 'stopPolling', 'reactivate'])

// Compute what action will be taken based on current state
const selectedPlanObj = computed(() => {
  return props.plans?.find((p) => p.name === props.selectedPlan)
})

const formattedCancellationDate = computed(() => {
  if (!props.currentPeriodEnd) return null
  try {
    const date = new Date(props.currentPeriodEnd)
    return date.toLocaleDateString(undefined, { 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    })
  } catch {
    return props.currentPeriodEnd
  }
})

const buttonText = computed(() => {
  if (props.pollingSubscription) {
    return t.value('cloudConnect.subscription.waitingForPayment')
  }

  // If subscription is scheduled for cancellation, don't show the normal button
  if (props.cancelAtPeriodEnd) {
    return t.value('cloudConnect.planManagement.changePlanButton')
  }

  // If no subscription and selecting free plan
  if (!props.hasActiveSubscription && selectedPlanObj.value?.is_free) {
    return t.value('cloudConnect.subscription.activateFreePlan') || 'Activate Free Plan'
  }

  // If no subscription and selecting paid plan
  if (!props.hasActiveSubscription && !selectedPlanObj.value?.is_free) {
    return t.value('cloudConnect.subscription.subscribe') || 'Subscribe'
  }

  // If has subscription and selecting free plan (cancel)
  if (props.hasActiveSubscription && selectedPlanObj.value?.is_free) {
    return t.value('cloudConnect.subscription.cancelSubscription') || 'Cancel Subscription'
  }

  // If has subscription and selecting different paid plan
  if (props.hasActiveSubscription && !selectedPlanObj.value?.is_free) {
    return t.value('cloudConnect.planManagement.changePlanButton')
  }

  return t.value('cloudConnect.planManagement.changePlanButton')
})
</script>

<template>
  <div v-if="loadingPlans" class="loading-plans">
    <Loader2 class="spinner" />
    <span>{{ $t('cloudConnect.subscription.loadingPlans') }}</span>
  </div>

  <div v-else class="plan-selector" :class="{ compact }">
    <div
      v-for="plan in plans"
      :key="plan.name"
      class="plan-card"
      :class="{ selected: selectedPlan === plan.name, current: plan.name === currentSubscriptionPlan }"
      @click="emit('update:selectedPlan', plan.name)"
    >
      <div class="plan-card-content">
        <div class="plan-card-header">
          <h4>{{ plan.display_name }}</h4>
          <div>
            <span v-if="plan.name === selectedPlan" class="selected-badge">
              {{ $t('cloudConnect.planManagement.selected') }}
            </span>
            <span v-if="plan.name === currentSubscriptionPlan" class="current-badge">
              {{ $t('cloudConnect.planManagement.current') }}
            </span>
          </div>
        </div>
        <div v-if="plan.price_cents" class="price">
          ${{ (plan.price_cents / 100).toFixed(2) }}
          <span>/{{ $t('cloudConnect.subscription.month') }}</span>
        </div>
        <div v-else class="price free">{{ $t('cloudConnect.planManagement.free') }}</div>
        <ul>
          <li>
            <Check class="list-icon included" />
            {{ $t('cloudConnect.subscription.instances', { count: plan.max_instances }) }}
          </li>
          <li>
            <Check class="list-icon included" />
            {{
              plan.max_transfer_gb
                ? $t('cloudConnect.subscription.transfer', { count: plan.max_transfer_gb }) ||
                  `${plan.max_transfer_gb} GB transfer`
                : $t('cloudConnect.subscription.transferUnlimited') || 'Unlimited transfer'
            }}
          </li>
          <li>
            <Check class="list-icon included" />
            {{
              plan.max_bandwidth_mbps
                ? $t('cloudConnect.subscription.bandwidth', { count: plan.max_bandwidth_mbps }) ||
                  `${plan.max_bandwidth_mbps} Mbps bandwidth`
                : $t('cloudConnect.subscription.bandwidthUnlimited') || 'Unlimited bandwidth'
            }}
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
        <p v-if="plan.description && !compact" class="plan-description">{{ plan.description }}</p>
      </div>
    </div>
  </div>

  <!-- Cancellation scheduled notice -->
  <div v-if="cancelAtPeriodEnd && !readOnly" class="cancellation-notice">
    <AlertTriangle class="notice-icon" />
    <div class="notice-content">
      <strong>{{ $t('cloudConnect.subscription.cancellationScheduledTitle') || 'Subscription Cancelling' }}</strong>
      <p>
        {{ $t('cloudConnect.subscription.cancellationScheduledMessage', { date: formattedCancellationDate }) || 
           `Your subscription will be cancelled on ${formattedCancellationDate}. You'll retain access until then.` }}
      </p>
    </div>
    <button @click="emit('reactivate')" :disabled="loading" class="reactivate-btn">
      <Loader2 v-if="loading" class="spinner" />
      <RotateCcw v-else />
      {{ $t('cloudConnect.subscription.reactivate') || 'Reactivate' }}
    </button>
  </div>

  <button
    v-if="!readOnly && !cancelAtPeriodEnd"
    @click="emit('checkout')"
    :disabled="loading || pollingSubscription || loadingPlans || (selectedPlan && selectedPlan == currentSubscriptionPlan)"
    :class="{ danger: hasActiveSubscription && selectedPlanObj?.is_free }"
  >
    <Loader2 v-if="loading || pollingSubscription" class="spinner" />
    <CreditCard v-else />
    {{ buttonText }}
  </button>

  <p v-if="pollingSubscription" class="polling-note">
    {{ $t('cloudConnect.subscription.pollingNote') }}
    <button @click="emit('stopPolling')" class="secondary">
      {{ $t('cloudConnect.subscription.stopWaiting') }}
    </button>
  </p>
</template>

<style lang="scss" scoped>
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

  &.compact {
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
}

.plan-card {
  border: 2px solid var(--panel-border-color);
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  isolation: isolate;

  .plan-card-content {
    position: relative;
    background: var(--panel-background-color);
    padding: 24px;
    z-index: 10;
    border-radius: var(--panel-border-radius);
    height: 100%;
  }

  &.selected {
    // border-color: var(--primary-button-background-color);
    // background: color-mix(in srgb, var(--primary-button-background-color) 10%, transparent);
  }

  &:hover:not(.selected) {
    border-color: var(--primary-button-background-color);
  }

  &.selected {
    border-color: transparent;

    &::before {
      content: '';
      position: absolute;
      inset: -2px;
      background: var(--primary-button-background-color);
      border-radius: var(--panel-border-radius);
      z-index: -1;
    }
  }

  .plan-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;

    h4 {
      margin: 0;
      font-size: 1.25rem;
      color: var(--panel-section-text-color);
    }

    .current-badge {
      font-size: 0.75rem;
      padding: 2px 8px;
      background: var(--primary-button-background-color);
      color: var(--primary-button-text-color);
      border-radius: 4px;
    }
    .selected-badge {
      font-size: 0.75rem;
      padding: 2px 8px;
      background: var(--secondary-button-background-color);
      color: var(--secondary-button-text-color);
      border-radius: 4px;
      margin-right: 10px;
      border: 1px solid var(--primary-button-background-color);
    }
  }

  .price {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-button-background-color);
    margin-bottom: 16px;

    span {
      font-size: 1rem;
      font-weight: 400;
      opacity: 0.7;
    }

    &.free {
      font-size: 1.5rem;
      color: var(--color-success);
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
          color: var(--color-success);
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

  .plan-description {
    margin: 16px 0 0;
    padding-top: 12px;
    border-top: 1px solid var(--panel-border-color);
    font-size: 0.8125rem;
    color: var(--panel-section-text-color);
    opacity: 0.7;
    line-height: 1.5;
  }
}

.polling-note {
  margin-top: 16px;
  font-size: 0.875rem;
  color: var(--panel-section-text-color);
  opacity: 0.7;
  text-align: center;
}

.cancellation-notice {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  margin-bottom: 16px;
  background: color-mix(in srgb, var(--color-warning) 15%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-warning) 40%, transparent);
  border-radius: var(--panel-border-radius, 8px);

  .notice-icon {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    color: var(--color-warning);
  }

  .notice-content {
    flex: 1;

    strong {
      display: block;
      color: var(--panel-section-text-color);
      margin-bottom: 4px;
    }

    p {
      margin: 0;
      font-size: 0.875rem;
      color: var(--panel-section-text-color);
      opacity: 0.8;
    }
  }

  .reactivate-btn {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--color-success);
    border: none;
    border-radius: 6px;
    color: white;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;

    &:hover:not(:disabled) {
      background: color-mix(in srgb, var(--color-success) 80%, black);
    }

    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    svg {
      width: 16px;
      height: 16px;
    }
  }
}

button.danger {
  background: var(--color-danger);
  border-color: var(--color-danger);

  &:hover:not(:disabled) {
    background: color-mix(in srgb, var(--color-danger) 80%, black);
    border-color: color-mix(in srgb, var(--color-danger) 80%, black);
  }
}

.spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
