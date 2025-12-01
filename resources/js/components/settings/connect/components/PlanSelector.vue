<script setup>
import { CreditCard, Check, X, Loader2 } from 'lucide-vue-next'

const props = defineProps({
  plans: Array,
  selectedPlan: String,
  currentSubscriptionPlan: String,
  loading: Boolean,
  loadingPlans: Boolean,
  pollingSubscription: Boolean,
  hasActiveSubscription: Boolean,
  currentPlan: Object,
  compact: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:selectedPlan', 'checkout', 'stopPolling'])
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
      <div class="plan-card-header">
        <h4>{{ plan.display_name }}</h4>
        <span v-if="plan.name === currentSubscriptionPlan" class="current-badge">
          {{ $t('cloudConnect.planManagement.current') }}
        </span>
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

  <button
    v-if="selectedPlan && selectedPlan !== currentSubscriptionPlan"
    @click="emit('checkout')"
    :disabled="loading || pollingSubscription || loadingPlans"
  >
    <Loader2 v-if="loading || pollingSubscription" class="spinner" />
    <CreditCard v-else />
    {{
      pollingSubscription
        ? $t('cloudConnect.subscription.waitingForPayment')
        : $t('cloudConnect.planManagement.changePlanButton')
    }}
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

  &.current {
    border-color: var(--color-success);
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
      background: var(--button-primary-background-color);
      color: var(--button-primary-text-color);
      border-radius: 4px;
    }
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

