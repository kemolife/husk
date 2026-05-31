import { createFileRoute } from '@tanstack/react-router'
import { useQuery } from '@tanstack/react-query'
import { PipelineRow } from '../../features/pipelines/components/PipelineRow'
import { fetchPipelines } from '../../api/client'

export const Route = createFileRoute('/pipelines/')({
  pendingComponent: PipelinesIndexSkeleton,
  component: PipelinesIndexPage,
})

function PipelinesIndexSkeleton() {
  return (
    <div>
      <div className="px-6 py-5 border-b border-gray-800">
        <div className="h-5 w-24 bg-gray-800 rounded animate-pulse" />
        <div className="h-3 w-48 bg-gray-800 rounded animate-pulse mt-1.5" />
      </div>
      {[...Array(3)].map((_, i) => (
        <div key={i} className="flex items-center gap-4 px-6 py-4 border-b border-gray-800">
          <div className="h-4 w-32 bg-gray-800 rounded animate-pulse" />
          <div className="h-5 w-16 bg-gray-800 rounded-full animate-pulse" />
          <div className="flex-1" />
          <div className="h-7 w-12 bg-gray-800 rounded animate-pulse" />
        </div>
      ))}
    </div>
  )
}

function PipelinesIndexPage() {
  const { data: pipelines = [], isLoading } = useQuery({
    queryKey: ['pipelines'],
    queryFn: fetchPipelines,
  })

  return (
    <div>
      <div className="px-6 py-5 border-b border-gray-800">
        <h1 className="text-white text-base font-semibold">Pipelines</h1>
        <p className="text-gray-400 text-xs mt-0.5">Run isolated container jobs defined in YAML</p>
      </div>
      {isLoading ? (
        <PipelinesIndexSkeleton />
      ) : pipelines.length === 0 ? (
        <div className="flex items-center justify-center py-16">
          <p className="text-gray-500 text-sm">No pipelines found. Add a <code className="text-gray-400">.yaml</code> file to the <code className="text-gray-400">pipelines/</code> directory.</p>
        </div>
      ) : (
        <div>
          {pipelines.map((p) => (
            <PipelineRow key={p.id} pipeline={{ id: p.id, name: p.name }} />
          ))}
        </div>
      )}
    </div>
  )
}
