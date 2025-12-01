<script setup>
import { onMounted, watch } from 'vue'
import {
  Cloud,
  CloudOff,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Loader2,
  ExternalLink,
  RefreshCw,
  CreditCard,
  Server,
  Wifi,
  WifiOff,
  Mail,
  BarChart3
} from 'lucide-vue-next'

// Composables
import { useErugoAuth } from './composables/useErugoAuth'
import { useErugoStatus } from './composables/useErugoStatus'
import { useErugoSubscription } from './composables/useErugoSubscription'
import { useErugoInstances } from './composables/useErugoInstances'
import { useErugoUsage } from './composables/useErugoUsage'

// Components
import StatusGrid from './components/StatusGrid.vue'
import UsageDashboard from './components/UsageDashboard.vue'
import PlanSelector from './components/PlanSelector.vue'
import InstancesList from './components/InstancesList.vue'
import InstanceForm from './components/InstanceForm.vue'

// Modals
import LoginModal from './components/modals/LoginModal.vue'
import RegisterModal from './components/modals/RegisterModal.vue'
import ForgotPasswordModal from './components/modals/ForgotPasswordModal.vue'
import EditInstanceModal from './components/modals/EditInstanceModal.vue'
import DeleteInstanceModal from './components/modals/DeleteInstanceModal.vue'
import RegenerateTokenModal from './components/modals/RegenerateTokenModal.vue'
import TokenDisplayModal from './components/modals/TokenDisplayModal.vue'
import ReclaimInstanceModal from './components/modals/ReclaimInstanceModal.vue'

const emit = defineEmits(['loginStateChanged', 'connectionStateChanged', 'connectingStateChanged', 'navItemClicked'])

// Initialize composables with callbacks
const {
  loading: statusLoading,
  status,
  currentStep,
  connecting,
  disconnecting,
  isConnected,
  isReconnecting,
  lastHeartbeatFormatted,
  heartbeatHealthy,
  needsEmailVerification,
  hasActiveSubscription,
  canConnect,
  loadStatus,
  refreshStatus,
  handleConnect,
  handleDisconnect,
  checkVerificationStatus,
  resendVerificationEmail
} = useErugoStatus({
  onStatusLoaded: () => {
    // Load additional data when status is loaded and user is logged in
    if (status.value?.is_logged_in) {
      loadUsage()
      loadInstances()
    }
  },
  onHasSubscription: () => {
    loadPlans()
  }
})

const {
  loginForm,
  registerForm,
  showLoginForm,
  showRegisterForm,
  showForgotPasswordForm,
  forgotPasswordEmail,
  sendingForgotPassword,
  authLoading,
  handleLogin,
  handleRegister,
  handleLogout,
  openLoginForm,
  openRegisterForm,
  closeAuthForms,
  openForgotPasswordForm,
  closeForgotPasswordForm,
  handleForgotPassword,
  loginFormClickOutside,
  registerFormClickOutside
} = useErugoAuth(loadStatus)

const {
  plans,
  selectedPlan,
  loadingPlans,
  pollingSubscription,
  showPlanManagement,
  loadingBillingPortal,
  currentPlan,
  loadPlans,
  handleCheckout,
  handleChangePlan,
  stopPolling,
  openPlanManagement,
  closePlanManagement,
  openBillingPortal
} = useErugoSubscription(status, loadStatus)

const {
  instances,
  loadingInstances,
  selectedInstance,
  instanceForm,
  editInstanceForm,
  showEditInstanceForm,
  editingInstanceSubdomain,
  showDeleteConfirm,
  showRegenerateTokenConfirm,
  regeneratedToken,
  showRegeneratedToken,
  checkingSubdomain,
  subdomainAvailable,
  subdomainSuggestions,
  subdomainOwnedByUser,
  existingInstanceName,
  showReclaimConfirm,
  instanceLoading,
  loadInstances,
  handleCheckSubdomain,
  selectSuggestion,
  handleCreateInstance,
  handleConfirmReclaim,
  handleCancelReclaim,
  openEditInstanceForm,
  closeEditInstanceForm,
  handleUpdateInstance,
  openDeleteConfirm,
  closeDeleteConfirm,
  handleDeleteInstance,
  openRegenerateTokenConfirm,
  closeRegenerateTokenConfirm,
  handleRegenerateToken,
  closeRegeneratedTokenModal,
  copyToken,
  getInstanceStatusClass,
  resetSubdomainState
} = useErugoInstances(async () => {
  await loadStatus(true)
})

const {
  usageData,
  loadingUsage,
  loadUsage,
  formatBytes,
  formatDate,
  clearUsage
} = useErugoUsage()

