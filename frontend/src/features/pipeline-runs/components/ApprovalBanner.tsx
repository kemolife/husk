import { useState } from 'react'
import { useApproveJob } from '../hooks/useApproveJob'
import { useRejectJob } from '../hooks/useRejectJob'
import type { PipelineRun } from '../../../api/client'

interface Props {
  run: PipelineRun
}

export function ApprovalBanner({ run }: Props) {
  const [confirming, setConfirming] = useState(false)
  const waitingJob = run.jobs.find((j) => j.status === 'awaiting_approval')
  const approve = useApproveJob(run.id)
  const reject = useRejectJob(run.id)
  const isPending = approve.isPending || reject.isPending

  if (!waitingJob) return null

  return (
    <div className="sticky bottom-0 z-50 border-t border-blue-800/50 bg-blue-950/90 backdrop-blur-sm px-6 py-3 flex items-center justify-between">
      <div className="flex items-center gap-3">
        <span className="size-2 rounded-full bg-amber-400 animate-pulse" />
        <span className="text-blue-100 text-sm font-medium">
          Manual approval required
        </span>
        <span className="text-blue-300 text-sm font-mono">— {waitingJob.job_id}</span>
      </div>
      <div className="flex items-center gap-2">
        <button
          onClick={() => approve.mutate({ jobId: waitingJob.job_id })}
          disabled={isPending}
          className="px-4 py-1.5 bg-green-600 hover:bg-green-500 disabled:opacity-50 text-white text-sm font-medium rounded transition-colors"
        >
          {approve.isPending ? 'Approving…' : 'Approve'}
        </button>
        {!confirming ? (
          <button
            onClick={() => setConfirming(true)}
            disabled={isPending}
            className="px-4 py-1.5 border border-blue-700 hover:border-blue-500 disabled:opacity-50 text-blue-200 hover:text-white text-sm rounded transition-colors"
          >
            Reject
          </button>
        ) : (
          <div className="flex items-center gap-2">
            <button
              onClick={() => reject.mutate({ jobId: waitingJob.job_id })}
              disabled={isPending}
              className="px-4 py-1.5 bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white text-sm font-medium rounded transition-colors"
            >
              {reject.isPending ? 'Rejecting…' : 'Confirm Reject'}
            </button>
            <button
              onClick={() => setConfirming(false)}
              className="text-blue-400 hover:text-blue-200 text-sm"
            >
              Cancel
            </button>
          </div>
        )}
      </div>
    </div>
  )
}
