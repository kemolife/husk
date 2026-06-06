import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { TanStackRouterVite } from '@tanstack/router-plugin/vite'
import type { IncomingMessage } from 'node:http'

function bypassHtmlNav(req: IncomingMessage): string | null | undefined | false {
  // Browser page navigations send Accept: text/html — let Vite serve index.html for those.
  // JSON/fetch API calls don't, so they get proxied to the backend.
  const accept = req.headers['accept'] ?? ''
  if (req.method === 'GET' && accept.includes('text/html')) {
    return '/index.html'
  }
  return undefined
}

export default defineConfig({
  plugins: [
    TanStackRouterVite({ target: 'react', autoCodeSplitting: true }),
    react(),
    tailwindcss(),
  ],
  server: {
    proxy: {
      '/pipelines': { target: 'http://localhost:8080', bypass: bypassHtmlNav },
      '/pipeline-runs': { target: 'http://localhost:8080', bypass: bypassHtmlNav },
      '/health': { target: 'http://localhost:8080', bypass: bypassHtmlNav },
      '/webhooks': { target: 'http://localhost:8080', bypass: bypassHtmlNav },
      '/playground': { target: 'http://localhost:8080', bypass: bypassHtmlNav },
    },
  },
})
