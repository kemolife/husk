import { createFileRoute, Link } from '@tanstack/react-router'
import { z } from 'zod'
import { StatusPill } from '../../features/pipeline-runs/components/StatusPill'
import { relativeTime, formatDuration, cn } from '../../lib/utils'
import { ENVIRONMENTS } from '../../lib/constants'
import type { PipelineRunStatus, Environment } from '../../lib/constants'

const searchSchema = z.object({
  status: z.string().optional(),
  environment: z.string().optional(),
  page: z.number().optional().default(1),
})

interface RunRecord {
  id: string
  pipelineId: string
  status: PipelineRunStatus
  environment: Environment
  startedAt: string
  durationMs?: number
}

// TODO: Replace with useQuery(['pipeline-runs', filters], fetchPipelineRuns) when GET /pipeline-runs ships
const MOCK_RUNS: RunRecord[] = [
  { id: 'aabbccdd-1111-2222-3333-444455556666', pipelineId: 'example', status: 'success', environment: 'staging', startedAt: new Date(Date.now() - 3 * 60 * 1000).toISOString(), durationMs: 47000 },
  { id: 'bbccddee-2222-3333-4444-555566667777', pipelineId: 'nightly-tests', status: 'failed', environment: 'production', startedAt: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(), durationMs: 12000 },
  { id: 'ccddeeff-3333-4444-5555-666677778888', pipelineId: 'example', status: 'awaiting_approval', environment: 'staging', startedAt: new Date(Date.now() - 10 * 60 * 1000).toISOString() },
  { id: 'ddeeffaa-4444-5555-6666-777788889999', pipelineId: 'deploy-staging', status: 'running', environment: 'staging', startedAt: new Date(Date.now() - 45 * 1000).toISOString() },
  { id: 'eeffaabb-5555-6666-7777-888899990000', pipelineId: 'nightly-tests', status: 'success', environment: 'development', startedAt: new Date(Date.now() - 12 * 60 * 60 * 1000).toISOString(), durationMs: 120000 },
]

const STATUS_FILTERS: PipelineRunStatus[] = ['running', 'success', 'failed', 'awaiting_approval']

export const Route = createFileRoute('/runs/')({
  validateSearch: searchSchema,
  component: RunHistoryPage,
})

function RunHistoryPage() {
  const search = Route.useSearch()
  const navigate = Route.useNavigate()

  const filtered = MOCK_RUNS.filter((r) => {
    if (search.status && r.status !== search.status) return false
    if (search.environment && r.environment !== search.environment) return false
    return true
  })

  function setFilter(key: 'status' | 'environment', value: string | undefined) {
    navigate({ search: (prev) => ({ ...prev, [key]: value, page: 1 }) })
  }

  return (
    <div>
      <div className="px-6 py-5 border-b border-gray-800">
        <h1 className="text-white text-base font-semibold">All Runs</h1>
        <p className="text-gray-500 text-xs mt-0.5">Across all pipelines</p>
      </div>

      {/* Filter bar */}
      <div className="px-6 py-3 border-b border-gray-800 flex items-center gap-4 flex-wrap">
        <div className="flex items-center gap-1.5">
          <span className="text-gray-500 text-xs">Status:</span>
          {STATUS_FILTERS.map((s) => (
            <button
              key={s}
              onClick={() => setFilter('status', search.status === s ? undefined : s)}
              className={cn(
                'px-2 py-0.5 rounded text-xs transition-colors',
                search.status === s
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-800 text-gray-400 hover:bg-gray-700',
              )}
            >
              {s.replace('_', ' ')}
            </button>
          ))}
        </div>
        <div className="flex items-center gap-1.5">
          <span className="text-gray-500 text-xs">Env:</span>
          {ENVIRONMENTS.map((e) => (
            <button
              key={e}
              onClick={() => setFilter('environment', search.environment === e ? undefined : e)}
              className={cn(
                'px-2 py-0.5 rounded text-xs transition-colors capitalize',
                search.environment === e
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-800 text-gray-400 hover:bg-gray-700',
              )}
            >
              {e}
            </button>
          ))}
        </div>
        {(search.status || search.environment) && (
          <button
            onClick={() => navigate({ search: { page: 1 } })}
            className="text-gray-500 hover:text-gray-300 text-xs ml-auto"
          >
            Clear filters
          </button>
        )}
      </div>

      {/* Table */}
      {filtered.length === 0 ? (
        <div className="flex items-center justify-center py-16">
          <p className="text-gray-500 text-sm">No runs match the current filters.</p>
        </div>
      ) : (
        <div>
          {filtered.map((run) => (
            <Link
              key={run.id}
              to="/pipelines/$pipelineId/runs/$runId"
              params={{ pipelineId: run.pipelineId, runId: run.id }}
              className="flex items-center gap-4 px-6 py-3 border-b border-gray-800 hover:bg-gray-900/50 group"
            >
              <StatusPill status={run.status} size="sm" />
              <span className="text-gray-300 text-sm font-medium w-40 truncate">{run.pipelineId}</span>
              <span className="text-xs text-gray-500 capitalize border border-gray-700 rounded px-1.5 py-0.5">
                {run.environment}
              </span>
              <span className="flex-1 text-gray-500 text-xs font-mono">{run.id.slice(0, 8)}</span>
              {run.durationMs !== undefined && (
                <span className="text-gray-500 text-xs">{formatDuration(run.durationMs)}</span>
              )}
              <span className="text-gray-500 text-xs">{relativeTime(run.startedAt)}</span>
              <span className="text-gray-600 group-hover:text-gray-400 text-xs">→</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}
