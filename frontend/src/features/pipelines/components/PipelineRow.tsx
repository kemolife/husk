import { useState } from 'react'
import { Link } from '@tanstack/react-router'
import { StatusPill } from '../../pipeline-runs/components/StatusPill'
import { EnvironmentPicker } from './EnvironmentPicker'
import { useTriggerRun } from '../hooks/useTriggerRun'
import { relativeTime } from '../../../lib/utils'
import type { Environment, PipelineRunStatus } from '../../../lib/constants'

interface Pipeline {
  id: string
  name: string
  lastRunStatus?: PipelineRunStatus
  lastRunAt?: string
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
    <div className="flex items-center justify-between px-6 py-4 border-b border-gray-800 hover:bg-gray-900/50">
      <div className="flex items-center gap-4 min-w-0">
        <Link
          to="/pipelines/$pipelineId"
          params={{ pipelineId: pipeline.id }}
          className="text-white font-medium text-sm hover:text-blue-300 truncate"
        >
          {pipeline.name}
        </Link>
        {pipeline.lastRunStatus && (
          <StatusPill status={pipeline.lastRunStatus} size="sm" />
        )}
        {pipeline.lastRunAt && (
          <span className="text-gray-500 text-xs">{relativeTime(pipeline.lastRunAt)}</span>
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
