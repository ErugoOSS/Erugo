<script setup>
import { Pencil, Loader2, CircleX, Check } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  editForm: Object,
  editingSubdomain: Boolean
})

const emit = defineEmits(['update:show', 'update:editingSubdomain', 'submit'])
</script>

<template>
  <div class="auth-form-overlay" :class="{ active: show }" @click.self="emit('update:show', false)">
    <div class="auth-slide-form">
      <h2>
        <Pencil />
        {{ $t('cloudConnect.instances.editTitle') || 'Edit Instance' }}
      </h2>
      <form @submit.prevent="emit('submit')">
        <div class="input-container">
          <label for="edit-instance-name">{{ $t('cloudConnect.instance.name') }}</label>
          <input
            type="text"
            id="edit-instance-name"
            :value="editForm.name"
            @input="editForm.name = $event.target.value"
            required
            :placeholder="$t('cloudConnect.instance.namePlaceholder')"
          />
        </div>
        <div class="input-container">
          <label for="edit-instance-subdomain">
            {{ $t('cloudConnect.instance.subdomain') }}
            <button type="button" class="btn-text-inline" @click="emit('update:editingSubdomain', !editingSubdomain)">
              {{
                editingSubdomain
                  ? $t('cloudConnect.instances.cancelSubdomainEdit') || 'Cancel'
                  : $t('cloudConnect.instances.editSubdomain') || 'Change'
              }}
            </button>
          </label>
          <div class="subdomain-input">
            <input
              type="text"
              id="edit-instance-subdomain"
              :value="editForm.subdomain"
              @input="editForm.subdomain = $event.target.value"
              :disabled="!editingSubdomain"
              pattern="^[a-z0-9][a-z0-9-]*[a-z0-9]$"
              minlength="3"
              maxlength="63"
            />
            <span class="subdomain-suffix">.erugo.cloud</span>
          </div>
          <p v-if="editingSubdomain" class="warning-text">
            {{
              $t('cloudConnect.instances.subdomainWarning') ||
              'Changing the subdomain will change your instance URL. You may need to reconnect.'
            }}
          </p>
        </div>
        <div class="button-bar">
          <button type="submit" :disabled="loading">
            <Loader2 v-if="loading" class="spinner" />
            <Check v-else />
            {{ $t('cloudConnect.instances.save') || 'Save Changes' }}
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
</style>

