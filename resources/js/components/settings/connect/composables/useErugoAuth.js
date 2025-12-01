import { ref } from 'vue'
import {
  cloudConnectRegister,
  cloudConnectLogin,
  cloudConnectLogout,
  cloudConnectForgotPassword
} from '../../../../api'
import { useToast } from 'vue-toastification'
import { useTranslate } from '@tolgee/vue'

export function useErugoAuth(onAuthStateChange) {
  const { t } = useTranslate()
  const toast = useToast()

  // State
  const loginForm = ref({
    email: '',
    password: ''
  })

  const registerForm = ref({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    accept_terms: false,
    accept_privacy: false,
    accept_marketing: false
  })

  const showLoginForm = ref(false)
  const showRegisterForm = ref(false)
  const showForgotPasswordForm = ref(false)
  const forgotPasswordEmail = ref('')
  const sendingForgotPassword = ref(false)
  const authLoading = ref(false)

  // Methods
  const handleLogin = async () => {
    try {
      authLoading.value = true
      await cloudConnectLogin(loginForm.value.email, loginForm.value.password)
      toast.success(t.value('cloudConnect.loginSuccess'))
      showLoginForm.value = false
      if (onAuthStateChange) {
        await onAuthStateChange()
      }
    } catch (error) {
      toast.error(error.message || t.value('cloudConnect.loginFailed'))
    } finally {
      authLoading.value = false
    }
  }

  const handleRegister = async () => {
    if (registerForm.value.password !== registerForm.value.password_confirmation) {
      toast.error(t.value('cloudConnect.passwordMismatch'))
      return
    }

    if (!registerForm.value.accept_terms || !registerForm.value.accept_privacy) {
      toast.error(t.value('cloudConnect.mustAcceptTerms'))
      return
    }

    try {
      authLoading.value = true
      await cloudConnectRegister(registerForm.value)
      toast.success(t.value('cloudConnect.registerSuccess'))
      // After registration, try to login
      await cloudConnectLogin(registerForm.value.email, registerForm.value.password)
      showRegisterForm.value = false
      if (onAuthStateChange) {
        await onAuthStateChange()
      }
    } catch (error) {
      toast.error(error.message || t.value('cloudConnect.registerFailed'))
    } finally {
      authLoading.value = false
    }
  }

  const handleLogout = async () => {
    try {
      authLoading.value = true
      await cloudConnectLogout()
      toast.success(t.value('cloudConnect.logoutSuccess'))
      if (onAuthStateChange) {
        await onAuthStateChange()
      }
    } catch (error) {
      toast.error(error.message || t.value('cloudConnect.logoutFailed'))
    } finally {
      authLoading.value = false
    }
  }

  const openLoginForm = () => {
    showLoginForm.value = true
    showRegisterForm.value = false
  }

  const openRegisterForm = () => {
    showRegisterForm.value = true
    showLoginForm.value = false
  }

  const closeAuthForms = () => {
    showLoginForm.value = false
    showRegisterForm.value = false
  }

  const openForgotPasswordForm = (currentEmail = '') => {
    forgotPasswordEmail.value = currentEmail
    showForgotPasswordForm.value = true
  }

  const closeForgotPasswordForm = () => {
    showForgotPasswordForm.value = false
    forgotPasswordEmail.value = ''
  }

  const handleForgotPassword = async () => {
    if (!forgotPasswordEmail.value) {
      toast.error(t.value('cloudConnect.auth.emailRequired'))
      return
    }

    try {
      sendingForgotPassword.value = true
      await cloudConnectForgotPassword(forgotPasswordEmail.value)
      toast.success(t.value('cloudConnect.auth.resetEmailSent'))
      closeForgotPasswordForm()
    } catch (error) {
      // Still show success to not reveal if email exists
      toast.success(t.value('cloudConnect.auth.resetEmailSent'))
      closeForgotPasswordForm()
    } finally {
      sendingForgotPassword.value = false
    }
  }

  const loginFormClickOutside = (event) => {
    if (!event.target.closest('.auth-slide-form')) {
      showLoginForm.value = false
    }
  }

  const registerFormClickOutside = (event) => {
    if (!event.target.closest('.auth-slide-form')) {
      showRegisterForm.value = false
    }
  }

  return {
    // State
    loginForm,
    registerForm,
    showLoginForm,
    showRegisterForm,
    showForgotPasswordForm,
    forgotPasswordEmail,
    sendingForgotPassword,
    authLoading,

    // Methods
    handleLogin,
    handleRegister,
    handleLogout,
    openLoginForm,
    openRegisterForm,
    closeAuthForms,
    openForgotPasswordForm,
    closeForgotPasswordForm,
    handleForgotPassword,
    loginFormClickOutside,
    registerFormClickOutside
  }
}

