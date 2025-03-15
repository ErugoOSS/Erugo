import { createApp } from 'vue'
import { Tolgee, DevTools, VueTolgee } from '@tolgee/vue'
import { FormatIcu } from '@tolgee/format-icu'

import './style.scss'
import 'vue-color-kit/dist/vue-color-kit.css'
import App from './App.vue'
import Toast, { POSITION } from 'vue-toastification'
import 'vue-toastification/dist/index.css'
import { domData } from './domData'

//import languages
import en from './i18n/en.json'
import de from './i18n/de.json'
import fr from './i18n/fr.json'
import it from './i18n/it.json'
import nl from './i18n/nl.json'
import pt from './i18n/pt.json'
let clickOutsideEvent = null

const showHelpTip = (event, helpTipId) => {
  const helpTip = document.querySelector(helpTipId)
  const cssShiftAmount = -15
  if (!helpTip) return

  helpTip.classList.add('no-transition')
  
  // Calculate initial position
  let top = event.clientY - helpTip.offsetHeight - 10
  let left = event.clientX - helpTip.offsetWidth / 2

  // Check top boundary - account for the shift amount
  if (top + cssShiftAmount < 0) {
    top = -cssShiftAmount // This ensures the tip is at the very top after the shift is applied
  }
  
  // Check left boundary
  if (left < 0) {
    left = 0
  }
  
  // Check right boundary
  if (left + helpTip.offsetWidth > window.innerWidth) {
    left = window.innerWidth - helpTip.offsetWidth
  }
  
  // Check bottom boundary - account for the shift amount
  if (top + cssShiftAmount + helpTip.offsetHeight > window.innerHeight) {
    top = window.innerHeight - helpTip.offsetHeight - cssShiftAmount
  }

  // Apply the position
  helpTip.style.top = `${top}px`
  helpTip.style.left = `${left}px`

  // Make the tooltip visible with a slight delay
  setTimeout(() => {
    helpTip.classList.remove('no-transition')
    helpTip.classList.add('visible')
    helpTip.classList.add('shifted-up')
  }, 1)

  // Register click-outside event to hide the tooltip
  clickOutsideEvent = document.addEventListener('click', (event) => {
    if (!helpTip.contains(event.target)) {
      hideHelpTip(helpTipId)
    }
  })
}

const hideHelpTip = (helpTipId) => {
  const helpTip = document.querySelector(helpTipId)
  if (!helpTip) return

  helpTip.classList.remove('visible')
  helpTip.classList.remove('shifted-up')

  //unregister click-outside event
  document.removeEventListener('click', clickOutsideEvent)
}

//use a mutation observer to recursively watch the dom for any .slide-text elements that might appear
const observer = new MutationObserver((mutations) => {
  const processNode = (node) => {
    if (node.classList && node.classList.contains('slide-text')) {
      const innerContent = node.querySelector('.content')
      if (innerContent) {
        const outerWidth = node.offsetWidth
        const innerWidth = innerContent.offsetWidth
        if (innerWidth > outerWidth) {
          innerContent.classList.add('needs-slide')
          node.classList.add('has-slide')
          innerContent.style.setProperty('--content-width', `${innerWidth}px`)
          const animationTime = Math.max(innerWidth / 100, 20) // Ensure a minimum animation time of 20 seconds
          innerContent.style.setProperty('--animation-time', `${animationTime}s`)
        }
      }
    }
    node.childNodes.forEach((child) => processNode(child))
  }

  mutations.forEach((mutation) => {
    mutation.addedNodes.forEach((node) => {
      processNode(node)
    })
  })
})

observer.observe(document.body, { childList: true, subtree: true })

const tolgee = Tolgee().use(DevTools()).use(FormatIcu()).init({
  language: localStorage.getItem('language') || domData().default_language || 'en',

  // for development
  apiUrl:  import.meta.env.VITE_APP_TOLGEE_API_URL,
  apiKey:  import.meta.env.VITE_APP_TOLGEE_API_KEY,

  // for production
  staticData: {
    en,
    de,
    fr,
    it,
    nl,
    pt
  }
})


createApp(App)
  .use(Toast, {
    position: POSITION.BOTTOM_RIGHT,
    transition: "Vue-Toastification__fade",
  })
  .use(VueTolgee, { tolgee })
  .provide('showHelpTip', showHelpTip)
  .provide('hideHelpTip', hideHelpTip)
  .mount('#app')
