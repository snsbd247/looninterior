import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

// Production build goes straight into Laravel's public/build folder,
// so the whole site (API + React) is deployed as one Laravel app.
// VITE_BASE_PATH (e.g. "/loon") lets the site live in a sub-folder;
// leave it empty when the site runs on the domain root.
export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const basePath = (env.VITE_BASE_PATH || '').replace(/\/+$/, '')

  return {
    plugins: [react()],
    base: command === 'build' ? `${basePath}/build/` : '/',
    define: {
      __BASE_PATH__: JSON.stringify(command === 'build' ? basePath : ''),
    },
    build: {
      outDir: '../backend/public/build',
      emptyOutDir: true,
      chunkSizeWarningLimit: 1200,
    },
    server: {
      port: 5173,
      proxy: {
        '/api': 'http://127.0.0.1:8000',
        '/uploads': 'http://127.0.0.1:8000',
      },
    },
  }
})
