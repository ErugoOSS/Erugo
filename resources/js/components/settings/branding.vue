<script setup>
import { ref, onMounted, watch, defineExpose, onBeforeUnmount, computed } from 'vue'
import { Pipette, Image, Ruler, Tag, X, Dice5, Images, FileDown, Trash, FileUp, Clock8, RotateCcw   } from 'lucide-vue-next'
import injectThemeVariables from '../../lib/injectThemeVariables'
import ButtonWithMenu from '../buttonWithMenu.vue'

import {
  getSettingsByGroup,
  saveSettingsById,
  saveLogo,
  resetLogo,
  saveFavicon,
  deleteFavicon,
  resetFavicon,
  getFaviconStatus,
  getBackgroundImages,
  saveBackgroundImage,
  deleteBackgroundImage,
  saveMobileBackground,
  deleteMobileBackground,
  getThemes,
  setActiveTheme,
  getActiveTheme,
  deleteTheme,
  installCustomTheme
} from '../../api'
import FileInput from '../fileInput.vue'
import { useToast } from 'vue-toastification'
import { niceFileName, mapSettings } from '../../utils'
import { useConfirmDialog } from '../../composables/useConfirmDialog'

import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()
const toast = useToast()
const confirmDialog = useConfirmDialog()
const themeEditor = ref(null)
const showThemeEditor = ref(false)

const settings = ref({
  logo: null,
  logo_width: '',
  css_primary_color: '',
  css_secondary_color: '',
  css_accent_color: '',
  css_accent_color_light: '',
  use_my_backgrounds: false,
  show_powered_by: true,
  application_name: '',
  background_slideshow_speed: '',
  background_image_size: 'cover',
  background_image_position: 'center center',
  mobile_background_type: 'desktop',
  mobile_background_image: '',
  mobile_background_size: '100% auto',
  mobile_background_position: 'center top',
  mobile_background_color: '#0f101e',
  mobile_background_gradient_from: '#0f101e',
  mobile_background_gradient_to: '#00a8cc',
  mobile_background_gradient_angle: 180,
  logo_alignment: 'left',
  logo_mobile_alignment: 'center',
  font_family_body: '',
  font_family_heading: '',
  font_stylesheet_url: ''
})

const faviconStatus = ref({
  has_custom_favicon: false,
  filename: null
})
const pendingFavicon = ref(null)  // File waiting to be uploaded on save

const newBackgroundImage = ref(null)
const backgroundImages = ref([])

const settingsLoaded = ref(false)
const saving = ref(false)
const logoTimestamp = ref(Date.now())
const faviconTimestamp = ref(Date.now())

const emit = defineEmits(['navItemClicked'])

// Selectable values for the background/logo style settings. The same lists live
// in App\Utils\UiStyleSettings, which is what the server validates against.
const backgroundSizeOptions = [
  { value: 'cover', label: 'cover — fill the screen' },
  { value: 'contain', label: 'contain — show the whole image' },
  { value: '100% auto', label: '100% auto — fit the width' },
  { value: 'auto 100%', label: 'auto 100% — fit the height' }
]
const backgroundPositionOptions = [
  { value: 'center center', label: 'centre' },
  { value: 'center top', label: 'top' },
  { value: 'center bottom', label: 'bottom' },
  { value: 'top left', label: 'top left' },
  { value: 'top right', label: 'top right' },
  { value: 'bottom left', label: 'bottom left' },
  { value: 'bottom right', label: 'bottom right' },
  { value: 'left center', label: 'left' },
  { value: 'right center', label: 'right' }
]
const mobileBackgroundTypeOptions = [
  { value: 'desktop', label: 'Same as desktop' },
  { value: 'image', label: 'Own image' },
  { value: 'gradient', label: 'Gradient' },
  { value: 'color', label: 'Solid colour' }
]

const pendingMobileBackground = ref(null)
const logoAlignmentOptions = [
  { label: 'Left', value: 'left' },
  { label: 'Center', value: 'center' },
  { label: 'Right', value: 'right' }
]

const genericFontFamilies = [
  'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui',
  'ui-serif', 'ui-sans-serif', 'ui-monospace', 'emoji', 'math', 'fangsong'
]

