import { useState } from 'react'
import { Link } from '@tanstack/react-router'
import { StatusPill } from './StatusPill'
import { JobStrip } from './JobStrip'
import { useApproveJob } from '../hooks/useApproveJob'
import { useRejectJob } from '../hooks/useRejectJob'
import { relativeTime, formatDuration } from '../../../lib/utils'
import type { PipelineRunStatus, JobRunStatus, Environment } from '../../../lib/constants'

export interface JobSummary {
  job_id: string
  status: JobRunStatus
}

interface RunSummary {
  id: string
  pipelineId: string
  status: PipelineRunStatus
  environment: Environment
  jobs?: JobSummary[]
  startedAt?: string
  durationMs?: number
}

interface Props {
  run: RunSummary
}

export function RunRow({ run }: Props) {
  const [confirming, setConfirming] = useState(false)
  const waitingJob = run.jobs?.find((j) => j.status === 'awaiting_approval')
  const approve = useApproveJob(run.id, run.pipelineId)
  const reject = useRejectJob(run.id, run.pipelineId)
  const isPending = approve.isPending || reject.isPending

  return (
    <Link
      to="/pipelines/$pipelineId/runs/$runId"
      params={{ pipelineId: run.pipelineId, runId: run.id }}
      className="flex items-center gap-4 px-6 py-3 border-b border-gray-800 hover:bg-gray-900/50 group"
    >
      <StatusPill status={run.status} size="sm" />
      <span className="text-xs text-gray-500 capitalize border border-gray-700 rounded px-1.5 py-0.5 shrink-0">
        {run.environment}
      </span>
      {run.jobs && run.jobs.length > 0 && <JobStrip jobs={run.jobs} />}
      <span className="flex-1 text-gray-400 text-xs font-mono truncate">{run.id.slice(0, 8)}</span>
      {run.durationMs !== undefined && (
        <span className="text-gray-500 text-xs shrink-0">{formatDuration(run.durationMs)}</span>
      )}
      {run.startedAt && (
        <span className="text-gray-500 text-xs shrink-0">{relativeTime(run.startedAt)}</span>
      )}
      <span className="text-gray-600 group-hover:text-gray-400 text-xs shrink-0">→</span>

      {waitingJob && (
        // stopPropagation prevents the Link from navigating when approval buttons are clicked
        <div
          onClick={(e) => e.preventDefault()}
          className="flex items-center gap-2 shrink-0 border-l border-amber-800/40 pl-4"
        >
          <span className="size-1.5 rounded-full bg-amber-400 animate-pulse" />
          <button
            onClick={(e) => { e.preventDefault(); approve.mutate({ jobId: waitingJob.job_id }) }}
            disabled={isPending}
            className="px-2.5 py-1 bg-green-700 hover:bg-green-600 disabled:opacity-50 text-white text-xs rounded transition-colors"
          >
            {approve.isPending ? '…' : 'Approve'}
          </button>
          {!confirming ? (
            <button
              onClick={(e) => { e.preventDefault(); setConfirming(true) }}
              disabled={isPending}
              className="px-2.5 py-1 border border-gray-700 hover:border-gray-500 disabled:opacity-50 text-gray-400 hover:text-gray-200 text-xs rounded transition-colors"
            >
              Reject
            </button>
          ) : (
            <div className="flex items-center gap-1.5">
              <button
                onClick={(e) => { e.preventDefault(); reject.mutate({ jobId: waitingJob.job_id }) }}
                disabled={isPending}
                className="px-2.5 py-1 bg-red-700 hover:bg-red-600 disabled:opacity-50 text-white text-xs rounded transition-colors"
              >
                {reject.isPending ? '…' : 'Confirm reject'}
              </button>
              <button
                onClick={(e) => { e.preventDefault(); setConfirming(false) }}
                className="text-gray-500 hover:text-gray-300 text-xs"
              >
                Cancel
              </button>
            </div>
          )}
        </div>
      )}
    </Link>
  )
}
