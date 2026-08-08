<script setup>
import { ref, onMounted } from 'vue'
import {
  createLiveshareEmailInvite,
  createLiveshareLinkInvite,
  getLiveshareInvites,
  revokeLiveshareInvite
} from '../../api'
import {
  Mail,
  Link,
  Trash2,
  Copy,
  Check,
  Loader2,
  QrCode as QrCodeIcon,
  X,
  ArrowLeft,
  Download,
  Share2
} from 'lucide-vue-next'
import { useToast } from 'vue-toastification'
import { useConfirmDialog } from '../../composables/useConfirmDialog'
import QrCode from './qrCode.vue'

const props = defineProps({
  liveshareLongId: { type: String, required: true }
})

const emit = defineEmits(['inviteAccepted'])

const toast = useToast()
const confirmDialog = useConfirmDialog()

// Email invite form
const emailInviteEmail = ref('')
const emailInviteRole = ref('collaborator')
const sendingEmail = ref(false)

// Link invite form
const linkInviteRole = ref('collaborator')
const linkInviteMaxUses = ref(null)
const generatingLink = ref(false)
const generatedLink = ref('')
const linkCopied = ref(false)

// Pending invites list
const invites = ref([])
const loadingInvites = ref(false)

// Per-invite copy state
const copiedInviteId = ref(null)

// QR code modal
const qrModalVisible = ref(false)
const qrModalUrl = ref('')
const qrModalRef = ref(null)

// Fallback URL modal (shown when clipboard copy fails)
const fallbackUrl = ref('')
const fallbackUrlVisible = ref(false)
const fallbackCopied = ref(false)

const showFallbackUrl = (url) => {
  fallbackUrl.value = url
  fallbackUrlVisible.value = true
  fallbackCopied.value = false
}

const closeFallbackUrl = () => {
  fallbackUrlVisible.value = false
  fallbackUrl.value = ''
}

const fallbackOverlayClick = (e) => {
  if (e.target === e.currentTarget) {
    closeFallbackUrl()
  }
}

const copyFallbackUrl = async () => {
  try {
    await navigator.clipboard.writeText(fallbackUrl.value)
    fallbackCopied.value = true
    toast.success('Link copied')
    setTimeout(() => {
      fallbackCopied.value = false
    }, 2000)
  } catch {
    // Still can't copy -- the user can select it manually from the input
  }
}

// Create invite overlay
const createOverlayVisible = ref(false)
const selectedType = ref(null)

const openCreateOverlay = () => {
  selectedType.value = null
  generatedLink.value = ''
  createOverlayVisible.value = true
}

const closeCreateOverlay = () => {
  createOverlayVisible.value = false
  selectedType.value = null
  generatedLink.value = ''
}

const goBackOrClose = () => {
  if (selectedType.value) {
    selectedType.value = null
    generatedLink.value = ''
  } else {
    closeCreateOverlay()
  }
}

const createOverlayClickOutside = (e) => {
  if (e.target === e.currentTarget) {
    closeCreateOverlay()
  }
}

onMounted(() => {
  loadInvites()
})

const loadInvites = async () => {
  loadingInvites.value = true
  try {
    invites.value = await getLiveshareInvites(props.liveshareLongId)
  } catch (error) {
    toast.error(error.message || 'Failed to load invites')
  }
  loadingInvites.value = false
}

const handleSendEmailInvite = async () => {
  if (!emailInviteEmail.value.trim()) return

  sendingEmail.value = true
  try {
    await createLiveshareEmailInvite(props.liveshareLongId, emailInviteEmail.value.trim(), emailInviteRole.value)
    toast.success('Invite sent')
    emailInviteEmail.value = ''
    emailInviteRole.value = 'collaborator'
    closeCreateOverlay()
    loadInvites()
  } catch (error) {
    toast.error(error.message || 'Failed to send invite')
  }
  sendingEmail.value = false
}