// Computed loading state
const loading = statusLoading

// Watch for login state changes and emit to parent
watch(
  () => status.value?.is_logged_in,
  (isLoggedIn) => {
    emit('loginStateChanged', !!isLoggedIn)
    if (!isLoggedIn) {
      clearUsage()
    }
  },
  { immediate: true }
)

// Watch for connection state changes and emit to parent
watch(
  isConnected,
  (connected) => {
    emit('connectionStateChanged', connected)
  },
  { immediate: true }
)

// Watch for connecting state changes and emit to parent
watch(
  connecting,
  (isConnecting) => {
    emit('connectingStateChanged', isConnecting)
  },
  { immediate: true }
)

// Lifecycle
onMounted(async () => {
  await loadStatus()
})

// Navigation handler
const handleNavItemClicked = (item) => {
  emit('navItemClicked', item)
}

// Handle forgot password from login modal
const handleOpenForgotPassword = () => {
  openForgotPasswordForm(status.value?.user_email || loginForm.value.email || '')
}

// Expose methods for parent component
defineExpose({
  refreshStatus,
  handleLogout,
  handleConnect,
  handleDisconnect
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
              <div
                class="capability-item"
                :class="{ success: status?.capabilities?.has_wg_tools, error: !status?.capabilities?.has_wg_tools }"
              >
                <component :is="status?.capabilities?.has_wg_tools ? CheckCircle : XCircle" />
                <span>{{ $t('cloudConnect.capabilities.wireguardTools') }}</span>
              </div>
              <div
                class="capability-item"
                :class="{ success: status?.capabilities?.has_tun_device, error: !status?.capabilities?.has_tun_device }"
              >
                <component :is="status?.capabilities?.has_tun_device ? CheckCircle : XCircle" />
                <span>{{ $t('cloudConnect.capabilities.tunDevice') }}</span>
              </div>
              <div
                class="capability-item"
                :class="{ success: status?.capabilities?.has_net_admin, error: !status?.capabilities?.has_net_admin }"
              >
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
          <li>
            <a href="#" @click.prevent="openBillingPortal" :disabled="loadingBillingPortal">
              <Loader2 v-if="loadingBillingPortal" class="spinner" />
              <ExternalLink v-else />
              {{ $t('cloudConnect.billing.openPortal') || 'Billing Portal' }}
            </a>
          </li>
        </ul>
      </div>

      <!-- Main Content -->
      <div class="col-12 col-md-10 pt-5">
        <!-- Combined Status & Connection Section -->
        <div class="row mb-4">
          <div class="col-12">
            <div class="setting-group" id="status-section">
              <div
                class="setting-group-header"
                :class="{ success: isConnected && heartbeatHealthy, warning: isConnected && !heartbeatHealthy }"
              >
                <h3>
                  <component :is="isConnected ? (heartbeatHealthy ? CheckCircle : AlertTriangle) : CloudOff" />
                  {{ $t('cloudConnect.nav.status') }}
                </h3>
              </div>
              <div class="setting-group-body">
                <StatusGrid
                  :status="status"
                  :isConnected="isConnected"
                  :isReconnecting="isReconnecting"
                  :heartbeatHealthy="heartbeatHealthy"
                  :lastHeartbeatFormatted="lastHeartbeatFormatted"
                  :currentPlan="currentPlan"
                  :loading="authLoading"
                  @login="openLoginForm"
                  @logout="handleLogout"
                  @register="openRegisterForm"
                />
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
                <UsageDashboard
                  :usageData="usageData"
                  :loading="loadingUsage"
                  :formatBytes="formatBytes"
                  :formatDate="formatDate"
                  @refresh="loadUsage"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Email Verification Section (only shown when verification is needed) -->
        <div v-if="needsEmailVerification" class="row mb-4">
          <div class="col-12">
            <div class="setting-group" id="verification-section">
              <div class="setting-group-header warning">
                <h3>
                  <Mail />
                  {{ $t('cloudConnect.emailVerification.title') || 'Email Verification Required' }}
                </h3>
              </div>
              <div class="setting-group-body">
                <div class="verification-message">
                  <p>{{ $t('cloudConnect.emailVerification.description') }}</p>
                  <p class="email-sent-to">
                    {{ $t('cloudConnect.emailVerification.sentTo') }}:
                    <strong>{{ status?.user_email }}</strong>
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
                  <button @click="checkVerificationStatus" :disabled="loading">
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

                <PlanSelector
                  :plans="plans"
                  :selectedPlan="selectedPlan"
                  :currentSubscriptionPlan="status?.subscription_plan"
                  :loading="loading"
                  :loadingPlans="loadingPlans"
                  :pollingSubscription="pollingSubscription"
                  :hasActiveSubscription="hasActiveSubscription"
                  :currentPlan="currentPlan"
                  @update:selectedPlan="selectedPlan = $event"
                  @checkout="handleCheckout"
                  @stopPolling="stopPolling"
                />

                <!-- Billing Portal Button -->
                <div v-if="hasActiveSubscription && currentPlan" class="billing-portal-section">
                  <hr class="section-divider" />
                  <p class="billing-portal-description">
                    {{
                      $t('cloudConnect.billing.portalDescription') ||
                      'Manage your subscription, update payment methods, or view invoices in the billing portal.'
                    }}
                  </p>
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
                <InstancesList
                  :instances="instances"
                  :currentInstanceId="status?.instance_id"
                  :loading="loadingInstances"
                  :formatBytes="formatBytes"
                  :formatDate="formatDate"
                  :getInstanceStatusClass="getInstanceStatusClass"
                  @edit="openEditInstanceForm"
                  @regenerateToken="openRegenerateTokenConfirm"
                  @delete="openDeleteConfirm"
                >
                  <template #empty>
                    <InstanceForm
                      :instanceForm="instanceForm"
                      :loading="instanceLoading"
                      :checkingSubdomain="checkingSubdomain"
                      :subdomainAvailable="subdomainAvailable"
                      :subdomainOwnedByUser="subdomainOwnedByUser"
                      :subdomainSuggestions="subdomainSuggestions"
                      @submit="handleCreateInstance"
                      @checkSubdomain="handleCheckSubdomain"
                      @selectSuggestion="selectSuggestion"
                      @subdomainInput="resetSubdomainState"
                    />
                  </template>
                </InstancesList>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Auth Modals -->
  <LoginModal
    :show="showLoginForm"
    :loading="authLoading"
    :loginForm="loginForm"
    @update:show="showLoginForm = $event"
    @submit="handleLogin"
    @switchToRegister="openRegisterForm"
    @forgotPassword="handleOpenForgotPassword"
    @clickOutside="showLoginForm = false"
  />

  <RegisterModal
    :show="showRegisterForm"
    :loading="authLoading"
    :registerForm="registerForm"
    @update:show="showRegisterForm = $event"
    @submit="handleRegister"
    @switchToLogin="openLoginForm"
    @clickOutside="showRegisterForm = false"
  />

  <ForgotPasswordModal
    :show="showForgotPasswordForm"
    :loading="sendingForgotPassword"
    :email="forgotPasswordEmail"
    @update:show="showForgotPasswordForm = $event"
    @update:email="forgotPasswordEmail = $event"
    @submit="handleForgotPassword"
  />

  <!-- Instance Modals -->
  <EditInstanceModal
    :show="showEditInstanceForm"
    :loading="instanceLoading"
    :editForm="editInstanceForm"
    :editingSubdomain="editingInstanceSubdomain"
    @update:show="closeEditInstanceForm"
    @update:editingSubdomain="editingInstanceSubdomain = $event"
    @submit="handleUpdateInstance"
  />

  <DeleteInstanceModal
    :show="showDeleteConfirm"
    :loading="instanceLoading"
    :instance="selectedInstance"
    @update:show="closeDeleteConfirm"
    @confirm="handleDeleteInstance"
  />

  <RegenerateTokenModal
    :show="showRegenerateTokenConfirm"
    :loading="instanceLoading"
    :instance="selectedInstance"
    @update:show="closeRegenerateTokenConfirm"
    @confirm="handleRegenerateToken"
  />

  <TokenDisplayModal
    :show="showRegeneratedToken"
    :token="regeneratedToken"
    @update:show="closeRegeneratedTokenModal"
    @copyToken="copyToken"
  />

  <ReclaimInstanceModal
    :show="showReclaimConfirm"
    :loading="instanceLoading"
    :instanceName="existingInstanceName"
    :subdomain="instanceForm.subdomain"
    @update:show="showReclaimConfirm = $event"
    @confirm="handleConfirmReclaim"
    @cancel="handleCancelReclaim"
  />
</template>

<style lang="scss" scoped>
// Header color modifiers
.setting-group-header {
  &.error h3 svg {
    color: var(--color-danger);
  }

  &.success h3 svg {
    color: var(--color-success);
  }

  &.warning h3 svg {
    color: var(--color-warning);
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
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
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
    color: var(--color-success);
  }

  &.error svg {
    color: var(--color-danger);
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

.verification-message {
  background: color-mix(in srgb, var(--color-warning) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-warning) 30%, transparent);
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

.button-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

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

.spinner {
  animation: spin 1s linear infinite;
}
</style>

