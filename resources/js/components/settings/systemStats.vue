<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import {
  HardDrive,
  Share2,
  Download,
  Users,
  FileType,
  TrendingUp,
  Calendar,
  Lock,
  Trash2,
  Clock,
  Activity,
  RefreshCw,
  Image,
  FileText,
  Video,
  Music,
  Archive,
  Code,
  File
} from 'lucide-vue-next'
import { getSystemStats } from '../../api'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()
const toast = useToast()

const stats = ref(null)
const loading = ref(true)
const selectedDays = ref(30)
const refreshing = ref(false)

const daysOptions = computed(() => [
  { label: t.value('settings.stats.downloads.days_option', { days: 7 }), value: 7 },
  { label: t.value('settings.stats.downloads.days_option', { days: 14 }), value: 14 },
  { label: t.value('settings.stats.downloads.days_option', { days: 30 }), value: 30 },
  { label: t.value('settings.stats.downloads.days_option', { days: 60 }), value: 60 },
  { label: t.value('settings.stats.downloads.days_option', { days: 90 }), value: 90 }
])

const emit = defineEmits(['navItemClicked'])

onMounted(async () => {
  await loadStats()
})

watch(selectedDays, async () => {
  await loadStats()
})

const loadStats = async () => {
  try {
    loading.value = true
    stats.value = await getSystemStats(selectedDays.value)
  } catch (error) {
    toast.error(t.value('settings.stats.error_loading'))
    console.error(error)
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

const refreshStats = async () => {
  refreshing.value = true
  await loadStats()
}

const handleNavItemClicked = (item) => {
  emit('navItemClicked', item)
}

// Chart data for downloads
const downloadChartData = computed(() => {
  if (!stats.value?.downloads?.by_day) return []
  return Object.entries(stats.value.downloads.by_day).map(([date, count]) => ({
    date: new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
    count
  }))
})

const maxDownloadCount = computed(() => {
  if (!downloadChartData.value.length) return 1
  return Math.max(...downloadChartData.value.map(d => d.count), 1)
})

// File category icons
const categoryIcons = {
  images: Image,
  documents: FileText,
  videos: Video,
  audio: Music,
  archives: Archive,
  code: Code,
  other: File
}

const categoryColors = {
  images: '#10b981',
  documents: '#3b82f6',
  videos: '#8b5cf6',
  audio: '#f59e0b',
  archives: '#ef4444',
  code: '#06b6d4',
  other: '#6b7280'
}
</script>

<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-2 d-none d-md-block">
        <ul class="settings-nav pt-5">
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('storage-stats')">
              <HardDrive />
              {{ $t('settings.stats.nav.storage') }}
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('share-stats')">
              <Share2 />
              {{ $t('settings.stats.nav.shares') }}
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('download-stats')">
              <Download />
              {{ $t('settings.stats.nav.downloads') }}
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('user-stats')">
              <Users />
              {{ $t('settings.stats.nav.users') }}
            </a>
          </li>
          <!-- File Type Stats
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('file-type-stats')">
              <FileType />
              {{ $t('settings.stats.nav.file_types') }}
            </a>
          </li> -->
        </ul>
      </div>
      <div class="col-12 col-md-10 pt-5">
        <!-- Loading State -->
        <div v-if="loading && !stats" class="loading-container">
          <RefreshCw class="spin" />
          <p>{{ $t('settings.stats.loading') }}</p>
        </div>

        <template v-else-if="stats">
          <!-- Refresh Button -->
          <div class="stats-header mb-4">
            <button class="refresh-btn" @click="refreshStats" :disabled="refreshing">
              <RefreshCw :class="{ spin: refreshing }" />
              {{ $t('settings.stats.refresh') }}
            </button>
          </div>

          <!-- Storage Stats -->
          <div class="row mb-5">
            <div class="col-12 col-lg-8 pe-0 ps-0 ps-md-3">
              <div class="setting-group" id="storage-stats">
                <div class="setting-group-header">
                  <h3>
                    <HardDrive />
                    {{ $t('settings.stats.storage.title') }}
                  </h3>
                </div>
                <div class="setting-group-body">
                  <div class="stats-grid">
                    <div class="stat-card large">
                      <div class="stat-label">{{ $t('settings.stats.storage.disk_usage') }}</div>
                      <div class="progress-container">
                        <div class="progress-bar-wrapper">
                          <div 
                            class="progress-bar-fill" 
                            :style="{ width: stats.storage.disk_usage_percent + '%' }"
                            :class="{ warning: stats.storage.disk_usage_percent > 80, danger: stats.storage.disk_usage_percent > 95 }"
                          ></div>
                        </div>
                        <div class="progress-labels">
                          <span>{{ stats.storage.disk_used_formatted }} {{ $t('settings.stats.storage.used') }}</span>
                          <span>{{ stats.storage.disk_free_formatted }} {{ $t('settings.stats.storage.free') }}</span>
                        </div>
                      </div>
                      <div class="stat-value">{{ stats.storage.disk_usage_percent }}%</div>
                      <div class="stat-sublabel">{{ $t('settings.stats.storage.of') }} {{ stats.storage.disk_total_formatted }}</div>
                    </div>

                    <div class="stat-card">
                      <div class="stat-icon">
                        <Share2 />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.storage.used_formatted }}</div>
                        <div class="stat-label">{{ $t('settings.stats.storage.share_storage') }}</div>
                        <div class="stat-sublabel">{{ stats.storage.shares_usage_percent }}% {{ $t('settings.stats.storage.of_disk') }}</div>
                      </div>
                    </div>

                    <div class="stat-card">
                      <div class="stat-icon free">
                        <HardDrive />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.storage.disk_free_formatted }}</div>
                        <div class="stat-label">{{ $t('settings.stats.storage.available_space') }}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="d-none d-lg-block col ps-0">
              <div class="section-help">
                <h6>{{ $t('settings.stats.help.storage_title') }}</h6>
                <p>{{ $t('settings.stats.help.storage_description') }}</p>
                <h6>{{ $t('settings.stats.help.disk_usage_title') }}</h6>
                <p>{{ $t('settings.stats.help.disk_usage_description') }}</p>
              </div>
            </div>
          </div>

          <!-- Share Stats -->
          <div class="row mb-5">
            <div class="col-12 col-lg-8 pe-0 ps-0 ps-md-3">
              <div class="setting-group" id="share-stats">
                <div class="setting-group-header">
                  <h3>
                    <Share2 />
                    {{ $t('settings.stats.shares.title') }}
                  </h3>
                </div>
                <div class="setting-group-body">
                  <div class="stats-grid four-col">
                    <div class="stat-card compact">
                      <div class="stat-icon active">
                        <Activity />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.shares.active }}</div>
                        <div class="stat-label">{{ $t('settings.stats.shares.active_shares') }}</div>
                      </div>
                    </div>

                    <div class="stat-card compact">
                      <div class="stat-icon warning">
                        <Clock />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.shares.expired }}</div>
                        <div class="stat-label">{{ $t('settings.stats.shares.expired') }}</div>
                      </div>
                    </div>

                    <div class="stat-card compact">
                      <div class="stat-icon danger">
                        <Trash2 />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.shares.deleted }}</div>
                        <div class="stat-label">{{ $t('settings.stats.shares.deleted') }}</div>
                      </div>
                    </div>

                    <div class="stat-card compact">
                      <div class="stat-icon secure">
                        <Lock />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.shares.password_protected }}</div>
                        <div class="stat-label">{{ $t('settings.stats.shares.protected') }}</div>
                      </div>
                    </div>
                  </div>

                  <div class="stats-grid mt-4">
                    <div class="stat-card">
                      <div class="stat-icon">
                        <Share2 />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.shares.total }}</div>
                        <div class="stat-label">{{ $t('settings.stats.shares.total_shares') }}</div>
                      </div>
                    </div>

                    <div class="stat-card">
                      <div class="stat-icon">
                        <TrendingUp />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.shares.recent_7_days }}</div>
                        <div class="stat-label">{{ $t('settings.stats.shares.last_7_days') }}</div>
                      </div>
                    </div>

                    <div class="stat-card">
                      <div class="stat-icon">
                        <FileType />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.shares.total_files }}</div>
                        <div class="stat-label">{{ $t('settings.stats.shares.total_files') }}</div>
                        <div class="stat-sublabel">~{{ stats.shares.avg_files_per_share }} {{ $t('settings.stats.shares.per_share') }}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="d-none d-lg-block col ps-0">
              <div class="section-help">
                <h6>{{ $t('settings.stats.help.shares_title') }}</h6>
                <p>{{ $t('settings.stats.help.shares_description') }}</p>
                <h6>{{ $t('settings.stats.help.active_vs_expired_title') }}</h6>
                <p>{{ $t('settings.stats.help.active_vs_expired_description') }}</p>
              </div>
            </div>
          </div>

          <!-- Download Stats -->
          <div class="row mb-5">
            <div class="col-12 col-lg-8 pe-0 ps-0 ps-md-3">
              <div class="setting-group" id="download-stats">
                <div class="setting-group-header">
                  <h3>
                    <Download />
                    {{ $t('settings.stats.downloads.title') }}
                  </h3>
                </div>
                <div class="setting-group-body">
                  <div class="period-selector mb-4">
                    <label>{{ $t('settings.stats.downloads.time_period') }}</label>
                    <select v-model="selectedDays">
                      <option v-for="opt in daysOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                      </option>
                    </select>
                  </div>

                  <div class="stats-grid">
                    <div class="stat-card">
                      <div class="stat-icon">
                        <Download />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.downloads.total_in_period }}</div>
                        <div class="stat-label">{{ $t('settings.stats.downloads.downloads_in_period', { days: selectedDays }) }}</div>
                      </div>
                    </div>

                    <div class="stat-card">
                      <div class="stat-icon">
                        <TrendingUp />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.downloads.all_time }}</div>
                        <div class="stat-label">{{ $t('settings.stats.downloads.all_time') }}</div>
                      </div>
                    </div>

                    <div class="stat-card">
                      <div class="stat-icon">
                        <Users />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.downloads.unique_downloaders }}</div>
                        <div class="stat-label">{{ $t('settings.stats.downloads.unique_downloaders') }}</div>
                      </div>
                    </div>
                  </div>

                  <!-- Download Chart -->
                  <div class="download-chart mt-4" v-if="downloadChartData.length > 0">
                    <h4>{{ $t('settings.stats.downloads.chart_title') }}</h4>
                    <div class="chart-container">
                      <div class="chart-bars">
                        <div 
                          v-for="(item, index) in downloadChartData" 
                          :key="index"
                          class="chart-bar-wrapper"
                          :title="`${item.date}: ${item.count} ${t('settings.stats.downloads.downloads')}`"
                        >
                          <div 
                            class="chart-bar"
                            :style="{ height: (item.count / maxDownloadCount * 100) + '%' }"
                          >
                            <span class="chart-bar-value" v-if="item.count > 0">{{ item.count }}</span>
                          </div>
                          <span class="chart-label" v-if="index % Math.ceil(downloadChartData.length / 7) === 0">
                            {{ item.date }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Top Downloads -->
                  <div class="top-downloads mt-4" v-if="stats.downloads.top_shares?.length > 0">
                    <h4>{{ $t('settings.stats.downloads.top_shares') }}</h4>
                    <div class="top-list">
                      <div 
                        v-for="(share, index) in stats.downloads.top_shares" 
                        :key="share.id"
                        class="top-item"
                      >
                        <span class="rank">#{{ index + 1 }}</span>
                        <span class="name">{{ share.name || share.long_id }}</span>
                        <span class="count">{{ share.download_count }} {{ $t('settings.stats.downloads.downloads') }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="d-none d-lg-block col ps-0">
              <div class="section-help">
                <h6>{{ $t('settings.stats.help.downloads_title') }}</h6>
                <p>{{ $t('settings.stats.help.downloads_description') }}</p>
                <h6>{{ $t('settings.stats.help.unique_downloaders_title') }}</h6>
                <p>{{ $t('settings.stats.help.unique_downloaders_description') }}</p>
              </div>
            </div>
          </div>

          <!-- User Stats -->
          <div class="row mb-5">
            <div class="col-12 col-lg-8 pe-0 ps-0 ps-md-3">
              <div class="setting-group" id="user-stats">
                <div class="setting-group-header">
                  <h3>
                    <Users />
                    {{ $t('settings.stats.users.title') }}
                  </h3>
                </div>
                <div class="setting-group-body">
                  <div class="stats-grid four-col">
                    <div class="stat-card compact">
                      <div class="stat-icon">
                        <Users />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.users.total }}</div>
                        <div class="stat-label">{{ $t('settings.stats.users.total_users') }}</div>
                      </div>
                    </div>

                    <div class="stat-card compact">
                      <div class="stat-icon active">
                        <Activity />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.users.active }}</div>
                        <div class="stat-label">{{ $t('settings.stats.users.active') }}</div>
                      </div>
                    </div>

                    <div class="stat-card compact">
                      <div class="stat-icon admin">
                        <Lock />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.users.admins }}</div>
                        <div class="stat-label">{{ $t('settings.stats.users.admins') }}</div>
                      </div>
                    </div>

                    <div class="stat-card compact">
                      <div class="stat-icon guest">
                        <Users />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.users.guests }}</div>
                        <div class="stat-label">{{ $t('settings.stats.users.guests') }}</div>
                      </div>
                    </div>
                  </div>

                  <div class="stat-card mt-4">
                    <div class="stat-icon">
                      <Share2 />
                    </div>
                    <div class="stat-content">
                      <div class="stat-value">{{ stats.users.with_shares }}</div>
                      <div class="stat-label">{{ $t('settings.stats.users.with_shares') }}</div>
                    </div>
                  </div>

                  <!-- Top Users -->
                  <div class="top-users mt-4" v-if="stats.users.top_users?.length > 0">
                    <h4>{{ $t('settings.stats.users.top_users') }}</h4>
                    <div class="top-list">
                      <div 
                        v-for="(user, index) in stats.users.top_users" 
                        :key="user.id"
                        class="top-item"
                      >
                        <span class="rank">#{{ index + 1 }}</span>
                        <span class="name">{{ user.name }}</span>
                        <span class="count">{{ user.shares_count }} {{ $t('settings.stats.users.shares') }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="d-none d-lg-block col ps-0">
              <div class="section-help">
                <h6>{{ $t('settings.stats.help.users_title') }}</h6>
                <p>{{ $t('settings.stats.help.users_description') }}</p>
                <h6>{{ $t('settings.stats.help.guest_users_title') }}</h6>
                <p>{{ $t('settings.stats.help.guest_users_description') }}</p>
              </div>
            </div>
          </div>

          <!-- File Type Stats 
          <div class="row mb-5">
            <div class="col-12 col-lg-8 pe-0 ps-0 ps-md-3">
              <div class="setting-group" id="file-type-stats">
                <div class="setting-group-header">
                  <h3>
                    <FileType />
                    {{ $t('settings.stats.file_types.title') }}
                  </h3>
                </div>
                <div class="setting-group-body">
                  <div class="file-categories">
                    <div 
                      v-for="(data, category) in stats.file_types.by_category" 
                      :key="category"
                      class="category-card"
                      v-show="data.count > 0"
                    >
                      <div class="category-icon" :style="{ backgroundColor: categoryColors[category] + '20', color: categoryColors[category] }">
                        <component :is="categoryIcons[category]" />
                      </div>
                      <div class="category-content">
                        <div class="category-name">{{ $t('settings.stats.file_types.categories.' + category) }}</div>
                        <div class="category-stats">
                          <span class="count">{{ data.count }} {{ $t('settings.stats.file_types.files') }}</span>
                          <span class="size">{{ data.total_size_formatted }}</span>
                        </div>
                      </div>
                      <div class="category-bar">
                        <div 
                          class="category-bar-fill" 
                          :style="{ 
                            width: Math.max((data.count / stats.shares.total_files * 100), 2) + '%',
                            backgroundColor: categoryColors[category]
                          }"
                        ></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="d-none d-lg-block col ps-0">
              <div class="section-help">
                <h6>{{ $t('settings.stats.help.file_types_title') }}</h6>
                <p>{{ $t('settings.stats.help.file_types_description') }}</p>
                <h6>{{ $t('settings.stats.help.categories_title') }}</h6>
                <p>{{ $t('settings.stats.help.categories_description') }}</p>
              </div>
            </div>
          </div>-->
        </template>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: var(--panel-text-color);
  
  svg {
    width: 40px;
    height: 40px;
    margin-bottom: 16px;
  }
  
  p {
    font-size: 1rem;
    opacity: 0.7;
  }
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.stats-header {
  display: flex;
  justify-content: flex-end;
  padding: 0 12px;
}

