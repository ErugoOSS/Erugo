<script setup>
import { ref, watch, onMounted } from 'vue'
import QRCode from 'qrcode'

const props = defineProps({
  url: { type: String, required: true },
  size: { type: Number, default: 200 }
})

const canvas = ref(null)

const generateQR = async () => {
  if (!canvas.value || !props.url) return
  try {
    await QRCode.toCanvas(canvas.value, props.url, {
      width: props.size,
      margin: 2,
      color: {
        dark: '#000000',
        light: '#ffffff'
      }
    })
  } catch (error) {
    console.error('Failed to generate QR code:', error)
  }
}

onMounted(() => {
  generateQR()
})

watch(() => props.url, () => {
  generateQR()
})
</script>

<template>
  <div class="qr-code">
    <canvas ref="canvas"></canvas>
  </div>
</template>

<style lang="scss" scoped>
.qr-code {
  display: inline-block;
  border-radius: 8px;
  overflow: hidden;
  background: #ffffff;
  padding: 8px;

  canvas {
    display: block;
  }
}
</style>
