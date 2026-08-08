import { reactive, nextTick } from 'vue'
import { useToast } from 'vue-toastification'
import mitt from 'mitt'
import debounce from './debounce'
const emitter = mitt()
const toast = useToast()

const uploadController = reactive({
  pause: false,
  pauseUpload() {
    this.pause = true
  },
  resumeUpload() {
    this.pause = false
  }
})

const store = reactive({
  userId: null,
  admin: false,
  guest: false,
  restricted: false,
  jwt: null,
  jwtExpires: null,
  loggedIn: false,
  settingsOpen: false,
  mode: 'upload',
  shareCode: null,
  liveshareCode: null,
  inviteToken: null,
  mustChangePassword: false,

  setUserId(userId) {
    this.userId = parseInt(userId)
  },

  setAdmin(admin) {
    this.admin = admin
  },

  setJwt(jwt) {
    this.jwt = jwt
  },

  setJwtExpires(jwtExpires) {
    this.jwtExpires = new Date(jwtExpires * 1000)
  },

  setLoggedIn(loggedIn) {
    this.loggedIn = loggedIn
  },

  setSettingsOpen(settingsOpen) {
    this.settingsOpen = settingsOpen
  },

  setMode(mode) {
    this.mode = mode
  },

  setShareCode(shareCode) {
    this.shareCode = shareCode
  },

  setLiveshareCode(liveshareCode) {
    this.liveshareCode = liveshareCode
  },

  setRestricted(restricted) {
    this.restricted = restricted
  },

  setInviteToken(inviteToken) {
    this.inviteToken = inviteToken
  },

  isRestricted() {
    return this.restricted
  },

  setMultiple(data) {
    const keys = Object.keys(data)
    keys.forEach(key => {
      this[`set${key.charAt(0).toUpperCase() + key.slice(1)}`](data[key])
    })
  },

  isAdmin() {
    return this.admin
  },

  isLoggedIn() {
    return this.loggedIn
  },

  isGuest() {
    return this.guest
  },

  authSuccess(data) {
    this.setMultiple({
      userId: data.userId,
      admin: data.admin,
      jwt: data.jwt,
      jwtExpires: data.jwtExpires,
      loggedIn: data.loggedIn
    })
    this.mustChangePassword = data.mustChangePassword
    this.guest = data.guest
    this.restricted = data.restricted || false
    this.logState()
  },

  logState() {  
    //no
  },

  autoShowProfileEdit: false,
  showPasswordResetForm() {
    this.autoShowProfileEdit = true
    emitter.emit('showPasswordResetForm')
  },

  
})

const showResetPasswordToast = () => {
  toast.error('You must change your password to continue')
}

const debouncedShowResetPasswordToast = debounce(showResetPasswordToast, 100)




export { emitter, store, uploadController }