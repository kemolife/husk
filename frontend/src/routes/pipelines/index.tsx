import { createFileRoute } from '@tanstack/react-router'
import { PipelineRow } from '../../features/pipelines/components/PipelineRow'
import type { PipelineRunStatus } from '../../lib/constants'

interface Pipeline {
  id: string
  name: string
  lastRunStatus?: PipelineRunStatus
  lastRunAt?: string
}

// TODO: Replace with useQuery(['pipelines'], fetchPipelines) when GET /pipelines ships
const MOCK_PIPELINES: Pipeline[] = [
  { id: 'example', name: 'Example Pipeline', lastRunStatus: 'success', lastRunAt: new Date(Date.now() - 3 * 60 * 1000).toISOString() },
  { id: 'nightly-tests', name: 'Nightly Tests', lastRunStatus: 'failed', lastRunAt: new Date(Date.now() - 8 * 60 * 60 * 1000).toISOString() },
  { id: 'deploy-staging', name: 'Deploy Staging', lastRunStatus: 'running', lastRunAt: new Date(Date.now() - 30 * 1000).toISOString() },
]

export const Route = createFileRoute('/pipelines/')({
  pendingComponent: PipelinesIndexSkeleton,
  component: PipelinesIndexPage,
})

function PipelinesIndexSkeleton() {
  return (
    <div>
      <div className="px-6 py-5 border-b border-gray-800">
        <div className="h-5 w-24 bg-gray-800 rounded animate-pulse" />
        <div className="h-3 w-48 bg-gray-800 rounded animate-pulse mt-1.5" />
      </div>
      {[...Array(3)].map((_, i) => (
        <div key={i} className="flex items-center gap-4 px-6 py-4 border-b border-gray-800">
          <div className="h-4 w-32 bg-gray-800 rounded animate-pulse" />
          <div className="h-5 w-16 bg-gray-800 rounded-full animate-pulse" />
          <div className="flex-1" />
          <div className="h-7 w-12 bg-gray-800 rounded animate-pulse" />
        </div>
      ))}
    </div>
  )
}

function PipelinesIndexPage() {
  return (
    <div>
      <div className="px-6 py-5 border-b border-gray-800">
        <h1 className="text-white text-base font-semibold">Pipelines</h1>
        <p className="text-gray-400 text-xs mt-0.5">Run isolated container jobs defined in YAML</p>
      </div>
      <div>
        {MOCK_PIPELINES.map((p) => (
          <PipelineRow key={p.id} pipeline={p} />
        ))}
      </div>
    </div>
  )
}
