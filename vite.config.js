import { defineConfig, loadEnv } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'

export default defineConfig(({ mode }) => {
  // Load .env variables so server.proxy can read VITE_APP_URL.
  const env = loadEnv(mode, process.cwd(), '')
  const backendUrl = env.VITE_APP_URL || 'http://127.0.0.1:8000'

  return {
    // Laravel already serves everything in public/ at the web root, and the
    // built CSS is served from that same origin, so root-absolute asset URLs
    // like /fonts/newsreader-latin.woff2 resolve correctly in production.
    //
    // In dev the CSS is served from the Vite origin instead, so the same URL
    // would resolve against :5173 and 404 — the self-hosted serif silently
    // fell back to Georgia. Pointing publicDir at Laravel's public/ makes
    // dev serve those files too, so both environments agree.
    publicDir: 'public',
    plugins: [
      laravel({
        input: ['resources/css/app.css', 'resources/js/app.js'],
        refresh: true
      }),
      vue({
        template: {
          transformAssetUrls: {
            base: null,
            includeAbsolute: false
          }
        }
      })
    ],
    resolve: {
      alias: {
        vue: 'vue/dist/vue.esm-bundler.js',
        // Sprint 3 (M-3): alias @ -> resources/js. Ya se usaba en varios
        // componentes y Vite lo resolvia laxamente; ahora es explicito.
        '@': '/resources/js'
      }
    },
    server: {
      watch: {
        // Browser-automation profiles and vendor dirs hold OS-locked files.
        // Watching them crashes the dev server with EBUSY on Windows.
        ignored: [
          '**/.tmp_chrome_profile/**',
          '**/.playwright-cli/**',
          '**/vendor/**',
          '**/storage/**',
          '**/public/images/pexels/**'
        ]
      },
      proxy: {
        // Proxy API requests to Laravel backend during development.
        // Permite acceder via localhost:5173 y que /api/* se reenvie
        // a php artisan serve en :8000.
        '/api': {
          target: backendUrl,
          changeOrigin: true
        },
        '/broadcasting': {
          target: backendUrl,
          changeOrigin: true
        }
      }
    }
  }
})
