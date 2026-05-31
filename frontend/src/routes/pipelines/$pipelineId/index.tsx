import { createFileRoute } from '@tanstack/react-router'
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { RunRow } from '../../../features/pipeline-runs/components/RunRow'
import { EnvironmentPicker } from '../../../features/pipelines/components/EnvironmentPicker'
import { useTriggerRun } from '../../../features/pipelines/hooks/useTriggerRun'
import { fetchPipelineRuns } from '../../../api/client'
import type { Environment } from '../../../lib/constants'

export const Route = createFileRoute('/pipelines/$pipelineId/')({
  component: PipelineDetailPage,
})

function PipelineDetailPage() {
  const { pipelineId } = Route.useParams()
  const [showPicker, setShowPicker] = useState(false)
  const [env, setEnv] = useState<Environment>('staging')
  const trigger = useTriggerRun()

  const { data: runs = [], isLoading } = useQuery({
    queryKey: ['pipeline-runs', pipelineId],
    queryFn: () => fetchPipelineRuns(pipelineId),
    refetchInterval: 5000,
  })

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

      {isLoading ? (
        <div className="space-y-px">
          {[...Array(3)].map((_, i) => (
            <div key={i} className="flex items-center gap-4 px-6 py-3 border-b border-gray-800">
              <div className="h-5 w-16 bg-gray-800 rounded-full animate-pulse" />
              <div className="h-4 w-20 bg-gray-800 rounded animate-pulse" />
              <div className="flex-1" />
              <div className="h-4 w-12 bg-gray-800 rounded animate-pulse" />
            </div>
          ))}
        </div>
      ) : runs.length === 0 ? (
        <div className="flex items-center justify-center py-16">
          <p className="text-gray-500 text-sm">No runs yet — trigger the first run.</p>
        </div>
      ) : (
        <div>
          {runs.map((run) => (
            <RunRow
              key={run.id}
              run={{
                id: run.id,
                pipelineId: run.pipeline_id,
                status: run.status,
                environment: run.environment,
              }}
            />
          ))}
        </div>
      )}
    </div>
  )
}
