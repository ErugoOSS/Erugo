<script setup>
import {
  Server,
  Globe,
  Wifi,
  WifiOff,
  Pencil,
  Key,
  Trash2,
  ArrowDown,
  ArrowUp,
  Loader2
} from 'lucide-vue-next'

const props = defineProps({
  instances: Array,
  currentInstanceId: [String, Number],
  loading: Boolean,
  formatBytes: Function,
  formatDate: Function,
  getInstanceStatusClass: Function
})

const emit = defineEmits(['edit', 'regenerateToken', 'delete'])
</script>

<template>
  <div v-if="loading" class="loading-plans">
    <Loader2 class="spinner" />
    <span>{{ $t('cloudConnect.instances.loading') || 'Loading instances...' }}</span>
  </div>

  <div v-else-if="instances.length > 0" class="instances-list">
    <div
      v-for="instance in instances"
      :key="instance.id"
      class="instance-card"
      :class="{
        current: instance.id === currentInstanceId,
        online: instance.status === 'online' || instance.status === 'connected',
        offline: instance.status === 'offline' || instance.status === 'disconnected'
      }"
    >
      <div class="instance-card-header">
        <div class="instance-name">
          <Server />
          <span>{{ instance.name || 'Unnamed Instance' }}</span>
          <span v-if="instance.id === currentInstanceId" class="current-badge">
            {{ $t('cloudConnect.instances.current') || 'Current' }}
          </span>
        </div>
        <div class="instance-status" :class="getInstanceStatusClass(instance)">
          <component
            :is="instance.status === 'online' || instance.status === 'connected' ? Wifi : WifiOff"
          />
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
        <button
          class="icon-only"
          @click="emit('edit', instance)"
          :title="$t('cloudConnect.instances.edit') || 'Edit'"
        >
          <Pencil />
        </button>
        <button
          class="icon-only"
          @click="emit('regenerateToken', instance)"
          :title="$t('cloudConnect.instances.regenerateToken') || 'Regenerate Token'"
        >
          <Key />
        </button>
        <button
          class="icon-only danger"
          @click="emit('delete', instance)"
          :title="$t('cloudConnect.instances.delete') || 'Delete'"
          :disabled="instances.length <= 1"
        >
          <Trash2 />
        </button>
      </div>
    </div>
  </div>

  <slot v-else name="empty"></slot>
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
    border-left: 4px solid var(--color-success);
  }

  &.offline {
    border-left: 4px solid var(--color-danger);
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
        background: color-mix(in srgb, var(--color-success) 15%, transparent);
        color: var(--color-success);
      }

      &.offline {
        background: color-mix(in srgb, var(--color-danger) 15%, transparent);
        color: var(--color-danger);
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
            color: var(--color-success);
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
        background: color-mix(in srgb, var(--color-danger) 15%, transparent);
        border-color: var(--color-danger);
        color: var(--color-danger);
      }

      &:disabled {
        opacity: 0.3;
        cursor: not-allowed;
      }
    }
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

