<script setup>
import { ref, computed } from 'vue'
import { Search } from 'lucide-vue-next'
const props = defineProps({
  modelValue: {
    type: [File, null, String],
    required: true
  },
  accept: {
    type: String,
    default: '*'
  },
  label: {
    type: String,
    default: 'Select File'
  },
  preview: {
    type: Object,
    default: null,
    validator: (value) => {
      if (!value) return true
      return value.url && typeof value.url === 'string'
    }
  }
})

const fileInput = ref(null)

//emit
const emit = defineEmits(['update:modelValue'])

//methods
const handleFileChange = (event) => {
  emit('update:modelValue', event.target.files[0])
}

const triggerFileInput = () => {
  fileInput.value.click()
}

const buttonMessage = computed(() => {
  let message = ''
  if (props.modelValue) {
    message = sensibleButtonMessage(typeof props.modelValue === 'string' ? props.modelValue : props.modelValue.name)
  } else {
    message = props.label
  }
  return message
})

const sensibleButtonMessage = (message) => {
  const maxLength = 25
  if (message.length > maxLength) {
    return message.slice(0, maxLength) + `&hellip;`
  }
  return message
}
</script>

<template>
  <div class="file-input">
    <input type="file" @change="handleFileChange" ref="fileInput" :accept="accept" />
    <div class="file-input-button" @click="triggerFileInput">
      <div class="file-input-preview" v-if="preview">
        <img :src="preview.url" :alt="preview.filename || 'Preview'" class="preview-img" />
      </div>
      <div class="file-label" v-html="buttonMessage"></div>
      <div class="file-input-actions">
        <button class="file-input-search" @click.stop="triggerFileInput">
          <Search />
        </button>
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.file-input {
  position: relative;
  width: 100%;
}
input[type='file'] {
  display: none;
}
.file-input-button {
  width: 100%;
  padding: 10px;
  border-radius: 5px;
  border: none;
  background: var(--input-background-color);
  color: var(--input-text-color);
  margin-bottom: 10px;
  border: 1px solid var(--input-border-color);
  transition: all 0.3s ease-in-out;
  outline: 2px solid transparent;
  height: 50px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  .file-input-preview {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    .preview-img {
      width: 32px;
      height: 32px;
      object-fit: contain;
      border-radius: 3px;
    }
  }
  .file-label {
    flex: 1;
    font-style: italic;
    padding-right: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .file-input-actions {
    display: flex;
    align-items: center;
    gap: 0px;
    flex-shrink: 0;
    :deep(button) {
      height: 40px;
      width: 40px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      svg {
        width: 20px;
        height: 20px;
        margin: 0;
      }
    }
  }
  button {
    height: 40px;
    width: 40px;
    padding:0;
    svg {
      width: 20px;
      height: 20px;
      margin: 0;
    }
  }
}
</style>
