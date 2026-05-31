import { createRootRouteWithContext, Outlet, Link } from '@tanstack/react-router'
import { TanStackRouterDevtools } from '@tanstack/router-devtools'
import type { QueryClient } from '@tanstack/react-query'

interface RouterContext {
  queryClient: QueryClient
}

export const Route = createRootRouteWithContext<RouterContext>()({
  component: RootLayout,
  errorComponent: ({ error }) => (
    <div className="min-h-screen bg-gray-950 flex items-center justify-center">
      <div className="text-center max-w-md px-6">
        <p className="text-gray-500 text-xs font-mono mb-2">Error</p>
        <h1 className="text-white text-xl font-semibold mb-3">Something went wrong</h1>
        <p className="text-red-400 text-xs font-mono bg-gray-900 px-3 py-2 rounded mb-4 text-left break-all">
          {error instanceof Error ? error.message : String(error)}
        </p>
        <button
          onClick={() => window.location.reload()}
          className="text-blue-400 hover:text-blue-300 text-sm"
        >
          Reload page
        </button>
      </div>
    </div>
  ),
  notFoundComponent: () => (
    <div className="flex min-h-screen items-center justify-center bg-gray-950">
      <div className="text-center">
        <p className="text-gray-400 text-sm font-mono mb-2">404</p>
        <h1 className="text-white text-xl font-semibold mb-4">Page not found</h1>
        <Link to="/pipelines" className="text-blue-400 hover:text-blue-300 text-sm">
          Go to pipelines →
        </Link>
      </div>
    </div>
  ),
})

function RootLayout() {
  return (
    <div className="min-h-screen bg-gray-950 text-gray-100">
      <nav className="border-b border-gray-800 px-6 h-12 flex items-center justify-between">
        <Link to="/pipelines" className="text-white font-semibold text-sm tracking-tight hover:text-gray-300">
          ⚡ pipelinerunner
        </Link>
        <div className="flex items-center gap-4">
          <Link to="/runs" className="text-gray-400 hover:text-gray-200 text-sm">
            All runs
          </Link>
        </div>
      </nav>
      <main className="flex-1">
        <Outlet />
      </main>
      {import.meta.env.DEV && <TanStackRouterDevtools />}
    </div>
  )
}
