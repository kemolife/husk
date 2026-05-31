import { createFileRoute } from '@tanstack/react-router'

export const Route = createFileRoute('/pipelines/$pipelineId/runs/$runId')({
  component: () => {
    const { pipelineId, runId } = Route.useParams()
    return (
      <div className="p-6">
        <p className="text-gray-400 text-sm font-mono">
          {pipelineId} / run {runId}
        </p>
        <p className="text-white mt-2">Run detail coming soon…</p>
      </div>
    )
  },
})
