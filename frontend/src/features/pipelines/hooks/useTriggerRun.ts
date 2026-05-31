import { useMutation } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { triggerPipelineRun } from '../../../api/client'

interface TriggerPayload {
  pipelineId: string
  environment: string
}

export function useTriggerRun() {
  const navigate = useNavigate()
  return useMutation({
    mutationFn: ({ pipelineId, environment }: TriggerPayload) =>
      triggerPipelineRun(pipelineId, environment),
    onSuccess: (data, { pipelineId }) => {
      navigate({
        to: '/pipelines/$pipelineId/runs/$runId',
        params: { pipelineId, runId: data.pipeline_run_id },
      })
    },
  })
}
