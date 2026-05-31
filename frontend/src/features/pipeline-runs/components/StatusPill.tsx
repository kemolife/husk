import { cn } from '../../../lib/utils'
import type { PipelineRunStatus, JobRunStatus } from '../../../lib/constants'

type Status = PipelineRunStatus | JobRunStatus

interface StatusConfig {
  label: string
  dot: string
  text: string
  bg: string
  animate?: string
}

export const STATUS_CONFIG: Record<string, StatusConfig> = {
  pending: {
    label: 'Pending',
    dot: 'bg-gray-500',
    text: 'text-gray-400',
    bg: 'bg-gray-800',
  },
  running: {
    label: 'Running',
    dot: 'bg-blue-400',
    text: 'text-blue-300',
    bg: 'bg-blue-950',
    animate: 'animate-pulse',
  },
  success: {
    label: 'Success',
    dot: 'bg-green-400',
    text: 'text-green-300',
    bg: 'bg-green-950',
  },
  failed: {
    label: 'Failed',
    dot: 'bg-red-400',
    text: 'text-red-300',
    bg: 'bg-red-950',
  },
  awaiting_approval: {
    label: 'Awaiting Approval',
    dot: 'bg-amber-400',
    text: 'text-amber-300',
    bg: 'bg-amber-950',
    animate: 'animate-pulse',
  },
  skipped: {
    label: 'Skipped',
    dot: 'bg-gray-600',
    text: 'text-gray-500',
    bg: 'bg-gray-900',
  },
}

interface Props {
  status: Status
  size?: 'sm' | 'md'
}

export function StatusPill({ status, size = 'md' }: Props) {
  const config = STATUS_CONFIG[status] ?? STATUS_CONFIG.pending

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full font-medium',
        config.bg,
        config.text,
        size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-xs',
      )}
    >
      <span
        className={cn(
          'rounded-full flex-shrink-0',
          config.dot,
          config.animate,
          size === 'sm' ? 'size-1.5' : 'size-2',
        )}
      />
      {config.label}
    </span>
  )
}
