import { cn } from '../../../lib/utils'
import { STATUS_CONFIG } from './StatusPill'
import type { JobRun } from '../../../api/client'

interface Props {
  job: JobRun
  isSelected: boolean
  onSelect: () => void
  isAwaitingApproval?: boolean
}

export function JobNode({ job, isSelected, onSelect }: Props) {
  const config = STATUS_CONFIG[job.status] ?? STATUS_CONFIG.pending

  return (
    <button
      onClick={onSelect}
      className={cn(
        'w-full flex items-center gap-2.5 px-3 h-9 rounded-lg text-left transition-all text-xs',
        'hover:bg-gray-800',
        isSelected
          ? 'bg-gray-800 border-l-2 border-blue-500 shadow-sm'
          : 'border-l-2 border-transparent',
      )}
    >
      <span
        className={cn(
          'size-2 rounded-full flex-shrink-0',
          config.dot,
          config.animate,
        )}
      />
      <span className="flex-1 font-mono text-gray-200 truncate">{job.job_id}</span>
      <span className={cn('text-xs shrink-0', config.text)}>{job.status.replace('_', ' ')}</span>
    </button>
  )
}