const handleGenerateLink = async () => {
  generatingLink.value = true
  try {
    const data = await createLiveshareLinkInvite(
      props.liveshareLongId,
      linkInviteRole.value,
      linkInviteMaxUses.value || null
    )
    generatedLink.value = data.invite_url
    linkCopied.value = false
    loadInvites()
  } catch (error) {
    toast.error(error.message || 'Failed to generate link')
  }
  generatingLink.value = false
}

const copyLink = async () => {
  try {
    await navigator.clipboard.writeText(generatedLink.value)
    linkCopied.value = true
    toast.success('Link copied')
    setTimeout(() => {
      linkCopied.value = false
    }, 2000)
  } catch {
    showFallbackUrl(generatedLink.value)
  }
}

const handleRevokeInvite = async (invite) => {
  const confirmed = await confirmDialog.show({
    title: 'Revoke Invite',
    message: 'Revoke this invite? It will no longer be usable.',
    okText: 'Revoke',
    cancelText: 'Cancel'
  })
  if (!confirmed) return

  try {
    await revokeLiveshareInvite(props.liveshareLongId, invite.id)
    toast.success('Invite revoked')
    // Clear generated link if this was that invite
    if (generatedLink.value && generatedLink.value.includes(invite.token)) {
      generatedLink.value = ''
    }
    loadInvites()
  } catch (error) {
    toast.error(error.message || 'Failed to revoke invite')
  }
}

const copyInviteLink = async (invite) => {
  if (!invite.invite_url) return
  try {
    await navigator.clipboard.writeText(invite.invite_url)
    copiedInviteId.value = invite.id
    toast.success('Link copied')
    setTimeout(() => {
      copiedInviteId.value = null
    }, 2000)
  } catch {
    showFallbackUrl(invite.invite_url)
  }
}

const openQrModal = (url) => {
  qrModalUrl.value = url
  qrModalVisible.value = true
}

const closeQrModal = () => {
  qrModalVisible.value = false
  qrModalUrl.value = ''
}

const qrModalClickOutside = (e) => {
  if (e.target === e.currentTarget) {
    closeQrModal()
  }
}

const getQrCanvas = () => {
  return qrModalRef.value?.$el?.querySelector('canvas') || null
}

const downloadQr = async () => {
  const canvas = getQrCanvas()
  if (!canvas) return

  try {
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'))
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'invite-qr.png'
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    toast.error('Failed to download QR code')
  }
}

const shareQr = async () => {
  // Fall back to copying the URL if Web Share API is unavailable
  if (!navigator.share) {
    try {
      await navigator.clipboard.writeText(qrModalUrl.value)
      toast.success('Link copied to clipboard')
    } catch {
      showFallbackUrl(qrModalUrl.value)
    }
    return
  }

  const canvas = getQrCanvas()
  if (!canvas) return

  try {
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'))
    const file = new File([blob], 'invite-qr.png', { type: 'image/png' })

    const shareData = {
      title: 'Liveshare Invite',
      url: qrModalUrl.value
    }

    // Include the QR image if the browser supports file sharing
    if (navigator.canShare && navigator.canShare({ files: [file] })) {
      shareData.files = [file]
    }

    await navigator.share(shareData)
  } catch (err) {
    // User cancelled share -- not an error
    if (err.name !== 'AbortError') {
      toast.error('Failed to share')
    }
  }
}

