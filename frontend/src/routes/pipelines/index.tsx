import { createFileRoute } from '@tanstack/react-router'

export const Route = createFileRoute('/pipelines/')({
  component: () => (
    <div className="p-6">
      <h1 className="text-white text-lg font-semibold">Pipelines</h1>
      <p className="text-gray-400 text-sm mt-1">Loading pipelines…</p>
    </div>
  ),
})
