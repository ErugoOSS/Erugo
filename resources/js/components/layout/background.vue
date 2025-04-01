<script setup>
import { ref, onMounted, nextTick } from 'vue'

import { unsplashImages } from '../../unsplashImages'
import { getBackgroundImages } from '../../api'
import { domData } from '../../domData'

const slideshowSpeed = ref(30)
const useMyBackgrounds = ref(false)
const backgroundImages = ref([])
const interval = ref(null)

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
      backgroundImages.value = data.files
      nextTick(() => {
        changeBackground()
      })
    })
  }
})

const currentBackgroundIndex = ref(0)
const changeBackground = async () => {
  if (!useMyBackgrounds.value) {
    return
  }
  let backgrounds = document.querySelectorAll('.backgrounds-item')
  if (backgrounds.length === 0) {
    return
  }
  backgrounds.forEach((background) => {
    background.classList.remove('active')
  })
  backgrounds[currentBackgroundIndex.value].classList.add('active')
  currentBackgroundIndex.value++
  if (currentBackgroundIndex.value >= backgrounds.length) {
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
    <div
      class="backgrounds-item"
      v-for="image in backgroundImages"
      :key="image"
      :style="{ backgroundImage: `url(/api/backgrounds/${image})` }"
    ></div>
  </div>
</template>
