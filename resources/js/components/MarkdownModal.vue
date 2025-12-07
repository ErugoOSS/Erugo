<script setup>
import { ref, watch, computed } from 'vue'
import { CircleX, FileWarning } from 'lucide-vue-next'
import { useTolgee } from '@tolgee/vue'

const props = defineProps({
  topic: {
    type: String,
    required: true
  },
  title: {
    type: String,
    default: ''
  }
})

const tolgee = useTolgee(['language'])
const isActive = ref(false)
const content = ref('')
const loading = ref(false)
const error = ref(false)

const currentLanguage = computed(() => {
  return tolgee.value.getLanguage() || 'en'
})

const fetchMarkdown = async () => {
  if (!props.topic) return
  
  loading.value = true
  error.value = false
  content.value = ''
  
  const lang = currentLanguage.value
  
  // Try current language first, fall back to English
  const urls = [
    `/md/${lang}/${props.topic}.md`,
    `/md/en/${props.topic}.md`
  ]
  
  for (const url of urls) {
    try {
      const response = await fetch(url)
      if (response.ok) {
        const text = await response.text()
        content.value = await parseMarkdown(text)
        loading.value = false
        return
      }
    } catch (e) {
      // Continue to next URL
    }
  }
  
  // All attempts failed
  error.value = true
  loading.value = false
}

