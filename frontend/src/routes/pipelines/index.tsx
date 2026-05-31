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
  component: PipelinesIndexPage,
})

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
