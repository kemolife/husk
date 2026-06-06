import type { JobRunStatus } from '../../../lib/constants'

export interface JobSummary {
  job_id: string
  status: JobRunStatus
}

export const JOB_DOT_COLOR: Record<JobRunStatus, string> = {
  pending: 'bg-gray-600',
  running: 'bg-blue-500 animate-pulse',
  success: 'bg-green-500',
  failed: 'bg-red-500',
  awaiting_approval: 'bg-yellow-400 animate-pulse',
  skipped: 'bg-gray-700',
}

const STATUS_PRIORITY: Record<JobRunStatus, number> = {
  failed: 0,
  awaiting_approval: 1,
  running: 2,
  pending: 3,
  skipped: 4,
  success: 5,
}

export function aggregateStatus(statuses: JobRunStatus[]): JobRunStatus {
  return statuses.reduce((worst, s) =>
    STATUS_PRIORITY[s] < STATUS_PRIORITY[worst] ? s : worst,
  )
}

export function JobStrip({ jobs }: { jobs: JobSummary[] }) {
  const byId = new Map<string, JobRunStatus[]>()
  for (const j of jobs) {
    const bucket = byId.get(j.job_id) ?? []
    bucket.push(j.status)
    byId.set(j.job_id, bucket)
  }

  return (
    <div className="flex items-center gap-1.5 flex-wrap">
      {Array.from(byId.entries()).map(([jobId, statuses]) => {
        const isMatrix = statuses.length > 1
        return (
          <div key={jobId} className="relative group/dot flex items-center gap-0.5">
            {isMatrix ? (
              statuses.map((s, i) => (
                <span key={i} className={`block w-1.5 h-1.5 rounded-full ${JOB_DOT_COLOR[s]}`} />
              ))
            ) : (
              <span className={`block w-2 h-2 rounded-full ${JOB_DOT_COLOR[aggregateStatus(statuses)]}`} />
            )}
            <span className="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 px-1.5 py-0.5 bg-gray-800 border border-gray-700 text-gray-200 text-[10px] rounded whitespace-nowrap opacity-0 group-hover/dot:opacity-100 pointer-events-none z-10">
              {jobId}{isMatrix ? ` ×${statuses.length}` : ''}
            </span>
          </div>
        )
      })}
    </div>
  )
}
