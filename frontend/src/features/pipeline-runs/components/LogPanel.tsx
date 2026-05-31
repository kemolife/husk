import { useRef, useEffect, useState } from 'react'
import { cn } from '../../../lib/utils'
import { TERMINAL_STATUSES } from '../../../lib/constants'
import type { JobRunStatus } from '../../../lib/constants'

interface Props {
  lines: string[]
  status?: JobRunStatus
  jobId?: string
  mode?: 'snapshot' | 'stream'
}

export function LogPanel({ lines, status, jobId, mode = 'snapshot' }: Props) {
  const anchorRef = useRef<HTMLDivElement>(null)
  const containerRef = useRef<HTMLDivElement>(null)
  const [autoScroll, setAutoScroll] = useState(true)
  const isTerminal = status ? (TERMINAL_STATUSES as readonly string[]).includes(status) : false
  const isRunning = status === 'running'

  // Auto-scroll to bottom when new lines arrive
  useEffect(() => {
    if (autoScroll) {
      anchorRef.current?.scrollIntoView({ behavior: 'smooth' })
    }
  }, [lines, autoScroll])

  // Detect manual upward scroll
  function handleScroll() {
    const el = containerRef.current
    if (!el) return
    const atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 32
    setAutoScroll(atBottom)
  }

  if (!jobId) {
    return (
      <div className="h-full bg-[#0d1117] flex items-center justify-center">
        <p className="text-gray-600 text-xs font-mono">Select a job to view logs</p>
      </div>
    )
  }

  return (
    <div className="relative h-full flex flex-col bg-[#0d1117]">
      {/* Header */}
      <div className="flex items-center gap-2 px-4 py-2 border-b border-gray-800 shrink-0">
        <span className="text-gray-500 text-xs font-mono"># {jobId}</span>
        {mode === 'stream' && isRunning && (
          <span className="text-blue-400 text-xs animate-pulse">● live</span>
        )}
      </div>

      {/* Log output */}
      <div
        ref={containerRef}
        onScroll={handleScroll}
        className="flex-1 overflow-y-auto px-4 py-3 font-mono text-xs leading-relaxed"
      >
        {lines.length === 0 && !isTerminal ? (
          <p className="text-gray-600">Waiting for output…</p>
        ) : (
          lines.map((line, i) => (
            <div key={i} className="text-gray-300 whitespace-pre-wrap break-all">
              {line}
            </div>
          ))
        )}

        {/* Final status line */}
        {isTerminal && (
          <div className={cn(
            'mt-2 pt-2 border-t border-gray-800 font-semibold',
            status === 'success' ? 'text-green-400' : 'text-red-400',
          )}>
            {status === 'success'
              ? '✓ Process exited with code 0'
              : '✗ Process exited with code 1 (failed)'}
          </div>
        )}

        {/* Blinking cursor while running */}
        {isRunning && (
          <span className="inline-block w-2 h-3 bg-gray-400 animate-pulse ml-0.5 align-middle" />
        )}

        <div ref={anchorRef} />
      </div>

      {/* Scroll to bottom pill */}
      {!autoScroll && (
        <div className="absolute bottom-4 left-1/2 -translate-x-1/2">
          <button
            onClick={() => {
              setAutoScroll(true)
              anchorRef.current?.scrollIntoView({ behavior: 'smooth' })
            }}
            className="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-gray-200 text-xs rounded-full shadow-lg transition-colors"
          >
            ↓ Scroll to bottom
          </button>
        </div>
      )}
    </div>
  )
}
