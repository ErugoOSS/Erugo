<script setup>
import { computed } from 'vue'
import { ArrowUpCircle, ArrowDownCircle, XCircle, RotateCcw, Loader2, CircleX } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  actionType: String, // 'upgrade', 'downgrade', 'cancel', 'reactivate'
  currentPlan: Object,
  targetPlan: Object,
  currentPeriodEnd: String
})

const emit = defineEmits(['update:show', 'confirm'])

const formattedPeriodEnd = computed(() => {
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

const icon = computed(() => {
  switch (props.actionType) {
    case 'upgrade':
      return ArrowUpCircle
    case 'downgrade':
    case 'cancel':
      return ArrowDownCircle
    case 'reactivate':
      return RotateCcw
    default:
      return XCircle
  }
})

const dialogClass = computed(() => {
  if (props.actionType === 'cancel') return 'danger-dialog'
  if (props.actionType === 'downgrade') return 'warning-dialog'
  return ''
})

const buttonClass = computed(() => {
  if (props.actionType === 'cancel') return 'danger'
  if (props.actionType === 'downgrade') return 'warning'
  return ''
})
</script>

<template>
  <div class="auth-form-overlay" :class="{ active: show }" @click.self="emit('update:show', false)">
    <div class="auth-slide-form" :class="dialogClass">
      <h2>
        <component :is="icon" />
        <template v-if="actionType === 'upgrade'">
          {{ $t('cloudConnect.planChange.upgradeTitle') || 'Upgrade Plan' }}
        </template>
        <template v-else-if="actionType === 'downgrade'">
          {{ $t('cloudConnect.planChange.downgradeTitle') || 'Downgrade Plan' }}
        </template>
        <template v-else-if="actionType === 'cancel'">
          {{ $t('cloudConnect.planChange.cancelTitle') || 'Cancel Subscription' }}
        </template>
        <template v-else-if="actionType === 'reactivate'">
          {{ $t('cloudConnect.planChange.reactivateTitle') || 'Reactivate Subscription' }}
        </template>
      </h2>

      <!-- Plan change info -->
      <div v-if="currentPlan && targetPlan && actionType !== 'cancel' && actionType !== 'reactivate'" class="plan-change-info">
        <div class="plan-box">
          <span class="plan-label">{{ $t('cloudConnect.planChange.from') || 'From' }}</span>
          <strong>{{ currentPlan.display_name }}</strong>
          <span v-if="currentPlan.price_cents" class="plan-price">${{ (currentPlan.price_cents / 100).toFixed(2) }}/{{ $t('cloudConnect.subscription.month') }}</span>
          <span v-else class="plan-price free">{{ $t('cloudConnect.planManagement.free') || 'Free' }}</span>
        </div>
        <div class="plan-arrow">→</div>
        <div class="plan-box">
          <span class="plan-label">{{ $t('cloudConnect.planChange.to') || 'To' }}</span>
          <strong>{{ targetPlan.display_name }}</strong>
          <span v-if="targetPlan.price_cents" class="plan-price">${{ (targetPlan.price_cents / 100).toFixed(2) }}/{{ $t('cloudConnect.subscription.month') }}</span>
          <span v-else class="plan-price free">{{ $t('cloudConnect.planManagement.free') || 'Free' }}</span>
        </div>
      </div>

      <!-- Cancel info -->
      <div v-if="actionType === 'cancel' && currentPlan" class="plan-change-info single">
        <div class="plan-box">
          <span class="plan-label">{{ $t('cloudConnect.planChange.cancelling') || 'Cancelling' }}</span>
          <strong>{{ currentPlan.display_name }}</strong>
          <span v-if="currentPlan.price_cents" class="plan-price">${{ (currentPlan.price_cents / 100).toFixed(2) }}/{{ $t('cloudConnect.subscription.month') }}</span>
        </div>
      </div>

      <!-- Message based on action type -->
      <div class="confirm-message">
        <template v-if="actionType === 'upgrade'">
          <p>{{ $t('cloudConnect.planChange.upgradeMessage') || 'You will be charged the pro-rated difference immediately. Your new plan features will be available right away.' }}</p>
        </template>
        <template v-else-if="actionType === 'downgrade'">
          <p>{{ $t('cloudConnect.planChange.downgradeMessage', { date: formattedPeriodEnd }) || `Your plan will be downgraded at the end of your current billing period (${formattedPeriodEnd}). You'll retain your current plan features until then.` }}</p>
        </template>
        <template v-else-if="actionType === 'cancel'">
          <p>{{ $t('cloudConnect.planChange.cancelMessage', { date: formattedPeriodEnd }) || `Your subscription will be cancelled at the end of your current billing period (${formattedPeriodEnd}). You'll retain access to all features until then.` }}</p>
        </template>
        <template v-else-if="actionType === 'reactivate'">
          <p>{{ $t('cloudConnect.planChange.reactivateMessage') || 'Your subscription will be reactivated and will continue as normal. No additional charges will be made until your next billing date.' }}</p>
        </template>
      </div>

      <div class="button-bar">
        <button 
          type="button" 
          :class="buttonClass"
          @click="emit('confirm')" 
          :disabled="loading"
        >
          <Loader2 v-if="loading" class="spinner" />
          <component v-else :is="icon" />
          <template v-if="actionType === 'upgrade'">
            {{ $t('cloudConnect.planChange.confirmUpgrade') || 'Upgrade Now' }}
          </template>
          <template v-else-if="actionType === 'downgrade'">
            {{ $t('cloudConnect.planChange.confirmDowngrade') || 'Schedule Downgrade' }}
          </template>
          <template v-else-if="actionType === 'cancel'">
            {{ $t('cloudConnect.planChange.confirmCancel') || 'Cancel Subscription' }}
          </template>
          <template v-else-if="actionType === 'reactivate'">
            {{ $t('cloudConnect.planChange.confirmReactivate') || 'Reactivate' }}
          </template>
        </button>
        <button type="button" class="secondary" @click="emit('update:show', false)">
          <CircleX />
          {{ $t('settings.close') || 'Close' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import './modalStyles.scss';

.plan-change-info {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  width: 100%;
  margin: 16px 0;

  &.single {
    justify-content: center;
  }

  .plan-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px 24px;
    background: var(--panel-section-background-color-alt);
    border-radius: 8px;
    min-width: 140px;

    .plan-label {
      font-size: 0.75rem;
      text-transform: uppercase;
      opacity: 0.6;
      margin-bottom: 4px;
    }

    strong {
      font-size: 1.1rem;
      color: var(--panel-text-color);
    }

    .plan-price {
      font-size: 0.875rem;
      color: var(--primary-button-background-color);
      margin-top: 4px;

      &.free {
        color: var(--color-success);
      }
    }
  }

  .plan-arrow {
    font-size: 1.5rem;
    color: var(--panel-text-color);
    opacity: 0.5;
  }
}

.confirm-message {
  text-align: center;
  margin: 16px 0;
  padding: 16px;
  background: var(--panel-section-background-color-alt);
  border-radius: 8px;
  width: 100%;

  p {
    margin: 0;
    color: var(--panel-text-color);
    line-height: 1.6;
  }
}

button.warning {
  background: var(--color-warning);
  border-color: var(--color-warning);
  color: white;

  &:hover:not(:disabled) {
    background: color-mix(in srgb, var(--color-warning) 80%, black);
  }
}
</style>

