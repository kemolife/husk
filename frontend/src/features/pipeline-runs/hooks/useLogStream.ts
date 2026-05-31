import { useState, useEffect } from 'react'

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080'

export function useLogStream(runId: string, jobId: string, enabled: boolean): string[] {
  const [lines, setLines] = useState<string[]>([])

  useEffect(() => {
    if (!enabled) return
    setLines([])
    const es = new EventSource(
      `${BASE_URL}/pipeline-runs/${runId}/jobs/${jobId}/logs/stream`
    )
    es.onmessage = (e) => setLines((prev) => [...prev, e.data as string])
    es.onerror = () => es.close()
    return () => es.close()
  }, [runId, jobId, enabled])

  return lines
}
