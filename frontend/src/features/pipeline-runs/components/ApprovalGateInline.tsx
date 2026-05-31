import { useState } from 'react'
import { useApproveJob } from '../hooks/useApproveJob'
import { useRejectJob } from '../hooks/useRejectJob'

interface Props {
  runId: string
  jobId: string
}

export function ApprovalGateInline({ runId, jobId }: Props) {
  const [confirming, setConfirming] = useState(false)
  const approve = useApproveJob(runId)
  const reject = useRejectJob(runId)
  const isPending = approve.isPending || reject.isPending

  return (
    <div className="border border-amber-800/50 rounded-lg p-3 bg-amber-950/30">
      <div className="flex items-center gap-2 mb-2">
        <span className="size-2 rounded-full bg-amber-400 animate-pulse" />
        <span className="text-amber-300 text-xs font-medium truncate">{jobId}</span>
      </div>
      <p className="text-gray-400 text-xs mb-3">Manual approval required</p>
      <div className="flex items-center gap-2">
        <button
          onClick={() => approve.mutate({ jobId })}
          disabled={isPending}
          className="px-2.5 py-1 bg-green-700 hover:bg-green-600 disabled:opacity-50 text-white text-xs rounded transition-colors"
        >
          {approve.isPending ? '…' : 'Approve'}
        </button>
        {!confirming ? (
          <button
            onClick={() => setConfirming(true)}
            disabled={isPending}
            className="px-2.5 py-1 border border-gray-700 hover:border-gray-500 disabled:opacity-50 text-gray-400 hover:text-gray-200 text-xs rounded transition-colors"
          >
            Reject
          </button>
        ) : (
          <div className="flex items-center gap-1.5">
            <button
              onClick={() => reject.mutate({ jobId })}
              disabled={isPending}
              className="px-2.5 py-1 bg-red-700 hover:bg-red-600 disabled:opacity-50 text-white text-xs rounded transition-colors"
            >
              {reject.isPending ? '…' : 'Confirm'}
            </button>
            <button
              onClick={() => setConfirming(false)}
              className="text-gray-500 hover:text-gray-300 text-xs"
            >
              Cancel
            </button>
          </div>
        )}
      </div>
    </div>
  )
}
