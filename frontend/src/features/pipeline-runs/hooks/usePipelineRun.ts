import { useQuery } from '@tanstack/react-query'
import { fetchPipelineRun } from '../../../api/client'
import { TERMINAL_STATUSES, POLL_INTERVALS } from '../../../lib/constants'

export function usePipelineRun(runId: string) {
  return useQuery({
    queryKey: ['pipeline-run', runId],
    queryFn: () => fetchPipelineRun(runId),
    staleTime: 0,
    refetchInterval: (query) => {
      const status = query.state.data?.status
      if (!status) return POLL_INTERVALS.ACTIVE
      if ((TERMINAL_STATUSES as readonly string[]).includes(status)) return false
      if (status === 'awaiting_approval') return POLL_INTERVALS.AWAITING_APPROVAL
      return POLL_INTERVALS.ACTIVE
    },
    refetchIntervalInBackground: false,
    retry: (failureCount, error) => {
      if ((error as { statusCode?: number }).statusCode === 404) return false
      return failureCount < 2
    },
  })
}
