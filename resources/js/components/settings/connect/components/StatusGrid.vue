<script setup>
import {
  Wifi,
  WifiOff,
  Globe,
  User,
  CreditCard,
  ExternalLink,
  Copy,
  Check,
  LogIn,
  LogOut,
  UserPlus,
  Loader2
} from 'lucide-vue-next'
import { ref } from 'vue'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()
const toast = useToast()

const props = defineProps({
  status: Object,
  isConnected: Boolean,
  isReconnecting: Boolean,
  heartbeatHealthy: Boolean,
  lastHeartbeatFormatted: String,
  currentPlan: Object,
  loading: Boolean
})

const emit = defineEmits(['login', 'logout', 'register'])

const copiedDomain = ref(false)

const copyDomain = async () => {
  try {
    await navigator.clipboard.writeText(`https://${props.status?.full_domain}`)
    copiedDomain.value = true
    setTimeout(() => {
      copiedDomain.value = false
    }, 2000)
  } catch (error) {
    toast.error(t.value('cloudConnect.copyFailed'))
  }
}
</script>

<template>
  <div class="status-grid">
    <!-- Connection Status Card -->
    <div class="status-card" :class="{ online: isConnected, offline: !isConnected }">
      <div class="status-card-icon">
        <Loader2 v-if="isReconnecting" class="spinner" />
        <Wifi v-else-if="isConnected" />
        <WifiOff v-else />
      </div>
      <div class="status-card-content">
        <span class="status-card-label">{{ $t('cloudConnect.status.connection') }}</span>
        <span class="status-card-value">
          <template v-if="isReconnecting">{{ $t('cloudConnect.connected.reconnecting') }}</template>
          <template v-else-if="isConnected">{{ $t('cloudConnect.status.connected') }}</template>
          <template v-else>{{ $t('cloudConnect.status.disconnected') }}</template>
        </span>
        <span v-if="isConnected && status?.last_heartbeat_at" class="status-card-meta" :class="{ healthy: heartbeatHealthy, unhealthy: !heartbeatHealthy }">
          {{ lastHeartbeatFormatted }}
        </span>
      </div>
    </div>

    <!-- Domain Card -->
    <div v-if="status?.full_domain" class="status-card domain-card">
      <div class="status-card-icon">
        <Globe />
      </div>
      <div class="status-card-content">
        <span class="status-card-label">{{ $t('cloudConnect.status.domain') }}</span>
        <a :href="`https://${status?.full_domain}`" target="_blank" class="status-card-value domain-link">
          {{ status?.full_domain }}
          <ExternalLink />
        </a>
        <button @click="copyDomain" class="copy-btn" :title="$t('cloudConnect.connected.copyUrl')">
          <Check v-if="copiedDomain" />
          <Copy v-else />
        </button>
      </div>
    </div>

    <!-- Account Card -->
    <div class="status-card account-card" :class="{ 'logged-in': status?.is_logged_in }">
      <div class="status-card-icon">
        <User />
      </div>
      <div class="status-card-content">
        <span class="status-card-label">{{ $t('cloudConnect.status.account') }}</span>
        <span class="status-card-value">
          {{ status?.is_logged_in ? status?.user_email : $t('cloudConnect.status.notLoggedIn') }}
        </span>
        <span v-if="status?.is_logged_in && status?.account_status" class="status-card-meta status-badge" :class="status?.account_status">
          {{ status?.account_status }}
        </span>
      </div>
      <div class="status-card-actions">
        <template v-if="status?.is_logged_in">
          <button @click="emit('logout')" class="card-action-btn" :disabled="loading" :title="$t('cloudConnect.auth.logout')">
            <Loader2 v-if="loading" class="spinner" />
            <LogOut v-else />
          </button>
        </template>
        <template v-else>
          <button @click="emit('login')" class="card-action-btn" :title="$t('cloudConnect.auth.login')">
            <LogIn /> {{ $t('cloudConnect.auth.login') }}
          </button>
          <button @click="emit('register')" class="card-action-btn secondary" :title="$t('cloudConnect.auth.register')">
            <UserPlus /> {{ $t('cloudConnect.auth.register') }}
          </button>
        </template>
      </div>
    </div>

    <!-- Plan Card -->
    <div v-if="status?.is_logged_in" class="status-card">
      <div class="status-card-icon">
        <CreditCard />
      </div>
      <div class="status-card-content">
        <span class="status-card-label">{{ $t('cloudConnect.status.plan') }}</span>
        <span class="status-card-value">
          {{ currentPlan?.display_name || status?.subscription_plan || $t('cloudConnect.status.noPlan') }}
        </span>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.status-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;

  @media (max-width: 768px) {
    grid-template-columns: 1fr;
  }
}