.refresh-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: var(--panel-section-background-color);
  border: 1px solid var(--input-border-color);
  border-radius: 6px;
  color: var(--panel-text-color);
  cursor: pointer;
  font-size: 0.9rem;
  transition: all 0.2s;
  
  &:hover:not(:disabled) {
    background: var(--panel-section-background-color-alt);
  }
  
  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  
  svg {
    width: 16px;
    height: 16px;
  }
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  
  &.four-col {
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  }
}

.stat-card {
  background: var(--panel-section-background-color-alt);
  border-radius: 10px;
  padding: 20px;
  display: flex;
  align-items: flex-start;
  gap: 16px;
  
  &.large {
    grid-column: 1 / -1;
    flex-direction: column;
    align-items: stretch;
  }
  
  &.compact {
    padding: 16px;
    
    .stat-value {
      font-size: 1.5rem;
      white-space: nowrap;
    }
  }
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--primary-button-background-color);
  color: var(--primary-button-text-color);
  flex-shrink: 0;
  
  svg {
    width: 22px;
    height: 22px;
  }
  
  &.active {
    background: #10b98130;
    color: #10b981;
  }
  
  &.warning {
    background: #f59e0b30;
    color: #f59e0b;
  }
  
  &.danger {
    background: #ef444430;
    color: #ef4444;
  }
  
  &.secure {
    background: #8b5cf630;
    color: #8b5cf6;
  }
  
  &.admin {
    background: #3b82f630;
    color: #3b82f6;
  }
  
  &.guest {
    background: #6b728030;
    color: #6b7280;
  }
  
  &.free {
    background: #10b98130;
    color: #10b981;
  }
}

