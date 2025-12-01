<script setup>
import {
  Server,
  Loader2,
  CheckCircle,
  XCircle,
  RefreshCw,
  AlertTriangle
} from 'lucide-vue-next'

const props = defineProps({
  instanceForm: Object,
  loading: Boolean,
  checkingSubdomain: Boolean,
  subdomainAvailable: Boolean,
  subdomainOwnedByUser: Boolean,
  subdomainSuggestions: Array
})

const emit = defineEmits(['submit', 'checkSubdomain', 'selectSuggestion', 'subdomainInput'])
</script>

<template>
  <div>
    <p>{{ $t('cloudConnect.instance.description') }}</p>
    <form @submit.prevent="emit('submit')" class="instance-form">
      <div class="form-group">
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
      <div class="form-group">
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
        <div v-if="subdomainOwnedByUser" class="subdomain-owned-notice">
          <AlertTriangle />
          <span>{{ $t('cloudConnect.subdomainOwnedByYou') }}</span>
        </div>
        <div
          v-else-if="subdomainAvailable === false && subdomainSuggestions.length > 0"
          class="subdomain-suggestions"
        >
          <span>{{ $t('cloudConnect.instance.suggestions') }}:</span>
          <button
            v-for="suggestion in subdomainSuggestions"
            :key="suggestion"
            type="button"
            @click="emit('selectSuggestion', suggestion)"
            class="suggestion-btn"
          >
            {{ suggestion }}
          </button>
        </div>
      </div>
      <button type="submit" :disabled="loading || !subdomainAvailable">
        <Loader2 v-if="loading" class="spinner" />
        <Server v-else />
        {{ $t('cloudConnect.instance.create') }}
      </button>
    </form>
  </div>
</template>

<style lang="scss" scoped>
.instance-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;

  label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--panel-section-text-color);
  }

  input {
    padding: 12px 16px;
    border: 1px solid var(--panel-border-color);
    border-radius: 8px;
    background: var(--panel-section-background-color-alt);
    color: var(--panel-section-text-color);
    font-size: 1rem;

    &:focus {
      outline: none;
      border-color: var(--button-primary-background-color);
    }
  }
}

.subdomain-input {
  display: flex;
  align-items: center;
  gap: 4px;
  position: relative;

  input {
    flex: 1;
    padding-right: 140px;
  }

  .subdomain-suffix {
    position: absolute;
    right: 40px;
    color: var(--panel-section-text-color);
    opacity: 0.6;
    font-size: 0.875rem;
  }

  .subdomain-status {
    position: absolute;
    right: 12px;

    svg {
      width: 20px;
      height: 20px;
    }

    &.checking svg {
      animation: spin 1s linear infinite;
    }

    &.available svg {
      color: var(--color-success);
    }

    &.owned svg {
      color: var(--color-warning);
    }

    &.unavailable svg {
      color: var(--color-danger);
    }
  }
}

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

    &:hover {
      background: var(--button-primary-background-color);
      color: var(--button-primary-text-color);
    }
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