// Mirror of the server-side normalisation so the preview matches the saved page:
// named families are quoted ("Source Sans 3" ends in a digit and is invalid CSS
// unquoted) while generic families stay unquoted so they still resolve.
const quoteFontStack = (value, fallback) => {
  const cleaned = (value || '').trim()

  if (!cleaned) {
    return fallback
  }

  const families = cleaned
    .split(',')
    .map((family) => family.trim().replace(/^["']|["']$/g, ''))
    .filter((family) => family !== '')
    .map((family) => genericFontFamilies.includes(family.toLowerCase()) ? family.toLowerCase() : '"' + family + '"')

  return families.length > 0 ? families.join(', ') : fallback
}

const logoAlignmentToFlex = (alignment) => ({
  left: 'flex-start',
  center: 'center',
  right: 'flex-end'
}[alignment] || 'flex-start')

onMounted(async () => {
  await loadSettings()
})

const loadSettings = async () => {
  try {
    const loadedSettings = mapSettings(await getSettingsByGroup('ui.*'))
    // Use the logo setting value as a string to show in the input
    // This is just for display - when saving, we only save if it's a File object
    settings.value = {
      ...loadedSettings,
      // If logo is a string from DB, keep it for display. If it's null/empty, set to null
      // If user has selected a File object, that takes precedence
      logo: settings.value.logo instanceof File ? settings.value.logo : (loadedSettings.logo || null)
    }

    settingsLoaded.value = true
  } catch (error) {
    toast.error('Failed to load settings')
    console.error(error)
  }

  loadBackgroundImages()
  loadThemes()
  loadFaviconStatus()
}

const loadFaviconStatus = async () => {
  try {
    faviconStatus.value = await getFaviconStatus()
  } catch (error) {
    console.error('Failed to load favicon status', error)
  }
}

const themes = ref(null)
const activeTheme = ref(null)
const loadThemes = async () => {
  themes.value = await getThemes()
}

const groupedThemes = computed(() => {
  if (!themes.value) {
    return {}
  }
  return themes.value.reduce((acc, theme) => {
    acc[theme.category] = acc[theme.category] || []
    acc[theme.category].push(theme)
    return acc
  }, {})
})

// Track object URLs for local file previews
const logoObjectUrl = ref(null)
const faviconObjectUrl = ref(null)

// Watch for logo file changes to create/revoke object URLs
watch(() => settings.value.logo, (newVal, oldVal) => {
  // Revoke old URL if it exists
  if (logoObjectUrl.value) {
    URL.revokeObjectURL(logoObjectUrl.value)
    logoObjectUrl.value = null
  }
  // Create new URL if new value is a File
  if (newVal instanceof File) {
    logoObjectUrl.value = URL.createObjectURL(newVal)
  }
}, { immediate: true })

// Watch for favicon file changes to create/revoke object URLs
watch(pendingFavicon, (newVal, oldVal) => {
  // Revoke old URL if it exists
  if (faviconObjectUrl.value) {
    URL.revokeObjectURL(faviconObjectUrl.value)
    faviconObjectUrl.value = null
  }
  // Create new URL if new value is a File
  if (newVal instanceof File) {
    faviconObjectUrl.value = URL.createObjectURL(newVal)
  }
}, { immediate: true })

const logoPreview = computed(() => {
  // If user has selected a new file, show local preview
  if (settings.value.logo instanceof File && logoObjectUrl.value) {
    return {
      url: logoObjectUrl.value,
      filename: settings.value.logo.name
    }
  }
  // Otherwise show current server logo
  return {
    url: `/images/logo.png?t=${logoTimestamp.value}`
  }
})

const faviconPreview = computed(() => {
  // If user has selected a new file, show local preview
  if (pendingFavicon.value instanceof File && faviconObjectUrl.value) {
    return {
      url: faviconObjectUrl.value,
      filename: pendingFavicon.value.name
    }
  }
  // Otherwise show current server favicon
  return {
    url: `/api/favicon?t=${faviconTimestamp.value}`,
    filename: faviconStatus.value.has_custom_favicon ? faviconStatus.value.filename : 'icon.svg'
  }
})

watch(themes, () => {
  themes.value.forEach((theme) => {
    if (theme.active) {
      activeTheme.value = theme
    }
  })
})

watch(activeTheme, () => {
  injectThemeVariables('body', activeTheme.value.theme)
})

const loadBackgroundImages = async () => {
  getBackgroundImages().then((data) => {
    backgroundImages.value = data.files
  })
}

const saveSettings = async () => {
  console.log('saving settings')
  saving.value = true
  try {
    // Upload logo if user selected a new file
    if (settings.value.logo instanceof File) {
      await saveLogo(settings.value.logo)
      logoTimestamp.value = Date.now()
      // Update the logo in the browser by forcing a cache refresh
      updateBrowserLogo()
      // Clear the File object after successful upload
      settings.value.logo = settings.value.logo.name
    }

    // Upload favicon if user selected a new file
    if (pendingFavicon.value instanceof File) {
      await saveFavicon(pendingFavicon.value)
      faviconTimestamp.value = Date.now()
      // Update the favicon in the browser
      updateBrowserFavicon()
      // Clear pending and reload status
      pendingFavicon.value = null
      loadFaviconStatus()
    }

    // Don't save logo setting if it's just a string (from DB) - only save if it's a File object
    const settingsToSave = { ...settings.value }
    if (!(settingsToSave.logo instanceof File)) {
      delete settingsToSave.logo
    }

    await saveSettingsById(settingsToSave)

    await setActiveTheme(activeTheme.value.name)

    applySettingsWithoutRefresh()
    applyStyleSettingsWithoutRefresh()
    loadThemes()

    saving.value = false
    toast.success(t.value('settings.branding.settings_saved'))
  } catch (error) {
    saving.value = false
    // Use error code for specific translated message, fallback to generic
    const errorKey = error.code 
      ? `settings.branding.errors.${error.code}`
      : 'settings.branding.failed_to_save_settings'
    toast.error(t.value(errorKey, t.value('settings.branding.failed_to_save_settings')))
    console.error(error)
  }
}

const applySettingsWithoutRefresh = () => {
  // Set the individual properties instead of rewriting the #erugo-css-variables
  // style tag: replacing its contents dropped every theme variable that the
  // server rendered into it (buttons, panels, tables, ...) until a page reload.
  const root = document.documentElement

  const setVar = (name, value) => {
    if (value === undefined || value === null || value === '') {
      return
    }

    root.style.setProperty(name, value)
  }

  setVar('--primary-color', settings.value.css_primary_color)
  setVar('--secondary-color', settings.value.css_secondary_color)
  setVar('--accent-color', settings.value.css_accent_color)
  setVar('--accent-color-light', settings.value.css_accent_color_light)

  //update the logo width
  const logo = document.getElementById('logo')
  if (logo) {
    logo.style.width = `${settings.value.logo_width}`
  }
}

// Live preview for the background/logo/font settings. Sets individual custom
// properties instead of rewriting #erugo-css-variables, so the theme variables
// are left untouched.
const applyStyleSettingsWithoutRefresh = () => {
  const root = document.documentElement

  const setVar = (name, value) => {
    if (value === undefined || value === null || value === '') {
      root.style.removeProperty(name)
      return
    }

    root.style.setProperty(name, value)
  }

  setVar('--background-image-size', settings.value.background_image_size)
  setVar('--background-image-position', settings.value.background_image_position)

  const mobileImage = settings.value.mobile_background_type === 'image'
    ? `url(/images/${settings.value.mobile_background_image})`
    : settings.value.mobile_background_type === 'gradient'
      ? `linear-gradient(${settings.value.mobile_background_gradient_angle || 180}deg, ${settings.value.mobile_background_gradient_from}, ${settings.value.mobile_background_gradient_to})`
      : 'none'

  setVar('--mobile-background-image', mobileImage)
  setVar('--mobile-background-color', settings.value.mobile_background_type === 'color' ? settings.value.mobile_background_color : 'transparent')
  setVar('--mobile-background-size', settings.value.mobile_background_size)
  setVar('--mobile-background-position', settings.value.mobile_background_position)
  setVar('--logo-alignment', logoAlignmentToFlex(settings.value.logo_alignment))
  setVar('--logo-alignment-mobile', logoAlignmentToFlex(settings.value.logo_mobile_alignment))

  const bodyFont = quoteFontStack(settings.value.font_family_body, '"Outfit", sans-serif')
  const headingFont = quoteFontStack(
    settings.value.font_family_heading,
    quoteFontStack(settings.value.font_family_body, '"Outfit", sans-serif')
  )

  setVar('--font-family-body', bodyFont)
  setVar('--font-family-heading', headingFont)
  document.body.style.fontFamily = bodyFont

  const fontLink = document.getElementById('erugo-font-stylesheet')
  const fontUrl = (settings.value.font_stylesheet_url || '').trim()

  if (fontUrl && fontLink) {
    fontLink.setAttribute('href', fontUrl)
  } else if (fontUrl) {
    const link = document.createElement('link')
    link.id = 'erugo-font-stylesheet'
    link.rel = 'stylesheet'
    link.href = fontUrl
    document.head.appendChild(link)
  } else if (fontLink) {
    fontLink.remove()
  }
}

//watch newBackgroundImage and upload it to the server
watch(newBackgroundImage, async () => {
  if (newBackgroundImage.value) {
    saveBackgroundImage(newBackgroundImage.value)
      .then((data) => {
        loadBackgroundImages()
        newBackgroundImage.value = null
        toast.success('Background image uploaded successfully')
      })
      .catch((error) => {
        toast.error('Failed to upload background image')
      })
  }
})

// No auto-upload for favicon - it's saved with other settings in saveSettings()

//mobile background upload
watch(pendingMobileBackground, async () => {
  if (!pendingMobileBackground.value) {
    return
  }

  try {
    const data = await saveMobileBackground(pendingMobileBackground.value)
    settings.value.mobile_background_image = data.data.file
    settings.value.mobile_background_type = 'image'
    pendingMobileBackground.value = null
    toast.success(t.value('settings.branding.mobile_background_uploaded'))
  } catch (error) {
    toast.error(error.message || t.value('settings.branding.mobile_background_upload_failed'))
  }
})

const handleDeleteMobileBackground = async () => {
  try {
    await deleteMobileBackground()
    settings.value.mobile_background_image = ''
    settings.value.mobile_background_type = 'desktop'
    toast.success(t.value('settings.branding.mobile_background_removed'))
  } catch (error) {
    toast.error(error.message || t.value('settings.branding.mobile_background_remove_failed'))
  }
}

const handleDeleteFavicon = async () => {
  const reallyDelete = confirm(t.value('settings.branding.confirm_delete_favicon'))
  if (!reallyDelete) {
    return
  }
  deleteFavicon()
    .then((data) => {
      faviconTimestamp.value = Date.now()
      loadFaviconStatus()
      toast.success(t.value('settings.branding.favicon_deleted'))
      // Update the favicon in the browser
      updateBrowserFavicon()
    })
    .catch((error) => {
      toast.error(t.value('settings.branding.favicon_delete_failed'))
    })
}

const handleResetLogo = async () => {
  const reallyReset = await confirmDialog.show({
    title: t.value('settings.branding.confirm'),
    message: t.value('settings.branding.confirm_reset_logo'),
    okText: t.value('settings.branding.reset'),
    cancelText: t.value('settings.close')
  })
  if (!reallyReset) {
    return
  }
  resetLogo()
    .then((data) => {
      logoTimestamp.value = Date.now()
      settings.value.logo = 'erugo-logo.png' // Show default logo filename in input
      toast.success(t.value('settings.branding.logo_reset_success'))
      // Update the logo in the browser by forcing a refresh
      updateBrowserLogo()
    })
    .catch((error) => {
      toast.error(t.value('settings.branding.logo_reset_failed'))
    })
}

const handleResetFavicon = async () => {
  const reallyReset = await confirmDialog.show({
    title: t.value('settings.branding.confirm'),
    message: t.value('settings.branding.confirm_reset_favicon'),
    okText: t.value('settings.branding.reset'),
    cancelText: t.value('settings.close')
  })
  if (!reallyReset) {
    return
  }
  resetFavicon()
    .then((data) => {
      faviconTimestamp.value = Date.now()
      loadFaviconStatus()
      toast.success(t.value('settings.branding.favicon_reset_success'))
      // Update the favicon in the browser
      updateBrowserFavicon()
    })
    .catch((error) => {
      toast.error(t.value('settings.branding.favicon_reset_failed'))
    })
}

const updateBrowserLogo = () => {
  const logo = document.getElementById('logo')
  if (logo) {
    logo.src = `/images/logo.png?t=${Date.now()}`
  }
}

const updateBrowserFavicon = () => {
  // Find existing favicon link or create one
  let link = document.querySelector("link[rel~='icon']")
  if (!link) {
    link = document.createElement('link')
    link.rel = 'icon'
    document.head.appendChild(link)
  }
  // Add timestamp to bust cache
  link.href = `/api/favicon?t=${Date.now()}`
}

watch(backgroundImages, () => {
  if (backgroundImages.value.length === 0) {
    settings.value.use_my_backgrounds = false
  }
})

const handleDeleteBackgroundImage = (file) => {
  const reallyDelete = confirm('Are you sure you want to delete this background image?')
  if (!reallyDelete) {
    return
  }
  deleteBackgroundImage(file)
    .then((data) => {
      loadBackgroundImages()
      toast.success('Background image deleted successfully')
    })
    .catch((error) => {
      toast.error('Failed to delete background image')
    })
}

const handleNavItemClicked = (item) => {
  emit('navItemClicked', item)
}

onBeforeUnmount(async () => {
  // Clean up object URLs to prevent memory leaks
  if (logoObjectUrl.value) {
    URL.revokeObjectURL(logoObjectUrl.value)
  }
  if (faviconObjectUrl.value) {
    URL.revokeObjectURL(faviconObjectUrl.value)
  }
  
  const activeTheme = await getActiveTheme()
  if (activeTheme) {
    injectThemeVariables('body', activeTheme.theme)
  }
})

const downloadTheme = () => {
  if (!activeTheme.value) {
    toast.error('No theme selected')
    return
  }
  const theme = activeTheme.value.theme
  const blob = new Blob([JSON.stringify(theme, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${activeTheme.value.name}.json`
  a.click()
  toast.success('Theme downloaded')
}

const customTheme = ref({
  file: null,
  name: ''
})

const handleInstallCustomTheme = async () => {
  if (!customTheme.value.file) {
    toast.error('No theme file selected')
    return
  }

  if (!customTheme.value.name) {
    toast.error('No theme name provided')
    return
  }

  const installedTheme = await installCustomTheme(customTheme.value.name, customTheme.value.file)
  console.log('installedTheme', installedTheme)
  toast.success('Theme installed successfully')
  loadThemes()
}

const handleDeleteTheme = async () => {
  const reallyDelete = await confirmDialog.show({
    title: t.value('settings.branding.confirm'),
    message: t.value('settings.branding.confirm_delete_theme', { name: activeTheme.value?.name }),
    okText: t.value('settings.branding.delete'),
    cancelText: t.value('settings.close')
  })
  if (!reallyDelete) {
    return
  }
  deleteTheme(activeTheme.value.name)
    .then((data) => {
      toast.success(t.value('settings.branding.theme_deleted_successfully'))
      loadThemes()
    })
    .catch((error) => {
      toast.error(t.value('settings.branding.theme_delete_failed'))
    })
}

const backgroundSlideshowSpeedOptions = [
  {
    label: t.value('settings.branding.background_slideshow_speed_3_minutes'),
    value: 180,
    icon: Clock8,
    action: () => {
      settings.value.background_slideshow_speed = 180
    }
  },
  {
    label: t.value('settings.branding.background_slideshow_speed_5_minutes'),
    value: 300,
    icon: Clock8,
    action: () => {
      settings.value.background_slideshow_speed = 300
    }
  },
  {
    label: t.value('settings.branding.background_slideshow_speed_1_hour'),
    value: 3600,
    icon: Clock8,
    action: () => {
      settings.value.background_slideshow_speed = 3600
    }
  },
  {
    label: t.value('settings.branding.background_slideshow_speed_2_hours'),
    value: 7200,
    icon: Clock8,
    action: () => {
      settings.value.background_slideshow_speed = 7200
    }
  },
  {
    label: t.value('settings.branding.background_slideshow_speed_1_day'),
    value: 86400,
    icon: Clock8,
    action: () => {
      settings.value.background_slideshow_speed = 86400
    }
  },
  {
    label: t.value('settings.branding.background_slideshow_speed_off'),
    value: 0,
    icon: Clock8,
    action: () => {
      settings.value.background_slideshow_speed = 0
    }
  }
]

//define exposed methods
defineExpose({
  saveSettings
})
</script>
<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-2 d-none d-md-block">
        <ul class="settings-nav pt-5">
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('background-images')">
              <Images />
              {{ $t('settings.branding.background_images') }}
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('logo-settings')">
              <Image />
              {{ $t('settings.branding.logo_and_favicon') }}
            </a>
          </li>

          <li>
            <a href="#" @click.prevent="handleNavItemClicked('font-settings')">
              <Tag />
              {{ $t('settings.branding.fonts') }}
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavItemClicked('ui-colours')">
              <Pipette />
              {{ $t('settings.branding.theme') }}
            </a>
          </li>

          <li>
            <a href="#" @click.prevent="handleNavItemClicked('other-ui-settings')">
              <Dice5 />
              {{ $t('settings.branding.other_ui_settings') }}
            </a>
          </li>
        </ul>
      </div>
      <div class="col-12 col-md-8 pt-5">
        <div class="row mb-5">
          <!-- backgrounds -->
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="background-images">
              <div class="setting-group-header">
                <h3>
                  <Images />
                  {{ $t('settings.branding.background_images') }}
                </h3>
              </div>

              <div class="setting-group-body">
                <div class="setting-group-body-item">
                  <div class="background-images">
                    <div class="background-image" v-for="image in backgroundImages" :key="image">
                      <img :src="`/api/backgrounds/${image}/thumb`" />
                      <div class="name">
                        {{ niceFileName(image) }}
                      </div>
                      <button class="delete" @click="handleDeleteBackgroundImage(image)">
                        <X />
                      </button>
                    </div>
                  </div>

                  <FileInput
                    v-model="newBackgroundImage"
                    accept="image/png, image/jpeg, image/webp, video/mp4, video/webm"
                    :label="$t('settings.branding.upload_background')"
                    class="mt-3 mb-4"
                  />

                  <div class="checkbox-container" :class="{ disabled: backgroundImages.length === 0 }">
                    <input type="checkbox" v-model="settings.use_my_backgrounds" id="useMyBackgrounds" />
                    <label for="useMyBackgrounds">{{ $t('settings.branding.use_my_backgrounds') }}</label>
                  </div>

                  <div class="setting-group-body-item">
                    <label for="backgroundSlideshowSpeed">
                      {{ $t('settings.branding.background_slideshow_speed') }}
                      <small>({{ $t('settings.branding.in_seconds') }})</small>
                  </label>
                    <div class="input-with-button">
                      <input type="number" v-model="settings.background_slideshow_speed" />
                      <buttonWithMenu :items="backgroundSlideshowSpeedOptions" :secondary="false">
                        <template #icon>
                          <Clock8 />
                        </template>
                      </buttonWithMenu>
                    </div>
                  </div>

                  <div class="setting-group-body-item mt-4">
                    <label>{{ $t('settings.branding.background_scaling') }}</label>

                    <div class="style-setting-row">
                      <span class="style-setting-label">{{ $t('settings.branding.desktop') }}</span>
                      <select id="backgroundImageSize" v-model="settings.background_image_size" :aria-label="$t('settings.branding.background_size')">
                        <option v-for="size in backgroundSizeOptions" :key="size.value" :value="size.value">{{ size.label }}</option>
                      </select>
                      <select id="backgroundImagePosition" v-model="settings.background_image_position" :aria-label="$t('settings.branding.background_position')">
                        <option v-for="position in backgroundPositionOptions" :key="position.value" :value="position.value">{{ position.label }}</option>
                      </select>
                    </div>

                    <small class="setting-hint">{{ $t('settings.branding.background_scaling_description') }}</small>
                  </div>

                  <div class="setting-group-body-item mt-4">
                    <label for="mobileBackgroundType">{{ $t('settings.branding.mobile_background') }}</label>

                    <div class="style-setting-row">
                      <span class="style-setting-label">{{ $t('settings.branding.source') }}</span>
                      <select id="mobileBackgroundType" v-model="settings.mobile_background_type">
                        <option v-for="option in mobileBackgroundTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                      </select>
                    </div>

                    <template v-if="settings.mobile_background_type === 'image'">
                      <img
                        v-if="settings.mobile_background_image"
                        :src="`/images/${settings.mobile_background_image}`"
                        class="mobile-background-preview"
                        :alt="$t('settings.branding.mobile_background')"
                      />

                      <FileInput
                        v-model="pendingMobileBackground"
                        accept="image/jpeg, image/png, image/webp"
                        :label="settings.mobile_background_image || $t('settings.branding.upload_mobile_background')"
                        class="mt-3 mb-3"
                      />

                      <div class="style-setting-row" v-if="settings.mobile_background_image">
                        <span class="style-setting-label"></span>
                        <button class="secondary" @click="handleDeleteMobileBackground">
                          <Trash />
                          {{ $t('settings.branding.remove') }}
                        </button>
                      </div>

                      <div class="style-setting-row">
                        <span class="style-setting-label">{{ $t('settings.branding.size') }}</span>
                        <select v-model="settings.mobile_background_size" :aria-label="$t('settings.branding.size')">
                          <option v-for="size in backgroundSizeOptions" :key="size.value" :value="size.value">{{ size.label }}</option>
                        </select>
                      </div>

                      <div class="style-setting-row">
                        <span class="style-setting-label">{{ $t('settings.branding.position') }}</span>
                        <select v-model="settings.mobile_background_position" :aria-label="$t('settings.branding.position')">
                          <option v-for="position in backgroundPositionOptions" :key="position.value" :value="position.value">{{ position.label }}</option>
                        </select>
                      </div>
                    </template>

                    <template v-if="settings.mobile_background_type === 'gradient'">
                      <div class="style-setting-row">
                        <span class="style-setting-label">{{ $t('settings.branding.gradient_from') }}</span>
                        <input type="color" v-model="settings.mobile_background_gradient_from" :aria-label="$t('settings.branding.gradient_from')" />
                        <span class="style-setting-label">{{ $t('settings.branding.gradient_to') }}</span>
                        <input type="color" v-model="settings.mobile_background_gradient_to" :aria-label="$t('settings.branding.gradient_to')" />
                      </div>

                      <div class="style-setting-row">
                        <span class="style-setting-label">{{ $t('settings.branding.gradient_angle') }}</span>
                        <input type="number" min="0" max="360" step="15" v-model="settings.mobile_background_gradient_angle" :aria-label="$t('settings.branding.gradient_angle')" />
                      </div>
                    </template>

                    <template v-if="settings.mobile_background_type === 'color'">
                      <div class="style-setting-row">
                        <span class="style-setting-label">{{ $t('settings.branding.colour') }}</span>
                        <input type="color" v-model="settings.mobile_background_color" :aria-label="$t('settings.branding.colour')" />
                      </div>
                    </template>

                    <small class="setting-hint">{{ $t('settings.branding.mobile_background_description') }}</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{ $t('settings.branding.background_images') }}</h6>
              <p>{{ $t('settings.branding.background_images_description') }}</p>

              <h6>{{ $t('settings.branding.use_my_backgrounds') }}</h6>
              <p>{{ $t('settings.branding.use_my_backgrounds_description') }}</p>

              <h6>{{ $t('settings.branding.background_slideshow_speed') }}</h6>
              <p>{{ $t('settings.branding.background_slideshow_speed_description') }}</p>
            </div>
          </div>
        </div>

        <div class="row mb-5">
          <!-- logo -->
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="logo-settings">
              <div class="setting-group-header">
                <h3>
                  <Image />
                  {{ $t('settings.branding.logo_and_favicon') }}
                </h3>
              </div>

              <div class="setting-group-body">
                <div class="setting-group-body-item">
                  <label for="logoFile">{{ $t('settings.branding.logo_image') }}</label>
                  <FileInput 
                    v-model="settings.logo" 
                    accept="image/png, image/svg+xml"
                    :preview="logoPreview"
                  >
                    <template #actions>
                      <button
                        @click.stop="handleResetLogo"
                        :title="$t('settings.branding.reset_logo_to_default')"
                        aria-label="Reset logo to default"
                      >
                        <RotateCcw />
                      </button>
                    </template>
                  </FileInput>
                </div>

                <div class="setting-group-body-item">
                  <label for="logoWidth">
                    {{ $t('settings.branding.logo_width') }}
                    <small>({{ $t('settings.branding.in_pixels') }})</small>
                  </label>
                  <input type="number" v-model="settings.logo_width" />
                </div>

                <div class="setting-group-body-item mt-4">
                  <label for="logoAlignment">{{ $t('settings.branding.logo_alignment') }}</label>

                  <div class="style-setting-row">
                    <span class="style-setting-label">{{ $t('settings.branding.desktop') }}</span>
                    <select id="logoAlignment" v-model="settings.logo_alignment">
                      <option v-for="option in logoAlignmentOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                  </div>

                  <div class="style-setting-row">
                    <span class="style-setting-label">{{ $t('settings.branding.mobile') }}</span>
                    <select id="logoMobileAlignment" v-model="settings.logo_mobile_alignment">
                      <option v-for="option in logoAlignmentOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                  </div>

                  <small class="setting-hint">{{ $t('settings.branding.logo_alignment_description') }}</small>
                </div>

                <div class="setting-group-body-item mt-4">
                  <label for="faviconFile">{{ $t('settings.branding.favicon') }}</label>
                  <FileInput 
                    v-model="pendingFavicon" 
                    accept="image/png, image/svg+xml" 
                    :label="faviconPreview.filename || $t('settings.branding.upload_favicon')"
                    :preview="faviconPreview"
                  >
                    <template #actions>
                      <button
                        @click.stop="handleResetFavicon"
                        :title="$t('settings.branding.reset_favicon_to_default')"
                        aria-label="Reset favicon to default"
                      >
                        <RotateCcw />
                      </button>
                    </template>
                  </FileInput>
                </div>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{ $t('settings.branding.logo') }}</h6>
              <p>
                {{ $t('settings.branding.logo_description') }}
              </p>
              <h6>{{ $t('settings.branding.favicon') }}</h6>
              <p>
                {{ $t('settings.branding.favicon_description') }}
              </p>
            </div>
          </div>
        </div>

        <div class="row mb-5">
          <!-- fonts -->
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="font-settings">
              <div class="setting-group-header">
                <h3>
                  <Tag />
                  {{ $t('settings.branding.fonts') }}
                </h3>
              </div>

              <div class="setting-group-body">
                <div class="setting-group-body-item">
                  <label for="fontFamilyBody">{{ $t('settings.branding.font_family_body') }}</label>
                  <input
                    type="text"
                    id="fontFamilyBody"
                    v-model="settings.font_family_body"
                    placeholder="Source Sans 3, sans-serif"
                  />
                </div>

                <div class="setting-group-body-item mt-4">
                  <label for="fontFamilyHeading">{{ $t('settings.branding.font_family_heading') }}</label>
                  <input
                    type="text"
                    id="fontFamilyHeading"
                    v-model="settings.font_family_heading"
                    placeholder="Source Sans 3, sans-serif"
                  />
                </div>

                <div class="setting-group-body-item mt-4">
                  <label for="fontStylesheetUrl">{{ $t('settings.branding.font_stylesheet_url') }}</label>
                  <input
                    type="text"
                    id="fontStylesheetUrl"
                    v-model="settings.font_stylesheet_url"
                    placeholder="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@200..900&display=swap"
                  />
                  <small class="setting-hint">{{ $t('settings.branding.font_stylesheet_url_description') }}</small>
                </div>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{ $t('settings.branding.fonts') }}</h6>
              <p>{{ $t('settings.branding.fonts_description') }}</p>
              <h6>{{ $t('settings.branding.font_stylesheet_url') }}</h6>
              <p>{{ $t('settings.branding.font_stylesheet_url_description') }}</p>
            </div>
          </div>
        </div>

        <div class="row mb-5">
          <!-- UI Colours -->
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="ui-colours">
              <div class="setting-group-header">
                <h3>
                  <Pipette />
                  {{ $t('settings.branding.theme') }}
                </h3>
              </div>

              <div class="setting-group-body" v-if="settingsLoaded">
                <div class="setting-group-body-item">
                  <label for="theme">{{ $t('settings.branding.select_theme') }}</label>
                  <select v-model="activeTheme" class="block" style="width: 100%">
                    <optgroup v-for="(category, label) in groupedThemes" :key="category" :label="label">
                      <option v-for="theme in category" :key="theme.id" :value="theme">
                        {{ theme.name }}
                      </option>
                    </optgroup>
                  </select>
                </div>

                <div class="row">
                  <div class="col-12">
                    <div class="setting-group-body-item mt-3">
                      <button @click="downloadTheme" class="block">
                        <FileDown />
                        {{$t('settings.branding.download')}} {{ activeTheme?.name }}
                      </button>
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="setting-group-body-item mt-3">
                      <button
                        class="secondary block"
                        @click="handleDeleteTheme"
                        :disabled="activeTheme?.active || activeTheme?.bundled"
                      >
                        <Trash />
                        {{$t('settings.branding.delete')}} {{ activeTheme?.name }}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{$t('settings.branding.select_theme')}}</h6>
              <p>{{$t('settings.branding.select_theme_description')}}</p>
              <h6>{{$t('settings.branding.download_theme')}}</h6>
              <p>
                {{$t('settings.branding.download_theme_description')}}
              </p>
              <h6>{{$t('settings.branding.delete_theme')}}</h6>
              <p>
                {{$t('settings.branding.delete_theme_description')}}
              </p>
            </div>
          </div>
        </div>

        <div class="row mb-5">
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="install-custom-theme">
              <div class="setting-group-header">
                <h3>
                  <Pipette />
                  {{$t('settings.branding.install_custom_theme')}}
                </h3>
              </div>

              <div class="setting-group-body" v-if="settingsLoaded">
                <div class="setting-group-body-item mt-4">
                  <label for="logoFile">{{$t('settings.branding.theme_file')}}</label>
                  <FileInput v-model="customTheme.file" accept="application/json" />
                </div>
                <div class="setting-group-body-item mt-3">
                  <label for="theme_name">{{$t('settings.branding.theme_name')}}</label>
                  <input type="text" id="theme_name" v-model="customTheme.name" placeholder="My Custom Theme" />
                </div>
                <div class="setting-group-body-item mt-3">
                  <button @click="handleInstallCustomTheme" class="block">
                    <FileUp />
                    {{$t('settings.branding.install_theme')}}
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{$t('settings.branding.install_custom_theme')}}</h6>
              <p>{{$t('settings.branding.install_custom_theme_description')}}</p>
              <p>
                <strong>
                  {{$t('settings.branding.install_custom_theme_description_2')}}
                </strong>
              </p>
            </div>
          </div>
        </div>

        <div class="row mb-5">
          <!-- Other UI settings -->
          <div class="col-12 col-md-6 pe-0 ps-0 ps-md-3">
            <div class="setting-group" id="other-ui-settings">
              <div class="setting-group-header">
                <h3>
                  <Dice5 />
                  {{$t('settings.branding.other_ui_settings')}}
                </h3>
              </div>

              <div class="checkbox-container">
                <input type="checkbox" v-model="settings.show_powered_by" id="showPoweredBy" />
                <label for="showPoweredBy">{{$t('settings.branding.show_powered_by')}}</label>
              </div>
            </div>
          </div>
          <div class="d-none d-md-block col ps-0">
            <div class="section-help">
              <h6>{{$t('settings.branding.show_powered_by')}}</h6>
              <p>
                {{$t('settings.branding.show_powered_by_description')}}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
