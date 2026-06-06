import { useState } from 'react'
import { Link } from '@tanstack/react-router'
import { StatusPill } from '../../pipeline-runs/components/StatusPill'
import { JobStrip } from '../../pipeline-runs/components/JobStrip'
import { EnvironmentPicker } from './EnvironmentPicker'
import { useTriggerRun } from '../hooks/useTriggerRun'
import type { Environment, JobRunStatus, PipelineRunStatus } from '../../../lib/constants'

interface LastRun {
  id: string
  status: PipelineRunStatus
  jobs: Array<{ job_id: string; status: JobRunStatus }>
}

interface Pipeline {
  id: string
  name: string
  lastRun?: LastRun
}

interface Props {
  pipeline: Pipeline
}

export function PipelineRow({ pipeline }: Props) {
  const [showPicker, setShowPicker] = useState(false)
  const [env, setEnv] = useState<Environment>('staging')
  const trigger = useTriggerRun()

  function handleRun() {
    if (!showPicker) {
      setShowPicker(true)
      return
    }
    trigger.mutate({ pipelineId: pipeline.id, environment: env })
  }

  return (
    <div className="flex items-center gap-4 px-6 py-4 border-b border-gray-800 hover:bg-gray-900/50">
      <div className="flex items-center gap-4 min-w-0 flex-1">
        <Link
          to="/pipelines/$pipelineId"
          params={{ pipelineId: pipeline.id }}
          className="text-white font-medium text-sm hover:text-blue-300 truncate shrink-0"
        >
          {pipeline.name}
        </Link>
        {pipeline.lastRun && (
          <Link
            to="/pipelines/$pipelineId/runs/$runId"
            params={{ pipelineId: pipeline.id, runId: pipeline.lastRun.id }}
            className="flex items-center gap-2"
          >
            <StatusPill status={pipeline.lastRun.status} size="sm" />
            {pipeline.lastRun.jobs.length > 0 && (
              <JobStrip jobs={pipeline.lastRun.jobs} />
            )}
          </Link>
        )}
      </div>

      <div className="flex items-center gap-3 shrink-0">
        {showPicker && (
          <EnvironmentPicker value={env} onChange={setEnv} />
        )}
        <div className="flex items-center gap-2">
          <button
            onClick={handleRun}
            disabled={trigger.isPending}
            className="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-xs font-medium rounded-md transition-colors"
          >
            {trigger.isPending ? 'Triggering…' : showPicker ? 'Confirm' : 'Run'}
          </button>
          {showPicker && (
            <button
              onClick={() => setShowPicker(false)}
              className="text-gray-500 hover:text-gray-300 text-xs"
            >
              Cancel
            </button>
          )}
        </div>
        {trigger.isError && (
          <p className="text-red-400 text-xs">{(trigger.error as Error).message}</p>
        )}
      </div>
    </div>
  )
}