const formatDate = (dateStr) => {
  if (!dateStr) return 'Never'
  const d = new Date(dateStr)
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

defineExpose({ openCreateOverlay })
</script>

<template>
  <div class="invite-manager mt-2">
    <!-- Pending Invites Table -->
    <table v-if="invites.length > 0">
      <thead class="secondary">
        <tr>
          <th colspan="99" style="text-align: center">Invites</th>
        </tr>
      </thead>
      <thead>
        <tr>
          <th>Target</th>
          <th>Role</th>
          <th>Uses</th>
          <th>Status</th>
          <th width="1px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="invite in invites" :key="invite.id">
          <tr>
            <td>
              <span class="invite-target">
                <Mail v-if="invite.type === 'email'" class="invite-type-icon" />
                <Link v-else class="invite-type-icon" />
                <span v-if="invite.type === 'email'">{{ invite.email }}</span>
                <span v-else class="invite-target-link">Shareable link</span>
              </span>
            </td>
            <td>{{ invite.role }}</td>
            <td style="text-align: center">{{ invite.use_count }}{{ invite.max_uses ? '/' + invite.max_uses : '' }}</td>
            <td>
              <span class="invite-status" :class="{ expired: !invite.can_be_used }">
                {{ invite.can_be_used ? 'Active' : 'Expired/Used' }}
              </span>
            </td>
            <td>
              <div class="invite-actions">
                <button
                  v-if="invite.type === 'link' && invite.invite_url && invite.can_be_used"
                  class="clear-button icon-only"
                  @click="copyInviteLink(invite)"
                  :title="copiedInviteId === invite.id ? 'Copied' : 'Copy link'"
                >
                  <Check v-if="copiedInviteId === invite.id" />
                  <Copy v-else />
                </button>
                <button
                  v-if="invite.type === 'link' && invite.invite_url && invite.can_be_used"
                  class="clear-button icon-only"
                  @click="openQrModal(invite.invite_url)"
                  title="Show QR code"
                >
                  <QrCodeIcon />
                </button>
                <button
                  class="clear-button icon-only secondary"
                  @click="handleRevokeInvite(invite)"
                  title="Revoke invite"
                >
                  <Trash2 />
                </button>
              </div>
            </td>
          </tr>
        </template>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="99" style="text-align: center">&nbsp;</td>
        </tr>
      </tfoot>
    </table>

    <div class="no-invites" v-else-if="!loadingInvites">
      <p>No pending invites</p>
    </div>

    <!-- Create Invite overlay -->
    <div class="create-invite-overlay" :class="{ visible: createOverlayVisible }" @click="createOverlayClickOutside">
      <div class="create-invite-panel">
        <div class="create-invite-header">
          <button class="secondary icon-only" @click="goBackOrClose">
            <ArrowLeft v-if="selectedType" />
            <X v-else />
          </button>
          <h3 v-if="!selectedType">Create Invite</h3>
          <h3 v-else-if="selectedType === 'email'">Email Invite</h3>
          <h3 v-else>Link / QR Invite</h3>
          <button class="secondary icon-only" @click="closeCreateOverlay" v-if="selectedType">
            <X />
          </button>
        </div>

        <!-- Type selection -->
        <div class="create-invite-body" v-if="!selectedType">
          <p class="type-select-hint">How would you like to invite someone?</p>
          <div class="invite-type-options">
            <button class="invite-type-option" @click="selectedType = 'email'">
              <Mail />
              <span class="option-label">Email Invite</span>
              <span class="option-desc">Send an invitation to a specific email address</span>
            </button>
            <button class="invite-type-option" @click="selectedType = 'link'">
              <Link />
              <span class="option-label">Link / QR Invite</span>
              <span class="option-desc">Generate a shareable link or QR code</span>
            </button>
          </div>
        </div>

        <!-- Email form -->
        <div class="create-invite-body" v-else-if="selectedType === 'email'">
          <div class="overlay-form">
            <label>Email address</label>
            <input
              type="email"
              v-model="emailInviteEmail"
              placeholder="name@example.com"
              @keyup.enter="handleSendEmailInvite"
            />
            <label>Role</label>
            <select v-model="emailInviteRole">
              <option value="manager">Manager</option>
              <option value="collaborator">Collaborator</option>
              <option value="viewer">Viewer</option>
            </select>
            <button
              class="send-btn"
              @click="handleSendEmailInvite"
              :disabled="sendingEmail || !emailInviteEmail.trim()"
            >
              <Loader2 v-if="sendingEmail" class="spin" />
              <Mail v-else />
              Send Invite
            </button>
          </div>
        </div>

        <!-- Link form -->
        <div class="create-invite-body" v-else-if="selectedType === 'link'">
          <div class="overlay-form">
            <label>Role</label>
            <select v-model="linkInviteRole">
              <option value="manager">Manager</option>
              <option value="collaborator">Collaborator</option>
              <option value="viewer">Viewer</option>
            </select>
            <label>Max uses (leave blank for unlimited)</label>
            <input type="number" v-model.number="linkInviteMaxUses" placeholder="Unlimited" min="1" />
            <button class="send-btn" @click="handleGenerateLink" :disabled="generatingLink">
              <Loader2 v-if="generatingLink" class="spin" />
              <Link v-else />
              Generate Link
            </button>
          </div>

          <!-- Generated link display -->
          <div class="generated-link" v-if="generatedLink">
            <div class="link-row">
              <input type="text" :value="generatedLink" readonly class="link-input" @focus="$event.target.select()" />
              <button class="secondary icon-only" @click="copyLink" :title="linkCopied ? 'Copied' : 'Copy link'">
                <Check v-if="linkCopied" />
                <Copy v-else />
              </button>
              <button class="secondary icon-only" @click="openQrModal(generatedLink)" title="Show QR code">
                <QrCodeIcon />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- QR Code modal -->
    <div class="qr-modal-overlay" :class="{ visible: qrModalVisible }" @click="qrModalClickOutside">
      <div class="qr-modal">
        <div class="qr-modal-header">
          <h3>Liveshare QR Code</h3>
          <button class="secondary icon-only" @click="closeQrModal">
            <X />
          </button>
        </div>
        <div class="qr-modal-body">
          <QrCode v-if="qrModalUrl" ref="qrModalRef" :url="qrModalUrl" :size="280" />
        </div>
        <div class="qr-modal-actions">
          <button class="secondary" @click="downloadQr">
            <Download />
            Download
          </button>
          <button class="secondary" @click="shareQr">
            <Share2 />
            Share
          </button>
        </div>
      </div>
    </div>

    <!-- Fallback URL modal (shown when clipboard copy fails) -->
    <div class="fallback-url-overlay" :class="{ visible: fallbackUrlVisible }" @click="fallbackOverlayClick">
      <div class="fallback-url-content">
        <div class="fallback-url-close" @click="closeFallbackUrl">
          <X />
        </div>
        <div class="fallback-url-title">Invite Link</div>
        <div class="fallback-url-row">
          <input type="text" :value="fallbackUrl" readonly class="fallback-url-input" @focus="$event.target.select()" />
          <button class="secondary icon-only" @click="copyFallbackUrl" :title="fallbackCopied ? 'Copied' : 'Copy link'">
            <Check v-if="fallbackCopied" />
            <Copy v-else />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.invite-manager {
  width: 100%;
}

.invite-target {
  display: flex;
  align-items: center;
  gap: 8px;
}

.invite-type-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  opacity: 0.5;
}

