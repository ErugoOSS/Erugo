<script setup>

const props = defineProps({
  modelValue: {
    type: Object,
    required: true
  }
})

// Create a computed property for the template to support v-model
import { computed } from 'vue'

const template = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const emit = defineEmits(['update:template'])

</script>

<template>
  <div>

    <!-- Subject -->
    <div class="setting-group-body-item">
      <label :for="`subject-${template.id}`">{{ $t('settings.emailTemplates.sections.subject') }}</label>
      <input type="text" :id="`subject-${template.id}`" v-model="template.subject" @input="emit('update:template', template)" />
    </div>

    <!-- Header -->
    <div class="setting-group-body-item">
      <label :for="`header-${template.id}`">{{ $t('settings.emailTemplates.sections.header') }}</label>
      <input type="text" :id="`header-${template.id}`" v-model="template.variables.header" @input="emit('update:template', template)" />
    </div>
    <!-- Action Text -->
    <div class="setting-group-body-item mt-2">
      <label :for="`action-text-${template.id}`">{{ $t('settings.emailTemplates.sections.action_text') }}</label>
      <input type="text" :id="`action-text-${template.id}`" v-model="template.variables.action_text" @input="emit('update:template', template)" />
    </div>
    <!-- Action URL -->
    <div class="setting-group-body-item mt-2">
      <label :for="`action-url-${template.id}`">{{ $t('settings.emailTemplates.sections.action_url') }}</label>
      <input type="text" :id="`action-url-${template.id}`" v-model="template.variables.action_url" readonly/>
    </div>
     <!-- Content -->
     <div class="setting-group-body-item mt-2">
      <label :for="`content-${template.id}`">{{ $t('settings.emailTemplates.sections.content') }}</label>
      <textarea :id="`content-${template.id}`" v-model="template.variables.content" rows="10" @input="emit('update:template', template)"></textarea>
    </div>
    
  </div>
</template>
