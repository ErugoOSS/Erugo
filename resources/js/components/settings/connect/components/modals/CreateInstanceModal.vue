<script setup>
import {
  Server,
  Loader2,
  CheckCircle,
  XCircle,
  RefreshCw,
  AlertTriangle,
  CircleX
} from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  instanceForm: Object,
  checkingSubdomain: Boolean,
  subdomainAvailable: Boolean,
  subdomainOwnedByUser: Boolean,
  subdomainSuggestions: Array
})

const emit = defineEmits(['update:show', 'submit', 'checkSubdomain', 'selectSuggestion', 'subdomainInput', 'clickOutside'])

const handleClickOutside = (event) => {
  if (!event.target.closest('.auth-slide-form')) {
    emit('clickOutside')
  }
}
</script>

<template>
  <div class="auth-form-overlay" :class="{ active: show }" @click="handleClickOutside">
    <div class="auth-slide-form">
      <h2>
        <Server />
        {{ $t('cloudConnect.instance.createTitle') }}
      </h2>
      <p>{{ $t('cloudConnect.instance.description') }}</p>
      <form @submit.prevent="emit('submit')">
        <div class="input-container">
          <label for="instance-name">{{ $t('cloudConnect.instance.name') }}</label>
          <input
            type="text"
            id="instance-name"
            :value="instanceForm.name"
            @input="instanceForm.name = $event.target.value"
            required
            :placeholder="$t('cloudConnect.instance.namePlaceholder')"
          />
        </div>
        <div class="input-container">
          <label for="instance-subdomain">{{ $t('cloudConnect.instance.subdomain') }}</label>
          <div class="subdomain-input">
            <input
              type="text"
              id="instance-subdomain"
              :value="instanceForm.subdomain"
              @input="instanceForm.subdomain = $event.target.value; emit('subdomainInput')"
              required
              pattern="^[a-z0-9][a-z0-9-]*[a-z0-9]$"
              minlength="3"
              maxlength="63"
              :placeholder="$t('cloudConnect.instance.subdomainPlaceholder')"
              @blur="emit('checkSubdomain')"
            />
            <span class="subdomain-suffix">.erugo.cloud</span>
            <span v-if="checkingSubdomain" class="subdomain-status checking">
              <Loader2 class="spinner" />
            </span>
            <span
              v-else-if="subdomainAvailable === true && !subdomainOwnedByUser"
              class="subdomain-status available"
            >
              <CheckCircle />
            </span>
            <span v-else-if="subdomainOwnedByUser" class="subdomain-status owned">
              <RefreshCw />
            </span>
            <span v-else-if="subdomainAvailable === false" class="subdomain-status unavailable">
              <XCircle />
            </span>
          </div>
          <p class="help-text">{{ $t('cloudConnect.instance.subdomainHelp') }}</p>
          <div v-if="subdomainOwnedByUser" class="subdomain-owned-notice">
            <AlertTriangle />
            <span>{{ $t('cloudConnect.subdomainOwnedByYou') }}</span>
          </div>
          
        </div>
        <div class="button-bar">
          <button type="submit" :disabled="loading || !subdomainAvailable">
            <Loader2 v-if="loading" class="spinner" />
            <Server v-else />
            {{ $t('cloudConnect.instance.create') }}
          </button>
          <button type="button" class="secondary close-button" @click="emit('update:show', false)">
            <CircleX />
            {{ $t('settings.close') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import './modalStyles.scss';

.subdomain-owned-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  padding: 8px 12px;
  background: color-mix(in srgb, var(--color-warning) 15%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-warning) 30%, transparent);
  border-radius: 6px;
  font-size: 0.875rem;
  color: var(--color-warning);

  svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
  }
}

.subdomain-suggestions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
  font-size: 0.875rem;
  color: var(--panel-section-text-color);

  .suggestion-btn {
    padding: 4px 12px;
    background: var(--panel-section-background-color-alt);
    border: 1px solid var(--panel-border-color);
    border-radius: 16px;
    color: var(--button-primary-background-color);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
    width: auto;

    &:hover {
      background: var(--button-primary-background-color);
      color: var(--button-primary-text-color);
    }
  }
}
</style>

