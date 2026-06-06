import { useMutation, useQueryClient } from '@tanstack/react-query'
import { approveJob } from '../../../api/client'
import type { PipelineRun } from '../../../api/client'

export function useApproveJob(runId: string, pipelineId?: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ jobId }: { jobId: string }) => approveJob(runId, jobId),
    onMutate: async ({ jobId }) => {
      await queryClient.cancelQueries({ queryKey: ['pipeline-run', runId] })
      const previous = queryClient.getQueryData<PipelineRun>(['pipeline-run', runId])
      queryClient.setQueryData<PipelineRun>(['pipeline-run', runId], (old) => {
        if (!old) return old
        return {
          ...old,
          status: 'running',
          jobs: old.jobs.map((j) =>
            j.job_id === jobId ? { ...j, status: 'running' } : j,
          ),
        }
      })
      return { previous }
    },
    onError: (_err, _vars, ctx) => {
      if (ctx?.previous) {
        queryClient.setQueryData(['pipeline-run', runId], ctx.previous)
      }
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['pipeline-run', runId] })
      if (pipelineId) {
        queryClient.invalidateQueries({ queryKey: ['pipeline-runs', pipelineId] })
      }
    },
  })
}
