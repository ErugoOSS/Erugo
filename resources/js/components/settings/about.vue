<script setup>
import { ref, onMounted, computed } from 'vue'
import { Info, ExternalLink } from 'lucide-vue-next'
import { domData } from '../../domData'

const version = ref('unknown')

onMounted(() => {
  const data = domData()
  version.value = data.version || 'unknown'
})

const erugoWebsiteUrl = computed(() => {
  const baseUrl = 'https://erugo.app'
  if (version.value && version.value !== 'unknown') {
    return `${baseUrl}?version=${encodeURIComponent(version.value)}`
  }
  return baseUrl
})

const emit = defineEmits(['navItemClicked'])
const handleNavItemClicked = (item) => {
  emit('navItemClicked', item)
}
</script>

<template>
  <div class="container-fluid">
    <div class="row mb-5">
      <div class="col-2 d-none d-md-block">
        <ul class="settings-nav pt-5">
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('about_erugo')">
              <Info />
              {{ $t('settings.about.title') }}
            </a>
          </li>
        </ul>
      </div>
      <div class="col-12 col-md-8 pt-5">
        <div class="row mb-5">
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="about_erugo">
              <div class="setting-group-header">
                <h3>
                  <Info />
                  {{ $t('settings.about.title') }}
                </h3>
              </div>

              <div class="setting-group-body">
                <div class="about-info">
                  <div class="version-display">
                    <span class="version-label">{{ $t('settings.about.version') }}</span>
                    <span class="version-value">{{ version }}</span>
                  </div>
                  
                  <div class="website-link">
                    <a :href="erugoWebsiteUrl" target="_blank" rel="noopener noreferrer" class="erugo-link">
                      <ExternalLink />
                      {{ $t('settings.about.visit_website') }}
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{ $t('settings.about.title') }}</h6>
              <p>{{ $t('settings.about.description') }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.about-info {
  padding: 10px 0;
}

.version-display {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding: 16px;
  background: var(--panel-section-background-color-alt);
  border-radius: var(--panel-border-radius);
  
  .version-label {
    font-weight: 600;
    color: var(--panel-text-color);
  }
  
  .version-value {
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
    font-size: 1.1rem;
    color: var(--link-color);
    background: var(--panel-section-background-color);
    padding: 4px 12px;
    border-radius: 4px;
  }
}

.website-link {
  .erugo-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--link-color);
    text-decoration: none;
    font-weight: 500;
    padding: 12px 20px;
    background: var(--secondary-button-background-color);
    border-radius: var(--button-border-radius);
    transition: all 0.2s ease;
    
    svg {
      width: 18px;
      height: 18px;
    }
    
    &:hover {
      background: var(--secondary-button-background-color-hover);
      color: var(--link-color-hover);
      transform: translateY(-1px);
    }
  }
}
</style>

