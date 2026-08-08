<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue'
import {
  getLiveshare,
  updateLiveshare,
  getLiveshareAvatarUrl,
  getLiveshareFiles,
  getLiveshareTags,
  downloadLiveshareFiles,
  removeLiveshareFile
} from '../../api'
import { store } from '../../store'
import { setPageTitle } from '../../pageTitle'
import {
  ArrowLeft,
  ArrowUpDown,
  Check,
  Download,
  Edit3,
  LayoutGrid,
  List,
  ListChecks,
  Loader2,
  PanelRight,
  Plus,
  Save,
  Search,
  Tags,
  UserPlus,
  Users,
  X
} from 'lucide-vue-next'
import { useToast } from 'vue-toastification'
import { useConfirmDialog } from '../../composables/useConfirmDialog'
import { fileDisplayName, triggerFileDownload } from './fileHelpers'

import FileGrid from './fileGrid.vue'
import FileList from './fileList.vue'
import FilterRail from './filterRail.vue'
import FileInspector from './fileInspector.vue'
import FileContextMenu from './fileContextMenu.vue'
import LiveshareUploader from './liveshareUploader.vue'
import MemberManager from './memberManager.vue'
import TagManager from './tagManager.vue'

const props = defineProps({
  liveshareCode: { type: String, required: true }
})

const toast = useToast()
const confirmDialog = useConfirmDialog()
const liveshare = ref(null)
const loading = ref(true)
const error = ref(null)

// Edit mode
const editing = ref(false)
const editName = ref('')
const editDescription = ref('')
const saving = ref(false)

// Search and filter state
const searchQuery = ref('')
const activeTagFilters = ref([]) // tag IDs
const activeTypeFilter = ref('') // media type name, e.g. 'image'
const filteredFiles = ref([])
const tags = ref([])
let searchDebounceTimer = null

// Tag management sheet
const tagManagerRef = ref(null)
const tagManagerSheetOpen = ref(false)

// Every file in the liveshare, regardless of the active filters. Backs the
// counts shown in the filter rail.
const allFiles = computed(() => liveshare.value?.files || [])

const loadLiveshare = async () => {
  loading.value = true
  error.value = null
  try {
    liveshare.value = await getLiveshare(props.liveshareCode)
    await loadTags()
    if (hasActiveFilters.value) {
      await loadFilteredFiles()
    } else {
      filteredFiles.value = liveshare.value.files || []
    }
    pruneSelection()
  } catch (err) {
    error.value = err.message || 'Failed to load liveshare'
  }
  loading.value = false
}

const loadTags = async () => {
  try {
    tags.value = await getLiveshareTags(props.liveshareCode)
  } catch (err) {
    // Non-fatal -- tags just won't show
    tags.value = []
  }
}

const loadFilteredFiles = async () => {
  try {
    const params = {}
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
    if (activeTagFilters.value.length) params.tags = activeTagFilters.value
    if (activeTypeFilter.value) params.type = activeTypeFilter.value
    filteredFiles.value = await getLiveshareFiles(props.liveshareCode, params)
    pruneSelection()
  } catch (err) {
    toast.error(err.message || 'Failed to load files')
  }
}

const hasActiveFilters = computed(() => {
  return searchQuery.value.trim() !== '' || activeTagFilters.value.length > 0 || activeTypeFilter.value !== ''
})

const activeTagObjects = computed(() =>
  activeTagFilters.value
    .map((id) => tags.value.find((t) => t.id === id))
    .filter(Boolean)
)

watch(
  () => liveshare.value?.name,
  (name) => {
    if (name) setPageTitle(name)
  }
)

onMounted(async () => {
  document.addEventListener('click', handleSortMenuClickOutside, true)
  document.addEventListener('keydown', handleKeydown)
  await loadLiveshare()
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleSortMenuClickOutside, true)
  document.removeEventListener('keydown', handleKeydown)
  clearTimeout(searchDebounceTimer)
})

const onSearchInput = () => {
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    loadFilteredFiles()
  }, 300)
}

const clearSearch = () => {
  searchQuery.value = ''
  loadFilteredFiles()
}

const toggleTypeFilter = (typeName) => {
  activeTypeFilter.value = activeTypeFilter.value === typeName ? '' : typeName
  loadFilteredFiles()
}

const toggleTagFilter = (tagId) => {
  const idx = activeTagFilters.value.indexOf(tagId)
  if (idx >= 0) {
    activeTagFilters.value.splice(idx, 1)
  } else {
    activeTagFilters.value.push(tagId)
  }
  loadFilteredFiles()
}

const clearAllFilters = () => {
  searchQuery.value = ''
  activeTagFilters.value = []
  activeTypeFilter.value = ''
  loadFilteredFiles()
}

// View mode and sorting
const viewMode = ref(localStorage.getItem('liveshareViewMode') || 'grid')

const setViewMode = (mode) => {
  viewMode.value = mode
  localStorage.setItem('liveshareViewMode', mode)
}

