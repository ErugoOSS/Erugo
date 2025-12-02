<script setup>
import { Trash2, Loader2, CircleX, AlertTriangle, Ban } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  instance: Object,
  currentInstanceId: [String, Number]
})

const emit = defineEmits(['update:show', 'confirm'])

const isCurrentInstance = computed(() => {
  return props.instance && props.instance.id === props.currentInstanceId
})

// Check if user is accessing via the tunnel domain
const isAccessingViaTunnel = computed(() => {
  if (!props.instance) return false
  const currentHost = window.location.hostname
  const instanceDomain = props.instance.full_domain || `${props.instance.subdomain}.erugo.cloud`
  return currentHost === instanceDomain
})

// Can only delete if not accessing via the tunnel domain
const canDelete = computed(() => {
  return !isAccessingViaTunnel.value
})
</script>

<template>
  <div class="auth-form-overlay" :class="{ active: show }" @click.self="emit('update:show', false)">
    <div class="auth-slide-form danger-dialog">
     
      
      <!-- Block deletion if accessing via tunnel domain -->
      <div v-if="isAccessingViaTunnel" class="tunnel-access-blocked">
        <Ban />
        <div>
          <strong>{{ $t('cloudConnect.instances.deleteBlockedTitle') || 'Cannot delete while using this domain' }}</strong>
          <p>
            {{
              $t('cloudConnect.instances.deleteBlockedWarning') ||
              'You are currently accessing Erugo via this instance\'s domain. Deleting it would lock you out. Please access Erugo via a different URL (e.g., localhost or direct IP) to delete this instance.'
            }}
          </p>
        </div>
      </div>
      
      <!-- Special warning for current instance (but not accessing via tunnel) -->
      <div v-else-if="isCurrentInstance" class="current-instance-warning">

        <div>
          <strong>{{ $t('cloudConnect.instances.deleteCurrentTitle') || 'This is your active instance!' }}</strong>
          <p>
            {{
              $t('cloudConnect.instances.deleteCurrentWarning') ||
              'Deleting this instance will disconnect your domain and disable Cloud Connect. You will need to create or link a new instance to reconnect.'
            }}
          </p>
        </div>
      </div>
      
     
      
      <div v-if="instance" class="instance-to-delete">
        <strong>{{ instance.name }}</strong>
        <span>{{ instance.full_domain || `${instance.subdomain}.erugo.cloud` }}</span>
      </div>
      <div class="button-bar">
        <button 
          v-if="canDelete"
          type="button" 
          class="danger" 
          @click="emit('confirm')" 
          :disabled="loading"
        >
          <Loader2 v-if="loading" class="spinner" />
          <Trash2 v-else />
          {{ isCurrentInstance 
            ? ($t('cloudConnect.instances.confirmDeleteCurrent') || 'Disconnect & Delete') 
            : ($t('cloudConnect.instances.confirmDelete') || 'Delete Instance') 
          }}
        </button>
        <button type="button" class="secondary close-button" @click="emit('update:show', false)">
          <CircleX />
          {{ $t('settings.close') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import './modalStyles.scss';

.tunnel-access-blocked,
.current-instance-warning {
  display: flex;
  gap: 12px;

  > svg {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
  }

  > div {
    flex: 1;

    strong {
      display: block;
      margin-bottom: 4px;
    }

    
  }
}

.tunnel-access-blocked {
  background: color-mix(in srgb, var(--color-danger, #ef4444) 15%, transparent);
  border: 1px solid var(--color-danger, #ef4444);

  > svg {
    color: var(--color-danger, #ef4444);
  }

  > div strong {
    color: var(--color-danger, #ef4444);
  }
}

.current-instance-warning {


  > svg {
    color: var(--color-warning, #f59e0b);
  }

  > div strong {

  }
}
</style>