.invite-target-link {
  opacity: 0.5;
}

.invite-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  button {
    margin: 0 !important;
    margin-right: 0 !important;
  }
}

.invite-status {
  font-size: 0.7rem;
  font-weight: 500;
  color: var(--panel-section-text-color);
  opacity: 0.7;

  &.expired {
    opacity: 0.4;
  }
}

.no-invites {
  padding: 15px;
  text-align: center;
  color: var(--panel-section-text-color);
  opacity: 0.5;
  font-size: 0.8rem;

  p {
    margin: 0;
  }
}

// Create invite overlay
.create-invite-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: var(--overlay-background-color);
  backdrop-filter: blur(10px);
  z-index: 260;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;

  &.visible {
    opacity: 1;
    pointer-events: auto;

    .create-invite-panel {
      transform: translateY(0);
    }
  }
}

.create-invite-panel {
  background: var(--panel-background-color);
  color: var(--panel-text-color);
  border-radius: 10px;
  box-shadow: 0 0 80px 0 rgba(0, 0, 0, 0.5);
  width: min(480px, calc(100vw - 40px));
  max-height: 80vh;
  overflow-y: auto;
  transform: translateY(20px);
  transition: transform 0.3s ease;
}

.create-invite-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 20px;
  border-bottom: 1px solid var(--input-border-color);

  h3 {
    margin: 0;
    font-size: 1.1rem;
    flex: 1;
  }
}