const SORT_OPTIONS = [
  { key: 'name', label: 'Name' },
  { key: 'size', label: 'Size' },
  { key: 'type', label: 'Type' },
  { key: 'uploader', label: 'Added by' },
  { key: 'created_at', label: 'Date added' }
]

const sortKey = ref('created_at')
const sortDir = ref('desc')
const sortMenuOpen = ref(false)
const sortMenuRef = ref(null)

const currentSortLabel = computed(
  () => SORT_OPTIONS.find((o) => o.key === sortKey.value)?.label || 'Sort'
)

const setSort = (key) => {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = key === 'created_at' || key === 'size' ? 'desc' : 'asc'
  }
}

const handleSortMenuClickOutside = (e) => {
  if (sortMenuOpen.value && sortMenuRef.value && !sortMenuRef.value.contains(e.target)) {
    sortMenuOpen.value = false
  }
}

const sortValue = (file, key) => {
  switch (key) {
    case 'name':
      return fileDisplayName(file).toLowerCase()
    case 'size':
      return file.size || 0
    case 'type':
      return (file.type || '').toLowerCase()
    case 'uploader':
      return (file.uploader?.name || '').toLowerCase()
    case 'created_at':
      return new Date(file.created_at || 0).getTime()
    default:
      return 0
  }
}

const displayFiles = computed(() => {
  const list = [...filteredFiles.value]
  list.sort((a, b) => {
    const av = sortValue(a, sortKey.value)
    const bv = sortValue(b, sortKey.value)
    if (av < bv) return sortDir.value === 'asc' ? -1 : 1
    if (av > bv) return sortDir.value === 'asc' ? 1 : -1
    return 0
  })
  return list
})

// Selection and the inspector
const selectMode = ref(false)
const selectedFileIds = ref([])
const activeFileId = ref(null)
const inspectorOpen = ref(true)
let selectionAnchorId = null

const activeFile = computed(() => filteredFiles.value.find((f) => f.id === activeFileId.value) || null)

const selectedFiles = computed(() => displayFiles.value.filter((f) => selectedFileIds.value.includes(f.id)))

// Drop ids that no longer exist in the current result set so the inspector and
// bulk actions never operate on stale files.
const pruneSelection = () => {
  const ids = new Set(filteredFiles.value.map((f) => f.id))
  selectedFileIds.value = selectedFileIds.value.filter((id) => ids.has(id))
  if (activeFileId.value !== null && !ids.has(activeFileId.value)) {
    activeFileId.value = null
  }
  if (selectionAnchorId !== null && !ids.has(selectionAnchorId)) {
    selectionAnchorId = null
  }
}

const toggleSelectMode = () => {
  selectMode.value = !selectMode.value
  if (!selectMode.value) {
    selectedFileIds.value = []
  }
}

const handleSelect = ({ file, additive, range }) => {
  if (range && selectionAnchorId !== null) {
    const ids = displayFiles.value.map((f) => f.id)
    const from = ids.indexOf(selectionAnchorId)
    const to = ids.indexOf(file.id)
    if (from !== -1 && to !== -1) {
      const [start, end] = from < to ? [from, to] : [to, from]
      selectedFileIds.value = ids.slice(start, end + 1)
    }
  } else if (additive || selectMode.value) {
    const idx = selectedFileIds.value.indexOf(file.id)
    if (idx >= 0) {
      selectedFileIds.value.splice(idx, 1)
    } else {
      selectedFileIds.value.push(file.id)
    }
    selectionAnchorId = file.id
  } else {
    selectedFileIds.value = [file.id]
    selectionAnchorId = file.id
  }

  activeFileId.value = file.id
  inspectorOpen.value = true
}

const selectAll = () => {
  selectedFileIds.value = displayFiles.value.map((f) => f.id)
}

const clearSelection = () => {
  selectedFileIds.value = []
  activeFileId.value = null
  selectionAnchorId = null
}

const handleKeydown = (e) => {
  const tag = e.target?.tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA' || e.target?.isContentEditable) return

  if (e.key === 'Escape') {
    if (contextMenu.value) {
      contextMenu.value = null
    } else if (selectedFileIds.value.length > 0) {
      clearSelection()
    }
    return
  }

  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'a' && displayFiles.value.length > 0) {
    e.preventDefault()
    selectAll()
  }
}

// Context menu
const contextMenu = ref(null)

const openContextMenu = ({ file, x, y }) => {
  if (!selectedFileIds.value.includes(file.id)) {
    selectedFileIds.value = [file.id]
    selectionAnchorId = file.id
  }
  activeFileId.value = file.id
  contextMenu.value = { file, x, y }
}

const closeContextMenu = () => {
  contextMenu.value = null
}

const contextMenuIsMulti = computed(
  () => !!contextMenu.value && selectedFileIds.value.length > 1 && selectedFileIds.value.includes(contextMenu.value.file.id)
)

const handleContextDownload = () => {
  const file = contextMenu.value?.file
  closeContextMenu()
  if (!file) return
  if (contextMenuIsMulti.value) {
    handleBulkDownload()
  } else {
    triggerFileDownload(props.liveshareCode, file)
  }
}

