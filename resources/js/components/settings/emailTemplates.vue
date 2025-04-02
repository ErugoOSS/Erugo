<script setup>
import { ref, onMounted, defineExpose, inject } from 'vue'
// import {} from 'lucide-vue-next'
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
const fallbackText = ref('aaa')

const emit = defineEmits(['navItemClicked'])

onMounted(async () => {
  try {
    const templates = await getEmailTemplates()
    console.log(templates)
    templates.forEach((template) => {
      emailTemplates.value.push({
        name: tidyTemplateName(template.name),
        id: template.name,
        content: template.content,
        variables: template.variables,
        subject: template.subject
      })
    })
  } catch (error) {
    console.error(error)
    toast.error(t.value('settings.emailTemplates.error_loading_templates'))
  }

  try {
    const text = await getSettingById('email_template_fallback_text')
    fallbackText.value = text.value
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

const saveEmailTemplates = async () => {
  try {
    await updateEmailTemplates(emailTemplates.value)
    await saveSettingsById({ email_template_fallback_text: fallbackText.value })
    toast.success(t.value('settings.emailTemplates.success'))
  } catch (error) {
    console.error(error)
    toast.error(t.value('settings.emailTemplates.error_saving_templates'))
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
              <h6>{{ $t(template.name) }}</h6>
              <p>{{ $t('settings.emailTemplates.' + template.id + 'Description') }}</p>
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
    color: var(--danger-color);
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
</style>
