import type { PipelineRunStatus, JobRunStatus, Environment } from '../lib/constants'

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080'

export interface JobRun {
  id: string
  job_id: string
  status: JobRunStatus
  output?: string
  started_at?: string
  finished_at?: string
}

export interface PipelineRun {
  id: string
  pipeline_id: string
  status: PipelineRunStatus
  environment: Environment
  jobs: JobRun[]
}

export interface TriggerResponse {
  pipeline_run_id: string
}

export interface ApproveResponse {
  status: string
}

class ApiError extends Error {
  constructor(public statusCode: number, message: string) {
    super(message)
    this.name = 'ApiError'
  }
}

async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${BASE_URL}${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      ...init?.headers,
    },
  })

  if (!res.ok) {
    let message = res.statusText
    try {
      const body = await res.json()
      message = body.error ?? body.message ?? message
    } catch {}
    throw new ApiError(res.status, message)
  }

  if (res.status === 204) return undefined as T
  return res.json()
}

export const fetchPipelineRun = (runId: string): Promise<PipelineRun> =>
  apiFetch(`/pipeline-runs/${runId}`)

export const triggerPipelineRun = (pipelineId: string, environment: string): Promise<TriggerResponse> =>
  apiFetch(`/pipelines/${pipelineId}/run`, {
    method: 'POST',
    body: JSON.stringify({ environment }),
  })

export const approveJob = (runId: string, jobId: string): Promise<ApproveResponse> =>
  apiFetch(`/pipeline-runs/${runId}/jobs/${jobId}/approve`, { method: 'POST' })

export const rejectJob = (runId: string, jobId: string): Promise<ApproveResponse> =>
  apiFetch(`/pipeline-runs/${runId}/jobs/${jobId}/reject`, { method: 'POST' })