const handleContextRemove = () => {
  const file = contextMenu.value?.file
  const multi = contextMenuIsMulti.value
  closeContextMenu()
  if (!file) return
  if (multi) {
    handleBulkRemove()
  } else {
    handleFileRemove(file)
  }
}

const handleContextDetails = () => {
  inspectorOpen.value = true
  closeContextMenu()
}

const handleContextToggleSelect = () => {
  const file = contextMenu.value?.file
  closeContextMenu()
  if (!file) return
  const idx = selectedFileIds.value.indexOf(file.id)
  if (idx >= 0) {
    selectedFileIds.value.splice(idx, 1)
  } else {
    selectedFileIds.value.push(file.id)
  }
}

const handleContextCopyName = async () => {
  const file = contextMenu.value?.file
  closeContextMenu()
  if (!file) return
  try {
    await navigator.clipboard.writeText(fileDisplayName(file))
    toast.success('Name copied')
  } catch (err) {
    toast.error('Could not copy to clipboard')
  }
}

// Permissions
const myRole = computed(() => liveshare.value?.my_role || null)

const canManage = computed(() => {
  return myRole.value === 'owner' || myRole.value === 'manager' || store.admin
})

const canAddFiles = computed(() => {
  return myRole.value === 'owner' || myRole.value === 'manager' || myRole.value === 'collaborator' || store.admin
})

const canRemoveFiles = computed(() => canManage.value)

const startEditing = () => {
  editName.value = liveshare.value.name
  editDescription.value = liveshare.value.description || ''
  editing.value = true
}

const cancelEditing = () => {
  editing.value = false
}

const saveEdits = async () => {
  if (!editName.value.trim()) {
    toast.error('Name is required')
    return
  }

  saving.value = true
  try {
    const updated = await updateLiveshare(props.liveshareCode, {
      name: editName.value.trim(),
      description: editDescription.value.trim() || null
    })
    liveshare.value.name = updated.name
    liveshare.value.description = updated.description
    editing.value = false
    toast.success('Liveshare updated')
  } catch (err) {
    toast.error(err.message || 'Failed to update liveshare')
  }
  saving.value = false
}

const handleFileRemove = async (file) => {
  const confirmed = await confirmDialog.show({
    title: 'Remove File',
    message: `Remove "${fileDisplayName(file)}" from this liveshare?`,
    okText: 'Remove',
    cancelText: 'Cancel'
  })
  if (!confirmed) return

  try {
    await removeLiveshareFile(props.liveshareCode, file.id)
    toast.success('File removed')
    await loadLiveshare()
  } catch (err) {
    toast.error(err.message || 'Failed to remove file')
  }
}

const handleBulkRemove = async () => {
  const files = [...selectedFiles.value]
  if (files.length === 0) return

  const confirmed = await confirmDialog.show({
    title: 'Remove Files',
    message: `Remove ${files.length} files from this liveshare?`,
    okText: 'Remove',
    cancelText: 'Cancel'
  })
  if (!confirmed) return

  let failed = 0
  for (const file of files) {
    try {
      await removeLiveshareFile(props.liveshareCode, file.id)
    } catch (err) {
      failed++
    }
  }

  if (failed > 0) {
    toast.error(`Failed to remove ${failed} of ${files.length} files`)
  } else {
    toast.success(`${files.length} files removed`)
  }

  clearSelection()
  await loadLiveshare()
}

const handleFileDownload = (file) => {
  triggerFileDownload(props.liveshareCode, file)
}

const handleFilesAdded = async () => {
  await loadLiveshare()
}

const handleTagsChanged = async () => {
  await loadTags()
  await loadFilteredFiles()
  await refreshAllFiles()
}

const handleFileTagsChanged = async () => {
  await loadFilteredFiles()
  await refreshAllFiles()
}

// Tag changes affect the rail counts, which read from the unfiltered file list.
const refreshAllFiles = async () => {
  if (!liveshare.value) return
  try {
    liveshare.value.files = await getLiveshareFiles(props.liveshareCode)
  } catch (err) {
    // Counts stay stale until the next full load; not worth interrupting the user.
  }
}

const openTagManagerSheet = () => {
  tagManagerSheetOpen.value = true
}

const closeTagManagerSheet = () => {
  tagManagerSheetOpen.value = false
}

const tagManagerSheetClickOutside = (e) => {
  if (e.target === e.currentTarget) {
    closeTagManagerSheet()
  }
}

const onTagFilterFromGrid = (tagId) => {
  if (!activeTagFilters.value.includes(tagId)) {
    activeTagFilters.value.push(tagId)
    loadFilteredFiles()
  }
}

// Members sheet
const memberManagerRef = ref(null)
const membersSheetOpen = ref(false)

const openMembersSheet = () => {
  membersSheetOpen.value = true
}

const closeMembersSheet = () => {
  membersSheetOpen.value = false
}

const membersSheetClickOutside = (e) => {
  if (e.target === e.currentTarget) {
    closeMembersSheet()
  }
}

const handleMembersChanged = async () => {
  await loadLiveshare()
}

const openCreateInvite = () => {
  memberManagerRef.value?.openCreateInvite()
}

