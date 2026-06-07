import { useState, useEffect } from 'react'

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080'

export function useLogStream(runId: string, jobRunId: string, enabled: boolean): string[] {
  const [lines, setLines] = useState<string[]>([])

  useEffect(() => {
    if (!enabled || !jobRunId) return
    setLines([])
    const es = new EventSource(
      `${BASE_URL}/pipeline-runs/${runId}/jobs/${jobRunId}/logs/stream`
    )
    es.onmessage = (e) => {
      try {
        const parsed = JSON.parse(e.data as string) as { line?: string }
        if (parsed.line !== undefined) {
          setLines((prev) => [...prev, parsed.line!])
        }
      } catch {
        setLines((prev) => [...prev, e.data as string])
      }
    }
    es.addEventListener('done', () => es.close())
    es.addEventListener('error', () => es.close())
    es.onerror = () => es.close()
    return () => es.close()
  }, [runId, jobRunId, enabled])

  return lines
}
