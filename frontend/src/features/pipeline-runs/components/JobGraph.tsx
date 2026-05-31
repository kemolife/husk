import { Fragment } from 'react'
import { JobNode } from './JobNode'
import type { JobRun } from '../../../api/client'

interface Props {
  jobs: JobRun[]
  selectedJobId: string | null
  onSelectJob: (jobId: string) => void
}

export function JobGraph({ jobs, selectedJobId, onSelectJob }: Props) {
  const visible = jobs.filter((j) => j.status !== 'skipped')

  return (
    <div className="flex flex-col">
      {visible.map((job, index) => (
        <Fragment key={job.id}>
          <JobNode
            job={job}
            isSelected={selectedJobId === job.job_id}
            onSelect={() => onSelectJob(job.job_id)}
          />
          {/* SVG connector line between nodes */}
          {index < visible.length - 1 && (
            <div className="flex justify-center py-0.5">
              <svg width="2" height="12" className="overflow-visible">
                <line
                  x1="1" y1="0" x2="1" y2="12"
                  stroke="#374151"
                  strokeWidth="2"
                  strokeDasharray="2 2"
                />
              </svg>
            </div>
          )}
        </Fragment>
      ))}
      {visible.length === 0 && (
        <p className="text-gray-600 text-xs text-center py-4">No jobs</p>
      )}
    </div>
  )
}