const avatarUrl = computed(() => {
  if (!liveshare.value) return null
  return getLiveshareAvatarUrl(props.liveshareCode)
})

const initials = computed(() => {
  if (!liveshare.value?.name) return ''
  return liveshare.value.name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(w => w[0].toUpperCase())
    .join('')
})

const avatarFailed = ref(false)
const onAvatarError = () => {
  avatarFailed.value = true
}

// Member avatar stack
const MAX_AVATARS = 20

const allPeople = computed(() => {
  if (!liveshare.value) return []

  const people = []

  if (liveshare.value.owner) {
    people.push({
      id: liveshare.value.owner.id,
      name: liveshare.value.owner.name,
      role: 'owner'
    })
  }

  if (liveshare.value.members) {
    for (const member of liveshare.value.members) {
      if (member.user) {
        people.push({
          id: member.user.id,
          name: member.user.name,
          role: member.role
        })
      }
    }
  }

  // Move current user to the end (rightmost = on top)
  const idx = people.findIndex(p => p.id === store.userId)
  if (idx > -1) {
    const [current] = people.splice(idx, 1)
    people.push(current)
  }

  return people
})

const visibleAvatars = computed(() => {
  const all = allPeople.value
  if (all.length <= MAX_AVATARS) return all
  return all.slice(all.length - MAX_AVATARS)
})

const overflowCount = computed(() => {
  return Math.max(0, allPeople.value.length - MAX_AVATARS)
})

const getUserInitials = (name) => {
  if (!name) return '?'
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(w => w[0].toUpperCase())
    .join('')
}

const goHome = () => {
  window.location.href = '/'
}

// Bulk download of the selection, or of everything matching the active filters
const downloading = ref(false)

