import { createFileRoute } from '@tanstack/react-router'
import { useState } from 'react'
import { RunRow } from '../../../features/pipeline-runs/components/RunRow'
import { EnvironmentPicker } from '../../../features/pipelines/components/EnvironmentPicker'
import { useTriggerRun } from '../../../features/pipelines/hooks/useTriggerRun'
import type { PipelineRunStatus, Environment } from '../../../lib/constants'

interface RunSummary {
  id: string
  pipelineId: string
  status: PipelineRunStatus
  environment: Environment
  startedAt?: string
  durationMs?: number
}

// TODO: Replace with useQuery when GET /pipeline-runs?pipeline_id=X ships in iteration 2
function getMockRuns(pipelineId: string): RunSummary[] {
  return [
    { id: 'aabbccdd-1111-2222-3333-444455556666', pipelineId, status: 'success', environment: 'staging', startedAt: new Date(Date.now() - 3 * 60 * 1000).toISOString(), durationMs: 47000 },
    { id: 'bbccddee-2222-3333-4444-555566667777', pipelineId, status: 'failed', environment: 'production', startedAt: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(), durationMs: 12000 },
    { id: 'ccddeeff-3333-4444-5555-666677778888', pipelineId, status: 'awaiting_approval', environment: 'staging', startedAt: new Date(Date.now() - 10 * 60 * 1000).toISOString() },
  ]
}

export const Route = createFileRoute('/pipelines/$pipelineId/')({
  component: PipelineDetailPage,
})

function PipelineDetailPage() {
  const { pipelineId } = Route.useParams()
  const [showPicker, setShowPicker] = useState(false)
  const [env, setEnv] = useState<Environment>('staging')
  const trigger = useTriggerRun()
  const runs = getMockRuns(pipelineId)

  function handleRunAgain() {
    if (!showPicker) { setShowPicker(true); return }
    trigger.mutate({ pipelineId, environment: env })
  }

  return (
    <div>
      <div className="px-6 py-5 border-b border-gray-800 flex items-start justify-between">
        <div>
          <h1 className="text-white text-base font-semibold">{pipelineId}</h1>
          <p className="text-gray-500 text-xs mt-0.5">Recent runs</p>
        </div>
        <div className="flex items-center gap-3">
          {showPicker && (
            <EnvironmentPicker value={env} onChange={setEnv} />
          )}
          <button
            onClick={handleRunAgain}
            disabled={trigger.isPending}
            className="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-xs font-medium rounded-md transition-colors"
          >
            {trigger.isPending ? 'Triggering…' : showPicker ? 'Confirm' : 'Run Again'}
          </button>
          {showPicker && (
            <button onClick={() => setShowPicker(false)} className="text-gray-500 hover:text-gray-300 text-xs">
              Cancel
            </button>
          )}
        </div>
      </div>

      {runs.length === 0 ? (
        <div className="flex items-center justify-center py-16">
          <p className="text-gray-500 text-sm">No runs yet — trigger the first run.</p>
        </div>
      ) : (
        <div>
          {runs.map((run) => (
            <RunRow key={run.id} run={run} />
          ))}
          <div className="px-6 py-3">
            <button className="text-gray-600 hover:text-gray-400 text-xs">Load more</button>
          </div>
        </div>
      )}
    </div>
  )
}
