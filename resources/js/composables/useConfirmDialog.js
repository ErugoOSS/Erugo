import { ref } from 'vue'

const isActive = ref(false)
const title = ref('')
const message = ref('')
const okText = ref('OK')
const cancelText = ref('Cancel')
let resolvePromise = null

export function useConfirmDialog() {
  const show = (options = {}) => {
    return new Promise((resolve) => {
      title.value = options.title || 'Confirm'
      message.value = options.message || 'Are you sure?'
      okText.value = options.okText || 'OK'
      cancelText.value = options.cancelText || 'Cancel'
      isActive.value = true
      resolvePromise = resolve
    })
  }

  const confirm = () => {
    if (resolvePromise) {
      resolvePromise(true)
      resolvePromise = null
    }
    isActive.value = false
  }

  const cancel = () => {
    if (resolvePromise) {
      resolvePromise(false)
      resolvePromise = null
    }
    isActive.value = false
  }

  return {
    isActive,
    title,
    message,
    okText,
    cancelText,
    show,
    confirm,
    cancel
  }
}

