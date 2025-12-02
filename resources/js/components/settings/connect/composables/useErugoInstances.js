import { ref } from 'vue'
import {
  getCloudConnectInstances,
  createCloudConnectInstance,
  updateCloudConnectInstance,
  deleteCloudConnectInstance,
  regenerateCloudConnectInstanceToken,
  linkCloudConnectInstance,
  checkCloudConnectSubdomain,
  disconnectCloudConnect
} from '../../../../api'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'

export function useErugoInstances(onInstanceChange, options = {}) {
  const { t } = useTranslate()
  const toast = useToast()
  const { getCurrentInstanceId } = options

  // State
  const instances = ref([])
  const loadingInstances = ref(false)
  const selectedInstance = ref(null)

  // Instance form (for creating new instances)
  const instanceForm = ref({
    name: 'My Erugo Server',
    subdomain: ''
  })

  // Edit instance form
  const editInstanceForm = ref({
    name: '',
    subdomain: ''
  })
  const showEditInstanceForm = ref(false)
  const editingInstanceSubdomain = ref(false)

  // Delete confirmation
  const showDeleteConfirm = ref(false)

  // Token regeneration
  const showRegenerateTokenConfirm = ref(false)
  const regeneratedToken = ref(null)
  const showRegeneratedToken = ref(false)

  // Link instance confirmation
  const showLinkConfirm = ref(false)

  // Subdomain checking
  const checkingSubdomain = ref(false)
  const subdomainAvailable = ref(null)
  const subdomainSuggestions = ref([])
  const subdomainOwnedByUser = ref(false)
  const existingInstanceName = ref(null)
  const showReclaimConfirm = ref(false)

  const instanceLoading = ref(false)

  // Methods
  const loadInstances = async () => {
    try {
      loadingInstances.value = true
      const result = await getCloudConnectInstances()
      instances.value = result.instances || []
    } catch (error) {
      console.error('Failed to load instances:', error)
      toast.error(t.value('cloudConnect.instances.loadFailed'))
    } finally {
      loadingInstances.value = false
    }
  }

  const handleCheckSubdomain = async () => {
    if (!instanceForm.value.subdomain || instanceForm.value.subdomain.length < 3) {
      return
    }

    try {
      checkingSubdomain.value = true
      subdomainOwnedByUser.value = false
      existingInstanceName.value = null
      const result = await checkCloudConnectSubdomain(instanceForm.value.subdomain)
      subdomainAvailable.value = result.available
      subdomainSuggestions.value = result.suggestions || []

      if (!result.available && result.owned_by_user) {
        subdomainOwnedByUser.value = true
        existingInstanceName.value = result.existing_instance_name
        subdomainAvailable.value = true
      }
    } catch (error) {
      toast.error(error.message || t.value('cloudConnect.subdomainCheckFailed'))
      subdomainAvailable.value = null
    } finally {
      checkingSubdomain.value = false
    }
  }

  const selectSuggestion = (suggestion) => {
    instanceForm.value.subdomain = suggestion
    subdomainAvailable.value = true
    subdomainOwnedByUser.value = false
    existingInstanceName.value = null
    subdomainSuggestions.value = []
  }

  const handleCreateInstance = async (confirmReclaim = false) => {
    if (!subdomainAvailable.value) {
      toast.error(t.value('cloudConnect.subdomainNotAvailable'))
      return
    }

    try {
      instanceLoading.value = true
      showReclaimConfirm.value = false
      const result = await createCloudConnectInstance(
        instanceForm.value.name,
        instanceForm.value.subdomain,
        confirmReclaim
      )

      if (result.reclaimed) {
        toast.success(t.value('cloudConnect.instanceReclaimed'))
      } else {
        toast.success(t.value('cloudConnect.instanceCreated'))
      }
      
      if (onInstanceChange) {
        await onInstanceChange()
      }
    } catch (error) {
      if (error.code === 'SUBDOMAIN_OWNED_BY_USER') {
        existingInstanceName.value = error.data?.existing_instance_name
        showReclaimConfirm.value = true
        return
      }
      toast.error(error.message || t.value('cloudConnect.instanceCreateFailed'))
    } finally {
      instanceLoading.value = false
    }
  }

  const handleConfirmReclaim = () => {
    handleCreateInstance(true)
  }

  const handleCancelReclaim = () => {
    showReclaimConfirm.value = false
  }

  const openEditInstanceForm = (instance) => {
    selectedInstance.value = instance
    editInstanceForm.value = {
      name: instance.name || '',
      subdomain: instance.subdomain || ''
    }
    editingInstanceSubdomain.value = false
    showEditInstanceForm.value = true
  }

  const closeEditInstanceForm = () => {
    showEditInstanceForm.value = false
    selectedInstance.value = null
    editInstanceForm.value = { name: '', subdomain: '' }
    editingInstanceSubdomain.value = false
  }

  const handleUpdateInstance = async () => {
    if (!selectedInstance.value) return

    try {
      instanceLoading.value = true
      const updateData = { name: editInstanceForm.value.name }

      if (editingInstanceSubdomain.value && editInstanceForm.value.subdomain !== selectedInstance.value.subdomain) {
        updateData.subdomain = editInstanceForm.value.subdomain
      }

      await updateCloudConnectInstance(selectedInstance.value.id, updateData)
      toast.success(t.value('cloudConnect.instances.updateSuccess'))
      closeEditInstanceForm()
      await loadInstances()
      if (onInstanceChange) {
        await onInstanceChange()
      }
    } catch (error) {
      toast.error(error.message || t.value('cloudConnect.instances.updateFailed'))
    } finally {
      instanceLoading.value = false
    }
  }

  const openDeleteConfirm = (instance) => {
    selectedInstance.value = instance
    showDeleteConfirm.value = true
  }

  const closeDeleteConfirm = () => {
    showDeleteConfirm.value = false
    selectedInstance.value = null
  }

  const handleDeleteInstance = async () => {
    if (!selectedInstance.value) return

    try {
      instanceLoading.value = true
      
      // Check if we're deleting the current instance
      const currentInstanceId = getCurrentInstanceId ? getCurrentInstanceId() : null
      const isDeletingCurrentInstance = currentInstanceId && selectedInstance.value.id === currentInstanceId
      
      // If deleting the current instance, disconnect first
      if (isDeletingCurrentInstance) {
        try {
          await disconnectCloudConnect()
        } catch (disconnectError) {
          // Log but continue - the tunnel might already be down
          console.warn('Failed to disconnect tunnel before deletion:', disconnectError)
        }
      }
      
      // Now delete the instance via API
      await deleteCloudConnectInstance(selectedInstance.value.id)
      
      // Show appropriate success message
      if (isDeletingCurrentInstance) {
        toast.success(t.value('cloudConnect.instances.deleteCurrentSuccess') || 'Instance deleted and Cloud Connect disconnected')
      } else {
        toast.success(t.value('cloudConnect.instances.deleteSuccess'))
      }
      
      closeDeleteConfirm()
      await loadInstances()
      if (onInstanceChange) {
        await onInstanceChange()
      }
    } catch (error) {
      toast.error(error.message || t.value('cloudConnect.instances.deleteFailed'))
    } finally {
      instanceLoading.value = false
    }
  }

  const openRegenerateTokenConfirm = (instance) => {
    selectedInstance.value = instance
    showRegenerateTokenConfirm.value = true
  }

  const closeRegenerateTokenConfirm = () => {
    showRegenerateTokenConfirm.value = false
    selectedInstance.value = null
  }

  const handleRegenerateToken = async () => {
    if (!selectedInstance.value) return

    try {
      instanceLoading.value = true
      const result = await regenerateCloudConnectInstanceToken(selectedInstance.value.id)
      regeneratedToken.value = result.instance_token
      showRegenerateTokenConfirm.value = false
      showRegeneratedToken.value = true
      toast.success(t.value('cloudConnect.instances.tokenRegenerated'))
    } catch (error) {
      toast.error(error.message || t.value('cloudConnect.instances.tokenRegenerateFailed'))
    } finally {
      instanceLoading.value = false
    }
  }

  const closeRegeneratedTokenModal = () => {
    showRegeneratedToken.value = false
    regeneratedToken.value = null
    selectedInstance.value = null
  }

  const copyToken = async () => {
    if (!regeneratedToken.value) return
    try {
      await navigator.clipboard.writeText(regeneratedToken.value)
      toast.success(t.value('cloudConnect.instances.tokenCopied'))
    } catch (error) {
      toast.error(t.value('cloudConnect.copyFailed'))
    }
  }

  const getInstanceStatusClass = (instance) => {
    if (instance.status === 'online' || instance.status === 'connected') return 'online'
    if (instance.status === 'offline' || instance.status === 'disconnected') return 'offline'
    return ''
  }

  const resetSubdomainState = () => {
    subdomainAvailable.value = null
    subdomainOwnedByUser.value = false
    existingInstanceName.value = null
    subdomainSuggestions.value = []
  }

  const openLinkConfirm = (instance) => {
    selectedInstance.value = instance
    showLinkConfirm.value = true
  }

  const closeLinkConfirm = () => {
    showLinkConfirm.value = false
    selectedInstance.value = null
  }

  const handleLinkInstance = async () => {
    if (!selectedInstance.value) return

    try {
      instanceLoading.value = true
      await linkCloudConnectInstance(selectedInstance.value.id)
      toast.success(t.value('cloudConnect.instances.linkSuccess') || 'Instance linked successfully')
      closeLinkConfirm()
      if (onInstanceChange) {
        await onInstanceChange()
      }
    } catch (error) {
      toast.error(error.message || t.value('cloudConnect.instances.linkFailed') || 'Failed to link instance')
    } finally {
      instanceLoading.value = false
    }
  }

  return {
    // State
    instances,
    loadingInstances,
    selectedInstance,
    instanceForm,
    editInstanceForm,
    showEditInstanceForm,
    editingInstanceSubdomain,
    showDeleteConfirm,
    showRegenerateTokenConfirm,
    regeneratedToken,
    showRegeneratedToken,
    showLinkConfirm,
    checkingSubdomain,
    subdomainAvailable,
    subdomainSuggestions,
    subdomainOwnedByUser,
    existingInstanceName,
    showReclaimConfirm,
    instanceLoading,

    // Methods
    loadInstances,
    handleCheckSubdomain,
    selectSuggestion,
    handleCreateInstance,
    handleConfirmReclaim,
    handleCancelReclaim,
    openEditInstanceForm,
    closeEditInstanceForm,
    handleUpdateInstance,
    openDeleteConfirm,
    closeDeleteConfirm,
    handleDeleteInstance,
    openRegenerateTokenConfirm,
    closeRegenerateTokenConfirm,
    handleRegenerateToken,
    closeRegeneratedTokenModal,
    copyToken,
    getInstanceStatusClass,
    resetSubdomainState,
    openLinkConfirm,
    closeLinkConfirm,
    handleLinkInstance
  }
}

