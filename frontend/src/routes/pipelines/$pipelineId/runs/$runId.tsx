import { createFileRoute, Link } from '@tanstack/react-router'
import { useState, useEffect } from 'react'
import { usePipelineRun } from '../../../../features/pipeline-runs/hooks/usePipelineRun'
import { RunHeader } from '../../../../features/pipeline-runs/components/RunHeader'
import { JobGraph } from '../../../../features/pipeline-runs/components/JobGraph'
import { LogPanel } from '../../../../features/pipeline-runs/components/LogPanel'
import { ApprovalBanner } from '../../../../features/pipeline-runs/components/ApprovalBanner'

export const Route = createFileRoute('/pipelines/$pipelineId/runs/$runId')({
  component: RunDetailPage,
})

function RunDetailPage() {
  const { pipelineId, runId } = Route.useParams()
  const { data: run, isLoading, isError, error } = usePipelineRun(runId)
  const [selectedJobId, setSelectedJobId] = useState<string | null>(null)

  // Auto-select first running job, or first failed job on terminal run
  useEffect(() => {
    if (!run) return
    if (selectedJobId) return
    const running = run.jobs.find((j) => j.status === 'running')
    if (running) { setSelectedJobId(running.job_id); return }
    const failed = run.jobs.find((j) => j.status === 'failed')
    if (failed) setSelectedJobId(failed.job_id)
  }, [run, selectedJobId])

  if (isLoading) {
    return (
      <div className="flex h-[calc(100vh-48px)]">
        <div className="w-80 border-r border-gray-800 p-4 space-y-2">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="h-9 bg-gray-800 rounded-lg animate-pulse" />
          ))}
        </div>
        <div className="flex-1 p-4">
          <div className="h-full bg-gray-900 rounded-lg animate-pulse" />
        </div>
      </div>
    )
  }

  if (isError) {
    const is404 = (error as { statusCode?: number }).statusCode === 404
    return (
      <div className="flex min-h-96 items-center justify-center">
        <div className="text-center">
          {is404 ? (
            <>
              <p className="text-gray-400 text-sm font-mono mb-2">404</p>
              <h2 className="text-white text-lg font-medium mb-3">Run not found</h2>
              <Link
                to="/pipelines/$pipelineId"
                params={{ pipelineId }}
                className="text-blue-400 hover:text-blue-300 text-sm"
              >
                ← Back to {pipelineId}
              </Link>
            </>
          ) : (
            <>
              <h2 className="text-white text-lg font-medium mb-2">Something went wrong</h2>
              <p className="text-red-400 text-xs font-mono">{(error as Error).message}</p>
            </>
          )}
        </div>
      </div>
    )
  }

  if (!run) return null

  const selectedJob = run.jobs.find((j) => j.job_id === selectedJobId)
  // Log lines from job output — will be replaced with SSE streaming once backend ships
  // TODO: replace with useLogStream when GET /pipeline-runs/{runId}/jobs/{jobId}/logs/stream is live
  const logLines = selectedJob?.output
    ? selectedJob.output.split('\n').filter(Boolean)
    : []

  return (
    <div className="flex flex-col h-[calc(100vh-48px)]">
      <RunHeader run={run} pipelineId={pipelineId} />
      <div className="flex flex-1 overflow-hidden">
        {/* Job graph — left panel */}
        <div className="w-80 shrink-0 overflow-y-auto border-r border-gray-800 p-4">
          <p className="text-gray-500 text-xs font-medium uppercase tracking-wide mb-3">Jobs</p>
          <JobGraph
            jobs={run.jobs}
            selectedJobId={selectedJobId}
            onSelectJob={setSelectedJobId}
          />
        </div>

        {/* Log panel — right panel */}
        <div className="flex-1 overflow-hidden">
          <LogPanel
            lines={logLines}
            status={selectedJob?.status}
            jobId={selectedJobId ?? undefined}
            mode="snapshot"
          />
        </div>
      </div>

      {/* Approval banner — sticky bottom, shown when any job awaiting approval */}
      <ApprovalBanner run={run} />
    </div>
  )
}
