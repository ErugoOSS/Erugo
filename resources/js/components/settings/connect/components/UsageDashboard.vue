<script setup>
import {
  Server,
  ArrowUpDown,
  ArrowDown,
  ArrowUp,
  HardDrive,
  CreditCard,
  RefreshCw,
  Loader2
} from 'lucide-vue-next'

const props = defineProps({
  usageData: Object,
  loading: Boolean,
  formatBytes: Function,
  formatDate: Function
})

const emit = defineEmits(['refresh'])
</script>

<template>
  <div v-if="loading" class="loading-plans">
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
            :style="{
              width: Math.min(100, (usageData.instances?.total / usageData.instances?.limit) * 100) + '%'
            }"
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
        {{ $t('cloudConnect.usage.periodStart') || 'Period started' }}:
        {{ formatDate(usageData.transfer?.period_start) }}
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
        <div class="plan-limit-item">
          <span class="limit-label">{{ $t('cloudConnect.usage.maxTransfer') || 'Max Transfer' }}:</span>
          <span v-if="usageData.plan.max_transfer_gb" class="limit-value">
            {{ usageData.plan.max_transfer_gb }} GB
          </span>
          <span v-else class="limit-value unlimited">
            {{ $t('cloudConnect.usage.unlimited') || 'Unlimited' }}
          </span>
        </div>
        <div class="plan-limit-item">
          <span class="limit-label">{{ $t('cloudConnect.usage.maxBandwidth') || 'Max Bandwidth' }}:</span>
          <span v-if="usageData.plan.max_bandwidth_mbps" class="limit-value">
            {{ usageData.plan.max_bandwidth_mbps }} Mbps
          </span>
          <span v-else class="limit-value unlimited">
            {{ $t('cloudConnect.usage.unlimited') || 'Unlimited' }}
          </span>
        </div>
      </div>
    </div>
  </div>

  <div v-else class="no-usage-data">
    <p>{{ $t('cloudConnect.usage.noData') || 'No usage data available' }}</p>
    <button @click="emit('refresh')" class="secondary">
      <RefreshCw />
      {{ $t('cloudConnect.refresh') || 'Refresh' }}
    </button>
  </div>
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
          background: var(--color-warning);
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
          color: var(--color-success);
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

        &.unlimited {
          color: var(--color-success);
        }
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

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>

