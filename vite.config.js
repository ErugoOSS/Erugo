import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import autoprefixer from 'autoprefixer'

export default defineConfig({
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    cors: true,
    hmr: {
      host: process.env.VITE_DEV_HOST || 'localhost'
    }
  },
  plugins: [
    laravel({
      input: ['resources/js/app.js'],
      refresh: true
    }),
    vue()
  ],
  css: {
    postcss: {
      plugins: [autoprefixer]
    }
  }
})