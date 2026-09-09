import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Build into the plugin's app/ directory for WP static serving.
export default defineConfig({
  plugins: [react()],
  base: '/seller-app/',
  build: {
    outDir: '../dejoiy-seller-app/app',
    emptyOutDir: true,
    assetsDir: 'assets',
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['react', 'react-dom', 'react-router-dom'],
        },
      },
    },
  },
})
