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

const daysOptions = [
  { label: '7 days', value: 7 },
  { label: '14 days', value: 14 },
  { label: '30 days', value: 30 },
  { label: '60 days', value: 60 },
  { label: '90 days', value: 90 }
]

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
    toast.error('Failed to load system stats')
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
              Storage
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('share-stats')">
              <Share2 />
              Shares
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('download-stats')">
              <Download />
              Downloads
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('user-stats')">
              <Users />
              Users
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('file-type-stats')">
              <FileType />
              File Types
            </a>
          </li>
        </ul>
      </div>
      <div class="col-12 col-md-10 pt-5">
        <!-- Loading State -->
        <div v-if="loading && !stats" class="loading-container">
          <RefreshCw class="spin" />
          <p>Loading statistics...</p>
        </div>

        <template v-else-if="stats">
          <!-- Refresh Button -->
          <div class="stats-header mb-4">
            <button class="refresh-btn" @click="refreshStats" :disabled="refreshing">
              <RefreshCw :class="{ spin: refreshing }" />
              Refresh
            </button>
          </div>

          <!-- Storage Stats -->
          <div class="row mb-5">
            <div class="col-12 col-lg-8 pe-0 ps-0 ps-md-3">
              <div class="setting-group" id="storage-stats">
                <div class="setting-group-header">
                  <h3>
                    <HardDrive />
                    Storage Overview
                  </h3>
                </div>
                <div class="setting-group-body">
                  <div class="stats-grid">
                    <div class="stat-card large">
                      <div class="stat-label">Disk Usage</div>
                      <div class="progress-container">
                        <div class="progress-bar-wrapper">
                          <div 
                            class="progress-bar-fill" 
                            :style="{ width: stats.storage.disk_usage_percent + '%' }"
                            :class="{ warning: stats.storage.disk_usage_percent > 80, danger: stats.storage.disk_usage_percent > 95 }"
                          ></div>
                        </div>
                        <div class="progress-labels">
                          <span>{{ stats.storage.disk_used_formatted }} used</span>
                          <span>{{ stats.storage.disk_free_formatted }} free</span>
                        </div>
                      </div>
                      <div class="stat-value">{{ stats.storage.disk_usage_percent }}%</div>
                      <div class="stat-sublabel">of {{ stats.storage.disk_total_formatted }}</div>
                    </div>

                    <div class="stat-card">
                      <div class="stat-icon">
                        <Share2 />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.storage.used_formatted }}</div>
                        <div class="stat-label">Share Storage</div>
                        <div class="stat-sublabel">{{ stats.storage.shares_usage_percent }}% of disk</div>
                      </div>
                    </div>

                    <div class="stat-card">
                      <div class="stat-icon free">
                        <HardDrive />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.storage.disk_free_formatted }}</div>
                        <div class="stat-label">Available Space</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="d-none d-lg-block col ps-0">
              <div class="section-help">
                <h6>Storage Overview</h6>
                <p>Monitor your server's disk usage and the space consumed by shares.</p>
                <h6>Disk Usage</h6>
                <p>Shows total disk space utilization. Consider cleaning up old shares if usage is high.</p>
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
                    Share Statistics
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
                        <div class="stat-label">Active Shares</div>
                      </div>
                    </div>

                    <div class="stat-card compact">
                      <div class="stat-icon warning">
                        <Clock />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.shares.expired }}</div>
                        <div class="stat-label">Expired</div>
                      </div>
                    </div>

                    <div class="stat-card compact">
                      <div class="stat-icon danger">
                        <Trash2 />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.shares.deleted }}</div>
                        <div class="stat-label">Deleted</div>
                      </div>
                    </div>

                    <div class="stat-card compact">
                      <div class="stat-icon secure">
                        <Lock />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.shares.password_protected }}</div>
                        <div class="stat-label">Protected</div>
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
                        <div class="stat-label">Total Shares</div>
                      </div>
                    </div>

                    <div class="stat-card">
                      <div class="stat-icon">
                        <TrendingUp />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.shares.recent_7_days }}</div>
                        <div class="stat-label">Last 7 Days</div>
                      </div>
                    </div>

                    <div class="stat-card">
                      <div class="stat-icon">
                        <FileType />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.shares.total_files }}</div>
                        <div class="stat-label">Total Files</div>
                        <div class="stat-sublabel">~{{ stats.shares.avg_files_per_share }} per share</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="d-none d-lg-block col ps-0">
              <div class="section-help">
                <h6>Share Statistics</h6>
                <p>Overview of all shares in the system, including their current status.</p>
                <h6>Active vs Expired</h6>
                <p>Active shares are accessible, while expired ones are pending deletion based on your cleanup settings.</p>
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
                    Download Activity
                  </h3>
                </div>
                <div class="setting-group-body">
                  <div class="period-selector mb-4">
                    <label>Time Period:</label>
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
                        <div class="stat-label">Downloads ({{ selectedDays }} days)</div>
                      </div>
                    </div>

                    <div class="stat-card">
                      <div class="stat-icon">
                        <TrendingUp />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.downloads.all_time }}</div>
                        <div class="stat-label">All-Time Downloads</div>
                      </div>
                    </div>

                    <div class="stat-card">
                      <div class="stat-icon">
                        <Users />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.downloads.unique_downloaders }}</div>
                        <div class="stat-label">Unique Downloaders</div>
                      </div>
                    </div>
                  </div>

                  <!-- Download Chart -->
                  <div class="download-chart mt-4" v-if="downloadChartData.length > 0">
                    <h4>Downloads Over Time</h4>
                    <div class="chart-container">
                      <div class="chart-bars">
                        <div 
                          v-for="(item, index) in downloadChartData" 
                          :key="index"
                          class="chart-bar-wrapper"
                          :title="`${item.date}: ${item.count} downloads`"
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
                    <h4>Most Downloaded Shares</h4>
                    <div class="top-list">
                      <div 
                        v-for="(share, index) in stats.downloads.top_shares" 
                        :key="share.id"
                        class="top-item"
                      >
                        <span class="rank">#{{ index + 1 }}</span>
                        <span class="name">{{ share.name || share.long_id }}</span>
                        <span class="count">{{ share.download_count }} downloads</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="d-none d-lg-block col ps-0">
              <div class="section-help">
                <h6>Download Activity</h6>
                <p>Track download activity across all shares over your selected time period.</p>
                <h6>Unique Downloaders</h6>
                <p>Count of unique IP addresses that have downloaded shares.</p>
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
                    User Statistics
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
                        <div class="stat-label">Total Users</div>
                      </div>
                    </div>

                    <div class="stat-card compact">
                      <div class="stat-icon active">
                        <Activity />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.users.active }}</div>
                        <div class="stat-label">Active</div>
                      </div>
                    </div>

                    <div class="stat-card compact">
                      <div class="stat-icon admin">
                        <Lock />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.users.admins }}</div>
                        <div class="stat-label">Admins</div>
                      </div>
                    </div>

                    <div class="stat-card compact">
                      <div class="stat-icon guest">
                        <Users />
                      </div>
                      <div class="stat-content">
                        <div class="stat-value">{{ stats.users.guests }}</div>
                        <div class="stat-label">Guests</div>
                      </div>
                    </div>
                  </div>

                  <div class="stat-card mt-4">
                    <div class="stat-icon">
                      <Share2 />
                    </div>
                    <div class="stat-content">
                      <div class="stat-value">{{ stats.users.with_shares }}</div>
                      <div class="stat-label">Users with Shares</div>
                    </div>
                  </div>

                  <!-- Top Users -->
                  <div class="top-users mt-4" v-if="stats.users.top_users?.length > 0">
                    <h4>Most Active Users</h4>
                    <div class="top-list">
                      <div 
                        v-for="(user, index) in stats.users.top_users" 
                        :key="user.id"
                        class="top-item"
                      >
                        <span class="rank">#{{ index + 1 }}</span>
                        <span class="name">{{ user.name }}</span>
                        <span class="count">{{ user.shares_count }} shares</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="d-none d-lg-block col ps-0">
              <div class="section-help">
                <h6>User Statistics</h6>
                <p>Overview of user accounts and their activity levels.</p>
                <h6>Guest Users</h6>
                <p>Users created via reverse share invitations.</p>
              </div>
            </div>
          </div>

          <!-- File Type Stats -->
          <div class="row mb-5">
            <div class="col-12 col-lg-8 pe-0 ps-0 ps-md-3">
              <div class="setting-group" id="file-type-stats">
                <div class="setting-group-header">
                  <h3>
                    <FileType />
                    File Type Distribution
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
                        <div class="category-name">{{ category.charAt(0).toUpperCase() + category.slice(1) }}</div>
                        <div class="category-stats">
                          <span class="count">{{ data.count }} files</span>
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
                <h6>File Type Distribution</h6>
                <p>Breakdown of files by category across all shares.</p>
                <h6>Categories</h6>
                <p>Files are grouped by type: images, documents, videos, audio, archives, code, and other.</p>
              </div>
            </div>
          </div>
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
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
  bottom: 0;
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

