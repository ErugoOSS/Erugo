<script setup>
import { ref, onMounted, computed } from 'vue'
import { Info, ExternalLink, Server, Database, HardDrive, Loader2 } from 'lucide-vue-next'
import { domData } from '../../domData'
import { getSystemInfo } from '../../api'

const version = ref('unknown')
const systemInfo = ref(null)
const loading = ref(true)

onMounted(async () => {
  const data = domData()
  version.value = data.version || 'unknown'
  
  try {
    systemInfo.value = await getSystemInfo()
  } catch (error) {
    console.error('Failed to load system info:', error)
  } finally {
    loading.value = false
  }
})

const erugoWebsiteUrl = computed(() => {
  const baseUrl = 'https://erugo.app'
  if (version.value && version.value !== 'unknown') {
    return `${baseUrl}?version=${encodeURIComponent(version.value)}`
  }
  return baseUrl
})

const databaseDisplayName = computed(() => {
  if (!systemInfo.value) return ''
  const driver = systemInfo.value.database_driver
  const names = {
    sqlite: 'SQLite',
    mysql: 'MySQL',
    pgsql: 'PostgreSQL',
    sqlsrv: 'SQL Server'
  }
  return names[driver] || driver
})

const emit = defineEmits(['navItemClicked'])
const handleNavItemClicked = (item) => {
  emit('navItemClicked', item)
}

const OpenExternalLink = (url) => {
  window.open(url, '_blank')
}
</script>

<template>
  <div class="container-fluid">
    <div class="row mb-5">
     
      <div class="col-12 col-md-12 pt-5">
        <div class="row mb-5">
          <div class="col-12 col-md-8 offset-md-2 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="about_erugo">
              <div class="setting-group-body">
                <p>{{ $t('settings.about.intro') }}</p>
                <p>{{ $t('settings.about.created_by') }} <a href="https://github.com/deanward" target="_blank" rel="noopener noreferrer">Dean Ward</a>. {{ $t('settings.about.support_cta') }} <a href="https://ko-fi.com/deanward" target="_blank" rel="noopener noreferrer">{{ $t('settings.about.support_link') }}</a>.</p>
                
                <div class="about-info">
                  <div class="version-display">
                    <span class="version-label">{{ $t('settings.about.version') }}</span>
                    <span class="version-value">{{ version }}</span>
                  </div>

                  <div class="license-display">
                    <span class="license-label">{{ $t('settings.about.license') }}</span>
                    <a href="https://github.com/DeanWard/erugo/blob/main/LICENSE" target="_blank" rel="noopener noreferrer" class="license-value">MIT</a>
                  </div>

                  <div class="button-row">
                    <button type="button" @click="OpenExternalLink(erugoWebsiteUrl)">
                      <ExternalLink />
                      {{ $t('settings.about.visit_website') }}
                    </button>
                    <button type="button" @click="OpenExternalLink('https://github.com/DeanWard/erugo')">
                      <ExternalLink />
                      {{ $t('settings.about.visit_github') }}
                    </button>
                    <button type="button" @click="OpenExternalLink('https://erugo.app/docs/')">
                      <ExternalLink />
                      {{ $t('settings.about.documentation') }}
                    </button>
                    <button type="button" @click="OpenExternalLink('https://discord.gg/M74X2wmqY8')">
                      <ExternalLink />
                      {{ $t('settings.about.discord') }}
                    </button>
                    <button type="button" class="donate-button" @click="OpenExternalLink('https://ko-fi.com/deanward')">
                      <ExternalLink />
                      {{ $t('settings.about.donate') }}
                    </button>
                  </div>
                </div>

                <div class="credits-section">
                  <h4>{{ $t('settings.about.built_with') }}</h4>
                  <div class="credits-list">
                    <a href="https://laravel.com" target="_blank" rel="noopener noreferrer" class="credit-item">
                      <span class="credit-name">Laravel</span>
                      <span class="credit-desc">{{ $t('settings.about.credits.laravel') }}</span>
                    </a>
                    <a href="https://vuejs.org" target="_blank" rel="noopener noreferrer" class="credit-item">
                      <span class="credit-name">Vue.js</span>
                      <span class="credit-desc">{{ $t('settings.about.credits.vue') }}</span>
                    </a>
                    <a href="https://tus.io" target="_blank" rel="noopener noreferrer" class="credit-item">
                      <span class="credit-name">tus</span>
                      <span class="credit-desc">{{ $t('settings.about.credits.tus') }}</span>
                    </a>
                  </div>
                </div>

                <div class="system-info-section">
                  <h4>{{ $t('settings.about.system_info') }}</h4>
                  
                  <div v-if="loading" class="loading-state">
                    <Loader2 class="spinner" />
                    {{ $t('settings.about.loading') }}
                  </div>
                  
                  <div v-else-if="systemInfo" class="system-info-grid">
                    <div class="info-item">
                      <Server class="info-icon" />
                      <div class="info-content">
                        <span class="info-label">PHP</span>
                        <span class="info-value">{{ systemInfo.php_version }}</span>
                      </div>
                    </div>
                    
                    <div class="info-item">
                      <Server class="info-icon" />
                      <div class="info-content">
                        <span class="info-label">Laravel</span>
                        <span class="info-value">{{ systemInfo.laravel_version }}</span>
                      </div>
                    </div>
                    
                    <div class="info-item">
                      <Database class="info-icon" />
                      <div class="info-content">
                        <span class="info-label">{{ $t('settings.about.database') }}</span>
                        <span class="info-value">{{ databaseDisplayName }} {{ systemInfo.database_version }}</span>
                      </div>
                    </div>
                    
                    <div class="info-item">
                      <HardDrive class="info-icon" />
                      <div class="info-content">
                        <span class="info-label">{{ $t('settings.about.max_upload') }}</span>
                        <span class="info-value">{{ systemInfo.max_upload_size.formatted }}</span>
                      </div>
                    </div>
                    
                    <div class="info-item storage-item">
                      <HardDrive class="info-icon" />
                      <div class="info-content">
                        <span class="info-label">{{ $t('settings.about.storage') }}</span>
                        <span class="info-value">{{ systemInfo.storage.used_formatted }} {{ $t('settings.about.of') }} {{ systemInfo.storage.disk_total_formatted }}</span>
                        <div class="storage-bar">
                          <div class="storage-fill" :style="{ width: systemInfo.storage.disk_usage_percent + '%' }"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
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

