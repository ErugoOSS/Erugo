<script setup>
import { ref, onMounted, watch, defineExpose, onBeforeUnmount, computed } from 'vue'
import { Pipette, Image, Ruler, Tag, X, Dice5, Images, FileDown, Trash, FileUp, Clock8   } from 'lucide-vue-next'
import injectThemeVariables from '../../lib/injectThemeVariables'
import ButtonWithMenu from '../buttonWithMenu.vue'

import {
  getSettingsByGroup,
  saveSettingsById,
  saveLogo,
  getBackgroundImages,
  saveBackgroundImage,
  deleteBackgroundImage,
  getThemes,
  setActiveTheme,
  getActiveTheme,
  deleteTheme,
  installCustomTheme
} from '../../api'
import FileInput from '../fileInput.vue'
import { useToast } from 'vue-toastification'
import { niceFileName, mapSettings } from '../../utils'

import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()
const toast = useToast()
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
  background_slideshow_speed: ''
})

const newBackgroundImage = ref(null)
const backgroundImages = ref([])

const settingsLoaded = ref(false)
const saving = ref(false)

const emit = defineEmits(['navItemClicked'])

onMounted(async () => {
  await loadSettings()
})

const loadSettings = async () => {
  try {
    settings.value = {
      ...mapSettings(await getSettingsByGroup('ui.*'))
    }

    settingsLoaded.value = true
  } catch (error) {
    toast.error('Failed to load settings')
    console.error(error)
  }

  loadBackgroundImages()
  loadThemes()
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
    if (settings.value.logo instanceof File) {
      saveLogo(settings.value.logo)
    }

    await saveSettingsById(settings.value)

    await setActiveTheme(activeTheme.value.name)

    applySettingsWithoutRefresh()
    loadThemes()

    saving.value = false
    toast.success('Settings saved successfully')
  } catch (error) {
    saving.value = false
    toast.error('Failed to save settings')
    console.error(error)
  }
}

const applySettingsWithoutRefresh = () => {
  //find the style tag #erugo-css-variables
  const styleTag = document.getElementById('erugo-css-variables')
  if (styleTag) {
    //update the css variables
    styleTag.innerHTML = `
      :root {
        --primary-color: ${settings.value.css_primary_color};
        --secondary-color: ${settings.value.css_secondary_color};
        --accent-color: ${settings.value.css_accent_color};
        --accent-color-light: ${settings.value.css_accent_color_light};
      }
      `
  } else {
    //add the style tag
    const styleTag = document.createElement('style')
    styleTag.id = 'erugo-css-variables'
    styleTag.innerHTML = `
      :root {
        --primary-color: ${settings.value.css_primary_color};
        --secondary-color: ${settings.value.css_secondary_color};
        --accent-color: ${settings.value.css_accent_color};
        --accent-color-light: ${settings.value.css_accent_color_light};
      }
      `
    document.head.appendChild(styleTag)
  }

  //update the logo width
  const logo = document.getElementById('logo')
  if (logo) {
    logo.style.width = `${settings.value.logo_width}`
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

watch(backgroundImages, () => {
  if (backgroundImages.value.length === 0) {
    useMyBackgrounds.value = false
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
  const reallyDelete = confirm('Are you sure you want to delete this theme?')
  if (!reallyDelete) {
    return
  }
  deleteTheme(activeTheme.value.name)
    .then((data) => {
      toast.success('Theme deleted successfully')
      loadThemes()
    })
    .catch((error) => {
      toast.error('Failed to delete theme')
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
              {{ $t('settings.branding.logo') }}
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
                    accept="image/png, image/jpeg, image/webp"
                    :label="$t('settings.branding.upload_background_image')"
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
                    <div class="row">
                      <div class="col pe-1">
                        <input type="number" v-model="settings.background_slideshow_speed" />
                      </div>
                      <div class="col-auto ps-1">
                        <buttonWithMenu :items="backgroundSlideshowSpeedOptions" :secondary="false">
                          <template #icon>
                            <Clock8 />
                          </template>
                        </buttonWithMenu>
                      </div>
                    </div>
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
                  {{ $t('settings.branding.logo') }}
                </h3>
              </div>

              <div class="setting-group-body">
                <div class="setting-group-body-item">
                  <label for="logoFile">{{ $t('settings.branding.logo_image') }}</label>
                  <FileInput v-model="settings.logo" accept="image/png" />
                </div>

                <div class="setting-group-body-item">
                  <label for="logoWidth">
                    {{ $t('settings.branding.logo_width') }}
                    <small>({{ $t('settings.branding.in_pixels') }})</small>
                  </label>
                  <input type="number" v-model="settings.logo_width" />
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
