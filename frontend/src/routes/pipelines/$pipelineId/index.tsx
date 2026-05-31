import { createFileRoute } from '@tanstack/react-router'

export const Route = createFileRoute('/pipelines/$pipelineId/')({
  component: () => {
    const { pipelineId } = Route.useParams()
    return (
      <div className="p-6">
        <h1 className="text-white text-base font-semibold">{pipelineId}</h1>
        <p className="text-gray-400 text-sm mt-1">Run history coming soon…</p>
      </div>
    )
  },
})
