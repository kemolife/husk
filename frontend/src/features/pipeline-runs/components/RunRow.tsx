import { Link } from '@tanstack/react-router'
import { StatusPill } from './StatusPill'
import { relativeTime, formatDuration } from '../../../lib/utils'
import type { PipelineRunStatus, Environment } from '../../../lib/constants'

interface RunSummary {
  id: string
  pipelineId: string
  status: PipelineRunStatus
  environment: Environment
  startedAt?: string
  durationMs?: number
}

interface Props {
  run: RunSummary
}

export function RunRow({ run }: Props) {
  return (
    <Link
      to="/pipelines/$pipelineId/runs/$runId"
      params={{ pipelineId: run.pipelineId, runId: run.id }}
      className="flex items-center gap-4 px-6 py-3 border-b border-gray-800 hover:bg-gray-900/50 group"
    >
      <StatusPill status={run.status} size="sm" />
      <span className="text-xs text-gray-500 capitalize border border-gray-700 rounded px-1.5 py-0.5">
        {run.environment}
      </span>
      <span className="flex-1 text-gray-400 text-xs font-mono truncate">{run.id.slice(0, 8)}</span>
      {run.durationMs !== undefined && (
        <span className="text-gray-500 text-xs">{formatDuration(run.durationMs)}</span>
      )}
      {run.startedAt && (
        <span className="text-gray-500 text-xs">{relativeTime(run.startedAt)}</span>
      )}
      <span className="text-gray-600 group-hover:text-gray-400 text-xs">→</span>
    </Link>
  )
}