const handleBulkDownload = async () => {
  downloading.value = true
  try {
    const body = {}
    if (selectedFileIds.value.length > 0) {
      body.fileIds = [...selectedFileIds.value]
    } else {
      if (searchQuery.value.trim()) body.search = searchQuery.value.trim()
      if (activeTagFilters.value.length) body.tags = activeTagFilters.value.join(',')
      if (activeTypeFilter.value) body.type = activeTypeFilter.value
    }

    const url = downloadLiveshareFiles(props.liveshareCode)
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${store.jwt}`
      },
      body: JSON.stringify(body)
    })

    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.message || 'Download failed')
    }

    const blob = await response.blob()
    const downloadUrl = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = downloadUrl
    const datePart = new Date().toISOString().slice(0, 10)
    const safeName = (liveshare.value?.name || 'liveshare').replace(/[^a-zA-Z0-9_-]/g, '_')
    a.download = `${safeName}_${datePart}.zip`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(downloadUrl)
  } catch (err) {
    toast.error(err.message || 'Failed to download files')
  }
  downloading.value = false
}
</script>

<template>
  <div class="liveshare-workspace">
    <!-- Loading -->
    <div class="workspace-loading" v-if="loading">
      <Loader2 class="spin" />
      <span>Loading liveshare...</span>
    </div>

    <!-- Error -->
    <div class="workspace-error" v-else-if="error">
      <p>{{ error }}</p>
      <button class="secondary" @click="goHome">Go Home</button>
    </div>

    <!-- Not logged in -->
    <div class="workspace-error" v-else-if="!store.isLoggedIn()">
      <p>You must be logged in to view this liveshare.</p>
      <button class="secondary" @click="goHome">Go to Login</button>
    </div>

    <!-- Workspace -->
    <div class="workspace-content" v-else-if="liveshare">
      <!-- Header -->
      <div class="workspace-header">
        <div class="header-top" v-if="!editing">
          <button class="secondary icon-only" @click="goHome" title="Back to home">
            <ArrowLeft />
          </button>
          <div class="liveshare-avatar">
            <img
              v-if="avatarUrl && !avatarFailed"
              :src="avatarUrl"
              alt=""
              @error="onAvatarError"
            />
            <span class="avatar-initials">{{ initials }}</span>
          </div>
          <h1>{{ liveshare.name }}</h1>
          <button
            class="secondary icon-only edit-btn"
            @click="startEditing"
            v-if="canManage"
            title="Edit"
          >
            <Edit3 />
          </button>
          <div class="header-spacer"></div>
          <button
            class="header-btn secondary icon-only"
            :class="{ active: selectMode }"
            @click="toggleSelectMode"
            title="Select files"
          >
            <ListChecks />
            <span class="header-badge" v-if="selectedFileIds.length > 0">{{ selectedFileIds.length }}</span>
          </button>
          <button
            class="header-btn secondary icon-only"
            :class="{ active: inspectorOpen }"
            @click="inspectorOpen = !inspectorOpen"
            title="Toggle details panel"
          >
            <PanelRight />
          </button>
          <div class="header-search-wrap">
            <Search class="header-search-icon" />
            <input
              type="text"
              v-model="searchQuery"
              @input="onSearchInput"
              placeholder="Search files..."
              class="header-search-input"
            />
            <button
              v-if="searchQuery"
              class="header-search-clear"
              @click="clearSearch"
              title="Clear search"
            >
              <X />
            </button>
          </div>
          <div class="avatar-stack">
            <span class="avatar-overflow" v-if="overflowCount > 0">+{{ overflowCount }} more</span>
            <div
              class="avatar-circle"
              v-for="(person, index) in visibleAvatars"
              :key="person.id"
              :style="{ zIndex: index }"
              :title="person.name + ' (' + person.role + ')'"
            >
              {{ getUserInitials(person.name) }}
            </div>
          </div>
          <button
            class="members-btn secondary icon-only"
            @click="openMembersSheet"
            title="Members"
          >
            <Users />
          </button>
          <button
            v-if="canManage"
            class="members-btn secondary icon-only"
            style="margin-left: -10px;"
            @click="openTagManagerSheet"
            title="Manage Tags"
          >
            <Tags />
          </button>
        </div>
        <!-- Edit mode row -->
        <div class="header-top header-top--editing" v-else>
          <button class="secondary icon-only" @click="goHome" title="Back to home">
            <ArrowLeft />
          </button>
          <div class="header-edit">
            <div class="edit-fields">
              <input
                type="text"
                v-model="editName"
                placeholder="Liveshare name"
                class="edit-name-input"
                @keyup.enter="saveEdits"
              />
              <input
                type="text"
                v-model="editDescription"
                placeholder="Description (optional)"
                class="edit-desc-input"
                @keyup.enter="saveEdits"
              />
            </div>
            <div class="edit-actions">
              <button @click="saveEdits" :disabled="saving || !editName.trim()">
                <Loader2 v-if="saving" class="spin" />
                <Save v-else />
                Save
              </button>
              <button class="secondary" @click="cancelEditing" :disabled="saving">
                <X />
                Cancel
              </button>
            </div>
          </div>
        </div>
        <p class="header-description" v-if="!editing && liveshare.description">{{ liveshare.description }}</p>
      </div>

      <!-- Main content area: rail | files | inspector -->
      <div class="workspace-main">
        <FilterRail
          class="workspace-rail"
          :files="allFiles"
          :tags="tags"
          :active-type-filter="activeTypeFilter"
          :active-tag-filters="activeTagFilters"
          :can-manage="canManage"
          @selectType="toggleTypeFilter"
          @toggleTag="toggleTagFilter"
          @clearAll="clearAllFilters"
          @manageTags="openTagManagerSheet"
        />

        <div class="workspace-files">
          <div class="files-toolbar">
            <div class="toolbar-left">
              <template v-if="selectedFileIds.length > 0">
                <span class="toolbar-count">{{ selectedFileIds.length }} selected</span>
                <button class="toolbar-link" @click="selectAll">Select all</button>
                <button class="toolbar-link" @click="clearSelection">Clear</button>
              </template>
              <span class="toolbar-count" v-else>
                {{ displayFiles.length }} {{ displayFiles.length === 1 ? 'file' : 'files' }}
              </span>

              <div class="filter-chips" v-if="hasActiveFilters">
                <span class="chip" v-if="searchQuery.trim()">
                  <span>"{{ searchQuery.trim() }}"</span>
                  <button @click="clearSearch"><X /></button>
                </span>
                <span class="chip" v-if="activeTypeFilter">
                  <span>{{ activeTypeFilter }}</span>
                  <button @click="toggleTypeFilter(activeTypeFilter)"><X /></button>
                </span>
                <span
                  class="chip"
                  v-for="tag in activeTagObjects"
                  :key="tag.id"
                  :style="tag.color ? { background: tag.color, color: '#fff' } : {}"
                >
                  <span>{{ tag.name }}</span>
                  <button @click="toggleTagFilter(tag.id)"><X /></button>
                </span>
                <button class="toolbar-link" @click="clearAllFilters">Clear all</button>
              </div>
            </div>

            <div class="toolbar-right">
              <div class="sort-anchor" ref="sortMenuRef">
                <button
                  class="toolbar-btn"
                  @click="sortMenuOpen = !sortMenuOpen"
                  title="Sort files"
                >
                  <ArrowUpDown />
                  <span>{{ currentSortLabel }}</span>
                </button>
                <div class="sort-menu" v-if="sortMenuOpen">
                  <button
                    v-for="option in SORT_OPTIONS"
                    :key="option.key"
                    class="sort-option"
                    :class="{ active: sortKey === option.key }"
                    @click="setSort(option.key)"
                  >
                    <span>{{ option.label }}</span>
                    <Check v-if="sortKey === option.key" />
                  </button>
                  <div class="sort-divider"></div>
                  <button
                    class="sort-option"
                    :class="{ active: sortDir === 'asc' }"
                    @click="sortDir = 'asc'"
                  >
                    <span>Ascending</span>
                    <Check v-if="sortDir === 'asc'" />
                  </button>
                  <button
                    class="sort-option"
                    :class="{ active: sortDir === 'desc' }"
                    @click="sortDir = 'desc'"
                  >
                    <span>Descending</span>
                    <Check v-if="sortDir === 'desc'" />
                  </button>
                </div>
              </div>

              <div class="view-toggle">
                <button
                  class="view-btn"
                  :class="{ active: viewMode === 'grid' }"
                  @click="setViewMode('grid')"
                  title="Grid view"
                >
                  <LayoutGrid />
                </button>
                <button
                  class="view-btn"
                  :class="{ active: viewMode === 'list' }"
                  @click="setViewMode('list')"
                  title="Details view"
                >
                  <List />
                </button>
              </div>
            </div>
          </div>

          <div class="workspace-files-list" :class="{ 'is-list-view': viewMode === 'list' }">
            <FileGrid
              v-if="viewMode === 'grid'"
              :files="displayFiles"
              :select-mode="selectMode"
              :selected-ids="selectedFileIds"
              :active-id="activeFileId"
              :filtered="hasActiveFilters"
              @select="handleSelect"
              @open="handleFileDownload"
              @contextmenu="openContextMenu"
            />
            <FileList
              v-else
              :files="displayFiles"
              :select-mode="selectMode"
              :selected-ids="selectedFileIds"
              :active-id="activeFileId"
              :sort-key="sortKey"
              :sort-dir="sortDir"
              :filtered="hasActiveFilters"
              @select="handleSelect"
              @open="handleFileDownload"
              @contextmenu="openContextMenu"
              @sort="setSort"
            />
          </div>

          <!-- Floating action buttons -->
          <div class="workspace-fabs" v-if="canAddFiles || hasActiveFilters || selectedFileIds.length > 1">
            <button
              v-if="hasActiveFilters || selectedFileIds.length > 1"
              class="fab-button fab-download"
              :class="{ 'fab-loading': downloading }"
              :disabled="downloading"
              @click="handleBulkDownload"
              :title="selectedFileIds.length > 0 ? `Download ${selectedFileIds.length} selected` : 'Download filtered files'"
            >
              <Loader2 v-if="downloading" class="spin" />
              <Download v-else />
            </button>
            <LiveshareUploader
              v-if="canAddFiles"
              :liveshare-long-id="liveshareCode"
              @filesAdded="handleFilesAdded"
            />
          </div>
        </div>

        <FileInspector
          v-if="inspectorOpen"
          class="workspace-inspector"
          :liveshare-long-id="liveshareCode"
          :file="activeFile"
          :selected-files="selectedFiles"
          :tags="tags"
          :can-remove-files="canRemoveFiles"
          :can-tag-files="canAddFiles"
          :downloading="downloading"
          @close="inspectorOpen = false"
          @download="handleFileDownload"
          @remove="handleFileRemove"
          @bulkDownload="handleBulkDownload"
          @bulkRemove="handleBulkRemove"
          @tagsChanged="handleFileTagsChanged"
          @filterByTag="onTagFilterFromGrid"
        />
      </div>

      <!-- Right click menu -->
      <FileContextMenu
        v-if="contextMenu"
        :x="contextMenu.x"
        :y="contextMenu.y"
        :file="contextMenu.file"
        :selected-count="selectedFileIds.length"
        :is-selected="selectedFileIds.includes(contextMenu.file.id)"
        :can-remove-files="canRemoveFiles"
        @close="closeContextMenu"
        @download="handleContextDownload"
        @remove="handleContextRemove"
        @details="handleContextDetails"
        @toggleSelect="handleContextToggleSelect"
        @copyName="handleContextCopyName"
      />

      <!-- Tag manager slide-up sheet -->
      <div
        class="members-sheet-overlay"
        :class="{ active: tagManagerSheetOpen }"
        @click="tagManagerSheetClickOutside"
      >
        <div class="members-sheet">
          <div class="members-sheet-header">
            <h2><Tags /> Manage Tags</h2>
            <div class="members-sheet-header-actions">
              <button class="secondary" @click="tagManagerRef?.openCreateForm()">
                <Plus />
                New Tag
              </button>
              <button class="secondary icon-only" @click="closeTagManagerSheet">
                <X />
              </button>
            </div>
          </div>
          <div class="members-sheet-body">
            <TagManager
              ref="tagManagerRef"
              :liveshare-long-id="liveshareCode"
              :tags="tags"
              @tagsChanged="handleTagsChanged"
            />
          </div>
        </div>
      </div>

      <!-- Members slide-up sheet -->
      <div
        class="members-sheet-overlay"
        :class="{ active: membersSheetOpen }"
        @click="membersSheetClickOutside"
      >
        <div class="members-sheet">
          <div class="members-sheet-header">
            <h2><Users /> Members &amp; Invites</h2>
            <div class="members-sheet-header-actions">
              <button v-if="canManage" class="secondary" @click="openCreateInvite">
                <UserPlus />
                Create Invite
              </button>
              <button class="secondary icon-only" @click="closeMembersSheet">
                <X />
              </button>
            </div>
          </div>
          <div class="members-sheet-body">
            <MemberManager
              ref="memberManagerRef"
              :liveshare-long-id="liveshareCode"
              :members="liveshare.members || []"
              :owner="liveshare.owner"
              :can-manage="canManage"
              @membersChanged="handleMembersChanged"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.liveshare-workspace {
  width: 100%;
  height: calc(100% - 20px);
  display: flex;
  flex-direction: column;
  color: var(--panel-section-text-color);
}

.workspace-loading {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 60px 20px;
  font-size: 1.1rem;
  background: var(--panel-background-color);
  color: var(--panel-text-color);
  border-radius: 8px;

  .spin {
    width: 24px;
    height: 24px;
    animation: spin 1s linear infinite;
  }
}

.workspace-error {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 15px;
  padding: 60px 20px;
  background: var(--panel-background-color);
  color: var(--panel-text-color);
  border-radius: 8px;

  p {
    font-size: 1.1rem;
    margin: 0;
  }
}

.workspace-content {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
}

.workspace-header {
  padding: 20px;
  background: var(--panel-header-background-color);
  border-radius: 8px;
  flex-shrink: 0;

  .header-top {
    display: flex;
    align-items: center;
    gap: 10px;

    &.header-top--editing {
      align-items: flex-start;
    }
  }

  .liveshare-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    position: relative;
    flex-shrink: 0;
    background: var(--primary-button-background-color);

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .avatar-initials {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--primary-button-text-color);
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
      pointer-events: none;
    }
  }

  h1 {
    font-size: 1.4rem;
    margin: 0;
    color: var(--panel-header-text-color);
    white-space: nowrap;
  }

  .edit-btn {
    opacity: 0.4;
    transition: opacity 0.15s;

    &:hover {
      opacity: 1;
    }
  }

  .header-spacer {
    flex: 1;
  }

  .header-description {
    font-size: 0.85rem;
    color: var(--panel-section-text-color);
    opacity: 0.6;
    margin: 8px 0 0 0;
    padding-left: 46px;
  }

  .header-edit {
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex: 1;
    max-width: 400px;

    .edit-fields {
      display: flex;
      flex-direction: column;
      gap: 8px;

      input {
        height: 36px;
        margin: 0;
        box-sizing: border-box;
        border-radius: var(--panel-border-radius);
      }

      .edit-name-input {
        font-size: 1.1rem;
        font-weight: 600;
      }

      .edit-desc-input {
        font-size: 0.85rem;
      }
    }

    .edit-actions {
      display: flex;
      gap: 8px;
    }
  }

  .header-search-wrap {
    position: relative;
    width: 300px;
    flex-shrink: 0;

    .header-search-icon {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      width: 16px;
      height: 16px;
      opacity: 0.4;
      pointer-events: none;
      color: var(--input-text-color);
    }

    .header-search-input {
      width: 100%;
      height: 36px;
      padding: 0 34px;
      font-size: 0.85rem;
      border: 1px solid var(--input-border-color);
      background: var(--input-background-color);
      color: var(--input-text-color);
      border-radius: var(--panel-border-radius);
      box-sizing: border-box;
      margin: 0;
      outline: none;
      transition: border-color 0.15s ease;

      &::placeholder {
        color: var(--input-placeholder-color);
      }

      &:focus {
        border-color: var(--input-border-color-focus);
      }
    }

    .header-search-clear {
      position: absolute;
      right: 4px;
      top: 50%;
      transform: translateY(-50%);
      width: 28px;
      height: 28px;
      border: none;
      background: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0.4;
      color: var(--input-text-color);
      padding: 0;

      &:hover {
        opacity: 1;
      }

      svg {
        width: 14px;
        height: 14px;
        margin: 0;
      }
    }
  }

  .avatar-stack {
    display: flex;
    align-items: center;
    justify-content: flex-end;

    .avatar-overflow {
      font-size: 0.75rem;
      color: var(--panel-section-text-color);
      opacity: 0.6;
      margin-right: 8px;
      white-space: nowrap;
    }

    .avatar-circle {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--primary-button-background-color);
      color: var(--primary-button-text-color);
      font-size: 0.65rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      margin-left: -16px;
      // Separates overlapping avatars; the header text colour is the only solid
      // variable guaranteed to contrast with the header surface.
      border: 2px solid var(--panel-header-text-color);
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
      cursor: default;
      position: relative;

      &:first-of-type {
        margin-left: 0;
      }
    }
  }

  .members-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    svg {
      width: 16px;
      height: 16px;
      margin: 0;
    }
  }
}

// Circular toggles in the header (select mode, details panel)
.header-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  position: relative;

  svg {
    width: 16px;
    height: 16px;
    margin: 0;
  }

  &.active {
    background: var(--primary-button-background-color);
    color: var(--primary-button-text-color);

    &:hover {
      background: var(--primary-button-background-color-hover);
      color: var(--primary-button-text-color-hover);
    }
  }

  .header-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--secondary-button-background-color);
    color: var(--secondary-button-text-color);
    font-size: 0.65rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
  }
}

.workspace-main {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: row;
  gap: 10px;
  margin-top: 10px;
}

.workspace-files {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
  min-height: 0;
  background: var(--panel-background-color);
  border-radius: 8px;
  padding: 14px 16px 16px;
  position: relative;
}

.files-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-shrink: 0;
  padding-bottom: 12px;
}

.toolbar-left {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex-wrap: wrap;
}

.toolbar-count {
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--panel-section-text-color);
  opacity: 0.55;
  white-space: nowrap;
}

.toolbar-link {
  border: none;
  background: none;
  padding: 0;
  margin: 0;
  height: auto;
  font-size: 0.75rem;
  color: var(--link-color);
  cursor: pointer;
  white-space: nowrap;

  &:hover {
    text-decoration: underline;
  }
}

.filter-chips {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  min-width: 0;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 2px 4px 2px 8px;
  border-radius: 11px;
  background: var(--panel-section-background-color-alt);
  color: var(--panel-section-text-color);
  font-size: 0.7rem;
  max-width: 160px;

  > span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  button {
    border: none;
    background: none;
    padding: 0;
    margin: 0;
    height: auto;
    display: flex;
    align-items: center;
    cursor: pointer;
    color: inherit;
    opacity: 0.6;

    &:hover {
      opacity: 1;
    }

    svg {
      width: 11px;
      height: 11px;
      margin: 0;
    }
  }
}

.toolbar-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.sort-anchor {
  position: relative;
}

.toolbar-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  height: 30px;
  margin: 0;
  padding: 0 10px;
  border: 1px solid var(--input-border-color);
  border-radius: var(--panel-border-radius);
  background: transparent;
  color: var(--panel-section-text-color);
  font-size: 0.76rem;
  cursor: pointer;

  &:hover {
    background: var(--panel-section-background-color);
  }

  svg {
    width: 14px;
    height: 14px;
    margin: 0;
    opacity: 0.6;
  }
}

.sort-menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 120;
  width: 175px;
  padding: 5px;
  background: var(--panel-background-color);
  border-radius: var(--panel-border-radius);
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.25);
}

.sort-option {
  width: 100%;
  height: auto;
  margin: 0;
  padding: 7px 9px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  border: none;
  border-radius: calc(var(--panel-border-radius) - 4px);
  background: none;
  color: var(--panel-text-color);
  font-size: 0.78rem;
  text-align: left;
  cursor: pointer;

  &:hover {
    background: var(--panel-section-background-color);
  }

  &.active {
    font-weight: 600;
  }

  svg {
    width: 13px;
    height: 13px;
    margin: 0;
    color: var(--link-color);
  }
}

.sort-divider {
  height: 1px;
  margin: 4px 6px;
  background: var(--panel-section-background-color-alt);
}

.view-toggle {
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 2px;
  border-radius: var(--panel-border-radius);
  background: var(--panel-section-background-color);
}

.view-btn {
  width: 28px;
  height: 26px;
  margin: 0;
  padding: 0;
  border: none;
  border-radius: calc(var(--panel-border-radius) - 4px);
  background: transparent;
  color: var(--panel-section-text-color);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;

  svg {
    width: 15px;
    height: 15px;
    margin: 0;
    opacity: 0.55;
  }

  &:hover svg {
    opacity: 0.9;
  }

  &.active {
    background: var(--panel-background-color);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);

    svg {
      opacity: 1;
    }
  }
}

.workspace-files-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;

  // The details view manages its own scrolling so the column headers can stick.
  &.is-list-view {
    overflow: hidden;
  }
}

.workspace-fabs {
  position: absolute;
  bottom: 20px;
  right: 20px;
  z-index: 10;
  display: flex;
  flex-direction: row;
  align-items: flex-end;
  gap: 10px;
}

.fab-download {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  margin: 0;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.2s ease;

  svg {
    width: 24px;
    height: 24px;
    margin: 0;
  }

  &:hover:not(:disabled) {
    transform: scale(1.08);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
  }

  &:active:not(:disabled) {
    transform: scale(0.96);
  }

  &.fab-loading {
    opacity: 0.7;
    cursor: wait;
  }
}

// Members slide-up sheet
.members-sheet-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: var(--overlay-background-color);
  backdrop-filter: blur(10px);
  z-index: 230;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;

  &.active {
    opacity: 1;
    pointer-events: auto;

    .members-sheet {
      transform: translate(-50%, 0%);
    }
  }
}

.members-sheet {
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translate(-50%, 100%);
  width: min(900px, 100vw);
  max-height: 80vh;
  background: var(--panel-background-color);
  color: var(--panel-text-color);
  border-radius: 10px 10px 0 0;
  box-shadow: 0 0 100px 0 rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
  transition: transform 0.3s ease;
}

.members-sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 20px 10px;
  flex-shrink: 0;
  margin-bottom: 10px;

  h2 {
    margin: 0;
    font-size: 1.2rem;
    color: var(--panel-text-color);
    display: flex;
    align-items: center;
    gap: 10px;

    svg {
      width: 22px;
      height: 22px;
    }
  }

  .members-sheet-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }
}

.members-sheet-body {
  padding: 0 20px 20px;
  overflow-y: auto;
  flex: 1;
  min-height: 0;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (max-width: 1200px) {
  .workspace-inspector {
    display: none;
  }
}

@media (max-width: 960px) {
  .workspace-rail {
    display: none;
  }

  .workspace-header .header-search-wrap {
    width: 200px;
  }
}
</style>
