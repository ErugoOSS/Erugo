<script setup>
import { ref, onMounted, defineExpose, inject, computed } from 'vue'
import { Check, X } from 'lucide-vue-next'
import { getEmailTemplates, updateEmailTemplates, getSettingById, saveSettingsById } from '../../api'

import { useToast } from 'vue-toastification'
import { mapSettings } from '../../utils'

import { useTranslate } from '@tolgee/vue'
import EmailTemplateEditor from '../emailTemplateEditor.vue'

const { t } = useTranslate()

const showHelpTip = inject('showHelpTip')

const toast = useToast()

const saving = ref(false)
const emailTemplates = ref([])
const originalTemplates = ref({}) // Store original state for dirty checking
const fallbackText = ref('')
const originalFallbackText = ref('')

const emit = defineEmits(['navItemClicked'])

onMounted(async () => {
  try {
    const templates = await getEmailTemplates()
    console.log(templates)
    templates.forEach((template) => {
      const templateData = {
        name: tidyTemplateName(template.name),
        id: template.id,
        content: template.content,
        variables: template.variables,
        subject: template.subject,
        requiredVariables: template.requiredVariables || []
      }
      emailTemplates.value.push(templateData)
      // Store original state for dirty checking
      originalTemplates.value[template.id] = JSON.stringify({
        subject: template.subject,
        variables: template.variables
      })
    })
  } catch (error) {
    console.error(error)
    toast.error(t.value('settings.emailTemplates.error_loading_templates'))
  }

  try {
    const text = await getSettingById('email_template_fallback_text')
    fallbackText.value = text.value
    originalFallbackText.value = text.value
  } catch (error) {
    console.error(error)
  }
})

const tidyTemplateName = (name) => {
  // First, remove 'Mail' or 'mail' at the end (case-insensitive)
  let result = name.replace(/[mM]ail$/, '')

  // Replace underscores with spaces
  result = result.replace(/_/g, ' ')

  // Insert spaces before capital letters in camelCase
  result = result.replace(/([a-z])([A-Z])/g, '$1 $2')

  // Capitalize first letter of each word
  result = result.replace(/\b\w/g, (char) => char.toUpperCase())

  return result
}

// Check if a template is dirty (has unsaved changes)
const isTemplateDirty = (template) => {
  const current = JSON.stringify({
    subject: template.subject,
    variables: template.variables
  })
  return current !== originalTemplates.value[template.id]
}

// Check if a specific variable is valid (non-empty string)
const isVariableValid = (template, variableName) => {
  if (variableName === 'subject') {
    return template.subject && template.subject.trim() !== ''
  }
  return template.variables[variableName] && template.variables[variableName].trim() !== ''
}

// Validate a single template - returns array of invalid required variable names
const getTemplateErrors = (template) => {
  const errors = []
  for (const varName of template.requiredVariables || []) {
    if (!isVariableValid(template, varName)) {
      errors.push(varName)
    }
  }
  return errors
}

// Check if template is valid (all required variables have values)
const isTemplateValid = (template) => {
  return getTemplateErrors(template).length === 0
}

const saveEmailTemplates = async () => {
  // Get dirty templates only
  const dirtyTemplates = emailTemplates.value.filter(isTemplateDirty)
  
  // Validate dirty templates before saving
  const invalidTemplates = []
  for (const template of dirtyTemplates) {
    const errors = getTemplateErrors(template)
    if (errors.length > 0) {
      invalidTemplates.push({ template, errors })
    }
  }
  
  if (invalidTemplates.length > 0) {
    // Show error for each invalid template
    for (const { template, errors } of invalidTemplates) {
      const errorFields = errors.join(', ')
      toast.error(t.value('settings.emailTemplates.validation_error', { 
        template: template.name, 
        fields: errorFields 
      }))
    }
    return
  }
  
  // Save dirty templates
  if (dirtyTemplates.length > 0) {
    try {
      await updateEmailTemplates(dirtyTemplates)
      // Update original state after successful save
      for (const template of dirtyTemplates) {
        originalTemplates.value[template.id] = JSON.stringify({
          subject: template.subject,
          variables: template.variables
        })
      }
      toast.success(t.value('settings.emailTemplates.success'))
    } catch (error) {
      console.error(error)
      toast.error(t.value('settings.emailTemplates.error_saving_templates'))
    }
  }
  
  // Save fallback text if changed
  if (fallbackText.value !== originalFallbackText.value) {
    try {
      await saveSettingsById({ email_template_fallback_text: fallbackText.value })
      originalFallbackText.value = fallbackText.value
    } catch (error) {
      console.error(error)
      toast.error(t.value('settings.emailTemplates.error_saving_fallback'))
    }
  }
  
  if (dirtyTemplates.length === 0 && fallbackText.value === originalFallbackText.value) {
    toast.info(t.value('settings.emailTemplates.no_changes'))
  }
}

const handleNavItemClicked = (item) => {
  emit('navItemClicked', item)
}

const updateTemplate = (event, template) => {
  emailTemplates.value = emailTemplates.value.map((t) => {
    if (t.id === template.id) {
      return template
    }
    return t
  })
}