.create-invite-body {
  padding: 20px;

  .type-select-hint {
    font-size: 0.85rem;
    color: var(--panel-section-text-color);
    opacity: 0.6;
    margin: 0 0 15px 0;
  }
}

.invite-type-options {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.invite-type-option {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  padding: 16px;
  border-radius: 8px;
  background: var(--panel-section-background-color);
  border: 1px solid var(--input-border-color);
  color: var(--panel-section-text-color);
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
  text-align: left;

  &:hover {
    background: var(--panel-section-background-color-alt);
    border-color: var(--input-border-color-focus);
  }

  svg {
    width: 20px;
    height: 20px;
    opacity: 0.7;
    flex-shrink: 0;
  }

  .option-label {
    font-size: 0.95rem;
    font-weight: 600;
  }

  .option-desc {
    width: 100%;
    font-size: 0.75rem;
    opacity: 0.5;
    margin-top: -4px;
  }
}

.overlay-form {
  display: flex;
  flex-direction: column;
  gap: 10px;

  label {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--panel-section-text-color);
    opacity: 0.7;
    margin-bottom: -4px;
  }

  .send-btn {
    margin-top: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
  }
}

.generated-link {
  margin-top: 15px;

  .link-row {
    display: flex;
    gap: 6px;
    align-items: center;

    .link-input {
      flex: 1;
      font-size: 0.8rem;
      font-family: monospace;
      cursor: text;
      min-width: 0;
    }
  }
}

// QR Code modal
.qr-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: var(--overlay-background-color);
  backdrop-filter: blur(10px);
  z-index: 270;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;

  &.visible {
    opacity: 1;
    pointer-events: auto;

    .qr-modal {
      transform: translateY(0);
    }
  }
}

.qr-modal {
  background: var(--panel-background-color);
  color: var(--panel-text-color);
  border-radius: 10px;
  box-shadow: 0 0 80px 0 rgba(0, 0, 0, 0.5);
  width: min(380px, calc(100vw - 40px));
  transform: translateY(20px);
  transition: transform 0.3s ease;
}

.qr-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--input-border-color);

  h3 {
    margin: 0;
    font-size: 1.1rem;
  }
}

.qr-modal-body {
  display: flex;
  justify-content: center;
  padding: 24px 20px;
}

.qr-modal-actions {
  display: flex;
  gap: 8px;
  padding: 0 20px 20px;
  justify-content: center;
}

// Fallback URL modal
.fallback-url-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: var(--overlay-background-color);
  backdrop-filter: blur(10px);
  z-index: 270;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease-in-out;

  &.visible {
    opacity: 1;
    pointer-events: auto;
  }
}

.fallback-url-content {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: var(--panel-background-color);
  padding: 20px;
  border-radius: 10px;
  width: calc(100% - 40px);
  max-width: 500px;

  .fallback-url-title {
    color: var(--panel-text-color);
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 10px;
  }

  .fallback-url-close {
    position: absolute;
    top: 10px;
    right: 10px;
    cursor: pointer;
    color: var(--panel-text-color);
    opacity: 0.6;
    transition: opacity 0.15s;

    &:hover {
      opacity: 1;
    }

    svg {
      width: 20px;
      height: 20px;
    }
  }
}

.fallback-url-row {
  display: flex;
  align-items: center;
  gap: 6px;

  .fallback-url-input {
    flex: 1;
    font-size: 0.8rem;
    font-family: monospace;
    padding: 10px;
    border-radius: 5px;
    background: var(--input-background-color);
    color: var(--input-text-color);
    border: 1px solid var(--input-border-color);
    cursor: text;
    min-width: 0;
  }
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
