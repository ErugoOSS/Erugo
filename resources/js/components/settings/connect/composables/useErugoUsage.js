import { ref } from 'vue'
import { getCloudConnectUsage } from '../../../../api'

export function useErugoUsage() {
  // State
  const usageData = ref(null)
  const loadingUsage = ref(false)

  // Methods
  const loadUsage = async () => {
    try {
      loadingUsage.value = true
      usageData.value = await getCloudConnectUsage()
    } catch (error) {
      console.error('Failed to load usage:', error)
    } finally {
      loadingUsage.value = false
    }
  }

  const formatBytes = (bytes) => {
    if (bytes === 0 || bytes === null || bytes === undefined) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
  }

  const formatDate = (dateString) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString()
  }

  const clearUsage = () => {
    usageData.value = null
  }

  return {
    // State
    usageData,
    loadingUsage,

    // Methods
    loadUsage,
    formatBytes,
    formatDate,
    clearUsage
  }
}

