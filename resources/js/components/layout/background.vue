<script setup>
import { ref, onMounted, nextTick } from 'vue'

import { getBackgroundImages } from '../../api'
import { domData } from '../../domData'

const VIDEO_EXTENSIONS = ['mp4', 'webm']

const slideshowSpeed = ref(30)
const useMyBackgrounds = ref(false)
const backgroundFiles = ref([])
const interval = ref(null)
const currentBackgroundIndex = ref(0)

const isVideo = (filename) => {
  const extension = filename.split('.').pop().toLowerCase()
  return VIDEO_EXTENSIONS.includes(extension)
}

const isActive = (index) => {
  return index === currentBackgroundIndex.value
}

onMounted(() => {
  slideshowSpeed.value = domData().background_slideshow_speed
  useMyBackgrounds.value = domData().use_my_backgrounds
  
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
  if (!useMyBackgrounds.value || backgroundFiles.value.length === 0) {
    return
  }
  currentBackgroundIndex.value++
  if (currentBackgroundIndex.value >= backgroundFiles.value.length) {
    currentBackgroundIndex.value = 0
  }
}
</script>
<template>
  <div class="backgrounds" v-if="!useMyBackgrounds">
    <div
      class="backgrounds-item active"
      :style="{
        backgroundImage: `url(/images/default-background.jpg)`
      }"
    ></div>
  </div>

  <div class="backgrounds" v-else>
    <template v-for="(file, index) in backgroundFiles" :key="file">
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
</template>
