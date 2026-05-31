import { useMutation, useQueryClient } from '@tanstack/react-query'
import { rejectJob } from '../../../api/client'
import type { PipelineRun } from '../../../api/client'

export function useRejectJob(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ jobId }: { jobId: string }) => rejectJob(runId, jobId),
    onMutate: async ({ jobId }) => {
      await queryClient.cancelQueries({ queryKey: ['pipeline-run', runId] })
      const previous = queryClient.getQueryData<PipelineRun>(['pipeline-run', runId])
      queryClient.setQueryData<PipelineRun>(['pipeline-run', runId], (old) => {
        if (!old) return old
        return {
          ...old,
          status: 'failed',
          jobs: old.jobs.map((j) =>
            j.job_id === jobId ? { ...j, status: 'failed' } : j,
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
    },
  })
}