const parseMarkdown = async (text) => {
  // Store code blocks temporarily to protect them from other transformations
  const codeBlocks = []
  
  // Extract and protect code blocks first
  text = text.replace(/```(\w*)\n([\s\S]*?)```/g, (match, lang, code) => {
    const placeholder = `___CODEBLOCK_${codeBlocks.length}___`
    codeBlocks.push(`<pre><code class="language-${lang || 'text'}">${escapeHtml(code.trimEnd())}</code></pre>`)
    return placeholder
  })
  
  // Headers
  text = text.replace(/^### (.*$)/gim, '<h3>$1</h3>')
  text = text.replace(/^## (.*$)/gim, '<h2>$1</h2>')
  text = text.replace(/^# (.*$)/gim, '<h1>$1</h1>')
  
  // Bold and italic
  text = text.replace(/\*\*\*(.*?)\*\*\*/g, '<strong><em>$1</em></strong>')
  text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
  text = text.replace(/\*(.*?)\*/g, '<em>$1</em>')
  
  // Inline code
  text = text.replace(/`([^`]+)`/g, '<code>$1</code>')
  
  // Links
  text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
  
  // Process lists - collect consecutive list items
  const lines = text.split('\n')
  const processedLines = []
  let listBuffer = []
  let inList = false
  
  const flushList = () => {
    if (listBuffer.length > 0) {
      processedLines.push('<ul>' + listBuffer.join('') + '</ul>')
      listBuffer = []
    }
    inList = false
  }
  
  for (let line of lines) {
    const trimmed = line.trim()
    
    // Check for unordered list item
    const listMatch = trimmed.match(/^[-*]\s+(.*)$/)
    if (listMatch) {
      inList = true
      listBuffer.push(`<li>${listMatch[1]}</li>`)
      continue
    }
    
    // Not a list item - flush any pending list
    if (inList) {
      flushList()
    }
    
    // Empty line
    if (!trimmed) {
      processedLines.push('')
      continue
    }
    
    // Code block placeholder - pass through as-is
    if (trimmed.startsWith('___CODEBLOCK_')) {
      processedLines.push(trimmed)
      continue
    }
    
    // Already an HTML tag
    if (trimmed.startsWith('<')) {
      processedLines.push(line)
      continue
    }
    
    // Regular text - wrap in paragraph
    processedLines.push(`<p>${trimmed}</p>`)
  }
  
  // Flush any remaining list
  flushList()
  
  // Join and restore code blocks
  let result = processedLines.join('\n')
  
  // Restore code blocks
  codeBlocks.forEach((block, index) => {
    result = result.replace(`___CODEBLOCK_${index}___`, block)
  })
  
  return result
}

const escapeHtml = (text) => {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }
  return text.replace(/[&<>"']/g, m => map[m])
}

const open = () => {
  isActive.value = true
  fetchMarkdown()
}

const close = () => {
  isActive.value = false
}

const handleClickOutside = (event) => {
  if (!event.target.closest('.markdown-modal-form')) {
    close()
  }
}

// Watch for language changes to reload content
watch(currentLanguage, () => {
  if (isActive.value) {
    fetchMarkdown()
  }
})

// Expose methods for parent component
defineExpose({
  open,
  close
})
</script>

<template>
  <div class="markdown-modal-overlay" :class="{ active: isActive }" @click="handleClickOutside">
    <div class="markdown-modal-form">
      <div class="markdown-modal-header">
        <h2 v-if="title">
          <FileWarning />
          {{ title }}
        </h2>
        <button class="close-btn icon-only secondary" @click="close">
          <CircleX />
        </button>
      </div>
      
      <div class="markdown-modal-content">
        <div v-if="loading" class="loading">Loading...</div>
        <div v-else-if="error" class="error">
          <p>Unable to load documentation.</p>
        </div>
        <div v-else class="markdown-body" v-html="content"></div>
      </div>
      
      <div class="button-bar">
        <button class="secondary" @click="close">
          <CircleX />
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.markdown-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: var(--overlay-background-color);
  backdrop-filter: blur(10px);
  z-index: 9998;
  opacity: 0;
  pointer-events: none;
  transition: all 0.3s ease;

  .markdown-modal-form {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translate(-50%, 100%);
    width: min(700px, 100vw);
    max-height: 80vh;
    background: var(--panel-background-color);
    color: var(--panel-text-color);
    padding: 20px;
    border-radius: 10px 10px 0 0;
    box-shadow: 0 0 100px 0 rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    gap: 15px;
    transition: all 0.3s ease;
  }

  &.active {
    opacity: 1;
    pointer-events: auto;
    .markdown-modal-form {
      transform: translate(-50%, 0%);
    }
  }
}

.markdown-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  
  h2 {
    margin: 0;
    font-size: 22px;
    color: var(--panel-text-color);
    display: flex;
    align-items: center;
    gap: 10px;

    svg {
      width: 24px;
      height: 24px;
    }
  }
  
  .close-btn {
    flex-shrink: 0;
  }
}

.markdown-modal-content {
  flex: 1;
  overflow-y: auto;
  padding-right: 10px;
  
  .loading, .error {
    text-align: center;
    padding: 40px 20px;
    color: var(--panel-text-color);
    opacity: 0.7;
  }
}

.markdown-body {
  color: var(--panel-text-color);
  line-height: 1.6;
  
  :deep(h1) {
    font-size: 1.5em;
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--panel-border-color);
  }
  
  :deep(h2) {
    font-size: 1.3em;
    margin: 20px 0 10px 0;
  }
  
  :deep(h3) {
    font-size: 1.1em;
    margin: 15px 0 8px 0;
  }
  
  :deep(p) {
    margin: 0 0 12px 0;
  }
  
  :deep(ul), :deep(ol) {
    margin: 0 0 12px 0;
    padding-left: 25px;
  }
  
  :deep(li) {
    margin: 5px 0;
  }
  
  :deep(code) {
    background: var(--panel-item-background-color);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'SF Mono', Monaco, 'Courier New', monospace;
    font-size: 0.9em;
  }
  
  :deep(pre) {
    background: var(--panel-item-background-color);
    padding: 15px;
    border-radius: 8px;
    overflow-x: auto;
    margin: 0 0 15px 0;
    
    code {
      background: none;
      padding: 0;
      font-size: 0.85em;
      line-height: 1.4;
      white-space: pre;
      display: block;
      font-family: 'SF Mono', Monaco, Consolas, 'Courier New', monospace;
    }
  }
  
  :deep(a) {
    color: var(--link-color);
    text-decoration: underline;
    
    &:hover {
      opacity: 0.8;
    }
  }
  
  :deep(strong) {
    font-weight: 600;
  }
}

.button-bar {
  display: flex;
  gap: 10px;
  
  button {
    flex: 1;
  }
}
</style>