.status-card {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 20px;
  background: var(--panel-section-background-color-alt);
  border-radius: 12px;
  border: 2px solid transparent;
  transition: all 0.2s;

  &.online {
    border-color: color-mix(in srgb, var(--color-success) 30%, transparent);
    
    .status-card-icon {
      background: color-mix(in srgb, var(--color-success) 15%, transparent);
      color: var(--color-success);
    }
  }

  &.offline {
    border-color: color-mix(in srgb, var(--color-danger) 30%, transparent);
    
    .status-card-icon {
      background: color-mix(in srgb, var(--color-danger) 15%, transparent);
      color: var(--color-danger);
    }
  }

  &.domain-card {
    .status-card-content {
      position: relative;
      padding-right: 36px;
    }

    .copy-btn {
      position: absolute;
      right: 0;
      top: 50%;
      transform: translateY(-50%);
      background: transparent;
      border: 1px solid var(--panel-border-color);
      border-radius: 6px;
      padding: 6px;
      cursor: pointer;
      color: var(--panel-section-text-color);
      transition: all 0.2s;

      svg {
        width: 14px;
        height: 14px;
        display: block;
      }

      &:hover {
        background: var(--panel-border-color);
        color: var(--button-primary-background-color);
      }
    }
  }

  &.account-card {
    &.logged-in {
      .status-card-icon {
        background: color-mix(in srgb, var(--color-success) 15%, transparent);
        color: var(--color-success);
      }
    }

    .status-card-meta.status-badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 10px;
      font-size: 0.7rem;
      font-weight: 500;
      background: var(--color-success);
      color: white;

      &.pending_email_verification {
        background: var(--color-warning);
      }

      &.suspended {
        background: var(--color-danger);
      }
    }
  }

  .status-card-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-left: auto;
    flex-shrink: 0;

    .card-action-btn {
      background: transparent;
      border: 1px solid var(--panel-border-color);
      border-radius: 6px;
      padding: 8px;
      cursor: pointer;
      color: var(--panel-section-text-color);
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;

      svg {
        width: 16px;
        height: 16px;
        display: block;
      }

      &:hover:not(:disabled) {
        background: var(--panel-border-color);
        color: var(--button-primary-background-color);
      }

      &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }

      &.secondary {
        border-color: transparent;
        background: var(--panel-border-color);
      }
    }
  }

  .status-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--panel-border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: var(--button-primary-background-color);

    svg {
      width: 24px;
      height: 24px;
    }

    .spinner {
      animation: spin 1s linear infinite;
    }
  }

  .status-card-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    flex: 1;

    .status-card-label {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--panel-section-text-color);
      opacity: 0.6;
    }

    .status-card-value {
      font-size: 1rem;
      font-weight: 600;
      color: var(--panel-section-text-color);
      word-break: break-word;

      &.domain-link {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--button-primary-background-color);
        text-decoration: none;
        font-weight: 500;

        svg {
          width: 14px;
          height: 14px;
          flex-shrink: 0;
        }

        &:hover {
          text-decoration: underline;
        }
      }
    }

    .status-card-meta {
      font-size: 0.75rem;
      color: var(--panel-section-text-color);
      opacity: 0.6;
      width: 100px;

      &.healthy {
        color: var(--color-success);
        opacity: 1;
      }

      &.unhealthy {
        color: var(--color-danger);
        opacity: 1;
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