// Format variable name for display
const formatVariableName = (name) => {
  return name.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

//define exposed methods
defineExpose({
  saveEmailTemplates
})
</script>
<template>
  <div class="container-fluid">
    <div class="row mb-5">
      <div class="col-2 d-none d-md-block">
        <ul class="settings-nav pt-5">
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('baseTemplate')">
              {{ $t('settings.emailTemplates.baseTemplate') }}
            </a>
          </li>
          <li v-for="template in emailTemplates" :key="template.id">
            <a href="#" @click.prevent="handleNavItemClicked(template.id)">
              {{ $t(template.name) }}
            </a>
          </li>
        </ul>
      </div>
      <div class="col-12 col-md-10 pt-5">
        <div class="row mb-5">
          <div class="col-12 col-md-8 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="baseTemplate">
              <div class="setting-group-header">
                <h3>
                  {{ $t('settings.emailTemplates.baseTemplate') }}
                </h3>
              </div>
              <div class="setting-group-body">
                <div class="setting-group-body-item">
                  <label for="baseTemplateFallbackText">{{ $t('settings.emailTemplates.sections.fallbackText') }}</label>
                  <input type="text" id="baseTemplateFallbackText" v-model="fallbackText" />
                </div>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{ $t('settings.emailTemplates.baseTemplate') }}</h6>
              <p>{{ $t('settings.emailTemplates.baseTemplateDescription') }}</p>
              <h6>{{ $t('settings.emailTemplates.sections.fallbackText') }}</h6>
              <p>{{ $t('settings.emailTemplates.sections.fallbackTextDescription') }}</p>
            </div>
          </div>
        </div>

        <div class="row mb-5" v-for="template in emailTemplates" :key="template.id">
          <div class="col-12 col-md-8 pe-0 ps-0 ps-md-3">
            <div class="setting-group" :id="template.id">
              <div class="setting-group-header">
                <h3>
                  {{ $t(template.name) }}
                </h3>
              </div>

              <div class="setting-group-body">
                <EmailTemplateEditor :model-value="template" @update:model-value="updateTemplate($event, template)" />
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6 class="mt-3">{{ $t('settings.emailTemplates.requiredFields') }}</h6>
              <ul class="required-variables-list">
                <li 
                  v-for="varName in template.requiredVariables" 
                  :key="varName"
                  :class="{ valid: isVariableValid(template, varName), invalid: !isVariableValid(template, varName) }"
                >
                  <Check v-if="isVariableValid(template, varName)" class="status-icon" />
                  <X v-else class="status-icon" />
                  {{ formatVariableName(varName) }}
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.auth-provider {
  margin-bottom: 8px;
  .icon {
    svg {
      width: 2rem;
      height: 2rem;
      stroke: url(#icon-gradient);
      &.custom {
        fill: url(#icon-gradient);
      }
    }
  }
}

.provider-type {
  background: var(--panel-section-background-color-alt);
  height: 80px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-radius: var(--panel-border-radius);

  h6 {
    cursor: pointer;
    margin-top: -4px;
  }

  small {
    font-size: 0.7rem;
    color: var(--panel-text-color-alt);
    margin-bottom: 0;
  }

  .checkbox-container {
    margin-top: 25px;
    label {
      margin-bottom: 0;
      font-weight: 400;
    }
  }

  &.open {
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
  }
}

.provider-settings {
  background: var(--panel-section-background-color-alt);
  border-radius: var(--panel-border-radius);
  border-top: 1px solid var(--input-border-color);
  border-top-left-radius: 0;
  border-top-right-radius: 0;
  padding: 1rem;
  padding-top: 0px;
  padding-bottom: 0px;
  margin-bottom: 0px;
  opacity: 0;
  max-height: 0;
  overflow: hidden;
  transition: all 0.5s ease;
  &.open {
    margin-bottom: 10px;
    opacity: 1;
    max-height: 800px;
    padding: 1rem;
  }
}

#gradientDefs {
  opacity: 0;
  position: absolute;
  top: 0;
  left: 0;
  width: 0;
  height: 0;
}

.input-group {
  position: relative;

  button {
    position: absolute;
    right: 5px;
    top: 5px;
    bottom: 0;
    height: 40px;
    width: 40px;
    border-radius: 100% !important;
    svg {
      margin-top: 1px;
    }
  }
}

.new-provider-form {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding-left: 15px;
  padding-right: 15px;
  select {
    opacity: 0;
    transition: all 0.3s ease;
    margin-top: 10px;
    width: calc(100% - 70px);
    pointer-events: none;
  }
  .new-provider-button {
    position: absolute;
    right: 50%;
    top: 50%;
    transform: translateX(50%) translateY(-50%);
    transition: all 0.3s ease;
    filter: grayscale(100%);
    opacity: 0.4;
  }
  &:hover {
    .new-provider-button {
      filter: grayscale(0%);
      opacity: 1;
    }
  }
  &.active {
    select {
      opacity: 1;
      pointer-events: auto;
    }
    .new-provider-button {
      left: unset;
      right: 12px;
      transform: translateX(0) translateY(-50%);
      filter: grayscale(0%);
      opacity: 1;
    }
  }
}

.delete-auth-provider {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-top: 10px;
  font-size: 0.8rem;
  color: var(--panel-text-color);
  &:hover {
    color: var(--color-danger);
  }
  svg {
    width: 15px;
    height: 15px;
    margin-top: -2px;
  }
}

.provider-info-link {
  display: flex;
  align-items: center;
  gap: 5px;
  svg {
    width: 15px;
    height: 15px;
    margin-top: -2px;
  }
}

.section-help {
  word-break: break-word;
}

.required-variables-list {
  list-style: none;
  padding: 0;
  margin: 0;

  li {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 0;
    font-size: 0.9rem;
    transition: color 0.2s ease;

    &.valid {
      color: var(--color-success, #22c55e);
    }

    &.invalid {
      color: var(--color-danger, #ef4444);
    }

    .status-icon {
      width: 14px;
      height: 14px;
      flex-shrink: 0;
    }
  }
}
</style>