.stat-content {
  flex: 1;
  min-width: 0;
}

.stat-value {
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--panel-text-color);
  line-height: 1.2;
  white-space: nowrap;
}

.stat-label {
  font-size: 0.85rem;
  color: var(--panel-text-color);
  opacity: 0.7;
  margin-top: 4px;
}

.stat-sublabel {
  font-size: 0.75rem;
  color: var(--panel-text-color);
  opacity: 0.5;
  margin-top: 2px;
}

.progress-container {
  width: 100%;
  margin: 12px 0;
}

.progress-bar-wrapper {
  height: 12px;
  background: var(--progress-bar-background-color);
  border-radius: 6px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  background: var(--progress-bar-fill-color);
  border-radius: 6px;
  transition: width 0.5s ease;
  
  &.warning {
    background: #f59e0b;
  }
  
  &.danger {
    background: #ef4444;
  }
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  margin-top: 8px;
  font-size: 0.8rem;
  color: var(--panel-text-color);
  opacity: 0.7;
}

.period-selector {
  display: flex;
  align-items: center;
  gap: 12px;
  
  label {
    font-size: 0.9rem;
    color: var(--panel-text-color);
  }
  
  select {
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid var(--input-border-color);
    background: var(--input-background-color);
    color: var(--input-text-color);
    font-size: 0.9rem;
    cursor: pointer;
  }
}

