import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

// Frontend smoke runner (issue #23). Mounts each module's main page under
// jsdom and asserts it renders without throwing. Deliberately separate from
// vite.config.js so production builds never pick up test-only settings.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url))
    }
  },
  define: {
    'import.meta.env.VITE_APP_URL': '"http://localhost:8000"'
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./tests/js/setup.js'],
    include: ['tests/js/smoke/**/*.test.js'],
    testTimeout: 15000
  }
})
