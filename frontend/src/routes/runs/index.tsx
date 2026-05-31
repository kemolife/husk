import { createFileRoute } from '@tanstack/react-router'

export const Route = createFileRoute('/runs/')({
  component: () => (
    <div className="p-6">
      <h1 className="text-white text-lg font-semibold">All Runs</h1>
      <p className="text-gray-400 text-sm mt-1">Loading runs…</p>
    </div>
  ),
})
