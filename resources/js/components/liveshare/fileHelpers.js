import {
  Archive,
  BookOpen,
  File,
  FileSpreadsheet,
  FileText,
  Film,
  Image,
  Music
} from 'lucide-vue-next'
import { downloadLiveshareFile } from '../../api'
import { store } from '../../store'

// Media types mirror the auto-tag names produced by AutoTaggingService, which is
// what the files endpoint matches its `type` filter against.
export const MEDIA_TYPES = [
  { name: 'image', label: 'Images', icon: Image },
  { name: 'video', label: 'Videos', icon: Film },
  { name: 'audio', label: 'Audio', icon: Music },
  { name: 'document', label: 'Documents', icon: FileText },
  { name: 'archive', label: 'Archives', icon: Archive },
  { name: 'ebook', label: 'Ebooks', icon: BookOpen }
]

export const getFileIcon = (type) => {
  if (!type) return File
  if (type.startsWith('image/')) return Image
  if (type.startsWith('video/')) return Film
  if (type.startsWith('audio/')) return Music
  if (type.includes('zip') || type.includes('tar') || type.includes('rar') || type.includes('7z')) return Archive
  if (type.includes('spreadsheet') || type.includes('excel') || type.includes('csv')) return FileSpreadsheet
  if (type.includes('epub') || type.includes('mobipocket')) return BookOpen
  if (type.includes('text') || type.includes('pdf') || type.includes('document')) return FileText
  return File
}

export const fileDisplayName = (file) => file?.original_name || file?.name || ''

export const fileExtension = (file) => {
  const name = fileDisplayName(file)
  if (!name.includes('.')) return ''
  return name.split('.').pop().toUpperCase()
}

// niceFileType() in utils assumes a well formed "type/subtype" string, so fall
// back to the file extension for anything unusual.
export const niceType = (file) => {
  const type = file?.type
  if (type && type !== 'unknown' && type.includes('/')) {
    return type.split('/')[1].split('+')[0].split('.').pop().toLowerCase()
  }
  return fileExtension(file).toLowerCase() || 'unknown'
}

export const thumbnailSrc = (file, size = 'small') => {
  if (!file?.thumbnail_url) return null
  return `${file.thumbnail_url}?size=${size}&token=${store.jwt}`
}

export const customTagsOf = (file) => (file?.tags || []).filter((t) => t.type === 'custom')

export const autoTagsOf = (file) => (file?.tags || []).filter((t) => t.type === 'auto')

export const countByAutoTag = (files, name) =>
  files.filter((f) => (f.tags || []).some((t) => t.type === 'auto' && t.name === name)).length

export const countByTagId = (files, tagId) =>
  files.filter((f) => (f.tags || []).some((t) => t.id === tagId)).length

export const niceDateTime = (value) => {
  if (!value) return '--'
  const date = new Date(value)
  if (isNaN(date.getTime())) return '--'
  return date.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

export const niceShortDate = (value) => {
  if (!value) return '--'
  const date = new Date(value)
  if (isNaN(date.getTime())) return '--'
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

export const triggerFileDownload = (liveshareLongId, file) => {
  const url = downloadLiveshareFile(liveshareLongId, file.id)
  const link = document.createElement('a')
  link.href = `${url}?token=${store.jwt}`
  link.target = '_blank'
  link.download = fileDisplayName(file)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}
