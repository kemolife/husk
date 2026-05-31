export const POLL_INTERVALS = {
  ACTIVE: 2000,
  AWAITING_APPROVAL: 5000,
  TERMINAL: false,
} as const

export const TERMINAL_STATUSES = ['success', 'failed', 'skipped'] as const

export const ENVIRONMENTS = ['development', 'staging', 'production'] as const

export type PipelineRunStatus = 'pending' | 'running' | 'success' | 'failed' | 'awaiting_approval'
export type JobRunStatus = 'pending' | 'running' | 'success' | 'failed' | 'awaiting_approval' | 'skipped'
export type Environment = 'development' | 'staging' | 'production'
export type JobType = 'script' | 'approval'
