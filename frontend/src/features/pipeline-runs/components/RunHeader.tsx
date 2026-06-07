import { useState, useEffect, useRef } from 'react'
import { Link } from '@tanstack/react-router'
import { StatusPill } from './StatusPill'
import { formatDuration } from '../../../lib/utils'
import type { PipelineRun } from '../../../api/client'
import { TERMINAL_STATUSES } from '../../../lib/constants'

interface Props {
  run: PipelineRun
  pipelineId: string
}

export function RunHeader({ run, pipelineId }: Props) {
  const [elapsed, setElapsed] = useState(0)
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null)
  const isTerminal = (TERMINAL_STATUSES as readonly string[]).includes(run.status)
  const ctx = run.trigger_context

  useEffect(() => {
    if (isTerminal) {
      if (intervalRef.current) clearInterval(intervalRef.current)
      return
    }
    intervalRef.current = setInterval(() => setElapsed((e) => e + 1000), 1000)
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current)
    }
  }, [isTerminal])

  return (
    <div className="flex items-center justify-between px-6 py-4 border-b border-gray-800">
      <div className="flex items-center gap-3 min-w-0 flex-wrap">
        <Link
          to="/pipelines/$pipelineId"
          params={{ pipelineId }}
          className="text-gray-400 hover:text-gray-200 text-sm"
        >
          {pipelineId}
        </Link>
        <span className="text-gray-600">/</span>
        <span className="text-gray-400 text-sm font-mono truncate">{run.id.slice(0, 8)}</span>
        <StatusPill status={run.status} />
        <span className="text-xs text-gray-500 capitalize border border-gray-700 rounded px-1.5 py-0.5">
          {run.environment}
        </span>
        {ctx?.branch && (
          <span className="text-xs text-blue-400 font-mono bg-blue-950/40 border border-blue-800/50 rounded px-1.5 py-0.5">
            {ctx.branch}
          </span>
        )}
        {ctx?.commitSha && (
          <span className="text-xs text-gray-500 font-mono" title={ctx.commitSha}>
            {ctx.commitSha.slice(0, 7)}
          </span>
        )}
        {ctx?.actor && (
          <span className="text-xs text-gray-500">by {ctx.actor}</span>
        )}
      </div>
      <div className="flex items-center gap-3 shrink-0">
        {!isTerminal && (
          <span className="text-gray-400 text-xs font-mono">
            {formatDuration(elapsed)}
          </span>
        )}
      </div>
    </div>
  )
}