.version-display,
.license-display {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
  padding: 16px;
  background: var(--panel-section-background-color-alt);
  border-radius: var(--panel-border-radius);

  .version-label,
  .license-label {
    font-weight: 600;
    color: var(--panel-text-color);
  }

  .version-value,
  .license-value {
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
    font-size: 1.1rem;
    color: var(--link-color);
    background: var(--panel-section-background-color);
    padding: 4px 12px;
    border-radius: 4px;
    text-decoration: none;
    
    &:hover {
      color: var(--link-color-hover);
    }
  }
}

.button-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 16px;
  
  button {
    flex: 0 0 auto;
  }
  
  .donate-button {
    background: var(--color-success);
    color: white;
    
    &:hover {
      filter: brightness(1.1);
    }
  }
}

.credits-section {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--panel-section-background-color-alt);
  
  h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--panel-text-color-alt);
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

.credits-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.credit-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: var(--panel-section-background-color-alt);
  border-radius: var(--panel-border-radius);
  text-decoration: none;
  transition: all 0.2s ease;
  
  &:hover {
    background: var(--panel-section-background-color);
    transform: translateX(4px);
  }
  
  .credit-name {
    font-weight: 600;
    color: var(--link-color);
    min-width: 80px;
  }
  
  .credit-desc {
    font-size: 0.9rem;
    color: var(--panel-text-color-alt);
  }
}

.system-info-section {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--panel-section-background-color-alt);
  
  h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--panel-text-color-alt);
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

.loading-state {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--panel-text-color-alt);
  padding: 16px;
  
  .spinner {
    animation: spin 1s linear infinite;
    width: 18px;
    height: 18px;
  }
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.system-info-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.info-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: var(--panel-section-background-color-alt);
  border-radius: var(--panel-border-radius);
  
  .info-icon {
    width: 18px;
    height: 18px;
    color: var(--link-color);
    flex-shrink: 0;
  }
  
  .info-content {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    flex: 1;
  }
  
  .info-label {
    font-weight: 600;
    color: var(--panel-text-color);
    min-width: 100px;
  }
  
  .info-value {
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
    font-size: 0.9rem;
    color: var(--panel-text-color-alt);
  }
  
  &.storage-item {
    .info-content {
      flex-direction: column;
      align-items: flex-start;
      gap: 6px;
      
      > span {
        display: flex;
        gap: 8px;
      }
    }
  }
}

.storage-bar {
  width: 100%;
  height: 6px;
  background: var(--panel-section-background-color);
  border-radius: 3px;
  overflow: hidden;
  
  .storage-fill {
    height: 100%;
    background: var(--link-color);
    border-radius: 3px;
    transition: width 0.3s ease;
  }
}
</style>