.download-chart {
  background: var(--panel-section-background-color-alt);
  border-radius: 10px;
  padding: 20px;
  
  h4 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 16px;
    color: var(--panel-text-color);
  }
}

.chart-container {
  height: 180px;
  position: relative;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  height: 100%;
  gap: 2px;
  padding-bottom: 24px;
}

.chart-bar-wrapper {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  height: 100%;
  position: relative;
  min-width: 0;
}

.chart-bar {
  width: 100%;
  max-width: 24px;
  min-height: 4px;
  background: var(--primary-button-background-color);
  border-radius: 3px 3px 0 0;
  position: relative;
  transition: height 0.3s ease;
  
  &:hover {
    opacity: 0.8;
  }
}

.chart-bar-value {
  position: absolute;
  top: -20px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 0.7rem;
  color: var(--panel-text-color);
  white-space: nowrap;
}

.chart-label {
  position: absolute;
  bottom: -20px;
  font-size: 0.65rem;
  color: var(--panel-text-color);
  opacity: 0.6;
  white-space: nowrap;
}

.top-downloads,
.top-users {
  background: var(--panel-section-background-color-alt);
  border-radius: 10px;
  padding: 20px;
  
  h4 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 16px;
    color: var(--panel-text-color);
  }
}

.top-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.top-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: var(--panel-section-background-color);
  border-radius: 8px;
  
  .rank {
    font-weight: 700;
    color: var(--primary-button-background-color);
    min-width: 30px;
  }
  
  .name {
    flex: 1;
    color: var(--panel-text-color);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  
  .count {
    font-size: 0.85rem;
    color: var(--panel-text-color);
    opacity: 0.7;
    white-space: nowrap;
  }
}

.file-categories {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.category-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: var(--panel-section-background-color-alt);
  border-radius: 10px;
}

.category-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  
  svg {
    width: 22px;
    height: 22px;
  }
}

.category-content {
  flex: 1;
  min-width: 0;
}

.category-name {
  font-weight: 600;
  color: var(--panel-text-color);
  margin-bottom: 4px;
}

.category-stats {
  display: flex;
  gap: 16px;
  font-size: 0.85rem;
  color: var(--panel-text-color);
  opacity: 0.7;
}

.category-bar {
  width: 100px;
  height: 8px;
  background: var(--progress-bar-background-color);
  border-radius: 4px;
  overflow: hidden;
  flex-shrink: 0;
}

.category-bar-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease;
}
</style>

