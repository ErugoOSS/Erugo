<script setup>
import { ref, onMounted, computed } from 'vue'

import { getBackgroundImages } from '../../api'
import { domData } from '../../domData'

const VIDEO_EXTENSIONS = ['mp4', 'webm']

const slideshowSpeed = ref(30)
const useMyBackgrounds = ref(false)
const backgroundFiles = ref([])
const interval = ref(null)
const currentBackgroundIndex = ref(0)
const mobileBackground = ref('')

const isVideo = (filename) => {
  const extension = filename.split('.').pop().toLowerCase()
  return VIDEO_EXTENSIONS.includes(extension)
}

const isActive = (index) => {
  return index === currentBackgroundIndex.value
}

// A theme can name an uploaded background to use on small screens only. It is
// removed from the rotation so it never shows up twice on desktop; if it was the
// only upload, keep it in the rotation rather than leaving the desktop blank.
const slideshowFiles = computed(() => {
  if (!mobileBackground.value) {
    return backgroundFiles.value
  }

  const withoutMobile = backgroundFiles.value.filter((file) => file !== mobileBackground.value)

  return withoutMobile.length > 0 ? withoutMobile : backgroundFiles.value
})

onMounted(() => {
  slideshowSpeed.value = domData().background_slideshow_speed
  useMyBackgrounds.value = domData().use_my_backgrounds
  mobileBackground.value = document.body.getAttribute('data-theme-mobile-background') || ''

  if (useMyBackgrounds.value) {
    //remove the interval if it exists
    if (interval.value) {
      clearInterval(interval.value)
    }
    interval.value = setInterval(changeBackground, slideshowSpeed.value * 1000)
    getBackgroundImages().then((data) => {
      backgroundFiles.value = data.files
    })
  }
})

const changeBackground = () => {
  if (!useMyBackgrounds.value || slideshowFiles.value.length === 0) {
    return
  }
  //randomly select a background image
  currentBackgroundIndex.value = Math.floor(Math.random() * slideshowFiles.value.length)
}
</script>
<template>
  <div class="backgrounds" :class="{ 'has-mobile-background': mobileBackground }" v-if="!useMyBackgrounds">
    <div
      class="backgrounds-item active"
      :style="{
        backgroundImage: `url(/images/default-background.jpg)`
      }"
    ></div>
  </div>

  <div class="backgrounds" :class="{ 'has-mobile-background': mobileBackground }" v-else>
    <template v-for="(file, index) in slideshowFiles" :key="file">
      <!-- Video backgrounds - only render when active -->
      <div 
        v-if="isVideo(file) && isActive(index)" 
        class="backgrounds-item backgrounds-item-video active"
      >
        <video
          autoplay
          loop
          muted
          playsinline
          :src="`/backgrounds/${file}`"
        ></video>
      </div>
      <!-- Image backgrounds - always in DOM, toggle active class -->
      <div
        v-else-if="!isVideo(file)"
        class="backgrounds-item"
        :class="{ active: isActive(index) }"
        :style="{ backgroundImage: `url(/backgrounds/${file})` }"
      ></div>
    </template>
  </div>

  <!-- Theme-supplied mobile background: hidden above the 900px breakpoint -->
  <div class="backgrounds backgrounds-mobile" v-if="mobileBackground">
    <div
      class="backgrounds-item active"
      :style="{ backgroundImage: `url(/backgrounds/${mobileBackground})` }"
    ></div>
  </div>
</template>
