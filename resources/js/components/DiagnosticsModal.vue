<script setup>
import { ref } from 'vue'
import { ShieldCheck, Copy, Check, TriangleAlert, X } from 'lucide-vue-next'

const props = defineProps({
  decryptionKey: { type: String, required: true },
  filename:      { type: String, required: true },
})
const emit = defineEmits(['close'])

const copied = ref(false)

const copyKey = async () => {
  try {
    await navigator.clipboard.writeText(props.decryptionKey)
    copied.value = true
    setTimeout(() => { copied.value = false }, 3000)
  } catch {
    // fallback: select the text
    const el = document.getElementById('diag-key-text')
    if (el) { el.select(); document.execCommand('copy') }
  }
}
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-box">

      <div class="modal-header">
        <ShieldCheck class="header-icon" />
        <h3>Diagnostics Bundle Ready</h3>
        <button class="close-btn" @click="emit('close')"><X /></button>
      </div>

      <p class="intro">
        The encrypted diagnostics bundle <strong>{{ filename }}</strong> is
        downloading in the background.  You need the decryption code below to
        open it.
      </p>

      <div class="key-area">
        <label class="key-label">Decryption code</label>
        <div class="key-row">
          <input
            id="diag-key-text"
            class="key-input"
            type="text"
            :value="decryptionKey"
            readonly
          />
          <button class="copy-btn" @click="copyKey" :class="{ copied }">
            <Check v-if="copied" />
            <Copy v-else />
            {{ copied ? 'Copied!' : 'Copy' }}
          </button>
        </div>
      </div>

      <div class="warning-box">
        <TriangleAlert />
        <div>
          <strong>Save this code now — it will not be shown again.</strong>
          <p>
            The ZIP is encrypted with AES-256.  Enter this code when opening the
            archive in any standard ZIP application (7-Zip, macOS Archive Utility,
            Windows Explorer, etc.).
          </p>
        </div>
      </div>

      <div class="modal-footer">
        <button @click="emit('close')">Done</button>
      </div>

    </div>
  </div>
</template>

<style lang="scss" scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-box {
  background: var(--panel-section-background-color);
  border-radius: 10px;
  padding: 24px;
  min-width: 380px;
  max-width: 540px;
  width: 100%;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.modal-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;

  .header-icon { width: 1.3rem; height: 1.3rem; color: #10b981; flex-shrink: 0; }
  h3 { flex: 1; margin: 0; font-size: 1.1rem; color: var(--panel-section-text-color); }

  .close-btn {
    background: none; border: none; padding: 4px; cursor: pointer;
    display: flex; align-items: center; color: var(--panel-section-text-color); opacity: 0.6;
    &:hover { opacity: 1; }
    svg { width: 1rem; height: 1rem; }
    margin-right: 0 !important;
  }
}

.intro {
  font-size: 0.88rem;
  color: var(--panel-section-text-color);
  opacity: 0.85;
  margin: 0 0 18px;
  line-height: 1.5;
}

.key-area {
  margin-bottom: 18px;

  .key-label {
    display: block;
    font-size: 0.8rem;
    color: var(--panel-section-text-color);
    opacity: 0.65;
    margin-bottom: 6px;
  }

  .key-row {
    display: flex;
    gap: 8px;
    align-items: center;
  }

  .key-input {
    flex: 1;
    font-family: monospace;
    font-size: 1rem;
    letter-spacing: 0.05em;
    padding: 10px 12px;
    border-radius: 6px;
    border: 1px solid rgba(128, 128, 128, 0.3);
    background: var(--panel-section-background-color-alt);
    color: var(--panel-section-text-color);
    box-sizing: border-box;
    cursor: text;
    // Override global input styles
    width: auto;
    height: auto;
    margin-bottom: 0;
    &:focus { outline: 2px solid var(--primary-color, #4f6ef7); border-color: transparent; }
  }

  .copy-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    border-radius: 6px;
    border: none;
    font-size: 0.88rem;
    cursor: pointer;
    white-space: nowrap;
    background: var(--primary-color, #4f6ef7);
    color: #fff;
    transition: background 0.15s, opacity 0.15s;
    margin-right: 0 !important;

    &.copied { background: #10b981; }
    &:hover { opacity: 0.9; }

    svg { width: 0.9rem; height: 0.9rem; }
  }
}

.warning-box {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.4);
  border-radius: 8px;
  padding: 14px;
  margin-bottom: 20px;

  svg {
    width: 1.1rem;
    height: 1.1rem;
    color: #f59e0b;
    flex-shrink: 0;
    margin-top: 2px;
  }

  strong {
    display: block;
    font-size: 0.88rem;
    color: var(--panel-section-text-color);
    margin-bottom: 4px;
  }

  p {
    margin: 0;
    font-size: 0.82rem;
    color: var(--panel-section-text-color);
    opacity: 0.8;
    line-height: 1.5;
  }
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
}
</style>
